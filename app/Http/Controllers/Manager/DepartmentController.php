<?php

namespace App\Http\Controllers\Manager;

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

class DepartmentController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveManagerSchoolId($request);
        $this->authorizeManagerDepartmentAccess($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'staff_type' => ['required', Rule::in(Department::allowedStaffTypes())],
            'org_structure_roles' => ['required', 'array', 'min:1'],
            'org_structure_roles.*.id' => ['nullable', 'integer'],
            'org_structure_roles.*.name' => ['required', 'string', 'max:255'],
            'org_structure_roles.*.org_structure_role_template_id' => ['nullable', 'integer', Rule::exists('org_structure_role_templates', 'id')],
            'org_structure_roles.*.can_manage_student_structure' => ['nullable', 'boolean'],
            'org_structure_roles.*.can_manage_student_attendance' => ['nullable', 'boolean'],
            'org_structure_roles.*.can_manage_academic_planning' => ['nullable', 'boolean'],
            'org_structure_roles.*.can_manage_student_leaves' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'اسم الإدارة مطلوب.',
            'name.unique' => 'يوجد إدارة بنفس الاسم داخل هذه المدرسة.',
            'staff_type.required' => 'نوع الإدارة مطلوب.',
            'staff_type.in' => 'نوع الإدارة غير صالح.',
            'org_structure_roles.required' => 'يجب إضافة دور وظيفي واحد على الأقل داخل الإدارة.',
            'org_structure_roles.array' => 'صيغة الأدوار الوظيفية غير صحيحة.',
            'org_structure_roles.min' => 'يجب إضافة دور وظيفي واحد على الأقل داخل الإدارة.',
            'org_structure_roles.*.name.required' => 'اسم الدور الوظيفي مطلوب.',
            'org_structure_roles.*.name.max' => 'اسم الدور الوظيفي يجب ألا يتجاوز 255 حرفًا.',
            'org_structure_roles.*.org_structure_role_template_id.exists' => 'أحد القوالب المرتبطة بالدور غير صالح.',
        ]);

        $normalizedRoles = $this->normalizeOrgStructureRolesPayload($request, $validated['staff_type'], null);

        DB::transaction(function () use ($validated, $normalizedRoles, $request, $schoolId): void {
            $department = Department::query()->create([
                'name' => $validated['name'],
                'staff_type' => $validated['staff_type'],
                'school_id' => $schoolId,
            ]);

            $this->syncDepartmentRoles($department, $normalizedRoles);

            $this->auditLogger->log(
                action: 'school_department.created',
                entityType: 'department',
                entityId: (int) $department->id,
                payload: [
                    'school_id' => $schoolId,
                    'name' => $department->name,
                    'staff_type' => $department->staff_type,
                    'role_names' => collect($normalizedRoles)->pluck('name')->values()->all(),
                ],
                request: $request,
                userId: $request->user()?->id
            );
        });

        return back();
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $schoolId = $this->resolveManagerSchoolId($request);
        $this->authorizeManagerDepartmentAccess($request);
        $department = $this->resolveManagedDepartment($department->id, $schoolId);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')
                    ->ignore($department->id)
                    ->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'staff_type' => ['required', Rule::in(Department::allowedStaffTypes())],
            'org_structure_roles' => ['required', 'array', 'min:1'],
            'org_structure_roles.*.id' => ['nullable', 'integer'],
            'org_structure_roles.*.name' => ['required', 'string', 'max:255'],
            'org_structure_roles.*.org_structure_role_template_id' => ['nullable', 'integer', Rule::exists('org_structure_role_templates', 'id')],
            'org_structure_roles.*.can_manage_student_structure' => ['nullable', 'boolean'],
            'org_structure_roles.*.can_manage_student_attendance' => ['nullable', 'boolean'],
            'org_structure_roles.*.can_manage_academic_planning' => ['nullable', 'boolean'],
            'org_structure_roles.*.can_manage_student_leaves' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'اسم الإدارة مطلوب.',
            'name.unique' => 'يوجد إدارة بنفس الاسم داخل هذه المدرسة.',
            'staff_type.required' => 'نوع الإدارة مطلوب.',
            'staff_type.in' => 'نوع الإدارة غير صالح.',
            'org_structure_roles.required' => 'يجب إضافة دور وظيفي واحد على الأقل داخل الإدارة.',
            'org_structure_roles.array' => 'صيغة الأدوار الوظيفية غير صحيحة.',
            'org_structure_roles.min' => 'يجب إضافة دور وظيفي واحد على الأقل داخل الإدارة.',
            'org_structure_roles.*.name.required' => 'اسم الدور الوظيفي مطلوب.',
            'org_structure_roles.*.name.max' => 'اسم الدور الوظيفي يجب ألا يتجاوز 255 حرفًا.',
            'org_structure_roles.*.org_structure_role_template_id.exists' => 'أحد القوالب المرتبطة بالدور غير صالح.',
        ]);

        $before = $department->only(['name', 'staff_type']);
        $normalizedRoles = $this->normalizeOrgStructureRolesPayload($request, $validated['staff_type'], $department);

        DB::transaction(function () use ($department, $validated, $normalizedRoles, $before, $request, $schoolId): void {
            $department->update([
                'name' => $validated['name'],
                'staff_type' => $validated['staff_type'],
            ]);

            $this->syncDepartmentRoles($department, $normalizedRoles);

            $this->auditLogger->log(
                action: 'school_department.updated',
                entityType: 'department',
                entityId: (int) $department->id,
                payload: [
                    'school_id' => $schoolId,
                    'before' => $before,
                    'after' => $department->only(['name', 'staff_type']),
                    'role_names' => collect($normalizedRoles)->pluck('name')->values()->all(),
                ],
                request: $request,
                userId: $request->user()?->id
            );
        });

        return back();
    }

    public function destroy(Request $request, Department $department): RedirectResponse
    {
        $schoolId = $this->resolveManagerSchoolId($request);
        $this->authorizeManagerDepartmentAccess($request);
        $department = $this->resolveManagedDepartment($department->id, $schoolId);

        $hasAssignedUsers = $department->users()
            ->where('school_id', $schoolId)
            ->exists();

        if ($hasAssignedUsers) {
            throw ValidationException::withMessages([
                'department' => 'لا يمكن حذف الإدارة لأنها مرتبطة حاليًا بمستخدمين داخل المدرسة. انقل المستخدمين أولًا ثم أعد المحاولة.',
            ]);
        }

        $before = $department->only(['id', 'name', 'staff_type', 'school_id']);
        $department->delete();

        $this->auditLogger->log(
            action: 'school_department.deleted',
            entityType: 'department',
            entityId: (int) $before['id'],
            payload: ['before' => $before],
            request: $request,
            userId: $request->user()?->id
        );

        return back();
    }

    private function authorizeManagerDepartmentAccess(Request $request): void
    {
        abort_unless((bool) $request->user()?->can('manage-school-users'), 403);
    }

    private function resolveManagerSchoolId(Request $request): int
    {
        $schoolId = (int) ($request->user()?->school_id ?? 0);

        if ($schoolId <= 0) {
            throw ValidationException::withMessages([
                'school' => 'حساب المدير يجب أن يكون مرتبطًا بمدرسة أولًا.',
            ]);
        }

        return $schoolId;
    }

    private function resolveManagedDepartment(int $departmentId, int $schoolId): Department
    {
        return Department::query()
            ->whereKey($departmentId)
            ->where('school_id', $schoolId)
            ->firstOrFail();
    }

    /**
     * @param array<int, array{
     *     id: int|null,
     *     name: string,
     *     org_structure_role_template_id: int|null,
     *     can_manage_student_structure: bool,
     *     can_manage_student_attendance: bool,
     *     can_manage_academic_planning: bool,
     *     can_manage_student_leaves: bool
     * }> $roles
     */
    private function syncDepartmentRoles(Department $department, array $roles): void
    {
        $superAdminAssignsPermissions = !$this->managerAssignsStructurePermissions();
        $existingRoles = $department->roles()->get()->keyBy('id');
        $submittedRoleIds = collect($roles)
            ->pluck('id')
            ->filter(fn ($roleId): bool => (int) $roleId > 0)
            ->map(fn ($roleId): int => (int) $roleId)
            ->values()
            ->all();

        $rolesMarkedForDeletion = $existingRoles
            ->reject(fn (DepartmentRole $role): bool => in_array((int) $role->id, $submittedRoleIds, true))
            ->values();

        $blockedRoleNames = $rolesMarkedForDeletion
            ->filter(fn (DepartmentRole $role): bool => $role->users()->where('school_id', $department->school_id)->exists())
            ->pluck('name')
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->values()
            ->all();

        if (count($blockedRoleNames) > 0) {
            throw ValidationException::withMessages([
                'org_structure_roles' => 'لا يمكن حذف بعض الأدوار الوظيفية لأنها ما تزال مرتبطة بمستخدمين داخل المدرسة: ' . implode('، ', $blockedRoleNames) . '. انقل المستخدمين أولًا ثم أعد المحاولة.',
            ]);
        }

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

            $attributes = [
                'name' => $rolePayload['name'],
                'is_active' => true,
                'org_structure_role_template_id' => $rolePayload['org_structure_role_template_id'],
                'can_manage_student_structure' => $canManageStudentStructure,
                'can_manage_student_attendance' => $canManageStudentAttendance,
                'can_manage_academic_planning' => $canManageAcademicPlanning,
                'can_manage_student_leaves' => $canManageStudentLeaves,
            ];

            $roleId = (int) ($rolePayload['id'] ?? 0);
            if ($roleId > 0 && $existingRoles->has($roleId)) {
                $existingRoles->get($roleId)?->update($attributes);
                continue;
            }

            $department->roles()->create($attributes);
        }

        if ($rolesMarkedForDeletion->isNotEmpty()) {
            DepartmentRole::query()
                ->whereIn('id', $rolesMarkedForDeletion->pluck('id')->all())
                ->delete();
        }
    }

    /**
     * @return array<int, array{
     *     id: int|null,
     *     name: string,
     *     org_structure_role_template_id: int|null,
     *     can_manage_student_structure: bool,
     *     can_manage_student_attendance: bool,
     *     can_manage_academic_planning: bool,
     *     can_manage_student_leaves: bool
     * }>
     */
    private function normalizeOrgStructureRolesPayload(Request $request, string $staffType, ?Department $department): array
    {
        $superAdminAssignsPermissions = !$this->managerAssignsStructurePermissions();
        $rolesInput = $request->input('org_structure_roles', []);
        $existingRoles = $department
            ? $department->roles()->get()->keyBy('id')
            : collect();

        $clean = collect(is_array($rolesInput) ? $rolesInput : [])
            ->values()
            ->map(function ($item, int $index) use ($staffType, $superAdminAssignsPermissions, $existingRoles): array {
                $roleId = (int) data_get($item, 'id', 0);
                $existingRole = $roleId > 0 ? $existingRoles->get($roleId) : null;

                if ($roleId > 0 && !$existingRole) {
                    throw ValidationException::withMessages([
                        "org_structure_roles.$index.id" => 'الدور الوظيفي المحدد غير صالح أو لا يتبع هذه الإدارة.',
                    ]);
                }

                $templateId = data_get($item, 'org_structure_role_template_id');
                $normalizedTemplateId = ($templateId === null || $templateId === '')
                    ? null
                    : (int) $templateId;

                if ($existingRole && !$normalizedTemplateId && $existingRole->org_structure_role_template_id) {
                    $normalizedTemplateId = (int) $existingRole->org_structure_role_template_id;
                }

                return [
                    'id' => $existingRole ? (int) $existingRole->id : null,
                    'name' => trim((string) data_get($item, 'name', '')),
                    'org_structure_role_template_id' => $normalizedTemplateId,
                    'can_manage_student_structure' => $superAdminAssignsPermissions
                        && $staffType === Department::STAFF_TYPE_ADMINISTRATIVE
                        && (bool) data_get($item, 'can_manage_student_structure', false),
                    'can_manage_student_attendance' => $superAdminAssignsPermissions
                        && $staffType === Department::STAFF_TYPE_ADMINISTRATIVE
                        && (bool) data_get($item, 'can_manage_student_attendance', false),
                    'can_manage_academic_planning' => $superAdminAssignsPermissions
                        && $staffType === Department::STAFF_TYPE_ADMINISTRATIVE
                        && (bool) data_get($item, 'can_manage_academic_planning', false),
                    'can_manage_student_leaves' => $superAdminAssignsPermissions
                        && $staffType === Department::STAFF_TYPE_ADMINISTRATIVE
                        && (bool) data_get($item, 'can_manage_student_leaves', false),
                ];
            })
            ->filter(fn (array $item): bool => $item['name'] !== '')
            ->values();

        if ($clean->isEmpty()) {
            throw ValidationException::withMessages([
                'org_structure_roles' => 'يجب إضافة دور وظيفي واحد على الأقل داخل الإدارة.',
            ]);
        }

        $normalizedNames = $clean
            ->map(fn (array $item): string => mb_strtolower($item['name'], 'UTF-8'));

        if ($normalizedNames->unique()->count() !== $normalizedNames->count()) {
            throw ValidationException::withMessages([
                'org_structure_roles' => 'يجب أن تكون أسماء الأدوار الوظيفية داخل الإدارة فريدة.',
            ]);
        }

        $templateIds = $clean
            ->pluck('org_structure_role_template_id')
            ->filter(fn ($templateId): bool => (int) $templateId > 0)
            ->map(fn ($templateId): int => (int) $templateId)
            ->unique()
            ->values()
            ->all();

        $templates = count($templateIds) > 0
            ? OrgStructureRoleTemplate::query()
                ->whereIn('id', $templateIds)
                ->get(['id', 'is_active'])
                ->keyBy('id')
            : collect();

        if ($templates->count() !== count($templateIds)) {
            throw ValidationException::withMessages([
                'org_structure_roles' => 'أحد القوالب المرتبطة بالدور غير صالح.',
            ]);
        }

        foreach ($clean as $index => $rolePayload) {
            $templateId = (int) ($rolePayload['org_structure_role_template_id'] ?? 0);

            if ($templateId <= 0) {
                continue;
            }

            $template = $templates->get($templateId);
            $existingRole = $rolePayload['id']
                ? $existingRoles->get((int) $rolePayload['id'])
                : null;

            $keepsCurrentInactiveTemplate = $existingRole
                && (int) ($existingRole->org_structure_role_template_id ?? 0) === $templateId;

            if (!$template || (!(bool) $template->is_active && !$keepsCurrentInactiveTemplate)) {
                throw ValidationException::withMessages([
                    "org_structure_roles.$index.org_structure_role_template_id" => 'القالب المرتبط بهذا الدور غير صالح أو غير مفعل.',
                ]);
            }
        }

        return $clean->all();
    }

    private function managerAssignsStructurePermissions(): bool
    {
        return (bool) config('features.rbac.manager_assigns_structure_permissions', true);
    }
}
