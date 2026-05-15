<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Concerns\HandlesUserIdentityUniqueness;
use App\Http\Controllers\Concerns\NormalizesSaudiPhoneInputs;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreSchoolUserWithRolesRequest;
use App\Http\Requests\School\SyncSchoolUserRolesRequest;
use App\Http\Requests\School\UpdateSchoolUserWithRolesRequest;
use App\Models\Department;
use App\Models\SchoolPermissionGroup;
use App\Models\User;
use App\Services\Auth\SchoolRoleAssignmentService;
use App\Services\School\SchoolDelegationService;
use App\Services\Subscription\SubscriptionPricingService;
use App\Services\Support\AttachmentService;
use App\Services\Support\AuditLogger;
use App\Support\SchoolPermissionCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SchoolUserManagementController extends Controller
{
    use NormalizesSaudiPhoneInputs;
    use HandlesUserIdentityUniqueness;

    public function __construct(
        private readonly SchoolRoleAssignmentService $roleAssignmentService,
        private readonly SchoolDelegationService $schoolDelegationService,
        private readonly AuditLogger $auditLogger,
        private readonly SubscriptionPricingService $subscriptionPricingService,
        private readonly AttachmentService $attachmentService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureFeatureEnabled();
        $schoolId = $this->resolveManagerSchoolId($request);
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 0);
        if ($perPage > 0) {
            $paginator = $this->schoolUsersQuery($schoolId)
                ->paginate($perPage, $this->managedUserColumns())
                ->appends($request->query());

            return response()->json([
                'data' => $paginator->getCollection()->map(fn (User $user) => $this->serializeUser($user))->values()->all(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        }

        $users = $this->schoolUsersQuery($schoolId)->get($this->managedUserColumns());

        return response()->json([
            'data' => $users->map(fn (User $user) => $this->serializeUser($user))->all(),
        ]);
    }

    public function store(StoreSchoolUserWithRolesRequest $request): JsonResponse
    {
        $this->ensureFeatureEnabled();
        $this->normalizeSaudiPhoneInputs($request, ['mobile']);

        $schoolId = $this->resolveManagerSchoolId($request);
        $validated = $request->validated();
        $request->validate(
            $this->attachmentService->uploadValidationRules(),
            $this->attachmentService->uploadValidationMessages()
        );
        $normalizedRoleNames = $this->normalizeRoleNames($validated['role_names'] ?? []);
        $delegationPermissionNames = $this->resolveDelegationPermissionNames($validated);
        $seatAddon = null;

        try {
            $result = DB::transaction(function () use (
                $request,
                $validated,
                $schoolId,
                $normalizedRoleNames,
                $delegationPermissionNames
            ): array {
                $department = Department::query()
                    ->whereKey($validated['department_id'])
                    ->where(function ($query) use ($schoolId): void {
                        $query->whereNull('school_id')
                            ->orWhere('school_id', $schoolId);
                    })
                    ->firstOrFail();

                if (!$department->staff_type) {
                    throw ValidationException::withMessages([
                        'department_id' => 'Selected department is missing staff type configuration.',
                    ]);
                }

                $roleBelongsToDepartment = $this->departmentAllowsRoleAssignment(
                    $department,
                    (int) $validated['department_role_id'],
                    $schoolId
                );

                if (!$roleBelongsToDepartment) {
                    throw ValidationException::withMessages([
                        'department_role_id' => 'Selected role does not belong to selected department.',
                    ]);
                }

                $seatAddon = $this->subscriptionPricingService->reserveSeatsForSchoolStaff(
                    $request->user(),
                    $schoolId
                );

                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'mobile' => $validated['mobile'],
                    'password' => Hash::make($validated['password']),
                    'role' => 'staff',
                    'is_active' => true,
                    'school_id' => $schoolId,
                    'department_id' => $department->id,
                    'department_role_id' => $validated['department_role_id'],
                    'school_staff_type' => $department->staff_type,
                ] + $this->resolveStructurePermissionPayload($validated, false));

                $this->storeSchoolUserAttachments($user, $request, $schoolId);

                $user->syncRoles($normalizedRoleNames);
                $this->schoolDelegationService->syncUserDelegation(
                    $user,
                    $schoolId,
                    $delegationPermissionNames,
                    $validated['school_permission_group_ids'] ?? [],
                    $request,
                    $request->user()
                );

                return [
                    'user' => $user->fresh(['roles', 'permissions', 'department', 'departmentRole', 'schoolPermissionGroups', 'attachments.uploader']),
                    'seat_addon' => $seatAddon,
                ];
            });

            $user = $result['user'];
            $seatAddon = $result['seat_addon'];
        } catch (QueryException $exception) {
            $this->rethrowAsDuplicateUserValidation($exception);
            throw $exception;
        }

        Log::info('school_user.created', [
            'actor_id' => $request->user()?->id,
            'school_id' => $schoolId,
            'target_user_id' => $user->id,
            'assigned_roles' => $user->roles->pluck('name')->values()->all(),
        ]);

        $this->auditLogger->log(
            action: 'school_user.created',
            entityType: 'user',
            entityId: (int) $user->id,
            payload: [
                'school_id' => $schoolId,
                'department_id' => (int) $user->department_id,
                'department_role_id' => (int) $user->department_role_id,
                'assigned_roles' => $user->roles->pluck('name')->values()->all(),
            ],
            request: $request,
            userId: $request->user()?->id
        );

        return response()->json([
            'data' => $this->serializeUser($user),
            'seat_addon' => $seatAddon ? [
                'added_seats_count' => (int) $seatAddon->added_seats_count,
                'extra_user_monthly_price' => $seatAddon->extra_user_monthly_price,
                'daily_price' => $seatAddon->daily_price,
                'remaining_days' => (int) $seatAddon->remaining_days,
                'amount' => $seatAddon->amount,
                'ends_at' => $seatAddon->ends_at?->toIso8601String(),
                'message' => $this->subscriptionPricingService->formatAddonSummary($seatAddon),
            ] : null,
        ], 201);
    }

    public function syncRoles(SyncSchoolUserRolesRequest $request, User $user): JsonResponse
    {
        $this->ensureFeatureEnabled();
        $schoolId = $this->resolveManagerSchoolId($request);
        $this->ensureManagedSchoolUser($request, $user, $schoolId);

        $beforeRoleNames = $user->roles()->pluck('name')->values()->all();
        $validated = $request->validated();
        $roleNames = $this->normalizeRoleNames($validated['role_names'] ?? []);

        $user->syncRoles($roleNames);

        if (!$user->hasLegacyRole('staff')) {
            $user->update(['role' => 'staff']);
        }

        $user->load(['roles', 'department', 'departmentRole']);

        Log::info('school_user.roles_synced', [
            'actor_id' => $request->user()?->id,
            'school_id' => $schoolId,
            'target_user_id' => $user->id,
            'assigned_roles' => $roleNames,
        ]);

        $this->auditLogger->log(
            action: 'school_user.roles_synced',
            entityType: 'user',
            entityId: (int) $user->id,
            payload: [
                'school_id' => $schoolId,
                'before_roles' => $beforeRoleNames,
                'after_roles' => $roleNames,
            ],
            request: $request,
            userId: $request->user()?->id
        );

        return response()->json([
            'data' => $this->serializeUser($user),
        ]);
    }

    public function update(UpdateSchoolUserWithRolesRequest $request, User $user): JsonResponse
    {
        $this->ensureFeatureEnabled();
        $this->normalizeSaudiPhoneInputs($request, ['mobile']);

        $schoolId = $this->resolveManagerSchoolId($request);
        $this->ensureManagedSchoolUser($request, $user, $schoolId);
        $validated = $request->validated();
        $request->validate(
            $this->attachmentService->uploadValidationRules(),
            $this->attachmentService->uploadValidationMessages()
        );
        $normalizedRoleNames = $this->normalizeRoleNames($validated['role_names'] ?? []);
        $beforeSnapshot = $this->serializeUser($user->loadMissing(['roles', 'permissions', 'department', 'departmentRole', 'schoolPermissionGroups']));
        $delegationPermissionNames = $this->resolveDelegationPermissionNames($validated, $user);
        $delegationGroupIds = array_key_exists('school_permission_group_ids', $validated)
            ? ($validated['school_permission_group_ids'] ?? [])
            : $user->schoolPermissionGroups()->pluck('school_permission_groups.id')->all();

        try {
            DB::transaction(function () use (
                $validated,
                $schoolId,
                $user,
                $normalizedRoleNames,
                $delegationPermissionNames,
                $delegationGroupIds,
                $request
            ): void {
                $department = Department::query()
                    ->whereKey($validated['department_id'])
                    ->where(function ($query) use ($schoolId): void {
                        $query->whereNull('school_id')
                            ->orWhere('school_id', $schoolId);
                    })
                    ->firstOrFail();

                if (!$department->staff_type) {
                    throw ValidationException::withMessages([
                        'department_id' => 'Selected department is missing staff type configuration.',
                    ]);
                }

                $requestedRoleId = (int) $validated['department_role_id'];
                $currentRoleId = (int) ($user->department_role_id ?? 0);

                $roleBelongsToDepartment = $this->departmentAllowsRoleAssignment(
                    $department,
                    (int) $validated['department_role_id'],
                    $schoolId,
                    $currentRoleId > 0 && $requestedRoleId === $currentRoleId ? $currentRoleId : null
                );

                if (!$roleBelongsToDepartment) {
                    throw ValidationException::withMessages([
                        'department_role_id' => 'Selected role does not belong to selected department.',
                    ]);
                }

                $payload = [
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'mobile' => $validated['mobile'],
                    'school_id' => $schoolId,
                    'department_id' => $department->id,
                    'department_role_id' => $validated['department_role_id'],
                    'school_staff_type' => $department->staff_type,
                ] + $this->resolveStructurePermissionPayload($validated, true);

                if (!empty($validated['password'])) {
                    $payload['password'] = Hash::make($validated['password']);
                }

                $user->update($payload);
                $this->storeSchoolUserAttachments($user, $request, $schoolId);
                $user->syncRoles($normalizedRoleNames);
                $this->schoolDelegationService->syncUserDelegation(
                    $user,
                    $schoolId,
                    $delegationPermissionNames,
                    $delegationGroupIds,
                    $request,
                    $request->user()
                );

                if (!$user->hasLegacyRole('staff')) {
                    $user->update(['role' => 'staff']);
                }
            });
        } catch (QueryException $exception) {
            $this->rethrowAsDuplicateUserValidation($exception);
            throw $exception;
        }

        $user->load(['roles', 'permissions', 'department', 'departmentRole', 'schoolPermissionGroups', 'attachments.uploader']);

        Log::info('school_user.updated', [
            'actor_id' => $request->user()?->id,
            'school_id' => $schoolId,
            'target_user_id' => $user->id,
            'assigned_roles' => $user->roles->pluck('name')->values()->all(),
        ]);

        $this->auditLogger->log(
            action: 'school_user.updated',
            entityType: 'user',
            entityId: (int) $user->id,
            payload: [
                'school_id' => $schoolId,
                'before' => $beforeSnapshot,
                'after' => $this->serializeUser($user),
            ],
            request: $request,
            userId: $request->user()?->id
        );

        return response()->json([
            'data' => $this->serializeUser($user),
        ]);
    }

    private function ensureFeatureEnabled(): void
    {
        if (!config('features.rbac.school_role_assignment_enabled', true)) {
            abort(404);
        }
    }

    private function resolveManagerSchoolId(Request $request): int
    {
        $schoolId = (int) ($request->user()?->school_id ?? 0);

        if ($schoolId <= 0) {
            throw ValidationException::withMessages([
                'school' => 'Manager account must be linked to a school first.',
            ]);
        }

        return $schoolId;
    }

    private function ensureManagedSchoolUser(Request $request, User $user, int $schoolId): void
    {
        if ((int) $user->school_id !== $schoolId) {
            abort(403, 'You are not allowed to manage this user.');
        }

        if ((int) $user->id === (int) $request->user()?->id) {
            abort(403, 'You cannot modify your own manager account roles from this endpoint.');
        }

        if (
            $user->hasSystemRole('super_admin')
            || $user->hasSystemRole('supervisor')
            || $user->hasSystemRole('school_manager')
        ) {
            abort(403, 'System accounts cannot be managed from this endpoint.');
        }
    }

    /**
     * @param array<int, string> $roleNames
     * @return array<int, string>
     */
    private function normalizeRoleNames(array $roleNames): array
    {
        return $this->roleAssignmentService->normalizeRoleNamesForSchoolManager($roleNames);
    }

    private function serializeUser(User $user): array
    {
        $groupSummaries = $user->schoolPermissionGroups
            ->map(fn (SchoolPermissionGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'group_type' => $group->group_type,
                'group_type_label' => SchoolPermissionCatalog::groupTypeLabel((string) $group->group_type),
                'permission_names' => collect($group->permission_names ?? [])
                    ->map(fn ($permissionName): string => trim((string) $permissionName))
                    ->filter()
                    ->values()
                    ->all(),
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

    private function storeSchoolUserAttachments(User $user, Request $request, int $schoolId): void
    {
        $files = $request->file('attachments', []);
        if (!is_array($files) || $files === []) {
            return;
        }

        $this->attachmentService->storeManyForAttachable(
            $user,
            $files,
            $request->user(),
            [
                'school_id' => $schoolId,
                'module' => 'staff_documents',
                'action_type' => 'identity_document',
                'metadata' => [
                    'user_id' => (int) $user->id,
                    'department_id' => (int) ($user->department_id ?? 0),
                    'department_role_id' => (int) ($user->department_role_id ?? 0),
                    'school_staff_type' => (string) ($user->school_staff_type ?? ''),
                ],
                'request' => $request,
            ]
        );
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function resolveStructurePermissionPayload(array $validated, bool $forUpdate): array
    {
        if (!(bool) config('features.rbac.manager_assigns_structure_permissions', true)) {
            return [];
        }

        $keys = $this->availableStructurePermissionColumns();
        if (count($keys) === 0) {
            return [];
        }

        $hasAnyPermissionField = collect($keys)->contains(fn (string $key): bool => array_key_exists($key, $validated));
        if (!$hasAnyPermissionField) {
            if ($forUpdate) {
                return [];
            }

            return collect($keys)
                ->mapWithKeys(fn (string $key): array => [$key => false])
                ->all();
        }

        $payload = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $validated)) {
                $payload[$key] = (bool) $validated[$key];
                continue;
            }

            if (!$forUpdate) {
                $payload[$key] = false;
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<int, string>
     */
    private function resolveDelegationPermissionNames(array $validated, ?User $user = null): array
    {
        if (array_key_exists('permission_names', $validated)) {
            return collect($validated['permission_names'] ?? [])
                ->map(fn ($permissionName): string => trim((string) $permissionName))
                ->filter()
                ->values()
                ->all();
        }

        $availableColumns = array_flip($this->availableStructurePermissionColumns());
        $permissionColumnMap = SchoolPermissionCatalog::permissionColumnMap();

        $hasLegacyPermissionInput = false;
        $resolvedPermissionNames = collect($permissionColumnMap)
            ->filter(function (string $column) use ($availableColumns): bool {
                return array_key_exists($column, $availableColumns);
            })
            ->map(function (string $column, string $permissionName) use ($validated, &$hasLegacyPermissionInput): ?string {
                if (!array_key_exists($column, $validated)) {
                    return null;
                }

                $hasLegacyPermissionInput = true;

                return (bool) $validated[$column] ? $permissionName : null;
            })
            ->filter()
            ->values()
            ->all();

        if ($hasLegacyPermissionInput) {
            return $resolvedPermissionNames;
        }

        return $user?->directSchoolPermissionNames() ?? [];
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
                'updated_at',
            ],
            $this->availableStructurePermissionColumns()
        );
    }

    /**
     * @return array<int, string>
     */
    private function availableStructurePermissionColumns(): array
    {
        $candidates = [
            'can_manage_student_structure',
            'can_manage_student_attendance',
            'can_manage_academic_planning',
            'can_manage_student_leaves',
            'can_manage_leave_types',
            'can_manage_school_calendar',
            'can_manage_school_holidays',
        ];

        return collect($candidates)
            ->filter(fn (string $column): bool => Schema::hasColumn('users', $column))
            ->values()
            ->all();
    }

    private function schoolUsersQuery(int $schoolId): Builder
    {
        return User::query()
            ->with([
                'roles:id,name',
                'permissions:id,name',
                'department:id,name',
                'departmentRole:id,department_id,name',
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
            ->orderByDesc('id');
    }

    private function departmentAllowsRoleAssignment(Department $department, int $departmentRoleId, int $schoolId, ?int $currentRoleId = null): bool
    {
        return $department->roles()
            ->whereKey($departmentRoleId)
            ->where('is_active', true)
            ->where(function ($roleQuery) use ($department, $schoolId, $currentRoleId, $departmentRoleId): void {
                if ((int) ($department->school_id ?? 0) === $schoolId) {
                    $roleQuery->where('department_roles.department_id', $department->id);

                    return;
                }

                $roleQuery
                    ->whereNull('org_structure_role_template_id')
                    ->orWhereHas('orgStructureRoleTemplate', fn ($templateQuery) => $templateQuery->where('is_active', true));

                if ($currentRoleId && $departmentRoleId === $currentRoleId) {
                    $roleQuery->orWhere('department_roles.id', $currentRoleId);
                }
            })
            ->exists();
    }

    /**
     * @param array<int, mixed> $groupIds
     * @return array<int, int>
     */
    private function normalizeSchoolPermissionGroupIds(array $groupIds, int $schoolId): array
    {
        $normalized = collect($groupIds)
            ->map(fn ($groupId): int => (int) $groupId)
            ->filter(fn (int $groupId): bool => $groupId > 0)
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            return [];
        }

        $existing = SchoolPermissionGroup::query()
            ->where('school_id', $schoolId)
            ->whereIn('id', $normalized->all())
            ->pluck('id')
            ->map(fn ($groupId): int => (int) $groupId)
            ->values();

        if ($existing->count() !== $normalized->count()) {
            throw ValidationException::withMessages([
                'school_permission_group_ids' => 'يمكن إسناد مجموعات صلاحيات تابعة لنفس المدرسة فقط.',
            ]);
        }

        return $existing->all();
    }
}
