<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\OrgStructureRoleTemplate;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function index(): Response
    {
        $departments = Department::query()
            ->whereNull('school_id')
            ->withCount(['users', 'roles'])
            ->with(['roles' => fn ($query) => $query
                ->select([
                    'id',
                    'department_id',
                    'org_structure_role_template_id',
                    'name',
                    'is_active',
                    'can_manage_student_structure',
                    'can_manage_student_attendance',
                    'can_manage_academic_planning',
                    'can_manage_student_leaves',
                ])
                ->orderBy('name')])
            ->orderByDesc('id')
            ->get(['id', 'name', 'staff_type', 'school_id']);

        $orgStructureRoleTemplates = OrgStructureRoleTemplate::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_active']);

        return Inertia::render('Admin/Departments/Index', [
            'departments' => $departments,
            'staffTypes' => Department::allowedStaffTypes(),
            'managerAssignsStructurePermissions' => $this->managerAssignsStructurePermissions(),
            'orgStructureRoleTemplates' => $orgStructureRoleTemplates,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->where(fn ($query) => $query->whereNull('school_id')),
            ],
            'staff_type' => ['required', Rule::in(Department::allowedStaffTypes())],
            'org_structure_roles' => ['required', 'array', 'min:1'],
            'org_structure_roles.*.org_structure_role_template_id' => [
                'required',
                'integer',
                Rule::exists('org_structure_role_templates', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'org_structure_roles.*.can_manage_student_structure' => ['nullable', 'boolean'],
            'org_structure_roles.*.can_manage_student_attendance' => ['nullable', 'boolean'],
            'org_structure_roles.*.can_manage_academic_planning' => ['nullable', 'boolean'],
            'org_structure_roles.*.can_manage_student_leaves' => ['nullable', 'boolean'],
        ]);

        $normalizedRoles = $this->normalizeOrgStructureRolesPayload($request, $validated['staff_type'], null);

        DB::transaction(function () use ($validated, $normalizedRoles, $request): void {
            $department = Department::query()->create([
                'name' => $validated['name'],
                'staff_type' => $validated['staff_type'],
                'school_id' => null,
            ]);

            $this->syncDepartmentRoles($department, $normalizedRoles);

            $this->auditLogger->log(
                action: 'department.created',
                entityType: 'department',
                entityId: (int) $department->id,
                payload: [
                    'name' => $department->name,
                    'staff_type' => $department->staff_type,
                    'org_structure_role_template_ids' => collect($normalizedRoles)
                        ->pluck('org_structure_role_template_id')
                        ->values()
                        ->all(),
                ],
                request: $request,
                userId: $request->user()?->id
            );
        });

        return back();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $department = Department::query()
            ->whereNull('school_id')
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')
                    ->ignore($department->id)
                    ->where(fn ($query) => $query->whereNull('school_id')),
            ],
            'staff_type' => ['required', Rule::in(Department::allowedStaffTypes())],
            'org_structure_roles' => ['required', 'array', 'min:1'],
            'org_structure_roles.*.org_structure_role_template_id' => [
                'required',
                'integer',
                Rule::exists('org_structure_role_templates', 'id'),
            ],
            'org_structure_roles.*.can_manage_student_structure' => ['nullable', 'boolean'],
            'org_structure_roles.*.can_manage_student_attendance' => ['nullable', 'boolean'],
            'org_structure_roles.*.can_manage_academic_planning' => ['nullable', 'boolean'],
            'org_structure_roles.*.can_manage_student_leaves' => ['nullable', 'boolean'],
        ]);

        $before = $department->only(['name', 'staff_type']);
        $normalizedRoles = $this->normalizeOrgStructureRolesPayload($request, $validated['staff_type'], $department);

        DB::transaction(function () use ($department, $validated, $normalizedRoles, $before, $request): void {
            $department->update([
                'name' => $validated['name'],
                'staff_type' => $validated['staff_type'],
            ]);

            $this->syncDepartmentRoles($department, $normalizedRoles);

            $this->auditLogger->log(
                action: 'department.updated',
                entityType: 'department',
                entityId: (int) $department->id,
                payload: [
                    'before' => $before,
                    'after' => $department->only(['name', 'staff_type']),
                    'org_structure_role_template_ids' => collect($normalizedRoles)
                        ->pluck('org_structure_role_template_id')
                        ->values()
                        ->all(),
                ],
                request: $request,
                userId: $request->user()?->id
            );
        });

        return back();
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $department = Department::query()
            ->whereNull('school_id')
            ->findOrFail($id);

        $before = $department->only(['id', 'name', 'staff_type']);
        $department->delete();

        $this->auditLogger->log(
            action: 'department.deleted',
            entityType: 'department',
            entityId: (int) $id,
            payload: ['before' => $before],
            request: $request,
            userId: $request->user()?->id
        );

        return back();
    }

    /**
     * @param array<int, array{
     *     org_structure_role_template_id: int,
     *     name: string,
     *     can_manage_student_structure: bool,
     *     can_manage_student_attendance: bool,
     *     can_manage_academic_planning: bool,
     *     can_manage_student_leaves: bool
     * }> $roles
     */
    private function syncDepartmentRoles(Department $department, array $roles): void
    {
        $superAdminAssignsPermissions = !$this->managerAssignsStructurePermissions();
        $keptRoleIds = [];

        foreach ($roles as $rolePayload) {
            $canManageStudentStructure = $superAdminAssignsPermissions
                && $department->staff_type === Department::STAFF_TYPE_ADMINISTRATIVE
                && (bool) ($rolePayload['can_manage_student_structure'] ?? false);
            $canManageStudentAttendance = $superAdminAssignsPermissions
                && $department->staff_type === Department::STAFF_TYPE_ADMINISTRATIVE
                && (bool) ($rolePayload['can_manage_student_attendance'] ?? false);
            $canManageAcademicPlanning = $superAdminAssignsPermissions
                && $department->staff_type === Department::STAFF_TYPE_ADMINISTRATIVE
                && (bool) ($rolePayload['can_manage_academic_planning'] ?? false);
            $canManageStudentLeaves = $superAdminAssignsPermissions
                && $department->staff_type === Department::STAFF_TYPE_ADMINISTRATIVE
                && (bool) ($rolePayload['can_manage_student_leaves'] ?? false);

            $role = DepartmentRole::query()->updateOrCreate(
                [
                    'department_id' => $department->id,
                    'org_structure_role_template_id' => $rolePayload['org_structure_role_template_id'],
                ],
                [
                    'name' => $rolePayload['name'],
                    'is_active' => true,
                    'can_manage_student_structure' => $canManageStudentStructure,
                    'can_manage_student_attendance' => $canManageStudentAttendance,
                    'can_manage_academic_planning' => $canManageAcademicPlanning,
                    'can_manage_student_leaves' => $canManageStudentLeaves,
                ]
            );

            $keptRoleIds[] = (int) $role->id;
        }

        $query = $department->roles();
        if (count($keptRoleIds) > 0) {
            $query->whereNotIn('id', $keptRoleIds);
        }

        $query->delete();
    }

    /**
     * @return array<int, array{
     *     org_structure_role_template_id: int,
     *     name: string,
     *     can_manage_student_structure: bool,
     *     can_manage_student_attendance: bool,
     *     can_manage_academic_planning: bool,
     *     can_manage_student_leaves: bool
     * }>
     */
    private function normalizeOrgStructureRolesPayload(Request $request, string $staffType, ?Department $department): array
    {
        $this->guardLegacyRoleCreationPayload($request);

        $superAdminAssignsPermissions = !$this->managerAssignsStructurePermissions();
        $rolesInput = $request->input('org_structure_roles', []);

        $clean = collect(is_array($rolesInput) ? $rolesInput : [])
            ->map(function ($item) use ($staffType, $superAdminAssignsPermissions): array {
                $templateId = (int) data_get($item, 'org_structure_role_template_id', 0);
                $canManage = $superAdminAssignsPermissions
                    && $staffType === Department::STAFF_TYPE_ADMINISTRATIVE
                    && (bool) data_get($item, 'can_manage_student_structure', false);
                $canManageAttendance = $superAdminAssignsPermissions
                    && $staffType === Department::STAFF_TYPE_ADMINISTRATIVE
                    && (bool) data_get($item, 'can_manage_student_attendance', false);
                $canManageAcademicPlanning = $superAdminAssignsPermissions
                    && $staffType === Department::STAFF_TYPE_ADMINISTRATIVE
                    && (bool) data_get($item, 'can_manage_academic_planning', false);
                $canManageStudentLeaves = $superAdminAssignsPermissions
                    && $staffType === Department::STAFF_TYPE_ADMINISTRATIVE
                    && (bool) data_get($item, 'can_manage_student_leaves', false);

                return [
                    'org_structure_role_template_id' => $templateId,
                    'can_manage_student_structure' => $canManage,
                    'can_manage_student_attendance' => $canManageAttendance,
                    'can_manage_academic_planning' => $canManageAcademicPlanning,
                    'can_manage_student_leaves' => $canManageStudentLeaves,
                ];
            })
            ->filter(fn (array $item): bool => $item['org_structure_role_template_id'] > 0)
            ->unique('org_structure_role_template_id')
            ->values();

        if ($clean->isEmpty()) {
            throw ValidationException::withMessages([
                'org_structure_roles' => 'At least one org structure role template is required.',
            ]);
        }

        $templateIds = $clean->pluck('org_structure_role_template_id')->values()->all();
        $templates = OrgStructureRoleTemplate::query()
            ->whereIn('id', $templateIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        if ($templates->count() !== count($templateIds)) {
            throw ValidationException::withMessages([
                'org_structure_roles' => 'One or more selected org structure role templates are invalid or inactive.',
            ]);
        }

        $activeTemplateIds = OrgStructureRoleTemplate::query()
            ->whereIn('id', $templateIds)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $activeTemplateIdLookup = array_fill_keys($activeTemplateIds, true);
        $allowedInactiveTemplateIdLookup = [];
        if ($department) {
            $allowedInactiveTemplateIdLookup = DepartmentRole::query()
                ->where('department_id', $department->id)
                ->whereIn('org_structure_role_template_id', $templateIds)
                ->pluck('org_structure_role_template_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->flip()
                ->all();
        }

        foreach ($templateIds as $templateId) {
            $isActive = isset($activeTemplateIdLookup[(int) $templateId]);
            $isAllowedInactiveForUpdate = $department && isset($allowedInactiveTemplateIdLookup[(int) $templateId]);

            if (!$isActive && !$isAllowedInactiveForUpdate) {
                throw ValidationException::withMessages([
                    'org_structure_roles' => 'One or more selected org structure role templates are invalid or inactive.',
                ]);
            }
        }

        return $clean->map(function (array $payload) use ($templates): array {
            $template = $templates->get($payload['org_structure_role_template_id']);

            return [
                'org_structure_role_template_id' => $payload['org_structure_role_template_id'],
                'name' => (string) $template->name,
                'can_manage_student_structure' => (bool) $payload['can_manage_student_structure'],
                'can_manage_student_attendance' => (bool) $payload['can_manage_student_attendance'],
                'can_manage_academic_planning' => (bool) $payload['can_manage_academic_planning'],
                'can_manage_student_leaves' => (bool) $payload['can_manage_student_leaves'],
            ];
        })->values()->all();
    }

    private function guardLegacyRoleCreationPayload(Request $request): void
    {
        $legacyRoleNames = $request->input('role_names');
        $legacyRoles = $request->input('roles');

        $hasLegacyRoleNames = is_array($legacyRoleNames) && count($legacyRoleNames) > 0;
        $hasLegacyRoles = is_array($legacyRoles) && count($legacyRoles) > 0;

        if (!$hasLegacyRoleNames && !$hasLegacyRoles) {
            return;
        }

        throw ValidationException::withMessages([
            'org_structure_roles' => 'Creating structure roles from departments page is disabled. Please create templates from Super Admin user roles page.',
        ]);
    }

    private function managerAssignsStructurePermissions(): bool
    {
        return (bool) config('features.rbac.manager_assigns_structure_permissions', true);
    }
}
