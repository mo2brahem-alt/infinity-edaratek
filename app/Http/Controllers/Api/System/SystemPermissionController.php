<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\StoreSystemPermissionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SystemPermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $permissions = Permission::query()
            ->orderBy('guard_name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $permissions->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
                'created_at' => optional($permission->created_at)->toISOString(),
                'updated_at' => optional($permission->updated_at)->toISOString(),
            ])->all(),
        ]);
    }

    public function store(StoreSystemPermissionRequest $request): JsonResponse
    {
        $this->ensureFeatureEnabled();
        $validated = $request->validated();

        $permission = Permission::query()->create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'web',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'data' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
                'created_at' => optional($permission->created_at)->toISOString(),
                'updated_at' => optional($permission->updated_at)->toISOString(),
            ],
        ], 201);
    }

    private function ensureFeatureEnabled(): void
    {
        if (!config('features.rbac.system_role_api_enabled', true)) {
            abort(404);
        }
    }
}

