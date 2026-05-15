<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Concerns\NormalizesSaudiPhoneInputs;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\StoreManagerOnboardingSchoolRequest;
use App\Models\Country;
use App\Models\EducationStage;
use App\Models\EducationType;
use App\Models\EducationalDirectorate;
use App\Models\Governorate;
use App\Models\School;
use App\Models\SchoolSupervisionRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Rules\SaudiMobile;
use App\Services\School\SchoolDefaultDataProvisioningService;
use App\Services\Subscription\SubscriptionService;
use App\Services\System\GlobalLocationTaxonomySyncService;
use App\Services\Support\AuditLogger;
use App\Services\Support\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use App\Support\SchoolAssociationState;
use Throwable;

class OnboardingController extends Controller
{
    use NormalizesSaudiPhoneInputs;

    /**
     * @var array<int, string>|null
     */
    private ?array $userColumns = null;

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly AuditLogger $auditLogger,
        private readonly SchoolDefaultDataProvisioningService $schoolDefaultDataProvisioningService,
        private readonly SubscriptionService $subscriptionService,
    ) {
    }

    public function show(Request $request): Response
    {
        $manager = $request->user();
        $school = null;

        if ($manager->school_id) {
            $school = School::query()
                ->with([
                    'directorate:id,name,governorate,country_id,governorate_id,education_type_id',
                    'directorate.country:id,name',
                    'directorate.governorateModel:id,country_id,name',
                    'directorate.educationType:id,name',
                    'educationStages:id,name,sort_order,is_active',
                ])
                ->whereKey($manager->school_id)
                ->where('manager_user_id', $manager->id)
                ->first([
                    'id',
                    'name',
                    'school_id',
                    'directorate_id',
                    'phone',
                    'email',
                    'address',
                    'notes',
                    'school_type',
                    'logo_path',
                    'default_template_key',
                    'default_template_name',
                    'status',
                    'supervision_status',
                    'manager_user_id',
                ]);
        }

        return Inertia::render('Manager/Onboarding', [
            'currentRegionId' => $this->resolveCurrentRegionId($manager, $school),
            'currentSchool' => $school ? $this->schoolPayload($school) : null,
            'accountStatus' => $this->accountStatusPayload($manager, $school),
        ]);
    }

    public function regions(GlobalLocationTaxonomySyncService $taxonomySyncService): JsonResponse
    {
        $this->ensureCountriesAvailable($taxonomySyncService);

        $regions = EducationalDirectorate::query()
            ->with([
                'country:id,name',
                'governorateModel:id,country_id,name',
                'educationType:id,name',
            ])
            ->orderBy('governorate')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'governorate',
                'country_id',
                'governorate_id',
                'education_type_id',
            ]);

        return response()->json([
            'countries' => Country::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'governorates' => Governorate::query()
                ->orderBy('name')
                ->get(['id', 'country_id', 'name']),
            'educationTypes' => EducationType::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'educationStages' => EducationStage::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'sort_order', 'is_active']),
            'regions' => $regions->map(fn (EducationalDirectorate $region): array => [
                'id' => (int) $region->id,
                'name' => (string) $region->name,
                'governorate' => (string) $region->governorate,
                'country_id' => $region->country_id ? (int) $region->country_id : null,
                'governorate_id' => $region->governorate_id ? (int) $region->governorate_id : null,
                'education_type_id' => $region->education_type_id ? (int) $region->education_type_id : null,
                'country' => $region->country ? [
                    'id' => (int) $region->country->id,
                    'name' => (string) $region->country->name,
                ] : null,
                'governorate_model' => $region->governorateModel ? [
                    'id' => (int) $region->governorateModel->id,
                    'country_id' => (int) $region->governorateModel->country_id,
                    'name' => (string) $region->governorateModel->name,
                ] : null,
                'education_type' => $region->educationType ? [
                    'id' => (int) $region->educationType->id,
                    'name' => (string) $region->educationType->name,
                ] : null,
            ])->all(),
        ]);
    }

    public function templates(Request $request): JsonResponse
    {
        /* Legacy inline validation preserved only as context while the FormRequest now owns the rules. */
        $validated = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'education_type_id' => ['required', 'integer', 'exists:education_types,id'],
        ], [], [
            'country_id' => 'الدولة',
            'education_type_id' => 'نوع التعليم',
        ]);

        $templates = $this->schoolDefaultDataProvisioningService->templateOptions(
            (int) $validated['country_id'],
            (int) $validated['education_type_id']
        );

        return response()->json([
            'templates' => $templates,
            'message' => $templates === []
                ? 'لا توجد قوالب افتراضية مطابقة لهذه الدولة ونوع التعليم حاليًا.'
                : null,
        ]);
    }

    public function governorates(Request $request, GlobalLocationTaxonomySyncService $taxonomySyncService): JsonResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
        ], [], [
            'country_id' => 'الدولة',
        ]);

        $country = Country::query()->findOrFail((int) $validated['country_id']);

        try {
            if (! $country->governorates()->exists()) {
                $taxonomySyncService->syncGovernoratesForCountry($country);
            }
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'تعذر تحميل محافظات هذه الدولة من المصدر العالمي حاليًا.',
                'governorates' => [],
            ], 503);
        }

        return response()->json([
            'message' => $country->governorates()->exists()
                ? null
                : 'لا توجد محافظات متاحة لهذه الدولة حاليًا.',
            'governorates' => Governorate::query()
                ->where('country_id', $country->id)
                ->orderBy('name')
                ->get(['id', 'country_id', 'name']),
        ]);
    }

    public function schools(Request $request, EducationalDirectorate $region): JsonResponse
    {
        $manager = $request->user();

        $schools = School::query()
            ->where('directorate_id', $region->id)
            ->where(function ($query) use ($manager): void {
                $query->whereNull('manager_user_id')
                    ->orWhere('manager_user_id', $manager->id);
            })
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'school_id',
                'directorate_id',
                'status',
                'supervision_status',
                'manager_user_id',
            ]);

        return response()->json($schools);
    }

    public function select(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'region_id' => 'required|exists:educational_directorates,id',
            'school_id' => 'required|exists:schools,id',
        ], [], [
            'region_id' => 'النطاق التعليمي',
            'school_id' => 'المدرسة',
        ]);

        $selection = DB::transaction(function () use ($request, $validated): array {
            $manager = User::query()
                ->whereKey($request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            $school = School::query()
                ->where('id', $validated['school_id'])
                ->where('directorate_id', $validated['region_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($manager->school_id && (int) $manager->school_id !== (int) $school->id) {
                $ownsCurrentSchool = School::query()
                    ->whereKey($manager->school_id)
                    ->where('manager_user_id', $manager->id)
                    ->lockForUpdate()
                    ->exists();

                if ($ownsCurrentSchool) {
                    throw ValidationException::withMessages([
                        'school_id' => 'حساب مدير المدرسة مرتبط بمدرسة أخرى بالفعل.',
                    ]);
                }
            }

            if ($school->manager_user_id && (int) $school->manager_user_id !== (int) $manager->id) {
                throw ValidationException::withMessages([
                    'school_id' => 'المدرسة المحددة مرتبطة بمدير آخر.',
                ]);
            }

            $hasAnotherManager = User::query()
                ->where('id', '!=', $manager->id)
                ->where('school_id', $school->id)
                ->where(function ($query): void {
                    $query->where('role', 'school_manager')
                        ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'school_manager'));
                })
                ->lockForUpdate()
                ->exists();

            if ($hasAnotherManager) {
                throw ValidationException::withMessages([
                    'school_id' => 'المدرسة المحددة مرتبطة بمدير آخر.',
                ]);
            }

            $manager->update($this->buildManagerLinkUpdates($school->id, (int) $validated['region_id']));
            $this->subscriptionService->syncSchoolContextForUser($manager, (int) $school->id);
            $school->update(['manager_user_id' => $manager->id]);

            $pendingRequests = SchoolSupervisionRequest::query()
                ->where('school_id', $school->id)
                ->whereNull('manager_id')
                ->where('status', SchoolSupervisionRequest::STATUS_SUPERVISOR_REQUESTED)
                ->get();

            foreach ($pendingRequests as $pendingRequest) {
                $pendingRequest->update(['manager_id' => $manager->id]);
            }

            return [
                'school' => $school->refresh(),
                'manager_id' => (int) $manager->id,
                'region_id' => (int) $validated['region_id'],
                'pending_request_ids' => $pendingRequests->pluck('id')->all(),
            ];
        });

        $this->logManagerOnboardingCompletion(
            $selection['school'],
            $selection['manager_id'],
            $selection['region_id'],
            $request
        );

        $this->notifyPendingRequestsAfterSchoolSelection(
            $selection['pending_request_ids'],
            $selection['school']
        );

        return response()->json([
            'school' => $selection['school'],
        ]);
    }

    public function storeSchool(StoreManagerOnboardingSchoolRequest $request): JsonResponse
    {
        $this->normalizeSaudiPhoneInputs($request, ['phone']);

        /* Legacy inline validation preserved only as context while the FormRequest now owns the rules.
        $validated = $request->validate([
            'region_id' => 'nullable|exists:educational_directorates,id',
            'country_id' => 'required_without:region_id|exists:countries,id',
            'education_type_id' => 'required_without:region_id|exists:education_types,id',
            'template_key' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'max:20', new SaudiMobile],
            'email' => 'nullable|email:rfc,dns|max:255',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:2000',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'country_id.required_without' => 'الدولة مطلوبة.',
            'education_type_id.required_without' => 'نوع التعليم مطلوب.',
        ], [
            'region_id' => 'النطاق التعليمي',
            'name' => 'اسم المدرسة',
            'phone' => 'الجوال',
            'email' => 'البريد الإلكتروني',
            'address' => 'العنوان',
            'notes' => 'الملاحظات',
            'logo' => 'شعار المدرسة',
        */
        $validated = $request->validated();

        $storedLogoPath = null;

        try {
            $creation = DB::transaction(function () use ($request, $validated, &$storedLogoPath): array {
                $manager = User::query()
                    ->whereKey($request->user()->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($manager->school_id) {
                    $ownsCurrentSchool = School::query()
                        ->whereKey($manager->school_id)
                        ->where('manager_user_id', $manager->id)
                        ->lockForUpdate()
                        ->exists();

                    if ($ownsCurrentSchool) {
                        throw ValidationException::withMessages([
                            'school' => 'حساب مدير المدرسة مرتبط بمدرسة أخرى بالفعل.',
                        ]);
                    }
                }

                $this->ensureUniqueSchoolIdentity($validated);

                $resolvedDirectorate = $this->resolveOnboardingDirectorate(
                    isset($validated['region_id']) ? (int) $validated['region_id'] : null,
                    isset($validated['country_id']) ? (int) $validated['country_id'] : null,
                    isset($validated['governorate_id']) ? (int) $validated['governorate_id'] : null,
                    isset($validated['education_type_id']) ? (int) $validated['education_type_id'] : null,
                );
                $selectedTemplate = $this->resolveSelectedTemplateForCreation($validated, $resolvedDirectorate);

                if ($request->hasFile('logo')) {
                    $storedLogoPath = $request->file('logo')->store('schools/logos', 'public');
                }

                $school = School::query()->create([
                    'directorate_id' => (int) $resolvedDirectorate->id,
                    'name' => $validated['name'],
                    'school_type' => (string) $validated['school_type'],
                    'school_id' => $this->generateUniqueSchoolCode(),
                    'phone' => $validated['phone'],
                    'email' => $validated['email'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'logo_path' => $storedLogoPath,
                    'status' => School::STATUS_SUSPENDED,
                    'supervision_status' => School::SUPERVISION_STATUS_SUSPENDED,
                    'manager_user_id' => $manager->id,
                    'default_template_key' => $selectedTemplate['key'] ?? null,
                    'default_template_name' => $selectedTemplate['template_name'] ?? null,
                ]);

                $school->educationStages()->sync(array_map('intval', $validated['education_stage_ids'] ?? []));

                $manager->update($this->buildManagerLinkUpdates($school->id, (int) $resolvedDirectorate->id));
                $this->subscriptionService->syncSchoolContextForUser($manager, (int) $school->id);

                if ($selectedTemplate !== null) {
                    $this->schoolDefaultDataProvisioningService->importForSchool(
                        (int) $school->id,
                        (int) $manager->id,
                        $request,
                        [
                            'education_stage_ids' => array_map('intval', $validated['education_stage_ids'] ?? []),
                        ]
                    );
                }

                return [
                    'school' => $school->fresh()->load([
                        'directorate:id,name,governorate,country_id,governorate_id,education_type_id',
                        'directorate.country:id,name',
                        'directorate.governorateModel:id,country_id,name',
                        'directorate.educationType:id,name',
                    ]),
                    'manager_id' => (int) $manager->id,
                    'region_id' => (int) $resolvedDirectorate->id,
                    'used_template_selection' => $selectedTemplate !== null,
                ];
            });
        } catch (Throwable $throwable) {
            if ($storedLogoPath) {
                Storage::disk('public')->delete($storedLogoPath);
            }

            throw $throwable;
        }

        $this->logManagerSchoolCreation(
            $creation['school'],
            $creation['manager_id'],
            $creation['region_id'],
            $request
        );

        if (! ($creation['used_template_selection'] ?? false)) {
            $this->attemptSchoolDefaultDataImport($creation['school'], $creation['manager_id'], $request);
        }
        $this->notifyManagerSchoolCreation($creation['school']);

        return response()->json([
            'school' => $this->schoolPayload($creation['school']),
        ], 201);
    }

    public function updateSchool(Request $request, School $school): JsonResponse
    {
        $this->normalizeSaudiPhoneInputs($request, ['phone']);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'max:20', new SaudiMobile],
            'email' => 'nullable|email:rfc,dns|max:255',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:2000',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [], [
            'name' => 'اسم المدرسة',
            'phone' => 'الجوال',
            'email' => 'البريد الإلكتروني',
            'address' => 'العنوان',
            'notes' => 'الملاحظات',
            'logo' => 'شعار المدرسة',
        ]);

        $storedLogoPath = null;
        $previousLogoPath = null;

        try {
            $updatedSchool = DB::transaction(function () use ($request, $validated, $school, &$storedLogoPath, &$previousLogoPath): School {
                $manager = User::query()
                    ->whereKey($request->user()->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $managedSchool = School::query()
                    ->with([
                        'directorate:id,name,governorate,country_id,governorate_id,education_type_id',
                        'directorate.country:id,name',
                        'directorate.governorateModel:id,country_id,name',
                        'directorate.educationType:id,name',
                    ])
                    ->whereKey($school->id)
                    ->where('manager_user_id', $manager->id)
                    ->where('id', $manager->school_id)
                    ->lockForUpdate()
                    ->first();

                if (! $managedSchool) {
                    throw ValidationException::withMessages([
                        'school' => 'لا يمكنك تعديل مدرسة لا تخص حسابك الحالي.',
                    ]);
                }

                $this->ensureUniqueSchoolIdentity($validated, $managedSchool->id);

                if ($request->hasFile('logo')) {
                    $storedLogoPath = $request->file('logo')->store('schools/logos', 'public');
                    $previousLogoPath = $managedSchool->logo_path;
                    $managedSchool->logo_path = $storedLogoPath;
                }

                $managedSchool->fill([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);

                $managedSchool->save();

                return $managedSchool->fresh()->load([
                    'directorate:id,name,governorate,country_id,governorate_id,education_type_id',
                    'directorate.country:id,name',
                    'directorate.governorateModel:id,country_id,name',
                    'directorate.educationType:id,name',
                ]);
            });
        } catch (Throwable $throwable) {
            if ($storedLogoPath) {
                Storage::disk('public')->delete($storedLogoPath);
            }

            throw $throwable;
        }

        if ($previousLogoPath && $previousLogoPath !== $storedLogoPath) {
            Storage::disk('public')->delete($previousLogoPath);
        }

        return response()->json([
            'school' => $this->schoolPayload($updatedSchool),
        ]);
    }

    private function generateUniqueSchoolCode(): string
    {
        do {
            $schoolCode = 'SCH-' . random_int(100000, 999999);
        } while (School::query()->where('school_id', $schoolCode)->exists());

        return $schoolCode;
    }

    /**
     * @return array{key: string, variant: string, title: string, message: string, action_label: string|null}
     */
    private function accountStatusPayload(User $manager, ?School $school): array
    {
        if (! $manager->hasApprovedAccountAccess()) {
            return [
                'key' => 'pending_approval',
                'variant' => 'info',
                'title' => __('messages.account_status_pending_approval_title'),
                'message' => __('messages.account_status_pending_approval_message'),
                'action_label' => null,
            ];
        }

        $hasActiveSubscription = $manager->subscriptions()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->exists();

        if (! $hasActiveSubscription) {
            return [
                'key' => 'approved_but_subscription_inactive',
                'variant' => 'warning',
                'title' => __('messages.account_status_subscription_inactive_title'),
                'message' => __('messages.account_status_subscription_inactive_message'),
                'action_label' => __('messages.account_status_contact_support'),
            ];
        }

        if (! $school) {
            return [
                'key' => 'approved_but_school_missing',
                'variant' => 'warning',
                'title' => __('messages.account_status_school_missing_title'),
                'message' => __('messages.manager_school_required'),
                'action_label' => __('messages.account_status_complete_school'),
            ];
        }

        if (SchoolAssociationState::isActiveAssociation($school)) {
            return [
                'key' => 'active_and_ready',
                'variant' => 'success',
                'title' => __('messages.account_status_active_title'),
                'message' => __('messages.account_status_active_message'),
                'action_label' => __('messages.account_status_go_to_manager_dashboard'),
            ];
        }

        return [
            'key' => 'approved_but_association_pending',
            'variant' => 'info',
            'title' => __('messages.account_status_association_pending_title'),
            'message' => __('messages.account_status_association_pending_message'),
            'action_label' => null,
        ];
    }

    private function resolveCurrentRegionId(User $manager, ?School $school): ?int
    {
        $currentRegionId = $manager->getAttribute('onboarding_region_id');

        if ($currentRegionId) {
            return (int) $currentRegionId;
        }

        return $school ? (int) $school->directorate_id : null;
    }

    private function resolveOnboardingDirectorate(
        ?int $regionId,
        ?int $countryId,
        ?int $governorateId,
        ?int $educationTypeId
    ): EducationalDirectorate {
        if ($regionId !== null) {
            return EducationalDirectorate::query()->findOrFail($regionId);
        }

        if ($countryId === null || $governorateId === null || $educationTypeId === null) {
            throw ValidationException::withMessages([
                'governorate_id' => 'يجب تحديد الدولة والمحافظة ونوع التعليم قبل إنشاء المدرسة.',
            ]);
        }

        return $this->resolveOrCreateOnboardingDirectorate($countryId, $governorateId, $educationTypeId);
    }

    private function ensureCountriesAvailable(GlobalLocationTaxonomySyncService $taxonomySyncService): void
    {
        if (Country::query()->exists()) {
            return;
        }

        try {
            $taxonomySyncService->syncCountries();
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

    private function resolveOrCreateOnboardingDirectorate(int $countryId, int $governorateId, int $educationTypeId): EducationalDirectorate
    {
        $country = Country::query()->findOrFail($countryId, ['id', 'name']);
        $governorate = Governorate::query()
            ->whereKey($governorateId)
            ->where('country_id', $country->id)
            ->first();

        if (! $governorate instanceof Governorate) {
            throw ValidationException::withMessages([
                'governorate_id' => 'المحافظة المحددة لا تنتمي إلى الدولة المختارة.',
            ]);
        }

        $educationType = EducationType::query()->findOrFail($educationTypeId, ['id', 'name']);

        $existingDirectorate = EducationalDirectorate::query()
            ->where('country_id', $country->id)
            ->where('education_type_id', $educationType->id)
            ->where('governorate_id', $governorate->id)
            ->orderBy('id')
            ->first();

        if ($existingDirectorate instanceof EducationalDirectorate) {
            return $existingDirectorate;
        }

        return EducationalDirectorate::query()->create([
            'country_id' => (int) $country->id,
            'governorate_id' => (int) $governorate->id,
            'education_type_id' => (int) $educationType->id,
            'governorate' => (string) $governorate->name,
            'name' => $educationType->name . ' - ' . $governorate->name,
        ]);
    }

    /**
     * @return array{school_id: int, onboarding_region_id?: int, onboarding_completed_at?: \Illuminate\Support\Carbon}
     */
    private function buildManagerLinkUpdates(int $schoolId, int $regionId): array
    {
        $updates = [
            'school_id' => $schoolId,
        ];

        if ($this->userColumnExists('onboarding_region_id')) {
            $updates['onboarding_region_id'] = $regionId;
        }

        if ($this->userColumnExists('onboarding_completed_at')) {
            $updates['onboarding_completed_at'] = now();
        }

        return $updates;
    }

    private function userColumnExists(string $column): bool
    {
        if ($this->userColumns === null) {
            $this->userColumns = Schema::getColumnListing('users');
        }

        return in_array($column, $this->userColumns, true);
    }

    private function logManagerOnboardingCompletion(
        School $school,
        int $managerId,
        int $regionId,
        Request $request
    ): void {
        try {
            $this->auditLogger->log(
                'manager.onboarding_completed',
                'school',
                $school->id,
                ['manager_id' => $managerId, 'region_id' => $regionId],
                $request,
                $managerId
            );
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

    private function notifyPendingRequestsAfterSchoolSelection(array $pendingRequestIds, School $school): void
    {
        if ($pendingRequestIds === []) {
            return;
        }

        try {
            $pendingRequests = SchoolSupervisionRequest::query()
                ->whereIn('id', $pendingRequestIds)
                ->get(['id', 'supervisor_id']);

            foreach ($pendingRequests as $pendingRequest) {
                $this->notificationService->notifyUser(
                    (int) $pendingRequest->supervisor_id,
                    'SCHOOL_MANAGER_SELECTED_SCHOOL',
                    'تم ربط مدير المدرسة',
                    'أكمل مدير المدرسة التهيئة ويمكنه الآن مراجعة طلب الإشراف.',
                    $this->notificationService->withRoute(
                        [
                            'request_id' => (int) $pendingRequest->id,
                            'school_id' => (int) $school->id,
                        ],
                        'supervisor.requests.page',
                        [],
                        'supervisor.requests.page'
                    )
                );
            }
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

    private function logManagerSchoolCreation(
        School $school,
        int $managerId,
        int $regionId,
        Request $request
    ): void {
        try {
            $this->auditLogger->log(
                'manager.onboarding_school_created',
                'school',
                $school->id,
                [
                    'manager_id' => $managerId,
                    'region_id' => $regionId,
                ],
                $request,
                $managerId
            );
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

    private function notifyManagerSchoolCreation(School $school): void
    {
        try {
            $this->notificationService->notifySuperAdmins(
                'MANAGER_SCHOOL_CREATED',
                'إضافة مدرسة جديدة بواسطة مدير مدرسة',
                'تمت إضافة مدرسة جديدة وتحتاج إلى متابعة إدارية.',
                $this->notificationService->withRoute(
                    [
                        'school_id' => (int) $school->id,
                        'region_id' => (int) $school->directorate_id,
                    ],
                    'admin.schools.index',
                    [],
                    'admin.schools.index'
                )
            );
        } catch (Throwable $throwable) {
            report($throwable);
        }

        try {
            $responsibleSupervisorIds = $this->notificationService->responsibleSupervisorUserIdsForSchool((int) $school->id);

            if ($responsibleSupervisorIds === []) {
                return;
            }

            $this->notificationService->notifyUsers(
                $responsibleSupervisorIds,
                'MANAGER_SCHOOL_CREATED',
                'مدير مدرسة أضاف مدرسة جديدة',
                'تم إنشاء مدرسة ضمن نطاقك الإشرافي، راجعها لبدء إجراءات الإشراف.',
                $this->notificationService->withRoute(
                    [
                        'school_id' => (int) $school->id,
                        'region_id' => (int) $school->directorate_id,
                    ],
                    'supervisor.onboarding.show',
                    [],
                    'supervisor.onboarding.show'
                )
            );
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

    private function ensureUniqueSchoolIdentity(array $validated, ?int $ignoreSchoolId = null): void
    {
        $duplicateSchool = School::query()
            ->when($ignoreSchoolId, fn ($query) => $query->where('id', '!=', $ignoreSchoolId))
            ->where('name', $validated['name'])
            ->lockForUpdate()
            ->first();

        if ($duplicateSchool) {
            throw ValidationException::withMessages([
                'name' => 'يوجد بالفعل اسم مدرسة مطابق للاسم المدخل.',
            ]);
        }

        $duplicatePhone = School::query()
            ->when($ignoreSchoolId, fn ($query) => $query->where('id', '!=', $ignoreSchoolId))
            ->where('phone', $validated['phone'])
            ->lockForUpdate()
            ->first();

        if ($duplicatePhone) {
            throw ValidationException::withMessages([
                'phone' => 'رقم الجوال مستخدم لمدرسة أخرى بالفعل.',
            ]);
        }

        if (! empty($validated['email'])) {
            $duplicateEmail = School::query()
                ->when($ignoreSchoolId, fn ($query) => $query->where('id', '!=', $ignoreSchoolId))
                ->where('email', $validated['email'])
                ->lockForUpdate()
                ->first();

            if ($duplicateEmail) {
                throw ValidationException::withMessages([
                    'email' => 'البريد الإلكتروني مستخدم لمدرسة أخرى بالفعل.',
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function schoolPayload(School $school): array
    {
        $school->loadMissing([
            'directorate:id,name,governorate,country_id,governorate_id,education_type_id',
            'directorate.country:id,name',
            'directorate.governorateModel:id,country_id,name',
            'directorate.educationType:id,name',
            'educationStages:id,name,sort_order,is_active',
        ]);

        return [
            'id' => (int) $school->id,
            'name' => $school->name,
            'school_id' => $school->school_id,
            'directorate_id' => (int) $school->directorate_id,
            'school_type' => $school->school_type,
            'school_type_label' => $this->schoolTypeLabel($school->school_type),
            'phone' => $school->phone,
            'email' => $school->email,
            'address' => $school->address,
            'notes' => $school->notes,
            'logo_path' => $school->logo_path,
            'logo_url' => $school->logo_path ? '/media-files/' . ltrim($school->logo_path, '/') : null,
            'status' => $school->status,
            'supervision_status' => $school->supervision_status,
            'manager_user_id' => $school->manager_user_id ? (int) $school->manager_user_id : null,
            'default_template' => $school->default_template_key || $school->default_template_name ? [
                'key' => $school->default_template_key,
                'name' => $school->default_template_name,
            ] : null,
            'education_stages' => $school->educationStages->map(fn (EducationStage $stage): array => [
                'id' => (int) $stage->id,
                'name' => (string) $stage->name,
                'sort_order' => (int) $stage->sort_order,
                'is_active' => (bool) $stage->is_active,
            ])->all(),
            'directorate' => $school->directorate ? [
                'id' => (int) $school->directorate->id,
                'name' => $school->directorate->name,
                'governorate' => $school->directorate->governorate,
                'country_id' => $school->directorate->country_id ? (int) $school->directorate->country_id : null,
                'governorate_id' => $school->directorate->governorate_id ? (int) $school->directorate->governorate_id : null,
                'education_type_id' => $school->directorate->education_type_id ? (int) $school->directorate->education_type_id : null,
                'country' => $school->directorate->country ? [
                    'id' => (int) $school->directorate->country->id,
                    'name' => (string) $school->directorate->country->name,
                ] : null,
                'governorate_model' => $school->directorate->governorateModel ? [
                    'id' => (int) $school->directorate->governorateModel->id,
                    'country_id' => (int) $school->directorate->governorateModel->country_id,
                    'name' => (string) $school->directorate->governorateModel->name,
                ] : null,
                'education_type' => $school->directorate->educationType ? [
                    'id' => (int) $school->directorate->educationType->id,
                    'name' => (string) $school->directorate->educationType->name,
                ] : null,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>|null
     */
    private function resolveSelectedTemplateForCreation(array $validated, EducationalDirectorate $directorate): ?array
    {
        $countryId = isset($validated['country_id'])
            ? (int) $validated['country_id']
            : ($directorate->country_id ? (int) $directorate->country_id : null);
        $educationTypeId = isset($validated['education_type_id'])
            ? (int) $validated['education_type_id']
            : ($directorate->education_type_id ? (int) $directorate->education_type_id : null);
        $templateKey = trim((string) ($validated['template_key'] ?? ''));
        $usesActiveCreationFlow = empty($validated['region_id']);

        if ($countryId === null || $educationTypeId === null) {
            return null;
        }

        $templates = collect($this->schoolDefaultDataProvisioningService->templateOptions($countryId, $educationTypeId));

        if ($templates->isEmpty()) {
            if (! $usesActiveCreationFlow) {
                return null;
            }

            throw ValidationException::withMessages([
                'template_key' => 'لا توجد قوالب افتراضية مطابقة لهذه الدولة ونوع التعليم حاليًا.',
            ]);
        }

        if ($templateKey !== '') {
            $selectedTemplate = $templates->first(
                fn (array $template): bool => (string) ($template['key'] ?? '') === $templateKey
            );

            if (is_array($selectedTemplate)) {
                return $selectedTemplate;
            }

            throw ValidationException::withMessages([
                'template_key' => 'القالب المحدد لا يطابق الدولة ونوع التعليم المختارين.',
            ]);
        }

        if ($usesActiveCreationFlow && $templates->count() > 1) {
            throw ValidationException::withMessages([
                'template_key' => 'يجب اختيار قالب واحد قبل إنشاء المدرسة.',
            ]);
        }

        return $templates->first();
    }

    private function attemptSchoolDefaultDataImport(School $school, int $actorId, Request $request): void
    {
        try {
            $status = $this->schoolDefaultDataProvisioningService->schoolProvisioningStatus(
                $school->loadMissing('directorate'),
                true
            );

            if (!($status['has_any_templates'] ?? false) || (bool) ($status['is_imported'] ?? false)) {
                return;
            }

            $this->schoolDefaultDataProvisioningService->importForSchool((int) $school->id, $actorId, $request);
        } catch (ValidationException $exception) {
            report($exception);
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

    private function schoolTypeLabel(?string $schoolType): ?string
    {
        return match ($schoolType) {
            School::TYPE_BOYS => 'بنين',
            School::TYPE_GIRLS => 'بنات',
            School::TYPE_MIXED => 'مختلطة',
            default => null,
        };
    }
}


