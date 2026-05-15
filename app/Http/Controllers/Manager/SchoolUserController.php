<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesUserIdentityUniqueness;
use App\Http\Controllers\Concerns\NormalizesSaudiPhoneInputs;
use App\Models\Department;
use App\Models\User;
use App\Rules\SaudiMobile;
use App\Services\Subscription\SubscriptionPricingService;
use App\Services\Support\AttachmentService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class SchoolUserController extends Controller
{
    use NormalizesSaudiPhoneInputs, HandlesUserIdentityUniqueness;

    public function __construct(
        private readonly SubscriptionPricingService $subscriptionPricingService,
        private readonly AttachmentService $attachmentService,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeSaudiPhoneInputs($request, ['mobile']);

        $schoolId = $this->resolveManagerSchoolId($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255|min:3',
            'email' => 'required|string|email:filter|max:255|unique:users,email',
            'mobile' => ['required', 'string', 'max:20', new SaudiMobile, 'unique:users,mobile'],
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where(fn ($query) => $query
                    ->where(function ($scopeQuery) use ($schoolId): void {
                        $scopeQuery->whereNull('school_id')
                            ->orWhere('school_id', $schoolId);
                    })),
            ],
            'department_role_id' => [
                'required',
                Rule::exists('department_roles', 'id')->where(fn ($query) => $query
                    ->where('department_id', (int) $request->input('department_id'))
                    ->where('is_active', true)),
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'can_manage_student_structure' => ['nullable', 'boolean'],
            'can_manage_student_attendance' => ['nullable', 'boolean'],
            'can_manage_academic_planning' => ['nullable', 'boolean'],
            'can_manage_student_leaves' => ['nullable', 'boolean'],
            'can_manage_leave_types' => ['nullable', 'boolean'],
            'can_manage_school_calendar' => ['nullable', 'boolean'],
            'can_manage_school_holidays' => ['nullable', 'boolean'],
        ], $this->duplicateUserValidationMessages());
        $request->validate(
            $this->attachmentService->uploadValidationRules(),
            $this->attachmentService->uploadValidationMessages()
        );
        $seatAddon = null;

        try {
            $result = DB::transaction(function () use ($request, $validated, $schoolId): array {
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

                return [
                    'user' => $user,
                    'seat_addon' => $seatAddon,
                ];
            });

            $user = $result['user'];
            $seatAddon = $result['seat_addon'];
        } catch (QueryException $exception) {
            $this->rethrowAsDuplicateUserValidation($exception);
            throw $exception;
        }

        if (method_exists($user, 'assignRole')) {
            Role::findOrCreate('staff', 'web');
            $user->assignRole('staff');
        }

        $summary = $this->subscriptionPricingService->formatAddonSummary($seatAddon);

        return $summary ? back()->with('success', $summary) : back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->normalizeSaudiPhoneInputs($request, ['mobile']);

        $schoolId = $this->resolveManagerSchoolId($request);
        $this->ensureManagedSchoolUser($user, $schoolId);

        $validated = $request->validate([
            'name' => 'required|string|max:255|min:3',
            'email' => ['required', 'string', 'email:filter', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'mobile' => ['required', 'string', 'max:20', new SaudiMobile, Rule::unique('users', 'mobile')->ignore($user->id)],
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where(fn ($query) => $query
                    ->where(function ($scopeQuery) use ($schoolId): void {
                        $scopeQuery->whereNull('school_id')
                            ->orWhere('school_id', $schoolId);
                    })),
            ],
            'department_role_id' => [
                'required',
                Rule::exists('department_roles', 'id')->where(fn ($query) => $query
                    ->where('department_id', (int) $request->input('department_id'))
                    ->where('is_active', true)),
            ],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'can_manage_student_structure' => ['nullable', 'boolean'],
            'can_manage_student_attendance' => ['nullable', 'boolean'],
            'can_manage_academic_planning' => ['nullable', 'boolean'],
            'can_manage_student_leaves' => ['nullable', 'boolean'],
            'can_manage_leave_types' => ['nullable', 'boolean'],
            'can_manage_school_calendar' => ['nullable', 'boolean'],
            'can_manage_school_holidays' => ['nullable', 'boolean'],
        ], $this->duplicateUserValidationMessages());
        $request->validate(
            $this->attachmentService->uploadValidationRules(),
            $this->attachmentService->uploadValidationMessages()
        );

        try {
            DB::transaction(function () use ($validated, $schoolId, $user, $request): void {
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
            });
        } catch (QueryException $exception) {
            $this->rethrowAsDuplicateUserValidation($exception);
            throw $exception;
        }

        if (method_exists($user, 'syncRoles')) {
            Role::findOrCreate('staff', 'web');
            $user->syncRoles(['staff']);
        }

        if (!$user->hasLegacyRole('staff')) {
            $user->update(['role' => 'staff']);
        }

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $schoolId = $this->resolveManagerSchoolId($request);
        $this->ensureManagedSchoolUser($user, $schoolId);

        if ((int) $user->id === (int) $request->user()->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own manager account from this page.',
            ]);
        }

        DB::transaction(function () use ($user, $request): void {
            foreach ($user->attachments()->get() as $attachment) {
                $this->attachmentService->deleteInstitutionalAttachment(
                    $attachment,
                    $request,
                    (int) ($request->user()?->id ?? 0) ?: null
                );
            }

            $user->delete();
        });

        return back();
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

    private function resolveManagerSchoolId(Request $request): int
    {
        $schoolId = (int) ($request->user()->school_id ?? 0);

        if ($schoolId <= 0) {
            throw ValidationException::withMessages([
                'school' => 'Manager account must be linked to a school first.',
            ]);
        }

        return $schoolId;
    }

    private function ensureManagedSchoolUser(User $user, int $schoolId): void
    {
        if ((int) $user->school_id !== $schoolId) {
            abort(403, 'You are not allowed to manage this user.');
        }

        $hasStaffRole = $user->hasLegacyRole('staff')
            || (method_exists($user, 'hasRole') && $user->hasRole('staff'));

        if (!$hasStaffRole) {
            abort(403, 'Only school staff users can be managed from this page.');
        }
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
}
