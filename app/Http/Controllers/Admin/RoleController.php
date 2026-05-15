<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrgStructureRoleTemplate;
use App\Services\Support\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function index(): Response
    {
        $roles = Role::query()
            ->where('name', '!=', 'super_admin')
            ->orderByDesc('id')
            ->get();

        $orgStructureRoleTemplates = OrgStructureRoleTemplate::query()
            ->withCount('departmentRoles')
            ->orderByDesc('id')
            ->get([
                'id',
                'name',
                'code',
                'is_active',
                'created_at',
                'updated_at',
            ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'orgStructureRoleTemplates' => $orgStructureRoleTemplates,
        ]);
    }

    public function indexOrgStructureRoles(): JsonResponse
    {
        $templates = OrgStructureRoleTemplate::query()
            ->withCount('departmentRoles')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $templates->map(fn (OrgStructureRoleTemplate $template): array => $this->serializeOrgStructureTemplate($template))->values()->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name|max:255',
            'display_name' => 'nullable|string|max:255',
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);

        $this->auditLogger->log(
            action: 'user_role.created',
            entityType: 'role',
            entityId: (int) $role->id,
            payload: [
                'name' => $role->name,
                'guard_name' => $role->guard_name,
            ],
            request: $request,
            userId: $request->user()?->id
        );

        return back();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $id,
        ]);

        $before = $role->only(['name']);
        $role->update(['name' => $validated['name']]);

        $this->auditLogger->log(
            action: 'user_role.updated',
            entityType: 'role',
            entityId: (int) $role->id,
            payload: [
                'before' => $before,
                'after' => $role->only(['name']),
            ],
            request: $request,
            userId: $request->user()?->id
        );

        return back();
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $role = Role::findOrFail($id);
        $before = $role->only(['name', 'guard_name']);
        $role->delete();

        $this->auditLogger->log(
            action: 'user_role.deleted',
            entityType: 'role',
            entityId: (int) $id,
            payload: [
                'before' => $before,
            ],
            request: $request,
            userId: $request->user()?->id
        );

        return back();
    }

    public function storeOrgStructureRole(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('org_structure_role_templates', 'name')],
            'code' => ['nullable', 'string', 'max:100', Rule::unique('org_structure_role_templates', 'code')],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $template = DB::transaction(function () use ($validated, $request): OrgStructureRoleTemplate {
                $resolvedCode = $this->normalizeTemplateCodeInput($validated['code'] ?? null)
                    ?: $this->generateOrgStructureTemplateCode((string) $validated['name']);
                $this->assertTemplateCodeAvailable($resolvedCode, null);

                return OrgStructureRoleTemplate::query()->create([
                    'name' => trim((string) $validated['name']),
                    'code' => $resolvedCode,
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                    'created_by' => $request->user()?->id,
                    'updated_by' => $request->user()?->id,
                ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateTemplateCode($exception);
            throw $exception;
        }

        $this->auditLogger->log(
            action: 'org_structure_role_template.created',
            entityType: 'org_structure_role_template',
            entityId: (int) $template->id,
            payload: $template->only(['name', 'code', 'is_active']),
            request: $request,
            userId: $request->user()?->id
        );

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->serializeOrgStructureTemplate($template->fresh()),
            ], 201);
        }

        return back();
    }

    public function updateOrgStructureRole(Request $request, OrgStructureRoleTemplate $orgStructureRoleTemplate): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('org_structure_role_templates', 'name')->ignore($orgStructureRoleTemplate->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('org_structure_role_templates', 'code')->ignore($orgStructureRoleTemplate->id),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $before = $orgStructureRoleTemplate->only(['name', 'code', 'is_active']);

        try {
            DB::transaction(function () use ($validated, $orgStructureRoleTemplate, $request): void {
                $providedCode = $this->normalizeTemplateCodeInput($validated['code'] ?? null);
                $resolvedCode = $providedCode
                    ?: ($orgStructureRoleTemplate->code ?: $this->generateOrgStructureTemplateCode((string) $validated['name'], (int) $orgStructureRoleTemplate->id));

                $this->assertTemplateCodeAvailable($resolvedCode, (int) $orgStructureRoleTemplate->id);

                $orgStructureRoleTemplate->update([
                    'name' => trim((string) $validated['name']),
                    'code' => $resolvedCode,
                    'is_active' => array_key_exists('is_active', $validated)
                        ? (bool) $validated['is_active']
                        : (bool) $orgStructureRoleTemplate->is_active,
                    'updated_by' => $request->user()?->id,
                ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateTemplateCode($exception);
            throw $exception;
        }

        $this->auditLogger->log(
            action: 'org_structure_role_template.updated',
            entityType: 'org_structure_role_template',
            entityId: (int) $orgStructureRoleTemplate->id,
            payload: [
                'before' => $before,
                'after' => $orgStructureRoleTemplate->only(['name', 'code', 'is_active']),
            ],
            request: $request,
            userId: $request->user()?->id
        );

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->serializeOrgStructureTemplate($orgStructureRoleTemplate->fresh()),
            ]);
        }

        return back();
    }

    public function disableOrgStructureRole(Request $request, OrgStructureRoleTemplate $orgStructureRoleTemplate): RedirectResponse|JsonResponse
    {
        $usageCount = (int) $orgStructureRoleTemplate->departmentRoles()->count();

        $orgStructureRoleTemplate->update([
            'is_active' => false,
            'updated_by' => $request->user()?->id,
        ]);

        $this->auditLogger->log(
            action: 'org_structure_role_template.disabled',
            entityType: 'org_structure_role_template',
            entityId: (int) $orgStructureRoleTemplate->id,
            payload: [
                'name' => $orgStructureRoleTemplate->name,
                'usage_count' => $usageCount,
            ],
            request: $request,
            userId: $request->user()?->id
        );

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->serializeOrgStructureTemplate($orgStructureRoleTemplate->fresh()),
            ]);
        }

        return back();
    }

    private function serializeOrgStructureTemplate(OrgStructureRoleTemplate $template): array
    {
        return [
            'id' => (int) $template->id,
            'name' => $template->name,
            'code' => $template->code,
            'is_active' => (bool) $template->is_active,
            'department_roles_count' => isset($template->department_roles_count)
                ? (int) $template->department_roles_count
                : (int) $template->departmentRoles()->count(),
            'created_at' => optional($template->created_at)->toISOString(),
            'updated_at' => optional($template->updated_at)->toISOString(),
        ];
    }

    private function normalizeTemplateCodeInput(mixed $value): ?string
    {
        $normalized = strtoupper(trim((string) ($value ?? '')));
        $normalized = preg_replace('/[^A-Z0-9_]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');
        $normalized = preg_replace('/_+/', '_', $normalized) ?? '';

        if ($normalized === '') {
            return null;
        }

        return substr($normalized, 0, 100);
    }

    private function generateOrgStructureTemplateCode(string $name, ?int $ignoreId = null): string
    {
        $base = $this->normalizeTemplateCodeInput($name) ?? 'ORG_ROLE';
        $base = substr($base, 0, 90);

        DB::table('org_structure_role_templates')
            ->select('id')
            ->orderByDesc('id')
            ->limit(1)
            ->lockForUpdate()
            ->get();

        $candidate = $base;
        $suffix = 2;

        while ($this->templateCodeExists($candidate, $ignoreId)) {
            $suffixText = '_' . $suffix;
            $maxBaseLength = max(1, 100 - strlen($suffixText));
            $candidate = substr($base, 0, $maxBaseLength) . $suffixText;
            $suffix++;
        }

        return $candidate;
    }

    private function templateCodeExists(string $code, ?int $ignoreId = null): bool
    {
        $query = OrgStructureRoleTemplate::query()->where('code', $code);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    private function assertTemplateCodeAvailable(string $code, ?int $ignoreId = null): void
    {
        DB::table('org_structure_role_templates')
            ->select('id')
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->lockForUpdate()
            ->get();

        if ($this->templateCodeExists($code, $ignoreId)) {
            throw ValidationException::withMessages([
                'code' => 'Template code already exists.',
            ]);
        }
    }

    private function rethrowDuplicateTemplateCode(QueryException $exception): void
    {
        if (!$this->isUniqueConstraintException($exception)) {
            return;
        }

        throw ValidationException::withMessages([
            'code' => 'Template code already exists.',
        ]);
    }

    private function isUniqueConstraintException(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['1062', '19', '2067'], true);
    }
}
