<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreSchoolPermissionGroupRequest;
use App\Http\Requests\School\UpdateSchoolPermissionGroupRequest;
use App\Models\SchoolPermissionGroup;
use App\Services\School\SchoolDelegationService;
use App\Services\Support\AuditLogger;
use App\Support\SchoolPermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolPermissionGroupController extends Controller
{
    public function __construct(
        private readonly SchoolDelegationService $schoolDelegationService,
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureFeatureEnabled();
        $schoolId = $this->resolveManagerSchoolId($request);

        $groups = SchoolPermissionGroup::query()
            ->where('school_id', $schoolId)
            ->withCount('users')
            ->orderBy('group_type')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $groups->map(fn (SchoolPermissionGroup $group) => $this->serializeGroup($group))->values()->all(),
        ]);
    }

    public function store(StoreSchoolPermissionGroupRequest $request): JsonResponse
    {
        $this->ensureFeatureEnabled();
        $schoolId = $this->resolveManagerSchoolId($request);
        $validated = $request->validated();

        $group = DB::transaction(function () use ($request, $schoolId, $validated): SchoolPermissionGroup {
            $group = SchoolPermissionGroup::query()->create([
                'school_id' => $schoolId,
                'name' => trim((string) $validated['name']),
                'group_type' => (string) $validated['group_type'],
                'permission_names' => $this->schoolDelegationService->normalizeManagerPermissionNames(
                    $validated['permission_names'],
                    (string) $validated['group_type'],
                    false
                ),
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);

            return $group->loadCount('users');
        });

        $this->auditLogger->log(
            action: 'school_permission_group.created',
            entityType: 'school_permission_group',
            entityId: (int) $group->id,
            payload: [
                'school_id' => $schoolId,
                'name' => $group->name,
                'group_type' => $group->group_type,
                'permission_names' => $group->permission_names,
            ],
            request: $request,
            userId: $request->user()?->id
        );

        return response()->json([
            'data' => $this->serializeGroup($group),
        ], 201);
    }

    public function update(UpdateSchoolPermissionGroupRequest $request, SchoolPermissionGroup $schoolPermissionGroup): JsonResponse
    {
        $this->ensureFeatureEnabled();
        $schoolId = $this->resolveManagerSchoolId($request);
        $this->ensureManagedGroup($schoolPermissionGroup, $schoolId);
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated, $schoolPermissionGroup): void {
            $schoolPermissionGroup->update([
                'name' => trim((string) $validated['name']),
                'group_type' => (string) $validated['group_type'],
                'permission_names' => $this->schoolDelegationService->normalizeManagerPermissionNames(
                    $validated['permission_names'],
                    (string) $validated['group_type'],
                    false
                ),
                'updated_by' => $request->user()?->id,
            ]);
        });

        $schoolPermissionGroup->loadCount('users');

        $this->auditLogger->log(
            action: 'school_permission_group.updated',
            entityType: 'school_permission_group',
            entityId: (int) $schoolPermissionGroup->id,
            payload: [
                'school_id' => $schoolId,
                'name' => $schoolPermissionGroup->name,
                'group_type' => $schoolPermissionGroup->group_type,
                'permission_names' => $schoolPermissionGroup->permission_names,
            ],
            request: $request,
            userId: $request->user()?->id
        );

        return response()->json([
            'data' => $this->serializeGroup($schoolPermissionGroup),
        ]);
    }

    public function destroy(Request $request, SchoolPermissionGroup $schoolPermissionGroup): JsonResponse
    {
        $this->ensureFeatureEnabled();
        $schoolId = $this->resolveManagerSchoolId($request);
        $this->ensureManagedGroup($schoolPermissionGroup, $schoolId);

        $snapshot = $this->serializeGroup($schoolPermissionGroup->loadCount('users'));
        $schoolPermissionGroup->delete();

        $this->auditLogger->log(
            action: 'school_permission_group.deleted',
            entityType: 'school_permission_group',
            entityId: (int) $snapshot['id'],
            payload: [
                'school_id' => $schoolId,
                'before' => $snapshot,
            ],
            request: $request,
            userId: $request->user()?->id
        );

        return response()->json([
            'message' => 'تم حذف مجموعة الصلاحيات بنجاح.',
        ]);
    }

    private function resolveManagerSchoolId(Request $request): int
    {
        $schoolId = (int) ($request->user()?->school_id ?? 0);

        if ($schoolId <= 0) {
            abort(403, 'Manager account must be linked to a school.');
        }

        return $schoolId;
    }

    private function ensureFeatureEnabled(): void
    {
        if (!config('features.rbac.school_role_assignment_enabled', true)) {
            abort(404);
        }
    }

    private function ensureManagedGroup(SchoolPermissionGroup $group, int $schoolId): void
    {
        if ((int) $group->school_id !== $schoolId) {
            abort(403, 'You are not allowed to manage this permission group.');
        }
    }

    private function serializeGroup(SchoolPermissionGroup $group): array
    {
        $metadata = SchoolPermissionCatalog::permissionMetadata();
        $permissionNames = collect($group->permission_names ?? [])
            ->map(fn ($permissionName) => trim((string) $permissionName))
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
            'users_count' => (int) ($group->users_count ?? $group->users()->count()),
            'created_at' => optional($group->created_at)->toISOString(),
            'updated_at' => optional($group->updated_at)->toISOString(),
        ];
    }
}
