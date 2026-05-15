<?php

namespace App\Services\School;

use App\Models\AuditLog;
use App\Models\SchoolPermissionGroup;
use App\Models\User;
use App\Services\Support\AuditLogger;
use App\Support\SchoolPermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class SchoolDelegationService
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function managerCatalogGroups(): array
    {
        return SchoolPermissionCatalog::catalogGroups(true);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function managerGroupTypeOptions(): array
    {
        return SchoolPermissionCatalog::groupTypeOptions(true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function delegationTemplates(): array
    {
        return collect(SchoolPermissionCatalog::delegationTemplates())
            ->map(function (array $template): array {
                $permissions = SchoolPermissionCatalog::normalizeManagerAssignablePermissionNames(
                    $template['permission_names'] ?? [],
                    allowEmpty: true
                );

                $metadata = SchoolPermissionCatalog::permissionMetadata(true);
                $modulesCount = collect($permissions)
                    ->map(fn (string $permissionName) => (string) ($metadata[$permissionName]['module'] ?? ''))
                    ->filter()
                    ->unique()
                    ->count();

                return [
                    ...$template,
                    'permission_names' => $permissions,
                    'permission_count' => count($permissions),
                    'module_count' => $modulesCount,
                ];
            })
            ->values()
            ->all();
    }

    public function ensurePermissionBaseline(): void
    {
        foreach (SchoolPermissionCatalog::permissionNames(false) as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }
    }

    /**
     * @param array<int, mixed> $permissionNames
     * @return array<int, string>
     */
    public function normalizeManagerPermissionNames(array $permissionNames, ?string $groupType = null, bool $allowEmpty = true): array
    {
        return SchoolPermissionCatalog::normalizeManagerAssignablePermissionNames($permissionNames, $groupType, $allowEmpty);
    }

    /**
     * @param array<int, mixed> $groupIds
     * @return array<int, int>
     */
    public function normalizeSchoolPermissionGroupIds(array $groupIds, int $schoolId): array
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
                'school_permission_group_ids' => 'لا يمكنك إسناد مجموعة صلاحيات خارج نطاق مدرستك.',
            ]);
        }

        return $existing->all();
    }

    /**
     * @param array<int, mixed> $permissionNames
     * @param array<int, mixed> $groupIds
     * @return array{
     *     before: array<string, mixed>,
     *     after: array<string, mixed>,
     *     changed: array<string, mixed>
     * }
     */
    public function syncUserDelegation(
        User $user,
        int $schoolId,
        array $permissionNames,
        array $groupIds,
        ?Request $request = null,
        ?User $actor = null
    ): array {
        $this->ensurePermissionBaseline();

        $normalizedPermissionNames = $this->normalizeManagerPermissionNames($permissionNames, allowEmpty: true);
        $normalizedGroupIds = $this->normalizeSchoolPermissionGroupIds($groupIds, $schoolId);

        $before = $this->delegationSnapshot($user->loadMissing(['permissions:id,name', 'schoolPermissionGroups:id,school_id,name,group_type,permission_names']));

        $catalogPermissionNames = SchoolPermissionCatalog::permissionNames();
        $nonCatalogDirectPermissions = $user->getDirectPermissions()
            ->pluck('name')
            ->map(fn ($permissionName): string => trim((string) $permissionName))
            ->filter()
            ->reject(fn (string $permissionName): bool => in_array($permissionName, $catalogPermissionNames, true))
            ->values()
            ->all();

        $user->syncPermissions(array_values(array_unique([
            ...$nonCatalogDirectPermissions,
            ...$normalizedPermissionNames,
        ])));

        $user->schoolPermissionGroups()->sync($normalizedGroupIds);

        $legacyPayload = $this->legacyPermissionColumnPayload($normalizedPermissionNames);
        if ($legacyPayload !== []) {
            $user->forceFill($legacyPayload)->save();
        }

        $user->unsetRelation('permissions');
        $user->unsetRelation('schoolPermissionGroups');
        $user->load(['permissions:id,name', 'schoolPermissionGroups:id,school_id,name,group_type,permission_names']);

        $after = $this->delegationSnapshot($user);
        $changed = $this->delegationDiff($before, $after);

        if ($request && $actor && $this->hasDelegationChanges($changed)) {
            $this->auditLogger->log(
                action: 'school_user.delegation_synced',
                entityType: 'user',
                entityId: (int) $user->id,
                payload: [
                    'school_id' => $schoolId,
                    'before' => $before,
                    'after' => $after,
                    'changed' => $changed,
                ],
                request: $request,
                userId: (int) $actor->id
            );
        }

        return [
            'before' => $before,
            'after' => $after,
            'changed' => $changed,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function delegationAuditFeedForSchool(int $schoolId, int $limit = 10): array
    {
        $actions = [
            'school_user.created',
            'school_user.updated',
            'school_user.roles_synced',
            'school_user.delegation_synced',
            'school_permission_group.created',
            'school_permission_group.updated',
            'school_permission_group.deleted',
        ];

        return AuditLog::query()
            ->with('user:id,name')
            ->whereIn('action', $actions)
            ->latest('id')
            ->limit(max(10, $limit * 4))
            ->get()
            ->filter(fn (AuditLog $entry): bool => (int) data_get($entry->payload, 'school_id', 0) === $schoolId)
            ->take($limit)
            ->map(function (AuditLog $entry): array {
                $payload = (array) ($entry->payload ?? []);
                $changed = (array) ($payload['changed'] ?? []);

                return [
                    'id' => (int) $entry->id,
                    'action' => (string) $entry->action,
                    'title' => $this->auditTitle($entry->action),
                    'description' => $this->auditDescription($entry->action, $payload),
                    'actor_name' => (string) ($entry->user?->name ?? 'مستخدم النظام'),
                    'changed_count' => (int) (
                        count((array) ($changed['added_direct_permissions'] ?? []))
                        + count((array) ($changed['removed_direct_permissions'] ?? []))
                        + count((array) ($changed['added_group_ids'] ?? []))
                        + count((array) ($changed['removed_group_ids'] ?? []))
                    ),
                    'created_at' => optional($entry->created_at)->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function delegationSnapshot(User $user): array
    {
        return [
            'direct_permission_names' => $user->directSchoolPermissionNames(),
            'effective_permission_names' => $user->effectiveSchoolPermissionNames(),
            'school_permission_group_ids' => $user->schoolPermissionGroups
                ->pluck('id')
                ->map(fn ($groupId): int => (int) $groupId)
                ->values()
                ->all(),
            'legacy_permissions' => collect($this->availableLegacyColumns())
                ->mapWithKeys(fn (string $column): array => [$column => (bool) ($user->{$column} ?? false)])
                ->all(),
        ];
    }

    /**
     * @param array<int, string> $directPermissionNames
     * @return array<string, bool>
     */
    private function legacyPermissionColumnPayload(array $directPermissionNames): array
    {
        $selected = collect($directPermissionNames)
            ->map(fn ($permissionName): string => trim((string) $permissionName))
            ->filter()
            ->values();

        return collect(SchoolPermissionCatalog::permissionColumnMap())
            ->filter(fn (string $column): bool => in_array($column, $this->availableLegacyColumns(), true))
            ->mapWithKeys(fn (string $column, string $permissionName): array => [
                $column => $selected->contains($permissionName),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function availableLegacyColumns(): array
    {
        return collect(array_values(SchoolPermissionCatalog::permissionColumnMap()))
            ->filter(fn (string $column): bool => Schema::hasColumn('users', $column))
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array<string, mixed>
     */
    private function delegationDiff(array $before, array $after): array
    {
        return [
            'added_direct_permissions' => array_values(array_diff(
                (array) ($after['direct_permission_names'] ?? []),
                (array) ($before['direct_permission_names'] ?? [])
            )),
            'removed_direct_permissions' => array_values(array_diff(
                (array) ($before['direct_permission_names'] ?? []),
                (array) ($after['direct_permission_names'] ?? [])
            )),
            'added_group_ids' => array_values(array_diff(
                (array) ($after['school_permission_group_ids'] ?? []),
                (array) ($before['school_permission_group_ids'] ?? [])
            )),
            'removed_group_ids' => array_values(array_diff(
                (array) ($before['school_permission_group_ids'] ?? []),
                (array) ($after['school_permission_group_ids'] ?? [])
            )),
            'legacy_permissions' => $after['legacy_permissions'] ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $changed
     */
    private function hasDelegationChanges(array $changed): bool
    {
        return count((array) ($changed['added_direct_permissions'] ?? [])) > 0
            || count((array) ($changed['removed_direct_permissions'] ?? [])) > 0
            || count((array) ($changed['added_group_ids'] ?? [])) > 0
            || count((array) ($changed['removed_group_ids'] ?? [])) > 0;
    }

    private function auditTitle(string $action): string
    {
        return match ($action) {
            'school_user.created' => 'إنشاء مستخدم مدرسة',
            'school_user.updated' => 'تحديث بيانات مستخدم',
            'school_user.roles_synced' => 'تحديث الأدوار العامة',
            'school_user.delegation_synced' => 'تحديث التفويضات المدرسية',
            'school_permission_group.created' => 'إنشاء مجموعة صلاحيات',
            'school_permission_group.updated' => 'تحديث مجموعة صلاحيات',
            'school_permission_group.deleted' => 'حذف مجموعة صلاحيات',
            default => 'تحديث تفويضات المدرسة',
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function auditDescription(string $action, array $payload): string
    {
        $before = (array) ($payload['before'] ?? []);
        $after = (array) ($payload['after'] ?? []);
        $targetName = (string) data_get($after, 'name', data_get($before, 'name', 'المستخدم'));

        return match ($action) {
            'school_user.delegation_synced' => 'تمت مزامنة صلاحيات وتفويضات المستخدم ' . $targetName . '.',
            'school_permission_group.created' => 'تم إنشاء مجموعة الصلاحيات ' . (string) ($payload['name'] ?? 'الجديدة') . '.',
            'school_permission_group.updated' => 'تم تحديث مجموعة الصلاحيات ' . (string) ($payload['name'] ?? 'الحالية') . '.',
            'school_permission_group.deleted' => 'تم حذف مجموعة صلاحيات مدرسية وإلغاء إسناداتها.',
            'school_user.roles_synced' => 'تم تحديث الأدوار العامة لمستخدم داخل المدرسة.',
            'school_user.updated' => 'تم تحديث بيانات مستخدم داخل المدرسة.',
            'school_user.created' => 'تم إنشاء مستخدم جديد داخل المدرسة.',
            default => 'تم إجراء تحديث على تفويضات المدرسة.',
        };
    }
}
