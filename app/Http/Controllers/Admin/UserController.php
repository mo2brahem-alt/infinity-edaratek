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
                'manager:id,name,email,mobile,role,approval_status,is_active',
                'supervisor:id,name,email,mobile,role,approval_status,is_active',
                'directorate:id,name,governorate,country_id,governorate_id,education_type_id',
                'directorate.country:id,name',
                'directorate.governorateModel:id,name',
                'directorate.educationType:id,name',
                'defaultDataImporter:id,name,email',
                'stages' => fn ($query) => $query
                    ->select(['id', 'school_id', 'name', 'code', 'sort_order', 'is_active'])
                    ->with([
                        'grades:id,school_id,school_stage_id,name,sort_order,is_active',
                        'classrooms:id,school_id,school_stage_id,name,grade_name,code,sort_order,is_active',
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'academicYears' => fn ($query) => $query
                    ->select(['id', 'school_id', 'name', 'starts_on', 'ends_on', 'is_active'])
                    ->orderByDesc('starts_on')
                    ->orderByDesc('id'),
                'terms' => fn ($query) => $query
                    ->select(['id', 'school_id', 'name', 'start_date', 'end_date', 'is_active'])
                    ->orderByDesc('start_date')
                    ->orderByDesc('id'),
            ])
            ->withCount([
                'users',
                'students',
                'stages',
                'classrooms',
                'subjects',
                'academicYears',
                'terms',
                'exams',
                'subscriptions',
            ])
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
                'address',
                'notes',
                'logo_path',
                'status',
                'supervision_status',
                'default_data_imported_at',
                'default_data_imported_by',
                'default_template_key',
                'default_template_name',
                'created_at',
                'updated_at',
            ])
            ->map(fn (School $school): array => [
                'id' => (int) $school->id,
                'name' => $school->name,
                'school_id' => $school->school_id,
                'school_type' => $school->school_type,
                'phone' => $school->phone,
                'email' => $school->email,
                'address' => $school->address,
                'notes' => $school->notes,
                'logo_path' => $school->logo_path,
                'status' => $school->status,
                'supervision_status' => $school->supervision_status,
                'default_data_imported_at' => $school->default_data_imported_at?->toISOString(),
                'default_template_key' => $school->default_template_key,
                'default_template_name' => $school->default_template_name,
                'created_at' => $school->created_at?->toISOString(),
                'updated_at' => $school->updated_at?->toISOString(),
                'users_count' => (int) ($school->users_count ?? 0),
                'students_count' => (int) ($school->students_count ?? 0),
                'stages_count' => (int) ($school->stages_count ?? 0),
                'classrooms_count' => (int) ($school->classrooms_count ?? 0),
                'subjects_count' => (int) ($school->subjects_count ?? 0),
                'academic_years_count' => (int) ($school->academic_years_count ?? 0),
                'terms_count' => (int) ($school->terms_count ?? 0),
                'exams_count' => (int) ($school->exams_count ?? 0),
                'subscriptions_count' => (int) ($school->subscriptions_count ?? 0),
                'manager' => $school->manager ? [
                    'id' => (int) $school->manager->id,
                    'name' => $school->manager->name,
                    'email' => $school->manager->email,
                    'mobile' => $school->manager->mobile,
                    'role' => $school->manager->role,
                    'approval_status' => $school->manager->approval_status,
                    'is_active' => (bool) $school->manager->is_active,
                ] : null,
                'supervisor' => $school->supervisor ? [
                    'id' => (int) $school->supervisor->id,
                    'name' => $school->supervisor->name,
                    'email' => $school->supervisor->email,
                    'mobile' => $school->supervisor->mobile,
                    'role' => $school->supervisor->role,
                    'approval_status' => $school->supervisor->approval_status,
                    'is_active' => (bool) $school->supervisor->is_active,
                ] : null,
                'default_data_importer' => $school->defaultDataImporter ? [
                    'id' => (int) $school->defaultDataImporter->id,
                    'name' => $school->defaultDataImporter->name,
                    'email' => $school->defaultDataImporter->email,
                ] : null,
                'directorate' => $school->directorate ? [
                    'id' => (int) $school->directorate->id,
                    'name' => $school->directorate->name,
                    'governorate' => $school->directorate->governorateModel?->name
                        ?: $school->directorate->governorate,
                    'country' => $school->directorate->country?->name,
                    'education_type' => $school->directorate->educationType?->name,
                ] : null,
                'structure' => [
                    'stages' => $school->stages->map(fn ($stage): array => [
                        'id' => (int) $stage->id,
                        'name' => $stage->name,
                        'code' => $stage->code,
                        'is_active' => (bool) $stage->is_active,
                        'grades' => $stage->grades
                            ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
                            ->map(fn ($grade): array => [
                                'id' => (int) $grade->id,
                                'name' => $grade->name,
                                'is_active' => (bool) $grade->is_active,
                            ])
                            ->values(),
                        'classrooms' => $stage->classrooms
                            ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
                            ->map(fn ($classroom): array => [
                                'id' => (int) $classroom->id,
                                'name' => $classroom->name,
                                'grade_name' => $classroom->grade_name,
                                'code' => $classroom->code,
                                'is_active' => (bool) $classroom->is_active,
                            ])
                            ->values(),
                    ])->values(),
                    'academic_years' => $school->academicYears->map(fn ($year): array => [
                        'id' => (int) $year->id,
                        'name' => $year->name,
                        'starts_on' => $year->starts_on?->format('Y-m-d'),
                        'ends_on' => $year->ends_on?->format('Y-m-d'),
                        'is_active' => (bool) $year->is_active,
                    ])->values(),
                    'terms' => $school->terms->map(fn ($term): array => [
                        'id' => (int) $term->id,
                        'name' => $term->name,
                        'start_date' => $term->start_date?->format('Y-m-d'),
                        'end_date' => $term->end_date?->format('Y-m-d'),
                        'is_active' => (bool) $term->is_active,
                    ])->values(),
                ],
            ]);
    }
}
