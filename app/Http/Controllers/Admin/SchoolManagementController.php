<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\EducationStage;
use App\Models\EducationType;
use App\Models\EducationalDirectorate;
use App\Models\Governorate;
use App\Models\SchoolDefaultAcademicYearTemplate;
use App\Models\SchoolDefaultHolidayTemplate;
use App\Models\SchoolDefaultLeaveTypeTemplate;
use App\Models\SchoolDefaultStageTemplate;
use App\Models\SchoolDefaultSubjectTemplate;
use App\Models\Setting;
use App\Services\System\GlobalLocationTaxonomySyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SchoolManagementController extends Controller
{
    private const TAXONOMY_SYNC_GROUP = 'taxonomy_sync';
    private const COUNTRY_SYNC_FRESHNESS_HOURS = 24;
    private const COUNTRY_SYNC_RETRY_COOLDOWN_MINUTES = 15;
    private const SETTING_COUNTRIES_LAST_SYNCED_AT = 'taxonomy_countries_last_synced_at';
    private const SETTING_COUNTRIES_LAST_ATTEMPTED_AT = 'taxonomy_countries_last_attempted_at';
    private const SETTING_COUNTRIES_LAST_STATUS = 'taxonomy_countries_last_status';
    private const SETTING_COUNTRIES_LAST_MESSAGE = 'taxonomy_countries_last_message';
    private const SETTING_GOVERNORATES_LAST_SYNCED_AT = 'taxonomy_governorates_last_synced_at';
    private const SETTING_GOVERNORATES_LAST_STATUS = 'taxonomy_governorates_last_status';
    private const SETTING_GOVERNORATES_LAST_MESSAGE = 'taxonomy_governorates_last_message';
    private const SETTING_GOVERNORATES_LAST_COUNTRY_ID = 'taxonomy_governorates_last_country_id';
    private const SETTING_GOVERNORATES_LAST_COUNTRY_NAME = 'taxonomy_governorates_last_country_name';

    public function index(Request $request, GlobalLocationTaxonomySyncService $syncService): Response
    {
        $this->ensureCountriesAvailable($syncService);

        $query = EducationalDirectorate::query()
            ->with([
                'country:id,name',
                'governorateModel:id,country_id,name',
                'educationType:id,name',
                'schools' => fn ($schoolQuery) => $schoolQuery->orderBy('name'),
            ])
            ->orderBy('governorate')
            ->orderBy('name');

        $directorates = $query->get()->map(fn (EducationalDirectorate $directorate): array => [
            'id' => (int) $directorate->id,
            'name' => (string) $directorate->name,
            'governorate' => (string) $directorate->governorate,
            'country_id' => $directorate->country_id ? (int) $directorate->country_id : null,
            'governorate_id' => $directorate->governorate_id ? (int) $directorate->governorate_id : null,
            'education_type_id' => $directorate->education_type_id ? (int) $directorate->education_type_id : null,
            'country' => $directorate->country ? [
                'id' => (int) $directorate->country->id,
                'name' => (string) $directorate->country->name,
            ] : null,
            'governorate_model' => $directorate->governorateModel ? [
                'id' => (int) $directorate->governorateModel->id,
                'country_id' => (int) $directorate->governorateModel->country_id,
                'name' => (string) $directorate->governorateModel->name,
            ] : null,
            'education_type' => $directorate->educationType ? [
                'id' => (int) $directorate->educationType->id,
                'name' => (string) $directorate->educationType->name,
            ] : null,
            'schools' => $directorate->schools->map(fn ($school): array => [
                'id' => (int) $school->id,
                'name' => (string) $school->name,
                'school_id' => (string) $school->school_id,
                'phone' => (string) ($school->phone ?? ''),
                'status' => (string) ($school->status ?? ''),
            ])->all(),
        ])->all();

        $countries = Country::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $governorates = Governorate::query()
            ->orderBy('name')
            ->get(['id', 'country_id', 'name']);

        $educationTypes = EducationType::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Schools/Index', [
            'directorates' => $directorates,
            'countries' => $countries,
            'governorates' => $governorates,
            'educationTypes' => $educationTypes,
            'taxonomySync' => $this->taxonomySyncPayload(),
        ]);
    }

    public function storeCountry(Request $request): RedirectResponse
    {
        return $this->rejectManualCountryMutation();
    }

    public function syncCountriesFromGlobalApi(Request $request, GlobalLocationTaxonomySyncService $syncService): RedirectResponse|JsonResponse
    {
        $this->storeSettings([
            self::SETTING_COUNTRIES_LAST_ATTEMPTED_AT => now()->toISOString(),
        ]);

        try {
            $summary = $syncService->syncCountries();
        } catch (\Throwable $exception) {
            $message = 'تعذر مزامنة الدول من المصدر العالمي حاليًا. حاول مرة أخرى لاحقًا.';

            $this->storeSettings([
                self::SETTING_COUNTRIES_LAST_STATUS => 'failed',
                self::SETTING_COUNTRIES_LAST_MESSAGE => $message,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                    'taxonomySync' => $this->taxonomySyncPayload(),
                ], 503);
            }

            return redirect()->back()->with('error', $message);
        }

        $message = sprintf(
            'تمت مزامنة الدول بنجاح: %d جديدة، و%d مطابقة موجودة مسبقًا، من أصل %d دولة في المصدر العالمي.',
            (int) $summary['created'],
            (int) $summary['matched'],
            (int) $summary['total'],
        );

        $this->storeSettings([
            self::SETTING_COUNTRIES_LAST_SYNCED_AT => now()->toISOString(),
            self::SETTING_COUNTRIES_LAST_STATUS => 'success',
            self::SETTING_COUNTRIES_LAST_MESSAGE => $message,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'summary' => $summary,
                'taxonomySync' => $this->taxonomySyncPayload(),
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function updateCountry(Request $request, int $id): RedirectResponse
    {
        return $this->rejectManualCountryMutation();
    }

    public function destroyCountry(int $id): RedirectResponse
    {
        return $this->rejectManualCountryMutation();
    }

    public function storeGovernorate(Request $request): RedirectResponse
    {
        return $this->rejectManualGovernorateMutation();
    }

    public function syncGovernoratesFromGlobalApi(Request $request, GlobalLocationTaxonomySyncService $syncService): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
        ], [], [
            'country_id' => 'الدولة',
        ]);

        $country = Country::query()->findOrFail((int) $validated['country_id']);

        try {
            $summary = $syncService->syncGovernoratesForCountry($country);
        } catch (\Throwable $exception) {
            $message = 'تعذر مزامنة المحافظات لهذه الدولة من المصدر العالمي حاليًا.';

            $this->storeSettings([
                self::SETTING_GOVERNORATES_LAST_STATUS => 'failed',
                self::SETTING_GOVERNORATES_LAST_MESSAGE => $message,
                self::SETTING_GOVERNORATES_LAST_COUNTRY_ID => (string) $country->id,
                self::SETTING_GOVERNORATES_LAST_COUNTRY_NAME => (string) $country->name,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                    'taxonomySync' => $this->taxonomySyncPayload(),
                ], 503);
            }

            return redirect()->back()->with('error', $message);
        }

        $status = (string) ($summary['status'] ?? 'synced');
        $message = match ($status) {
            'skipped_existing' => sprintf(
                'تم تخطي مزامنة محافظات %s لأن هذه الدولة تحتوي بالفعل على محافظات محلية مرتبطة.',
                (string) $summary['country'],
            ),
            'empty' => sprintf(
                'لم يعرض المصدر العالمي محافظات متاحة حاليًا للدولة %s.',
                (string) $summary['country'],
            ),
            default => sprintf(
                'تمت مزامنة محافظات %s بنجاح: %d جديدة، و%d مطابقة موجودة مسبقًا، من أصل %d محافظة في المصدر العالمي.',
                (string) $summary['country'],
                (int) $summary['created'],
                (int) $summary['matched'],
                (int) $summary['total'],
            ),
        };

        $this->storeSettings([
            self::SETTING_GOVERNORATES_LAST_SYNCED_AT => now()->toISOString(),
            self::SETTING_GOVERNORATES_LAST_STATUS => $status === 'synced' ? 'success' : $status,
            self::SETTING_GOVERNORATES_LAST_MESSAGE => $message,
            self::SETTING_GOVERNORATES_LAST_COUNTRY_ID => (string) $country->id,
            self::SETTING_GOVERNORATES_LAST_COUNTRY_NAME => (string) $country->name,
        ]);

        if (($summary['status'] ?? null) === 'skipped_existing') {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'warning',
                    'message' => $message,
                    'summary' => $summary,
                    'taxonomySync' => $this->taxonomySyncPayload(),
                ]);
            }

            return redirect()->back()->with('warning', $message);
        }

        if (($summary['status'] ?? null) === 'empty') {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'info',
                    'message' => $message,
                    'summary' => $summary,
                    'taxonomySync' => $this->taxonomySyncPayload(),
                ]);
            }

            return redirect()->back()->with('info', $message);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'summary' => $summary,
                'taxonomySync' => $this->taxonomySyncPayload(),
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function updateGovernorate(Request $request, int $id): RedirectResponse
    {
        return $this->rejectManualGovernorateMutation();
    }

    public function destroyGovernorate(int $id): RedirectResponse
    {
        return $this->rejectManualGovernorateMutation();
    }

    public function storeEducationType(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $payload = $this->validateEducationTypePayload($request);

        $educationType = EducationType::query()->create($payload);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => (int) $educationType->id,
                    'name' => (string) $educationType->name,
                ],
            ], 201);
        }

        return redirect()->back();
    }

    public function updateEducationType(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $educationType = EducationType::query()->findOrFail($id);
        $payload = $this->validateEducationTypePayload($request, $educationType);

        $educationType->update($payload);

        EducationalDirectorate::query()
            ->where('education_type_id', $educationType->id)
            ->update(['name' => $payload['name']]);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => (int) $educationType->id,
                    'name' => (string) $educationType->name,
                ],
            ]);
        }

        return redirect()->back();
    }

    public function destroyEducationType(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $educationType = EducationType::query()
            ->withCount('directorates')
            ->findOrFail($id);

        $linkedTemplateCount = $this->countEducationTypeScopedTemplates((int) $educationType->id);

        if ((int) $educationType->directorates_count > 0 || $linkedTemplateCount > 0) {
            throw ValidationException::withMessages([
                'education_type' => 'لا يمكن حذف نوع التعليم لوجود نطاقات تعليمية أو مدارس مرتبطة به.',
            ]);
        }

        $educationType->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'deleted',
            ]);
        }

        return redirect()->back();
    }

    public function storeEducationStage(Request $request): RedirectResponse|JsonResponse
    {
        $payload = $this->validateEducationStagePayload($request);

        $educationStage = EducationStage::query()->create($payload);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->serializeEducationStage($educationStage),
            ], 201);
        }

        return redirect()->back();
    }

    public function updateEducationStage(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $educationStage = EducationStage::query()->findOrFail($id);
        $payload = $this->validateEducationStagePayload($request, $educationStage);

        $educationStage->update($payload);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->serializeEducationStage($educationStage),
            ]);
        }

        return redirect()->back();
    }

    public function destroyEducationStage(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $educationStage = EducationStage::query()
            ->withCount('schools')
            ->findOrFail($id);

        $linkedTemplateCount = $this->countEducationStageScopedTemplates($educationStage);

        if ((int) $educationStage->schools_count > 0 || $linkedTemplateCount > 0) {
            throw ValidationException::withMessages([
                'education_stage' => 'لا يمكن حذف المرحلة التعليمية لوجود مدارس أو قوالب افتراضية مرتبطة بها.',
            ]);
        }

        $educationStage->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'deleted',
            ]);
        }

        return redirect()->back();
    }

    public function storeDirectorate(Request $request): RedirectResponse
    {
        $payload = $this->validateDirectoratePayload($request);

        EducationalDirectorate::query()->create($payload);

        return redirect()->back();
    }

    public function updateDirectorate(Request $request, int $id): RedirectResponse
    {
        $directorate = EducationalDirectorate::query()->findOrFail($id);
        $payload = $this->validateDirectoratePayload($request, $directorate);

        $directorate->update($payload);

        return redirect()->back();
    }

    public function destroyDirectorate(int $id): RedirectResponse
    {
        $directorate = EducationalDirectorate::query()
            ->withCount('schools')
            ->findOrFail($id);

        if ((int) $directorate->schools_count > 0) {
            throw ValidationException::withMessages([
                'directorate' => 'لا يمكن حذف النطاق التعليمي لوجود مدارس مرتبطة به.',
            ]);
        }

        $directorate->delete();

        return redirect()->back();
    }

    public function storeSchool(): never
    {
        abort(403, 'إضافة المدارس متاحة فقط من خلال تهيئة مدير المدرسة.');
    }

    public function updateSchool(): never
    {
        abort(403, 'إدارة المدارس من حساب السوبر أدمن غير متاحة في هذا المسار.');
    }

    public function destroySchool(): never
    {
        abort(403, 'إدارة المدارس من حساب السوبر أدمن غير متاحة في هذا المسار.');
    }

    /**
     * @return array{name: string}
     */
    private function validateCountryPayload(Request $request, ?Country $current = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('countries', 'name')->ignore($current?->id),
            ],
        ], [], [
            'name' => 'اسم الدولة',
        ]);

        return [
            'name' => trim((string) $validated['name']),
        ];
    }

    /**
     * @return array{country_id: int, name: string}
     */
    private function validateGovernoratePayload(Request $request, ?Governorate $current = null): array
    {
        $validated = $request->validate([
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('governorates', 'name')
                    ->where(fn ($query) => $query->where('country_id', (int) $request->input('country_id')))
                    ->ignore($current?->id),
            ],
        ], [], [
            'country_id' => 'الدولة',
            'name' => 'اسم المحافظة',
        ]);

        return [
            'country_id' => (int) $validated['country_id'],
            'name' => trim((string) $validated['name']),
        ];
    }

    private function rejectManualCountryMutation(): RedirectResponse
    {
        return redirect()
            ->back()
            ->with('error', 'الدول تُدار بالمزامنة من المصدر العالمي فقط، ولا يمكن إضافتها أو تعديلها أو حذفها يدويًا.');
    }

    private function rejectManualGovernorateMutation(): RedirectResponse
    {
        return redirect()
            ->back()
            ->with('error', 'المحافظات تُدار بالمزامنة من المصدر العالمي فقط، ولا يمكن إضافتها أو تعديلها أو حذفها يدويًا.');
    }

    private function ensureCountriesAvailable(GlobalLocationTaxonomySyncService $syncService): void
    {
        if (Country::query()->exists()) {
            return;
        }

        try {
            $syncService->syncCountries();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return array{
     *   countries: array{
     *     last_synced_at: string|null,
     *     last_attempted_at: string|null,
     *     last_status: string,
     *     last_message: string|null,
     *     should_auto_sync: bool
     *   },
     *   governorates: array{
     *     last_synced_at: string|null,
     *     last_status: string,
     *     last_message: string|null,
     *     last_country_id: int|null,
     *     last_country_name: string|null
     *   }
     * }
     */
    private function taxonomySyncPayload(): array
    {
        $settings = Setting::query()
            ->whereIn('key', [
                self::SETTING_COUNTRIES_LAST_SYNCED_AT,
                self::SETTING_COUNTRIES_LAST_ATTEMPTED_AT,
                self::SETTING_COUNTRIES_LAST_STATUS,
                self::SETTING_COUNTRIES_LAST_MESSAGE,
                self::SETTING_GOVERNORATES_LAST_SYNCED_AT,
                self::SETTING_GOVERNORATES_LAST_STATUS,
                self::SETTING_GOVERNORATES_LAST_MESSAGE,
                self::SETTING_GOVERNORATES_LAST_COUNTRY_ID,
                self::SETTING_GOVERNORATES_LAST_COUNTRY_NAME,
            ])
            ->pluck('value', 'key');

        $countriesLastSyncedAt = $settings->get(self::SETTING_COUNTRIES_LAST_SYNCED_AT);
        $countriesLastAttemptedAt = $settings->get(self::SETTING_COUNTRIES_LAST_ATTEMPTED_AT);

        return [
            'countries' => [
                'last_synced_at' => $countriesLastSyncedAt,
                'last_attempted_at' => $countriesLastAttemptedAt,
                'last_status' => (string) ($settings->get(self::SETTING_COUNTRIES_LAST_STATUS) ?: 'idle'),
                'last_message' => $settings->get(self::SETTING_COUNTRIES_LAST_MESSAGE),
                'should_auto_sync' => $this->shouldAutoSyncCountries($countriesLastSyncedAt, $countriesLastAttemptedAt),
            ],
            'governorates' => [
                'last_synced_at' => $settings->get(self::SETTING_GOVERNORATES_LAST_SYNCED_AT),
                'last_status' => (string) ($settings->get(self::SETTING_GOVERNORATES_LAST_STATUS) ?: 'idle'),
                'last_message' => $settings->get(self::SETTING_GOVERNORATES_LAST_MESSAGE),
                'last_country_id' => $settings->get(self::SETTING_GOVERNORATES_LAST_COUNTRY_ID)
                    ? (int) $settings->get(self::SETTING_GOVERNORATES_LAST_COUNTRY_ID)
                    : null,
                'last_country_name' => $settings->get(self::SETTING_GOVERNORATES_LAST_COUNTRY_NAME),
            ],
        ];
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private function storeSettings(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'type' => 'text',
                    'group' => self::TAXONOMY_SYNC_GROUP,
                ],
            );
        }
    }

    private function shouldAutoSyncCountries(?string $lastSyncedAt, ?string $lastAttemptedAt): bool
    {
        $lastSynced = $this->parseIsoTimestamp($lastSyncedAt);
        if ($lastSynced && $lastSynced->greaterThanOrEqualTo(now()->subHours(self::COUNTRY_SYNC_FRESHNESS_HOURS))) {
            return false;
        }

        $lastAttempted = $this->parseIsoTimestamp($lastAttemptedAt);
        if ($lastAttempted && $lastAttempted->greaterThanOrEqualTo(now()->subMinutes(self::COUNTRY_SYNC_RETRY_COOLDOWN_MINUTES))) {
            return false;
        }

        return true;
    }

    private function parseIsoTimestamp(?string $value): ?Carbon
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{name: string}
     */
    private function validateEducationTypePayload(Request $request, ?EducationType $current = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('education_types', 'name')->ignore($current?->id),
            ],
        ], [], [
            'name' => 'نوع التعليم',
        ]);

        return [
            'name' => trim((string) $validated['name']),
        ];
    }

    /**
     * @return array{name: string, sort_order: int, is_active: bool}
     */
    private function validateEducationStagePayload(Request $request, ?EducationStage $current = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('education_stages', 'name')->ignore($current?->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'name' => 'المرحلة التعليمية',
            'sort_order' => 'ترتيب المرحلة',
            'is_active' => 'حالة التفعيل',
        ]);

        return [
            'name' => trim((string) $validated['name']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }

    private function countEducationTypeScopedTemplates(int $educationTypeId): int
    {
        return SchoolDefaultStageTemplate::query()->where('education_type_id', $educationTypeId)->count()
            + SchoolDefaultAcademicYearTemplate::query()->where('education_type_id', $educationTypeId)->count()
            + SchoolDefaultHolidayTemplate::query()->where('education_type_id', $educationTypeId)->count()
            + SchoolDefaultLeaveTypeTemplate::query()->where('education_type_id', $educationTypeId)->count()
            + SchoolDefaultSubjectTemplate::query()->where('education_type_id', $educationTypeId)->count();
    }

    private function countEducationStageScopedTemplates(EducationStage $educationStage): int
    {
        return SchoolDefaultStageTemplate::query()
            ->where(function ($query) use ($educationStage): void {
                $query->where('education_stage_id', (int) $educationStage->id)
                    ->orWhere(function ($legacyQuery) use ($educationStage): void {
                        $legacyQuery->whereNull('education_stage_id')
                            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $educationStage->name))]);
                    });
            })
            ->count();
    }

    /**
     * @return array{id: int, name: string, sort_order: int, is_active: bool}
     */
    private function serializeEducationStage(EducationStage $educationStage): array
    {
        return [
            'id' => (int) $educationStage->id,
            'name' => (string) $educationStage->name,
            'sort_order' => (int) $educationStage->sort_order,
            'is_active' => (bool) $educationStage->is_active,
        ];
    }

    /**
     * @return array{name: string, governorate: string, country_id?: int|null, governorate_id?: int|null, education_type_id?: int|null}
     */
    private function validateDirectoratePayload(Request $request, ?EducationalDirectorate $current = null): array
    {
        $usesStructuredTaxonomy = $request->filled('country_id')
            || $request->filled('governorate_id')
            || $request->filled('education_type_id');

        if (!$usesStructuredTaxonomy) {
            return $this->validateLegacyDirectoratePayload($request, $current);
        }

        $validated = $request->validate([
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            'governorate_id' => ['required', 'integer', Rule::exists('governorates', 'id')],
            'education_type_id' => ['required', 'integer', Rule::exists('education_types', 'id')],
        ], [], [
            'country_id' => 'الدولة',
            'governorate_id' => 'المحافظة',
            'education_type_id' => 'نوع التعليم',
        ]);

        $governorate = Governorate::query()->findOrFail((int) $validated['governorate_id']);
        $educationType = EducationType::query()->findOrFail((int) $validated['education_type_id']);

        if ((int) $governorate->country_id !== (int) $validated['country_id']) {
            throw ValidationException::withMessages([
                'governorate_id' => 'المحافظة المختارة لا تتبع الدولة المحددة.',
            ]);
        }

        $duplicateQuery = EducationalDirectorate::query()
            ->where('country_id', (int) $validated['country_id'])
            ->where('governorate_id', (int) $validated['governorate_id'])
            ->where('education_type_id', (int) $validated['education_type_id']);

        if ($current) {
            $duplicateQuery->where('id', '!=', $current->id);
        }

        if ($duplicateQuery->exists()) {
            throw ValidationException::withMessages([
                'education_type_id' => 'تمت إضافة هذا النطاق التعليمي لهذه المحافظة مسبقًا.',
            ]);
        }

        return [
            'country_id' => (int) $validated['country_id'],
            'governorate_id' => (int) $validated['governorate_id'],
            'education_type_id' => (int) $validated['education_type_id'],
            'governorate' => (string) $governorate->name,
            'name' => (string) $educationType->name,
        ];
    }

    /**
     * @return array{name: string, governorate: string}
     */
    private function validateLegacyDirectoratePayload(Request $request, ?EducationalDirectorate $current = null): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'governorate' => 'required|string|max:255',
        ], [], [
            'name' => 'نوع التعليم',
            'governorate' => 'المحافظة',
        ]);

        $payload = [
            'name' => trim((string) $validated['name']),
            'governorate' => trim((string) $validated['governorate']),
            'country_id' => null,
            'governorate_id' => null,
            'education_type_id' => null,
        ];

        $duplicateQuery = EducationalDirectorate::query()
            ->where('name', $payload['name'])
            ->where('governorate', $payload['governorate']);

        if ($current) {
            $duplicateQuery->where('id', '!=', $current->id);
        }

        if ($duplicateQuery->exists()) {
            throw ValidationException::withMessages([
                'name' => 'تمت إضافة نوع التعليم لهذه المحافظة مسبقًا.',
            ]);
        }

        return $payload;
    }
}
