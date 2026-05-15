<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\ListAssignableSchoolRolesRequest;
use App\Services\Auth\SchoolRoleAssignmentService;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class SchoolAssignableRoleController extends Controller
{
    public function __construct(
        private readonly SchoolRoleAssignmentService $roleAssignmentService
    ) {
    }

    public function index(ListAssignableSchoolRolesRequest $request): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $roles = $this->roleAssignmentService->assignableRolesForSchoolManager();

        return response()->json([
            'data' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
                'assignable_by_school_admin' => (bool) $role->assignable_by_school_admin,
                'is_system' => (bool) $role->is_system,
            ])->all(),
        ]);
    }

    private function ensureFeatureEnabled(): void
    {
        if (!config('features.rbac.school_role_assignment_enabled', true)) {
            abort(404);
        }
    }
}
