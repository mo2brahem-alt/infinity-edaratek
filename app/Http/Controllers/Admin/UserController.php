<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesUserIdentityUniqueness;
use App\Http\Controllers\Concerns\NormalizesSaudiPhoneInputs;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\School;
use App\Models\User;
use App\Rules\SaudiMobile;
use App\Services\Auth\UserApprovalService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use NormalizesSaudiPhoneInputs, HandlesUserIdentityUniqueness;

    public function __construct(private readonly UserApprovalService $userApprovalService)
    {
    }

    public function index(): Response
    {
        $users = User::query()
            ->with(['roles', 'department'])
            ->where(function ($query): void {
                $query->where('role', '!=', 'super_admin')
                    ->orWhereNull('role');
            })
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'super_admin'))
            ->orderByDesc('id')
            ->get();

        $roles = Role::query()
            ->where('name', '!=', 'super_admin')
            ->get();

        $departments = Department::query()
            ->whereNull('school_id')
            ->orderBy('name')
            ->get();

        $approvalScopedUsers = $this->approvalScopedUsers()->get();
        $pendingApprovals = $approvalScopedUsers
            ->where('approval_status', User::APPROVAL_PENDING)
            ->values();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'departments' => $departments,
            'pendingApprovals' => $pendingApprovals,
            'approvalStats' => $this->approvalStats($approvalScopedUsers),
            'schools' => $this->accountSchools(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeSaudiPhoneInputs($request, ['mobile']);

        $validated = $request->validate([
            'name' => 'required|string|max:255|min:3',
            'email' => 'required|string|email:filter|max:255|unique:users,email',
            'mobile' => ['required', 'string', 'max:20', new SaudiMobile, 'unique:users,mobile'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role_name' => 'required|exists:roles,name',
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where(fn ($query) => $query->whereNull('school_id')),
            ],
        ], $this->duplicateUserValidationMessages());

        $approvalState = $this->userApprovalService->initialStateForRole($validated['role_name']);
        if ($this->userApprovalService->roleRequiresApproval($validated['role_name'])) {
            $approvalState['is_active'] = true;
            $approvalState['approval_status'] = User::APPROVAL_APPROVED;
            $approvalState['approved_at'] = now();
            $approvalState['approved_by'] = (int) $request->user()->id;
        }

        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'mobile' => $validated['mobile'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role_name'],
                'department_id' => $validated['department_id'],
                ...$approvalState,
            ]);
        } catch (QueryException $exception) {
            $this->rethrowAsDuplicateUserValidation($exception);
            throw $exception;
        }

        $user->assignRole($validated['role_name']);

        return back()->with('success', 'تم إنشاء المستخدم بنجاح.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->normalizeSaudiPhoneInputs($request, ['mobile']);

        $user = User::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|min:3',
            'email' => ['required', 'string', 'email:filter', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'mobile' => ['required', 'string', 'max:20', new SaudiMobile, Rule::unique('users', 'mobile')->ignore($id)],
            'role_name' => 'required|exists:roles,name',
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where(fn ($query) => $query->whereNull('school_id')),
            ],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ], $this->duplicateUserValidationMessages());

        try {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'mobile' => $validated['mobile'],
                'role' => $validated['role_name'],
                'department_id' => $validated['department_id'],
            ]);

            if (! empty($validated['password'])) {
                $user->update([
                    'password' => Hash::make($validated['password']),
                ]);
            }
        } catch (QueryException $exception) {
            $this->rethrowAsDuplicateUserValidation($exception);
            throw $exception;
        }

        $user->syncRoles([$validated['role_name']]);

        return back()->with('success', 'تم تحديث بيانات المستخدم بنجاح.');
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->userApprovalService->approve($user, $request->user(), $validated['reason'] ?? null);

        return back()->with('success', 'تمت الموافقة على الحساب وتفعيله بنجاح.');
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->userApprovalService->reject($user, $request->user(), $validated['reason'] ?? null);

        return back()->with('success', 'تم رفض طلب الحساب بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = User::query()->findOrFail($id);
        $user->delete();

        return back()->with('success', 'تم حذف المستخدم بنجاح.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    private function approvalScopedUsers()
    {
        return User::query()
            ->with(['roles', 'department'])
            ->where(function ($query): void {
                $query->whereIn('role', User::APPROVAL_REQUIRED_ROLES)
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', User::APPROVAL_REQUIRED_ROLES));
            })
            ->orderByDesc('created_at');
    }

    /**
     * @param Collection<int, User> $users
     * @return array<string, int>
     */
    private function approvalStats(Collection $users): array
    {
        return [
            'pending' => $users->where('approval_status', User::APPROVAL_PENDING)->count(),
            'approved' => $users->where('approval_status', User::APPROVAL_APPROVED)->count(),
            'rejected' => $users->where('approval_status', User::APPROVAL_REJECTED)->count(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function accountSchools(): Collection
    {
        return School::query()
            ->with([
                'manager:id,name,email',
                'supervisor:id,name,email',
                'directorate:id,name,governorate,country_id,governorate_id,education_type_id',
                'directorate.country:id,name',
                'directorate.governorateModel:id,name',
                'directorate.educationType:id,name',
            ])
            ->withCount(['users', 'students'])
            ->latest('id')
            ->get([
                'id',
                'directorate_id',
                'manager_user_id',
                'supervisor_id',
                'name',
                'school_id',
                'school_type',
                'phone',
                'email',
                'status',
                'supervision_status',
                'created_at',
            ])
            ->map(fn (School $school): array => [
                'id' => (int) $school->id,
                'name' => $school->name,
                'school_id' => $school->school_id,
                'school_type' => $school->school_type,
                'phone' => $school->phone,
                'email' => $school->email,
                'status' => $school->status,
                'supervision_status' => $school->supervision_status,
                'created_at' => $school->created_at?->toISOString(),
                'users_count' => (int) ($school->users_count ?? 0),
                'students_count' => (int) ($school->students_count ?? 0),
                'manager' => $school->manager ? [
                    'id' => (int) $school->manager->id,
                    'name' => $school->manager->name,
                    'email' => $school->manager->email,
                ] : null,
                'supervisor' => $school->supervisor ? [
                    'id' => (int) $school->supervisor->id,
                    'name' => $school->supervisor->name,
                    'email' => $school->supervisor->email,
                ] : null,
                'directorate' => $school->directorate ? [
                    'id' => (int) $school->directorate->id,
                    'name' => $school->directorate->name,
                    'governorate' => $school->directorate->governorateModel?->name
                        ?: $school->directorate->governorate,
                    'country' => $school->directorate->country?->name,
                    'education_type' => $school->directorate->educationType?->name,
                ] : null,
            ]);
    }
}
