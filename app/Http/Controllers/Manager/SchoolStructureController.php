<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\OrgStructureRoleTemplate;
use App\Models\School;
use App\Models\SchoolPermissionGroup;
use App\Models\User;
use App\Services\School\SchoolDelegationService;
use App\Services\Support\AttachmentService;
use App\Support\SchoolPermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class SchoolStructureController extends Controller
{
    public function __construct(
        private readonly AttachmentService $attachmentService,
        private readonly SchoolDelegationService $schoolDelegationService
    ) {
    }

    public function index(Request $request): Response
    {
        $manager = $request->user();
        $schoolId = (int) ($manager->school_id ?? 0);
        $departmentRoleColumns = $this->departmentRoleColumns();
        $userColumns = $this->managedUserColumns();

        $school = $schoolId > 0
            ? School::query()->find($schoolId, ['id', 'name', 'school_id'])
            : null;

        $departments = $schoolId > 0
            ? Department::query()
                ->where(function ($query) use ($schoolId): void {
                    $query
                        ->where('school_id', $schoolId)
                        ->orWhere(function ($legacyQuery) use ($schoolId): void {
                            $legacyQuery
                                ->whereNull('school_id')
                                ->whereHas('users', fn ($userQuery) => $userQuery->where('school_id', $schoolId));
                        });
                })
                ->with(['roles' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with('orgStructureRoleTemplate:id,is_active')
                    ->orderBy('name')
                    ->select($departmentRoleColumns)])
                ->withCount(['users' => fn ($query) => $query->where('school_id', $schoolId)])
                ->orderByRaw('school_id is null asc')
                ->orderBy('name')
                ->get(['id', 'name', 'staff_type', 'school_id', 'created_at'])
                ->map(fn (Department $department): array => $this->serializeDepartment($department, $schoolId))
            : collect();

        $orgStructureRoleTemplates = OrgStructureRoleTemplate::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_active']);

        $users = $schoolId > 0
            ? User::query()
                ->with([
                    'department:id,name,staff_type,school_id',
                    'departmentRole:' . implode(',', $this->departmentRoleColumnsForUserRelation()),
                    'roles:id,name',
                    'permissions:id,name',
                    'schoolPermissionGroups:id,school_id,name,group_type,permission_names',
                    'attachments' => fn ($attachments) => $attachments
                        ->whereNull('deleted_at')
                        ->with('uploader:id,name')
                        ->orderByDesc('id'),
                ])
                ->where('school_id', $schoolId)
                ->where(function ($query): void {
                    $query->where('role', 'staff')
                        ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'staff'));
                })
                ->orderByDesc('id')
                ->get($userColumns)
                ->map(fn (User $user): array => $this->serializeManagedUser($user))
            : collect();

        $schoolPermissionGroups = $schoolId > 0
            ? SchoolPermissionGroup::query()
                ->where('school_id', $schoolId)
                ->withCount('users')
                ->orderBy('group_type')
                ->orderBy('name')
                ->get()
                ->map(fn (SchoolPermissionGroup $group): array => $this->serializePermissionGroup($group))
            : collect();

        return Inertia::render('Manager/SchoolStructure', [
            'school' => $school,
            'departments' => $departments,
            'users' => $users,
            'staffTypes' => [
                Department::STAFF_TYPE_ADMINISTRATIVE,
                Department::STAFF_TYPE_EDUCATIONAL,
            ],
            'orgStructureRoleTemplates' => $orgStructureRoleTemplates,
            'managerAssignsStructurePermissions' => (bool) config('features.rbac.manager_assigns_structure_permissions', true),
            'assignablePermissionGroups' => $this->schoolDelegationService->managerCatalogGroups(),
            'permissionGroupTypeOptions' => $this->schoolDelegationService->managerGroupTypeOptions(),
            'delegationTemplates' => $this->schoolDelegationService->delegationTemplates(),
            'delegationAuditEntries' => $schoolId > 0 ? $this->schoolDelegationService->delegationAuditFeedForSchool($schoolId) : [],
            'schoolPermissionGroups' => $schoolPermissionGroups,
            'roleAssignmentEnabled' => (bool) config('features.rbac.school_role_assignment_enabled', true),
        ]);
    }

    private function serializeManagedUser(User $user): array
    {
        $groupSummaries = $user->schoolPermissionGroups
            ->map(fn ($group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'group_type' => $group->group_type,
                'group_type_label' => SchoolPermissionCatalog::groupTypeLabel((string) $group->group_type),
                'permission_names' => collect($group->permission_names ?? [])->map(fn ($permissionName): string => trim((string) $permissionName))->filter()->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'role' => $user->role,
            'school_id' => $user->school_id,
            'department_id' => $user->department_id,
            'department_role_id' => $user->department_role_id,
            'school_staff_type' => $user->school_staff_type,
            'can_manage_student_structure' => $user->can_manage_student_structure,
            'can_manage_student_attendance' => $user->can_manage_student_attendance,
            'can_manage_academic_planning' => $user->can_manage_academic_planning,
            'can_manage_student_leaves' => $user->can_manage_student_leaves,
            'can_manage_leave_types' => $user->can_manage_leave_types,
            'can_manage_school_calendar' => $user->can_manage_school_calendar,
            'can_manage_school_holidays' => $user->can_manage_school_holidays,
            'department' => $user->department ? [
                'id' => $user->department->id,
                'name' => $user->department->name,
            ] : null,
            'department_role' => $user->departmentRole ? [
                'id' => $user->departmentRole->id,
                'name' => $user->departmentRole->name,
            ] : null,
            'roles' => $user->roles->map(fn ($role): array => [
                'id' => $role->id,
                'name' => $role->name,
            ])->values()->all(),
            'role_names' => $user->roles->pluck('name')->values()->all(),
            'permission_names' => $user->directSchoolPermissionNames(),
            'direct_permission_names' => $user->directSchoolPermissionNames(),
            'effective_permission_names' => $user->effectiveSchoolPermissionNames(),
            'school_permission_group_ids' => collect($groupSummaries)->pluck('id')->values()->all(),
            'school_permission_groups' => $groupSummaries,
            'attachments' => $user->attachments
                ->map(fn ($attachment) => $this->attachmentService->serializeForUi($attachment))
                ->values()
                ->all(),
            'created_at' => optional($user->created_at)->toISOString(),
            'updated_at' => optional($user->updated_at)->toISOString(),
        ];
    }

    private function serializePermissionGroup(SchoolPermissionGroup $group): array
    {
        $metadata = SchoolPermissionCatalog::permissionMetadata();
        $permissionNames = collect($group->permission_names ?? [])
            ->map(fn ($permissionName): string => trim((string) $permissionName))
            ->filter()
            ->values();

        return [
            'id' => $group->id,
            'name' => $group->name,
            'school_id' => $group->school_id,
            'group_type' => $group->group_type,
            'group_type_label' => $group->groupTypeLabel(),
            'permission_names' => $permissionNames->all(),
            'permissions' => $permissionNames
                ->map(fn (string $permissionName): array => $metadata[$permissionName] ?? [
                    'name' => $permissionName,
                    'label' => $permissionName,
                    'description' => '',
                    'tone' => 'slate',
                    'type' => $group->group_type,
                ])
                ->values()
                ->all(),
            'users_count' => (int) ($group->users_count ?? 0),
            'created_at' => optional($group->created_at)->toISOString(),
            'updated_at' => optional($group->updated_at)->toISOString(),
        ];
    }

    private function serializeDepartment(Department $department, int $schoolId): array
    {
        $isSchoolOwned = (int) ($department->school_id ?? 0) === $schoolId;
        $roles = $department->roles
            ->filter(function (DepartmentRole $role) use ($isSchoolOwned): bool {
                if ($isSchoolOwned) {
                    return true;
                }

                return $role->org_structure_role_template_id === null
                    || (bool) optional($role->orgStructureRoleTemplate)->is_active;
            })
            ->values();

        return [
            'id' => $department->id,
            'name' => $department->name,
            'staff_type' => $department->staff_type,
            'school_id' => $department->school_id,
            'users_count' => (int) ($department->users_count ?? 0),
            'is_school_owned' => $isSchoolOwned,
            'is_legacy_global' => !$isSchoolOwned,
            'can_manage' => $isSchoolOwned,
            'scope_label' => $isSchoolOwned ? 'إدارة خاصة بالمدرسة' : 'إدارة عامة قديمة',
            'roles' => $roles->map(fn (DepartmentRole $role): array => [
                'id' => $role->id,
                'department_id' => $role->department_id,
                'org_structure_role_template_id' => $role->org_structure_role_template_id,
                'name' => $role->name,
                'is_active' => (bool) $role->is_active,
                'can_manage_student_structure' => (bool) $role->can_manage_student_structure,
                'can_manage_student_attendance' => (bool) $role->can_manage_student_attendance,
                'can_manage_academic_planning' => (bool) $role->can_manage_academic_planning,
                'can_manage_student_leaves' => (bool) $role->can_manage_student_leaves,
                'can_manage_leave_types' => (bool) $role->can_manage_leave_types,
                'can_manage_school_calendar' => (bool) $role->can_manage_school_calendar,
                'can_manage_school_holidays' => (bool) $role->can_manage_school_holidays,
            ])->values()->all(),
            'created_at' => optional($department->created_at)->toISOString(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function departmentRoleColumns(): array
    {
        return array_merge(
            ['id', 'department_id', 'org_structure_role_template_id', 'name', 'is_active'],
            $this->availableColumns('department_roles')
        );
    }

    /**
     * @return array<int, string>
     */
    private function departmentRoleColumnsForUserRelation(): array
    {
        return array_merge(
            ['id', 'department_id', 'org_structure_role_template_id', 'name'],
            $this->availableColumns('department_roles')
        );
    }

    /**
     * @return array<int, string>
     */
    private function managedUserColumns(): array
    {
        return array_merge(
            [
                'id',
                'name',
                'email',
                'mobile',
                'role',
                'department_id',
                'department_role_id',
                'school_id',
                'school_staff_type',
                'created_at',
            ],
            $this->availableColumns('users')
        );
    }

    /**
     * @return array<int, string>
     */
    private function availableColumns(string $table): array
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $permissionColumns = [
            'can_manage_student_structure',
            'can_manage_student_attendance',
            'can_manage_academic_planning',
            'can_manage_student_leaves',
            'can_manage_leave_types',
            'can_manage_school_calendar',
            'can_manage_school_holidays',
        ];

        $cache[$table] = collect($permissionColumns)
            ->filter(fn (string $column): bool => Schema::hasColumn($table, $column))
            ->values()
            ->all();

        return $cache[$table];
    }
}
