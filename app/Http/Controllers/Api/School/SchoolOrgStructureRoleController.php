<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\OrgStructureRoleTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SchoolOrgStructureRoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureFeatureEnabled();
        $schoolId = $this->resolveManagerSchoolId($request);

        $validated = $request->validate([
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(fn ($query) => $query->where(function ($scopeQuery) use ($schoolId): void {
                    $scopeQuery->whereNull('school_id')
                        ->orWhere('school_id', $schoolId);
                })),
            ],
        ]);

        $departmentId = (int) ($validated['department_id'] ?? 0);

        $query = OrgStructureRoleTemplate::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($departmentId > 0) {
            $departmentExists = Department::query()
                ->whereKey($departmentId)
                ->where(function ($scopeQuery) use ($schoolId): void {
                    $scopeQuery->whereNull('school_id')
                        ->orWhere('school_id', $schoolId);
                })
                ->exists();

            if (!$departmentExists) {
                abort(404);
            }

            $query->whereHas('departmentRoles', function ($departmentRolesQuery) use ($departmentId): void {
                $departmentRolesQuery
                    ->where('department_id', $departmentId)
                    ->where('is_active', true);
            });
        }

        $templates = $query->get(['id', 'name', 'code', 'is_active', 'created_at', 'updated_at']);

        return response()->json([
            'data' => $templates->map(fn (OrgStructureRoleTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'code' => $template->code,
                'is_active' => (bool) $template->is_active,
                'created_at' => optional($template->created_at)->toISOString(),
                'updated_at' => optional($template->updated_at)->toISOString(),
            ])->values()->all(),
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
}
