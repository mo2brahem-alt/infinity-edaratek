<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\StoreSystemRoleRequest;
use App\Http\Requests\System\SyncSystemRolePermissionsRequest;
use App\Http\Requests\System\UpdateSystemRoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SystemRoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $roles = Role::query()
            ->with(['permissions' => fn ($query) => $query
                ->select(['permissions.id', 'name', 'guard_name'])
                ->orderBy('name')])
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $roles->map(fn (Role $role) => $this->serializeRole($role))->all(),
        ]);
    }

    public function store(StoreSystemRoleRequest $request): JsonResponse
    {
        $this->ensureFeatureEnabled();
        $validated = $request->validated();

        $role = DB::transaction(function () use ($validated): Role {
            $role = Role::query()->create([
                'name' => $validated['name'],
                'guard_name' => $validated['guard_name'] ?? 'web',
                'display_name' => $validated['display_name'] ?? null,
                'description' => $validated['description'] ?? null,
                'assignable_by_school_admin' => (bool) ($validated['assignable_by_school_admin'] ?? false),
                'is_system' => (bool) ($validated['is_system'] ?? false),
            ]);

            if (!empty($validated['permission_names']) && is_array($validated['permission_names'])) {
                $role->syncPermissions($validated['permission_names']);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role->fresh(['permissions']);
        });

        return response()->json([
            'data' => $this->serializeRole($role),
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $role->load(['permissions' => fn ($query) => $query->orderBy('name')]);

        return response()->json([
            'data' => $this->serializeRole($role),
        ]);
    }

    public function update(UpdateSystemRoleRequest $request, Role $role): JsonResponse
    {
        $this->ensureFeatureEnabled();
        $validated = $request->validated();
        $this->guardSuperAdminRole($role, $validated);

        $updatedRole = DB::transaction(function () use ($validated, $role): Role {
            $payload = [];

            foreach (['name', 'guard_name', 'display_name', 'description', 'assignable_by_school_admin', 'is_system'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $payload[$field] = in_array($field, ['assignable_by_school_admin', 'is_system'], true)
                        ? (bool) $validated[$field]
                        : $validated[$field];
                }
            }

            if (count($payload) > 0) {
                $role->update($payload);
            }

            if (array_key_exists('permission_names', $validated)) {
                $role->syncPermissions($validated['permission_names'] ?? []);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role->fresh(['permissions']);
        });

        return response()->json([
            'data' => $this->serializeRole($updatedRole),
        ]);
    }

    public function syncPermissions(SyncSystemRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $this->ensureFeatureEnabled();
        $validated = $request->validated();

        $updatedRole = DB::transaction(function () use ($validated, $role): Role {
            $role->syncPermissions($validated['permission_names'] ?? []);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role->fresh(['permissions']);
        });

        return response()->json([
            'data' => $this->serializeRole($updatedRole),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->ensureFeatureEnabled();

        if ((bool) ($role->is_system ?? false)) {
            throw ValidationException::withMessages([
                'role' => 'System roles cannot be deleted.',
            ]);
        }

        $hasAssignedModels = DB::table(config('permission.table_names.model_has_roles'))
            ->where('role_id', $role->id)
            ->exists();

        if ($hasAssignedModels) {
            throw ValidationException::withMessages([
                'role' => 'This role is already assigned to users and cannot be deleted.',
            ]);
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([], 204);
    }

    private function serializeRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'display_name' => $role->display_name,
            'description' => $role->description,
            'assignable_by_school_admin' => (bool) ($role->assignable_by_school_admin ?? false),
            'is_system' => (bool) ($role->is_system ?? false),
            'permission_names' => $role->permissions->pluck('name')->values()->all(),
            'created_at' => optional($role->created_at)->toISOString(),
            'updated_at' => optional($role->updated_at)->toISOString(),
        ];
    }

    private function guardSuperAdminRole(Role $role, array $validated): void
    {
        if ($role->name !== 'super_admin') {
            return;
        }

        if (array_key_exists('name', $validated) && $validated['name'] !== 'super_admin') {
            throw ValidationException::withMessages([
                'name' => 'super_admin role name cannot be changed.',
            ]);
        }

        if (array_key_exists('is_system', $validated) && !$validated['is_system']) {
            throw ValidationException::withMessages([
                'is_system' => 'super_admin role must remain a system role.',
            ]);
        }

        if (array_key_exists('assignable_by_school_admin', $validated) && $validated['assignable_by_school_admin']) {
            throw ValidationException::withMessages([
                'assignable_by_school_admin' => 'super_admin role cannot be assignable by school admins.',
            ]);
        }
    }

    private function ensureFeatureEnabled(): void
    {
        if (!config('features.rbac.system_role_api_enabled', true)) {
            abort(404);
        }
    }
}
