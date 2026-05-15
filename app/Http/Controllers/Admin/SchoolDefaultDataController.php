<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\EducationStage;
use App\Models\EducationType;
use App\Models\School;
use App\Models\SchoolDefaultAcademicYearTemplate;
use App\Models\SchoolDefaultClassroomTemplate;
use App\Models\SchoolDefaultHolidayTemplate;
use App\Models\SchoolDefaultLeaveTypeTemplate;
use App\Models\SchoolDefaultStageGradeTemplate;
use App\Models\SchoolDefaultStageGradeTermTemplate;
use App\Models\SchoolDefaultStageTemplate;
use App\Models\SchoolDefaultStageTermTemplate;
use App\Models\SchoolDefaultSubjectTemplate;
use App\Models\EducationalDirectorate;
use App\Services\School\SchoolDefaultTemplateBootstrapService;
use App\Services\System\CountryReferenceDataService;
use App\Services\System\GlobalLocationTaxonomySyncService;
use App\Services\School\SchoolDefaultTemplateScopeRegistry;
use App\Services\Support\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SchoolDefaultDataController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(
        Request $request,
        GlobalLocationTaxonomySyncService $taxonomySyncService,
        SchoolDefaultTemplateScopeRegistry $scopeRegistry
    ): Response|JsonResponse|RedirectResponse
    {
        if (Country::query()->doesntExist()) {
            try {
                $taxonomySyncService->syncCountries();
            } catch (\Throwable $throwable) {
                report($throwable);
            }
        }

        $payload = $this->indexPayload($request, $scopeRegistry);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Admin/SchoolDefaults/Index', $payload + [
            'embedded' => $request->boolean('embedded'),
            'editor' => $request->boolean('editor'),
        ]);
    }

    public function storeScopeConfig(
        Request $request,
        SchoolDefaultTemplateScopeRegistry $scopeRegistry,
        CountryReferenceDataService $countryReferenceDataService,
        SchoolDefaultTemplateBootstrapService $templateBootstrapService
    ): RedirectResponse|JsonResponse {
        $validated = $request->validate([
            'template_name' => ['required', 'string', 'max:255'],
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            'education_type_id' => ['required', 'integer', Rule::exists('education_types', 'id')],
            'reference_snapshot' => ['nullable', 'array'],
        ], [], [
            'template_name' => 'اسم القالب',
            'country_id' => 'الدولة',
            'education_type_id' => 'نوع التعليم',
        ]);

        $country = Country::query()->findOrFail((int) $validated['country_id'], ['id', 'name']);
        $educationType = EducationType::query()->findOrFail((int) $validated['education_type_id'], ['id', 'name']);
        $providedReferenceSnapshot = $scopeRegistry->normalizeReferenceSnapshot($request->input('reference_snapshot'));

        if (($providedReferenceSnapshot['country']['id'] ?? null) !== null
            && (int) $providedReferenceSnapshot['country']['id'] !== (int) $country->id) {
            throw ValidationException::withMessages([
                'reference_snapshot' => 'مرجعيات الدولة المرسلة لا تتطابق مع الدولة المختارة.',
            ]);
        }

        if ($providedReferenceSnapshot !== []) {
            $providedReferenceSnapshot['country'] = [
                'id' => (int) $country->id,
                'name' => (string) $country->name,
                'code' => $providedReferenceSnapshot['country']['code'] ?? null,
            ];
        }

        $countryReference = $providedReferenceSnapshot !== []
            ? $providedReferenceSnapshot
            : $this->safeResolveCountryReference($countryReferenceDataService, $country);

        $scope = $scopeRegistry->upsert(
            templateName: (string) $validated['template_name'],
            countryId: (int) $validated['country_id'],
            educationTypeId: (int) $educationType->id,
            directorateId: null,
            referenceSnapshot: $countryReference
        );

        $holidaySync = $this->syncCountryReferenceHolidayTemplates(
            countryId: (int) $validated['country_id'],
            educationTypeId: (int) $educationType->id,
            actorId: (int) $request->user()->id,
            countryReference: $countryReference
        );

        $academicYearSync = $this->ensureCurrentAcademicYearTemplate(
            countryId: (int) $validated['country_id'],
            educationTypeId: (int) $educationType->id,
            actorId: (int) $request->user()->id,
            request: $request,
            countryReference: $countryReference,
        );

        $templateBootstrap = $templateBootstrapService->ensureFallbackDefaults(
            countryId: (int) $validated['country_id'],
            educationTypeId: (int) $educationType->id,
            actorId: (int) $request->user()->id,
            directorateId: null,
            countryReference: $countryReference
        );

        $message = 'تم حفظ القالب وربطه بالدولة ونوع التعليم بنجاح.';

        if (($holidaySync['created'] > 0 || ($holidaySync['updated'] ?? 0) > 0)
            && ($academicYearSync['status'] ?? null) === 'created') {
            $message = 'تم حفظ القالب وتعبئة العطلات الرسمية الوطنية مع إضافة العام الدراسي الحالي تلقائيًا بنجاح.';
        } elseif (($holidaySync['created'] > 0 || ($holidaySync['updated'] ?? 0) > 0)) {
            $message = 'تم حفظ القالب وتعبئة العطلات الرسمية الوطنية بنجاح.';
        } elseif (($academicYearSync['status'] ?? null) === 'created') {
            $message = 'تم حفظ القالب مع إضافة العام الدراسي الحالي تلقائيًا بنجاح.';
        }

        if ($templateBootstrap['has_generated_defaults'] ?? false) {
            $message = 'تم حفظ القالب واستكمال البيانات الافتراضية الأساسية تلقائيًا داخل القالب.';
        }

        if (($holidaySync['created'] > 0 || ($holidaySync['updated'] ?? 0) > 0)
            && ($academicYearSync['status'] ?? null) === 'created'
            && ($templateBootstrap['has_generated_defaults'] ?? false)) {
            $message = 'تم حفظ القالب واعتماد بيانات API الدولة أولًا، ثم استكمال العناصر الافتراضية الناقصة وإضافة العام الدراسي الحالي.';
        } elseif (($holidaySync['created'] > 0 || ($holidaySync['updated'] ?? 0) > 0)
            && ($templateBootstrap['has_generated_defaults'] ?? false)) {
            $message = 'تم حفظ القالب واعتماد البيانات المتاحة من API الدولة، ثم استكمال بقية عناصر القالب تلقائيًا.';
        } elseif (($academicYearSync['status'] ?? null) === 'created'
            && ($templateBootstrap['has_generated_defaults'] ?? false)) {
            $message = 'تم حفظ القالب وإضافة العام الدراسي الحالي مع استكمال البيانات الافتراضية الناقصة.';
        }

        return $this->respondSuccess(
            $request,
            $message,
            [
                'scope' => $scope,
                'country_reference' => $countryReference,
                'holiday_sync' => $holidaySync,
                'academic_year_sync' => $academicYearSync,
                'template_bootstrap' => $templateBootstrap,
            ]
        );
    }

    public function destroyScope(
        Request $request,
        int $country,
        int $educationType,
        SchoolDefaultTemplateScopeRegistry $scopeRegistry
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $countryModel = Country::query()->findOrFail($country, ['id', 'name']);
        $educationTypeModel = EducationType::query()->findOrFail($educationType, ['id', 'name']);

        $scopeCounts = $this->countScopedTemplateRecords(
            (int) $countryModel->id,
            (int) $educationTypeModel->id
        );

        $scopeExists = $scopeRegistry->find((int) $countryModel->id, (int) $educationTypeModel->id) !== null
            || array_sum($scopeCounts) > 0;

        if (!$scopeExists) {
            throw ValidationException::withMessages([
                'scope' => 'تعذر العثور على القالب المطلوب حذفه.',
            ]);
        }

        $schoolsWithImportedCopies = School::query()
            ->whereNotNull('default_data_imported_at')
            ->whereHas('directorate', function ($query) use ($countryModel, $educationTypeModel): void {
                $query
                    ->where('country_id', (int) $countryModel->id)
                    ->where('education_type_id', (int) $educationTypeModel->id);
            })
            ->count();

        DB::transaction(function () use ($countryModel, $educationTypeModel, $scopeRegistry): void {
            $this->deleteScopedTemplateRecords(
                (int) $countryModel->id,
                (int) $educationTypeModel->id
            );

            $scopeRegistry->delete((int) $countryModel->id, (int) $educationTypeModel->id);
        });

        $this->auditLogger->log(
            'school_default_template_scope.deleted',
            'school_default_template_scope',
            null,
            [
                'country_id' => (int) $countryModel->id,
                'country_name' => (string) $countryModel->name,
                'education_type_id' => (int) $educationTypeModel->id,
                'education_type_name' => (string) $educationTypeModel->name,
                'counts' => $scopeCounts,
                'schools_with_imported_copies' => $schoolsWithImportedCopies,
            ],
            $request,
            $actorId
        );

        $message = $schoolsWithImportedCopies > 0
            ? 'تم حذف القالب العام بنجاح. النسخ المدرسية التي سبق استيرادها لن تتأثر بهذا الحذف.'
            : 'تم حذف القالب العام وجميع بياناته الافتراضية بنجاح.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'deleted',
                'message' => $message,
                'data' => [
                    'country_id' => (int) $countryModel->id,
                    'education_type_id' => (int) $educationTypeModel->id,
                    'deleted_counts' => $scopeCounts,
                    'schools_with_imported_copies' => $schoolsWithImportedCopies,
                ],
            ]);
        }

        return redirect()
            ->route('admin.school_defaults.index', array_filter([
                'embedded' => $request->boolean('embedded') ? 1 : null,
                'editor' => $request->boolean('editor') ? 1 : null,
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', $message);
    }

    public function countryReference(
        Request $request,
        CountryReferenceDataService $countryReferenceDataService
    ): JsonResponse {
        $validated = $request->validate([
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
        ], [], [
            'country_id' => 'الدولة',
        ]);

        $country = Country::query()->findOrFail((int) $validated['country_id'], ['id', 'name']);

        return response()->json(
            $this->safeResolveCountryReference($countryReferenceDataService, $country)
        );
    }

    public function previewReferenceHolidays(
        Request $request,
        CountryReferenceDataService $countryReferenceDataService
    ): JsonResponse {
        $validated = $request->validate([
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ], [], [
            'country_id' => 'الدولة',
            'year' => 'السنة',
        ]);

        $country = Country::query()->findOrFail((int) $validated['country_id'], ['id', 'name', 'iso2_code', 'api_source', 'api_synced_at']);
        $payload = $countryReferenceDataService->fetchForCountry(
            $country,
            isset($validated['year']) ? (int) $validated['year'] : null
        );

        return response()->json($payload);
    }

    public function importReferenceHolidays(
        Request $request,
        CountryReferenceDataService $countryReferenceDataService
    ): JsonResponse {
        $validated = $request->validate([
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            'education_type_id' => ['required', 'integer', Rule::exists('education_types', 'id')],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ], [], [
            'country_id' => 'الدولة',
            'education_type_id' => 'نوع التعليم',
            'year' => 'السنة',
        ]);

        $country = Country::query()->findOrFail((int) $validated['country_id'], ['id', 'name', 'iso2_code', 'api_source', 'api_synced_at']);
        $countryReference = $countryReferenceDataService->fetchForCountry(
            $country,
            isset($validated['year']) ? (int) $validated['year'] : null
        );

        $result = $this->syncCountryReferenceHolidayTemplates(
            countryId: (int) $validated['country_id'],
            educationTypeId: (int) $validated['education_type_id'],
            actorId: (int) $request->user()->id,
            countryReference: $countryReference
        );

        return response()->json($result);
    }

    public function storeStage(Request $request): RedirectResponse|JsonResponse
    {
        $actorId = (int) $request->user()->id;
        $scope = $this->resolveScopeContext($request);
        $countryId = $scope['country_id'];
        $educationTypeId = $scope['education_type_id'];
        $directorateId = $scope['directorate_id'];
        $validated = $request->validate([
            'education_stage_id' => ['nullable', 'integer', Rule::exists('education_stages', 'id')],
            'name' => ['required', 'string', 'max:255', $this->scopedTemplateNameUnique('school_default_stage_templates', $countryId, $educationTypeId, $directorateId)],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('school_default_stage_templates', 'code')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'school_day_start_time' => ['nullable', 'date_format:H:i', 'required_with:school_day_end_time', 'before:school_day_end_time'],
            'school_day_end_time' => ['nullable', 'date_format:H:i', 'required_with:school_day_start_time', 'after:school_day_start_time'],
        ], [
            'name.unique' => 'اسم المرحلة الافتراضية مستخدم بالفعل.',
            'code.unique' => 'كود المرحلة الافتراضية مستخدم بالفعل.',
        ]);

        $educationStage = $this->resolveEducationStage($validated['education_stage_id'] ?? null);

        if ($educationStage instanceof EducationStage) {
            $validated['name'] = (string) $educationStage->name;
            if (!isset($validated['sort_order'])) {
                $validated['sort_order'] = (int) $educationStage->sort_order;
            }
        }

        try {
            $stageTemplate = DB::transaction(function () use ($validated, $actorId, $countryId, $educationTypeId, $directorateId, $educationStage): SchoolDefaultStageTemplate {
                $resolvedCode = $this->resolveTemplateCode(
                    table: 'school_default_stage_templates',
                    column: 'code',
                    preferredCode: $this->normalizeCodeInput($validated['code'] ?? null),
                    existingCode: null,
                    prefix: 'STG'
                );

                return SchoolDefaultStageTemplate::query()->create([
                    'country_id' => $countryId,
                    'education_type_id' => $educationTypeId,
                    'directorate_id' => $directorateId,
                    'education_stage_id' => $educationStage?->id,
                    'name' => trim((string) $validated['name']),
                    'code' => $resolvedCode,
                    'sort_order' => (int) ($validated['sort_order'] ?? 0),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                    'school_day_start_time' => $this->normalizeTimeInput($validated['school_day_start_time'] ?? null),
                    'school_day_end_time' => $this->normalizeTimeInput($validated['school_day_end_time'] ?? null),
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'اسم المرحلة الافتراضية مستخدم بالفعل.',
                'code' => 'كود المرحلة الافتراضية مستخدم بالفعل.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'school_default_stage_template.created',
            'school_default_stage_template',
            (int) $stageTemplate->id,
            [
                'payload' => $stageTemplate->only([
                    'name',
                    'code',
                    'sort_order',
                    'is_active',
                    'school_day_start_time',
                    'school_day_end_time',
                ]),
            ],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم حفظ قالب المرحلة الافتراضية بنجاح.',
            $this->serializeStageTemplate($stageTemplate),
            201
        );
    }

    public function updateStage(
        Request $request,
        SchoolDefaultStageTemplate $schoolDefaultStageTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $scope = $this->resolveScopeContext(
            $request,
            $schoolDefaultStageTemplate->directorate_id ? (int) $schoolDefaultStageTemplate->directorate_id : null,
            $schoolDefaultStageTemplate->country_id ? (int) $schoolDefaultStageTemplate->country_id : null,
            $schoolDefaultStageTemplate->education_type_id ? (int) $schoolDefaultStageTemplate->education_type_id : null
        );
        $countryId = $scope['country_id'];
        $educationTypeId = $scope['education_type_id'];
        $directorateId = $scope['directorate_id'];
        $validated = $request->validate([
            'education_stage_id' => ['nullable', 'integer', Rule::exists('education_stages', 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                $this->scopedTemplateNameUnique('school_default_stage_templates', $countryId, $educationTypeId, $directorateId, $schoolDefaultStageTemplate->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('school_default_stage_templates', 'code')->ignore($schoolDefaultStageTemplate->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'school_day_start_time' => ['nullable', 'date_format:H:i', 'required_with:school_day_end_time', 'before:school_day_end_time'],
            'school_day_end_time' => ['nullable', 'date_format:H:i', 'required_with:school_day_start_time', 'after:school_day_start_time'],
        ], [
            'name.unique' => 'اسم المرحلة الافتراضية مستخدم بالفعل.',
            'code.unique' => 'كود المرحلة الافتراضية مستخدم بالفعل.',
        ]);

        $educationStage = $this->resolveEducationStage($validated['education_stage_id'] ?? null);

        if ($educationStage instanceof EducationStage) {
            $validated['name'] = (string) $educationStage->name;
            if (!isset($validated['sort_order'])) {
                $validated['sort_order'] = (int) $educationStage->sort_order;
            }
        }

        $before = $schoolDefaultStageTemplate->only([
            'name',
            'code',
            'sort_order',
            'is_active',
            'school_day_start_time',
            'school_day_end_time',
        ]);

        try {
            DB::transaction(function () use ($validated, $actorId, $schoolDefaultStageTemplate, $countryId, $educationTypeId, $directorateId, $educationStage): void {
                $resolvedCode = $this->resolveTemplateCode(
                    table: 'school_default_stage_templates',
                    column: 'code',
                    preferredCode: $this->normalizeCodeInput($validated['code'] ?? null),
                    existingCode: $this->normalizeCodeInput($schoolDefaultStageTemplate->code),
                    prefix: 'STG',
                    ignoreId: (int) $schoolDefaultStageTemplate->id
                );

                $schoolDefaultStageTemplate->update([
                    'country_id' => $countryId,
                    'education_type_id' => $educationTypeId,
                    'directorate_id' => $directorateId,
                    'education_stage_id' => $educationStage?->id,
                    'name' => trim((string) $validated['name']),
                    'code' => $resolvedCode,
                    'sort_order' => (int) ($validated['sort_order'] ?? 0),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                    'school_day_start_time' => $this->normalizeTimeInput($validated['school_day_start_time'] ?? null),
                    'school_day_end_time' => $this->normalizeTimeInput($validated['school_day_end_time'] ?? null),
                    'updated_by' => $actorId,
                ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'اسم المرحلة الافتراضية مستخدم بالفعل.',
                'code' => 'كود المرحلة الافتراضية مستخدم بالفعل.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'school_default_stage_template.updated',
            'school_default_stage_template',
            (int) $schoolDefaultStageTemplate->id,
            [
                'before' => $before,
                'after' => $schoolDefaultStageTemplate->only([
                    'name',
                    'code',
                    'sort_order',
                    'is_active',
                    'school_day_start_time',
                    'school_day_end_time',
                ]),
            ],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم تحديث قالب المرحلة الافتراضية بنجاح.',
            $this->serializeStageTemplate($schoolDefaultStageTemplate)
        );
    }

    public function destroyStage(
        Request $request,
        SchoolDefaultStageTemplate $schoolDefaultStageTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $snapshot = $schoolDefaultStageTemplate->load(['stageTerms', 'grades', 'classrooms'])->toArray();

        $schoolDefaultStageTemplate->delete();

        $this->auditLogger->log(
            'school_default_stage_template.deleted',
            'school_default_stage_template',
            (int) $schoolDefaultStageTemplate->id,
            ['before' => $snapshot],
            $request,
            $actorId
        );

        return $this->respondSuccess($request, 'تم حذف قالب المرحلة الافتراضية بنجاح.');
    }

    public function storeStageTerm(Request $request): RedirectResponse|JsonResponse
    {
        $actorId = (int) $request->user()->id;
        $validated = $this->validateStageTermTemplate($request);

        try {
            $stageTermTemplate = SchoolDefaultStageTermTemplate::query()->create([
                'school_default_stage_template_id' => (int) $validated['school_default_stage_template_id'],
                'name' => trim((string) $validated['name']),
                'start_date' => $this->normalizeDateInput($validated['start_date'] ?? null),
                'end_date' => $this->normalizeDateInput($validated['end_date'] ?? null),
                'source' => 'manual',
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'اسم الفصل الدراسي الافتراضي مستخدم بالفعل داخل هذه المرحلة.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'school_default_stage_term_template.created',
            'school_default_stage_term_template',
            (int) $stageTermTemplate->id,
            [
                'payload' => $stageTermTemplate->only([
                    'school_default_stage_template_id',
                    'name',
                    'start_date',
                    'end_date',
                    'source',
                    'sort_order',
                    'is_active',
                ]),
            ],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم حفظ الفصل الدراسي المرتبط بالمرحلة بنجاح.',
            $this->serializeStageTermTemplate($stageTermTemplate),
            201
        );
    }

    public function updateStageTerm(
        Request $request,
        SchoolDefaultStageTermTemplate $schoolDefaultStageTermTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $validated = $this->validateStageTermTemplate($request, (int) $schoolDefaultStageTermTemplate->id);
        $before = $schoolDefaultStageTermTemplate->only([
            'school_default_stage_template_id',
            'name',
            'start_date',
            'end_date',
            'source',
            'sort_order',
            'is_active',
        ]);

        try {
            $schoolDefaultStageTermTemplate->update([
                'school_default_stage_template_id' => (int) $validated['school_default_stage_template_id'],
                'name' => trim((string) $validated['name']),
                'start_date' => $this->normalizeDateInput($validated['start_date'] ?? null),
                'end_date' => $this->normalizeDateInput($validated['end_date'] ?? null),
                'source' => 'manual',
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'updated_by' => $actorId,
            ]);
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'اسم الفصل الدراسي الافتراضي مستخدم بالفعل داخل هذه المرحلة.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'school_default_stage_term_template.updated',
            'school_default_stage_term_template',
            (int) $schoolDefaultStageTermTemplate->id,
            [
                'before' => $before,
                'after' => $schoolDefaultStageTermTemplate->only([
                    'school_default_stage_template_id',
                    'name',
                    'start_date',
                    'end_date',
                    'source',
                    'sort_order',
                    'is_active',
                ]),
            ],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم تحديث الفصل الدراسي المرتبط بالمرحلة بنجاح.',
            $this->serializeStageTermTemplate($schoolDefaultStageTermTemplate)
        );
    }

    public function destroyStageTerm(
        Request $request,
        SchoolDefaultStageTermTemplate $schoolDefaultStageTermTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $snapshot = $this->serializeStageTermTemplate($schoolDefaultStageTermTemplate);

        $schoolDefaultStageTermTemplate->delete();

        $this->auditLogger->log(
            'school_default_stage_term_template.deleted',
            'school_default_stage_term_template',
            (int) $schoolDefaultStageTermTemplate->id,
            ['before' => $snapshot],
            $request,
            $actorId
        );

        return $this->respondSuccess($request, 'تم حذف الفصل الدراسي المرتبط بالمرحلة بنجاح.');
    }

    public function storeStageGrade(Request $request): RedirectResponse|JsonResponse
    {
        $actorId = (int) $request->user()->id;
        $validated = $request->validate([
            'school_default_stage_template_id' => ['required', Rule::exists('school_default_stage_templates', 'id')],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('school_default_stage_grade_templates', 'name')->where(
                    fn ($query) => $query->where('school_default_stage_template_id', (int) $request->input('school_default_stage_template_id'))
                ),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'اسم الصف الافتراضي مستخدم بالفعل داخل هذه المرحلة.',
        ]);

        try {
            $stageGradeTemplate = SchoolDefaultStageGradeTemplate::query()->create([
                'school_default_stage_template_id' => (int) $validated['school_default_stage_template_id'],
                'name' => trim((string) $validated['name']),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'اسم الصف الافتراضي مستخدم بالفعل داخل هذه المرحلة.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'school_default_stage_grade_template.created',
            'school_default_stage_grade_template',
            (int) $stageGradeTemplate->id,
            [
                'payload' => $stageGradeTemplate->only([
                    'school_default_stage_template_id',
                    'name',
                    'sort_order',
                    'is_active',
                ]),
            ],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم حفظ قالب الصف الافتراضي بنجاح.',
            $stageGradeTemplate->fresh()->toArray(),
            201
        );
    }

    public function updateStageGrade(
        Request $request,
        SchoolDefaultStageGradeTemplate $schoolDefaultStageGradeTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $validated = $request->validate([
            'school_default_stage_template_id' => ['required', Rule::exists('school_default_stage_templates', 'id')],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('school_default_stage_grade_templates', 'name')
                    ->where(fn ($query) => $query->where('school_default_stage_template_id', (int) $request->input('school_default_stage_template_id')))
                    ->ignore($schoolDefaultStageGradeTemplate->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'اسم الصف الافتراضي مستخدم بالفعل داخل هذه المرحلة.',
        ]);

        $before = $schoolDefaultStageGradeTemplate->only([
            'school_default_stage_template_id',
            'name',
            'sort_order',
            'is_active',
        ]);

        try {
            DB::transaction(function () use ($validated, $actorId, $schoolDefaultStageGradeTemplate): void {
                $newStageId = (int) $validated['school_default_stage_template_id'];
                $oldStageId = (int) $schoolDefaultStageGradeTemplate->school_default_stage_template_id;

                $schoolDefaultStageGradeTemplate->update([
                    'school_default_stage_template_id' => $newStageId,
                    'name' => trim((string) $validated['name']),
                    'sort_order' => (int) ($validated['sort_order'] ?? 0),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                    'updated_by' => $actorId,
                ]);

                if ($newStageId !== $oldStageId) {
                    SchoolDefaultClassroomTemplate::query()
                        ->where('school_default_stage_grade_template_id', (int) $schoolDefaultStageGradeTemplate->id)
                        ->update([
                            'school_default_stage_template_id' => $newStageId,
                            'updated_at' => now(),
                            'updated_by' => $actorId,
                        ]);
                }
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'اسم الصف الافتراضي مستخدم بالفعل داخل هذه المرحلة.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'school_default_stage_grade_template.updated',
            'school_default_stage_grade_template',
            (int) $schoolDefaultStageGradeTemplate->id,
            [
                'before' => $before,
                'after' => $schoolDefaultStageGradeTemplate->only([
                    'school_default_stage_template_id',
                    'name',
                    'sort_order',
                    'is_active',
                ]),
            ],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم تحديث قالب الصف الافتراضي بنجاح.',
            $schoolDefaultStageGradeTemplate->fresh()->toArray()
        );
    }

    public function destroyStageGrade(
        Request $request,
        SchoolDefaultStageGradeTemplate $schoolDefaultStageGradeTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $snapshot = $schoolDefaultStageGradeTemplate->load(['gradeTerms', 'classrooms'])->toArray();

        $schoolDefaultStageGradeTemplate->delete();

        $this->auditLogger->log(
            'school_default_stage_grade_template.deleted',
            'school_default_stage_grade_template',
            (int) $schoolDefaultStageGradeTemplate->id,
            ['before' => $snapshot],
            $request,
            $actorId
        );

        return $this->respondSuccess($request, 'تم حذف قالب الصف الافتراضي بنجاح.');
    }

    public function storeStageGradeTerm(Request $request): RedirectResponse|JsonResponse
    {
        $actorId = (int) $request->user()->id;
        $validated = $this->validateStageGradeTermTemplate($request);
        $this->assertStageGradeMatchesStage(
            (int) $validated['school_default_stage_template_id'],
            (int) $validated['school_default_stage_grade_template_id']
        );

        try {
            $stageGradeTermTemplate = SchoolDefaultStageGradeTermTemplate::query()->create([
                'school_default_stage_grade_template_id' => (int) $validated['school_default_stage_grade_template_id'],
                'name' => trim((string) $validated['name']),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'اسم الترم الدراسي الافتراضي مستخدم بالفعل داخل هذا الصف.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'school_default_stage_grade_term_template.created',
            'school_default_stage_grade_term_template',
            (int) $stageGradeTermTemplate->id,
            [
                'payload' => $stageGradeTermTemplate->only([
                    'school_default_stage_grade_template_id',
                    'name',
                    'sort_order',
                    'is_active',
                ]),
            ],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم حفظ ترم الصف الافتراضي بنجاح.',
            $this->serializeStageGradeTermTemplate($stageGradeTermTemplate),
            201
        );
    }

    public function updateStageGradeTerm(
        Request $request,
        SchoolDefaultStageGradeTermTemplate $gradeTermTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $validated = $this->validateStageGradeTermTemplate($request, (int) $gradeTermTemplate->id);
        $this->assertStageGradeMatchesStage(
            (int) $validated['school_default_stage_template_id'],
            (int) $validated['school_default_stage_grade_template_id']
        );

        $before = $gradeTermTemplate->only([
            'school_default_stage_grade_template_id',
            'name',
            'sort_order',
            'is_active',
        ]);

        try {
            $gradeTermTemplate->update([
                'school_default_stage_grade_template_id' => (int) $validated['school_default_stage_grade_template_id'],
                'name' => trim((string) $validated['name']),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'updated_by' => $actorId,
            ]);
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'اسم الترم الدراسي الافتراضي مستخدم بالفعل داخل هذا الصف.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'school_default_stage_grade_term_template.updated',
            'school_default_stage_grade_term_template',
            (int) $gradeTermTemplate->id,
            [
                'before' => $before,
                'after' => $gradeTermTemplate->only([
                    'school_default_stage_grade_template_id',
                    'name',
                    'sort_order',
                    'is_active',
                ]),
            ],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم تحديث ترم الصف الافتراضي بنجاح.',
            $this->serializeStageGradeTermTemplate($gradeTermTemplate)
        );
    }

    public function destroyStageGradeTerm(
        Request $request,
        SchoolDefaultStageGradeTermTemplate $gradeTermTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $snapshot = $this->serializeStageGradeTermTemplate($gradeTermTemplate);

        $gradeTermTemplate->delete();

        $this->auditLogger->log(
            'school_default_stage_grade_term_template.deleted',
            'school_default_stage_grade_term_template',
            (int) $gradeTermTemplate->id,
            ['before' => $snapshot],
            $request,
            $actorId
        );

        return $this->respondSuccess($request, 'تم حذف ترم الصف الافتراضي بنجاح.');
    }

    public function storeClassroom(Request $request): RedirectResponse|JsonResponse
    {
        $actorId = (int) $request->user()->id;
        $validated = $this->validateClassroomTemplate($request);
        $this->assertStageGradeMatchesStage(
            (int) $validated['school_default_stage_template_id'],
            (int) $validated['school_default_stage_grade_template_id']
        );

        try {
            $classroomTemplate = DB::transaction(function () use ($validated, $actorId): SchoolDefaultClassroomTemplate {
                $resolvedCode = $this->resolveTemplateCode(
                    table: 'school_default_classroom_templates',
                    column: 'code',
                    preferredCode: $this->normalizeCodeInput($validated['code'] ?? null),
                    existingCode: null,
                    prefix: 'CLS'
                );

                return SchoolDefaultClassroomTemplate::query()->create([
                    'school_default_stage_template_id' => (int) $validated['school_default_stage_template_id'],
                    'school_default_stage_grade_template_id' => (int) $validated['school_default_stage_grade_template_id'],
                    'name' => trim((string) $validated['name']),
                    'code' => $resolvedCode,
                    'sort_order' => (int) ($validated['sort_order'] ?? 0),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'اسم الفصل الافتراضي مستخدم بالفعل داخل هذا الصف.',
                'code' => 'كود الفصل الافتراضي مستخدم بالفعل.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'school_default_classroom_template.created',
            'school_default_classroom_template',
            (int) $classroomTemplate->id,
            [
                'payload' => $classroomTemplate->only([
                    'school_default_stage_template_id',
                    'school_default_stage_grade_template_id',
                    'name',
                    'code',
                    'sort_order',
                    'is_active',
                ]),
            ],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم حفظ قالب الفصل الافتراضي بنجاح.',
            $this->serializeClassroomTemplate($classroomTemplate),
            201
        );
    }

    public function updateClassroom(
        Request $request,
        SchoolDefaultClassroomTemplate $schoolDefaultClassroomTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $validated = $this->validateClassroomTemplate($request, (int) $schoolDefaultClassroomTemplate->id);
        $this->assertStageGradeMatchesStage(
            (int) $validated['school_default_stage_template_id'],
            (int) $validated['school_default_stage_grade_template_id']
        );

        $before = $schoolDefaultClassroomTemplate->only([
            'school_default_stage_template_id',
            'school_default_stage_grade_template_id',
            'name',
            'code',
            'sort_order',
            'is_active',
        ]);

        try {
            DB::transaction(function () use ($validated, $actorId, $schoolDefaultClassroomTemplate): void {
                $resolvedCode = $this->resolveTemplateCode(
                    table: 'school_default_classroom_templates',
                    column: 'code',
                    preferredCode: $this->normalizeCodeInput($validated['code'] ?? null),
                    existingCode: $this->normalizeCodeInput($schoolDefaultClassroomTemplate->code),
                    prefix: 'CLS',
                    ignoreId: (int) $schoolDefaultClassroomTemplate->id
                );

                $schoolDefaultClassroomTemplate->update([
                    'school_default_stage_template_id' => (int) $validated['school_default_stage_template_id'],
                    'school_default_stage_grade_template_id' => (int) $validated['school_default_stage_grade_template_id'],
                    'name' => trim((string) $validated['name']),
                    'code' => $resolvedCode,
                    'sort_order' => (int) ($validated['sort_order'] ?? 0),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                    'updated_by' => $actorId,
                ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'اسم الفصل الافتراضي مستخدم بالفعل داخل هذا الصف.',
                'code' => 'كود الفصل الافتراضي مستخدم بالفعل.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'school_default_classroom_template.updated',
            'school_default_classroom_template',
            (int) $schoolDefaultClassroomTemplate->id,
            [
                'before' => $before,
                'after' => $schoolDefaultClassroomTemplate->only([
                    'school_default_stage_template_id',
                    'school_default_stage_grade_template_id',
                    'name',
                    'code',
                    'sort_order',
                    'is_active',
                ]),
            ],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم تحديث قالب الفصل الافتراضي بنجاح.',
            $this->serializeClassroomTemplate($schoolDefaultClassroomTemplate)
        );
    }

    public function destroyClassroom(
        Request $request,
        SchoolDefaultClassroomTemplate $schoolDefaultClassroomTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $snapshot = $this->serializeClassroomTemplate($schoolDefaultClassroomTemplate);

        $schoolDefaultClassroomTemplate->delete();

        $this->auditLogger->log(
            'school_default_classroom_template.deleted',
            'school_default_classroom_template',
            (int) $schoolDefaultClassroomTemplate->id,
            ['before' => $snapshot],
            $request,
            $actorId
        );

        return $this->respondSuccess($request, 'تم حذف قالب الفصل الافتراضي بنجاح.');
    }

    public function storeAcademicYear(Request $request): RedirectResponse|JsonResponse
    {
        $actorId = (int) $request->user()->id;
        $scope = $this->resolveScopeContext($request);
        $countryId = $scope['country_id'];
        $educationTypeId = $scope['education_type_id'];
        $directorateId = $scope['directorate_id'];
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->scopedTemplateNameUnique('school_default_academic_year_templates', $countryId, $educationTypeId, $directorateId)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'اسم العام الدراسي الافتراضي مستخدم بالفعل.',
        ]);

        $academicYearTemplate = SchoolDefaultAcademicYearTemplate::query()->create([
            'country_id' => $countryId,
            'education_type_id' => $educationTypeId,
            'directorate_id' => $directorateId,
            'name' => trim((string) $validated['name']),
            'starts_on' => Carbon::parse($validated['starts_on'])->toDateString(),
            'ends_on' => Carbon::parse($validated['ends_on'])->toDateString(),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        $this->auditLogger->log(
            'school_default_academic_year_template.created',
            'school_default_academic_year_template',
            (int) $academicYearTemplate->id,
            ['payload' => $academicYearTemplate->only(['name', 'starts_on', 'ends_on', 'is_active'])],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم حفظ قالب العام الدراسي الافتراضي بنجاح.',
            $academicYearTemplate->fresh()->toArray(),
            201
        );
    }

    public function updateAcademicYear(
        Request $request,
        SchoolDefaultAcademicYearTemplate $yearTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $scope = $this->resolveScopeContext(
            $request,
            $yearTemplate->directorate_id ? (int) $yearTemplate->directorate_id : null,
            $yearTemplate->country_id ? (int) $yearTemplate->country_id : null,
            $yearTemplate->education_type_id ? (int) $yearTemplate->education_type_id : null
        );
        $countryId = $scope['country_id'];
        $educationTypeId = $scope['education_type_id'];
        $directorateId = $scope['directorate_id'];
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                $this->scopedTemplateNameUnique('school_default_academic_year_templates', $countryId, $educationTypeId, $directorateId, $yearTemplate->id),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'اسم العام الدراسي الافتراضي مستخدم بالفعل.',
        ]);

        $before = $yearTemplate->only(['name', 'starts_on', 'ends_on', 'is_active']);

        $yearTemplate->update([
            'country_id' => $countryId,
            'education_type_id' => $educationTypeId,
            'directorate_id' => $directorateId,
            'name' => trim((string) $validated['name']),
            'starts_on' => Carbon::parse($validated['starts_on'])->toDateString(),
            'ends_on' => Carbon::parse($validated['ends_on'])->toDateString(),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'updated_by' => $actorId,
        ]);

        $this->auditLogger->log(
            'school_default_academic_year_template.updated',
            'school_default_academic_year_template',
            (int) $yearTemplate->id,
            [
                'before' => $before,
                'after' => $yearTemplate->only(['name', 'starts_on', 'ends_on', 'is_active']),
            ],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم تحديث قالب العام الدراسي الافتراضي بنجاح.',
            $yearTemplate->fresh()->toArray()
        );
    }

    public function destroyAcademicYear(
        Request $request,
        SchoolDefaultAcademicYearTemplate $yearTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $snapshot = $yearTemplate->toArray();

        $yearTemplate->delete();

        $this->auditLogger->log(
            'school_default_academic_year_template.deleted',
            'school_default_academic_year_template',
            (int) $yearTemplate->id,
            ['before' => $snapshot],
            $request,
            $actorId
        );

        return $this->respondSuccess($request, 'تم حذف قالب العام الدراسي الافتراضي بنجاح.');
    }

    public function storeHoliday(Request $request): RedirectResponse|JsonResponse
    {
        $actorId = (int) $request->user()->id;
        $scope = $this->resolveScopeContext($request);
        $countryId = $scope['country_id'];
        $educationTypeId = $scope['education_type_id'];
        $directorateId = $scope['directorate_id'];
        $validated = $this->validateHolidayTemplate($request);
        $this->assertHolidayTemplatePeriodAvailable(
            Carbon::parse($validated['start_date'])->toDateString(),
            Carbon::parse($validated['end_date'])->toDateString(),
            null,
            (bool) ($validated['is_active'] ?? true),
            $countryId,
            $educationTypeId,
            $directorateId
        );

        $holidayTemplate = SchoolDefaultHolidayTemplate::query()->create([
            'country_id' => $countryId,
            'education_type_id' => $educationTypeId,
            'directorate_id' => $directorateId,
            'name' => trim((string) $validated['name']),
            'start_date' => Carbon::parse($validated['start_date'])->toDateString(),
            'end_date' => Carbon::parse($validated['end_date'])->toDateString(),
            'return_date' => $validated['return_date'] ? Carbon::parse($validated['return_date'])->toDateString() : null,
            'notes' => $this->emptyToNull($validated['notes'] ?? null),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        $this->auditLogger->log(
            'school_default_holiday_template.created',
            'school_default_holiday_template',
            (int) $holidayTemplate->id,
            ['payload' => $holidayTemplate->only(['name', 'start_date', 'end_date', 'return_date', 'notes', 'is_active'])],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم حفظ قالب العطلة الرسمية الافتراضي بنجاح.',
            $holidayTemplate->fresh()->toArray(),
            201
        );
    }

    public function updateHoliday(
        Request $request,
        SchoolDefaultHolidayTemplate $schoolDefaultHolidayTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $scope = $this->resolveScopeContext(
            $request,
            $schoolDefaultHolidayTemplate->directorate_id ? (int) $schoolDefaultHolidayTemplate->directorate_id : null,
            $schoolDefaultHolidayTemplate->country_id ? (int) $schoolDefaultHolidayTemplate->country_id : null,
            $schoolDefaultHolidayTemplate->education_type_id ? (int) $schoolDefaultHolidayTemplate->education_type_id : null
        );
        $countryId = $scope['country_id'];
        $educationTypeId = $scope['education_type_id'];
        $directorateId = $scope['directorate_id'];
        $validated = $this->validateHolidayTemplate($request);
        $this->assertHolidayTemplatePeriodAvailable(
            Carbon::parse($validated['start_date'])->toDateString(),
            Carbon::parse($validated['end_date'])->toDateString(),
            (int) $schoolDefaultHolidayTemplate->id,
            (bool) ($validated['is_active'] ?? true),
            $countryId,
            $educationTypeId,
            $directorateId
        );

        $before = $schoolDefaultHolidayTemplate->only(['name', 'start_date', 'end_date', 'return_date', 'notes', 'is_active']);

        $schoolDefaultHolidayTemplate->update([
            'country_id' => $countryId,
            'education_type_id' => $educationTypeId,
            'directorate_id' => $directorateId,
            'name' => trim((string) $validated['name']),
            'start_date' => Carbon::parse($validated['start_date'])->toDateString(),
            'end_date' => Carbon::parse($validated['end_date'])->toDateString(),
            'return_date' => $validated['return_date'] ? Carbon::parse($validated['return_date'])->toDateString() : null,
            'notes' => $this->emptyToNull($validated['notes'] ?? null),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'updated_by' => $actorId,
        ]);

        $this->auditLogger->log(
            'school_default_holiday_template.updated',
            'school_default_holiday_template',
            (int) $schoolDefaultHolidayTemplate->id,
            [
                'before' => $before,
                'after' => $schoolDefaultHolidayTemplate->only(['name', 'start_date', 'end_date', 'return_date', 'notes', 'is_active']),
            ],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم تحديث قالب العطلة الرسمية الافتراضي بنجاح.',
            $schoolDefaultHolidayTemplate->fresh()->toArray()
        );
    }

    public function destroyHoliday(
        Request $request,
        SchoolDefaultHolidayTemplate $schoolDefaultHolidayTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $snapshot = $schoolDefaultHolidayTemplate->toArray();

        $schoolDefaultHolidayTemplate->delete();

        $this->auditLogger->log(
            'school_default_holiday_template.deleted',
            'school_default_holiday_template',
            (int) $schoolDefaultHolidayTemplate->id,
            ['before' => $snapshot],
            $request,
            $actorId
        );

        return $this->respondSuccess($request, 'تم حذف قالب العطلة الرسمية الافتراضي بنجاح.');
    }

    public function storeLeaveType(Request $request): RedirectResponse|JsonResponse
    {
        $actorId = (int) $request->user()->id;
        $scope = $this->resolveScopeContext($request);
        $countryId = $scope['country_id'];
        $educationTypeId = $scope['education_type_id'];
        $directorateId = $scope['directorate_id'];
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->scopedTemplateNameUnique('school_default_leave_type_templates', $countryId, $educationTypeId, $directorateId)],
            'code' => ['nullable', 'string', 'max:60', 'alpha_dash', Rule::unique('school_default_leave_type_templates', 'code')],
            'requires_attachment' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'اسم نوع الإجازة الافتراضي مستخدم بالفعل.',
            'code.unique' => 'كود نوع الإجازة الافتراضي مستخدم بالفعل.',
        ]);

        try {
            $leaveTypeTemplate = DB::transaction(function () use ($validated, $actorId, $countryId, $educationTypeId, $directorateId): SchoolDefaultLeaveTypeTemplate {
                $resolvedCode = $this->resolveTemplateCode(
                    table: 'school_default_leave_type_templates',
                    column: 'code',
                    preferredCode: $this->normalizeCodeInput($validated['code'] ?? null),
                    existingCode: null,
                    prefix: 'LEAVE'
                );

                return SchoolDefaultLeaveTypeTemplate::query()->create([
                    'country_id' => $countryId,
                    'education_type_id' => $educationTypeId,
                    'directorate_id' => $directorateId,
                    'name' => trim((string) $validated['name']),
                    'code' => $resolvedCode,
                    'requires_attachment' => (bool) ($validated['requires_attachment'] ?? false),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'اسم نوع الإجازة الافتراضي مستخدم بالفعل.',
                'code' => 'كود نوع الإجازة الافتراضي مستخدم بالفعل.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'school_default_leave_type_template.created',
            'school_default_leave_type_template',
            (int) $leaveTypeTemplate->id,
            ['payload' => $leaveTypeTemplate->only(['name', 'code', 'requires_attachment', 'is_active'])],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم حفظ قالب نوع الإجازة الافتراضي بنجاح.',
            $leaveTypeTemplate->fresh()->toArray(),
            201
        );
    }

    public function updateLeaveType(
        Request $request,
        SchoolDefaultLeaveTypeTemplate $schoolDefaultLeaveTypeTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $scope = $this->resolveScopeContext(
            $request,
            $schoolDefaultLeaveTypeTemplate->directorate_id ? (int) $schoolDefaultLeaveTypeTemplate->directorate_id : null,
            $schoolDefaultLeaveTypeTemplate->country_id ? (int) $schoolDefaultLeaveTypeTemplate->country_id : null,
            $schoolDefaultLeaveTypeTemplate->education_type_id ? (int) $schoolDefaultLeaveTypeTemplate->education_type_id : null
        );
        $countryId = $scope['country_id'];
        $educationTypeId = $scope['education_type_id'];
        $directorateId = $scope['directorate_id'];
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                $this->scopedTemplateNameUnique('school_default_leave_type_templates', $countryId, $educationTypeId, $directorateId, $schoolDefaultLeaveTypeTemplate->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:60',
                'alpha_dash',
                Rule::unique('school_default_leave_type_templates', 'code')->ignore($schoolDefaultLeaveTypeTemplate->id),
            ],
            'requires_attachment' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'اسم نوع الإجازة الافتراضي مستخدم بالفعل.',
            'code.unique' => 'كود نوع الإجازة الافتراضي مستخدم بالفعل.',
        ]);

        $before = $schoolDefaultLeaveTypeTemplate->only(['name', 'code', 'requires_attachment', 'is_active']);

        try {
            DB::transaction(function () use ($validated, $actorId, $schoolDefaultLeaveTypeTemplate, $countryId, $educationTypeId, $directorateId): void {
                $resolvedCode = $this->resolveTemplateCode(
                    table: 'school_default_leave_type_templates',
                    column: 'code',
                    preferredCode: $this->normalizeCodeInput($validated['code'] ?? null),
                    existingCode: $this->normalizeCodeInput($schoolDefaultLeaveTypeTemplate->code),
                    prefix: 'LEAVE',
                    ignoreId: (int) $schoolDefaultLeaveTypeTemplate->id
                );

                $schoolDefaultLeaveTypeTemplate->update([
                    'country_id' => $countryId,
                    'education_type_id' => $educationTypeId,
                    'directorate_id' => $directorateId,
                    'name' => trim((string) $validated['name']),
                    'code' => $resolvedCode,
                    'requires_attachment' => (bool) ($validated['requires_attachment'] ?? false),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                    'updated_by' => $actorId,
                ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'اسم نوع الإجازة الافتراضي مستخدم بالفعل.',
                'code' => 'كود نوع الإجازة الافتراضي مستخدم بالفعل.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'school_default_leave_type_template.updated',
            'school_default_leave_type_template',
            (int) $schoolDefaultLeaveTypeTemplate->id,
            [
                'before' => $before,
                'after' => $schoolDefaultLeaveTypeTemplate->only(['name', 'code', 'requires_attachment', 'is_active']),
            ],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم تحديث قالب نوع الإجازة الافتراضي بنجاح.',
            $schoolDefaultLeaveTypeTemplate->fresh()->toArray()
        );
    }

    public function destroyLeaveType(
        Request $request,
        SchoolDefaultLeaveTypeTemplate $schoolDefaultLeaveTypeTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $snapshot = $schoolDefaultLeaveTypeTemplate->toArray();

        $schoolDefaultLeaveTypeTemplate->delete();

        $this->auditLogger->log(
            'school_default_leave_type_template.deleted',
            'school_default_leave_type_template',
            (int) $schoolDefaultLeaveTypeTemplate->id,
            ['before' => $snapshot],
            $request,
            $actorId
        );

        return $this->respondSuccess($request, 'تم حذف قالب نوع الإجازة الافتراضي بنجاح.');
    }

    public function storeSubject(Request $request): RedirectResponse|JsonResponse
    {
        $actorId = (int) $request->user()->id;
        $scope = $this->resolveScopeContext($request);
        $countryId = $scope['country_id'];
        $educationTypeId = $scope['education_type_id'];
        $directorateId = $scope['directorate_id'];
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->scopedTemplateNameUnique('school_default_subject_templates', $countryId, $educationTypeId, $directorateId)],
            'code' => ['nullable', 'string', 'max:60', 'alpha_dash', Rule::unique('school_default_subject_templates', 'code')],
            'branches' => ['nullable', 'array'],
            'branches.*' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'اسم المادة الافتراضية مستخدم بالفعل.',
            'code.unique' => 'كود المادة الافتراضية مستخدم بالفعل.',
        ]);

        try {
            $subjectTemplate = DB::transaction(function () use ($validated, $actorId, $countryId, $educationTypeId, $directorateId): SchoolDefaultSubjectTemplate {
                $resolvedCode = $this->resolveTemplateCode(
                    table: 'school_default_subject_templates',
                    column: 'code',
                    preferredCode: $this->normalizeCodeInput($validated['code'] ?? null),
                    existingCode: null,
                    prefix: 'SUB'
                );

                return SchoolDefaultSubjectTemplate::query()->create([
                    'country_id' => $countryId,
                    'education_type_id' => $educationTypeId,
                    'directorate_id' => $directorateId,
                    'name' => trim((string) $validated['name']),
                    'code' => $resolvedCode,
                    'branches' => $this->normalizeSubjectBranches((array) ($validated['branches'] ?? [])),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'اسم المادة الافتراضية مستخدم بالفعل.',
                'code' => 'كود المادة الافتراضية مستخدم بالفعل.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'school_default_subject_template.created',
            'school_default_subject_template',
            (int) $subjectTemplate->id,
            ['payload' => $subjectTemplate->only(['name', 'code', 'branches', 'is_active'])],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم حفظ قالب المادة الافتراضية بنجاح.',
            $subjectTemplate->fresh()->toArray(),
            201
        );
    }

    public function updateSubject(
        Request $request,
        SchoolDefaultSubjectTemplate $schoolDefaultSubjectTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $scope = $this->resolveScopeContext(
            $request,
            $schoolDefaultSubjectTemplate->directorate_id ? (int) $schoolDefaultSubjectTemplate->directorate_id : null,
            $schoolDefaultSubjectTemplate->country_id ? (int) $schoolDefaultSubjectTemplate->country_id : null,
            $schoolDefaultSubjectTemplate->education_type_id ? (int) $schoolDefaultSubjectTemplate->education_type_id : null
        );
        $countryId = $scope['country_id'];
        $educationTypeId = $scope['education_type_id'];
        $directorateId = $scope['directorate_id'];
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                $this->scopedTemplateNameUnique('school_default_subject_templates', $countryId, $educationTypeId, $directorateId, $schoolDefaultSubjectTemplate->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:60',
                'alpha_dash',
                Rule::unique('school_default_subject_templates', 'code')->ignore($schoolDefaultSubjectTemplate->id),
            ],
            'branches' => ['nullable', 'array'],
            'branches.*' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'اسم المادة الافتراضية مستخدم بالفعل.',
            'code.unique' => 'كود المادة الافتراضية مستخدم بالفعل.',
        ]);

        $before = $schoolDefaultSubjectTemplate->only(['name', 'code', 'branches', 'is_active']);

        try {
            DB::transaction(function () use ($validated, $actorId, $schoolDefaultSubjectTemplate, $countryId, $educationTypeId, $directorateId): void {
                $resolvedCode = $this->resolveTemplateCode(
                    table: 'school_default_subject_templates',
                    column: 'code',
                    preferredCode: $this->normalizeCodeInput($validated['code'] ?? null),
                    existingCode: $this->normalizeCodeInput($schoolDefaultSubjectTemplate->code),
                    prefix: 'SUB',
                    ignoreId: (int) $schoolDefaultSubjectTemplate->id
                );

                $schoolDefaultSubjectTemplate->update([
                    'country_id' => $countryId,
                    'education_type_id' => $educationTypeId,
                    'directorate_id' => $directorateId,
                    'name' => trim((string) $validated['name']),
                    'code' => $resolvedCode,
                    'branches' => $this->normalizeSubjectBranches((array) ($validated['branches'] ?? [])),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                    'updated_by' => $actorId,
                ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'اسم المادة الافتراضية مستخدم بالفعل.',
                'code' => 'كود المادة الافتراضية مستخدم بالفعل.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'school_default_subject_template.updated',
            'school_default_subject_template',
            (int) $schoolDefaultSubjectTemplate->id,
            [
                'before' => $before,
                'after' => $schoolDefaultSubjectTemplate->only(['name', 'code', 'branches', 'is_active']),
            ],
            $request,
            $actorId
        );

        return $this->respondSuccess(
            $request,
            'تم تحديث قالب المادة الافتراضية بنجاح.',
            $schoolDefaultSubjectTemplate->fresh()->toArray()
        );
    }

    public function destroySubject(
        Request $request,
        SchoolDefaultSubjectTemplate $schoolDefaultSubjectTemplate
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;
        $snapshot = $schoolDefaultSubjectTemplate->toArray();

        $schoolDefaultSubjectTemplate->delete();

        $this->auditLogger->log(
            'school_default_subject_template.deleted',
            'school_default_subject_template',
            (int) $schoolDefaultSubjectTemplate->id,
            ['before' => $snapshot],
            $request,
            $actorId
        );

        return $this->respondSuccess($request, 'تم حذف قالب المادة الافتراضية بنجاح.');
    }

    private function indexPayload(Request $request, SchoolDefaultTemplateScopeRegistry $scopeRegistry): array
    {
        $filters = $request->validate([
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'education_type_id' => ['nullable', 'integer', Rule::exists('education_types', 'id')],
        ], [], [
            'country_id' => 'الدولة',
            'education_type_id' => 'نوع التعليم',
        ]);

        $countryId = $this->normalizeNullableInt($filters['country_id'] ?? null);
        $educationTypeId = $this->normalizeNullableInt($filters['education_type_id'] ?? null);

        $stageTemplates = $this->scopeScopedTemplateQuery(
            SchoolDefaultStageTemplate::query()
                ->with([
                    'educationStage:id,name,sort_order,is_active',
                    'stageTerms' => fn ($query) => $query
                        ->orderBy('sort_order')
                        ->orderBy('name'),
                    'grades' => fn ($query) => $query
                        ->with([
                            'gradeTerms' => fn ($gradeTerms) => $gradeTerms
                                ->orderBy('sort_order')
                                ->orderBy('name'),
                        ])
                        ->orderBy('sort_order')
                        ->orderBy('name'),
                    'classrooms' => fn ($query) => $query
                        ->with(['grade'])
                        ->orderBy('sort_order')
                        ->orderBy('name'),
                ])
                ->orderBy('sort_order')
                ->orderBy('name'),
            $countryId,
            $educationTypeId
        )->get();

        $academicYearTemplates = $this->scopeScopedTemplateQuery(
            SchoolDefaultAcademicYearTemplate::query()
                ->orderByDesc('starts_on')
                ->orderBy('name'),
            $countryId,
            $educationTypeId
        )->get();

        $holidayTemplates = $this->scopeScopedTemplateQuery(
            SchoolDefaultHolidayTemplate::query()
                ->orderByDesc('is_active')
                ->orderBy('start_date')
                ->orderBy('name'),
            $countryId,
            $educationTypeId
        )->get();

        $leaveTypeTemplates = $this->scopeScopedTemplateQuery(
            SchoolDefaultLeaveTypeTemplate::query()
                ->orderByDesc('is_active')
                ->orderBy('name'),
            $countryId,
            $educationTypeId
        )->get();

        $subjectTemplates = $this->scopeScopedTemplateQuery(
            SchoolDefaultSubjectTemplate::query()
                ->orderByDesc('is_active')
                ->orderBy('name'),
            $countryId,
            $educationTypeId
        )->get();

        $countries = Country::query()->orderBy('name')->get(['id', 'name']);
        $educationStages = EducationStage::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'sort_order', 'is_active']);
        $educationTypes = EducationType::query()->orderBy('name')->get(['id', 'name']);

        return [
            'summary' => [
                'schools_imported_count' => School::query()->whereNotNull('default_data_imported_at')->count(),
                'schools_pending_count' => School::query()->whereNull('default_data_imported_at')->count(),
                'template_counts' => [
                    'stages' => $stageTemplates->count(),
                    'stage_terms' => $stageTemplates->sum(fn (SchoolDefaultStageTemplate $stage) => $stage->stageTerms->count()),
                    'stage_grades' => $stageTemplates->sum(fn (SchoolDefaultStageTemplate $stage) => $stage->grades->count()),
                    'grade_terms' => $stageTemplates->sum(fn (SchoolDefaultStageTemplate $stage) => $stage->grades->sum(fn (SchoolDefaultStageGradeTemplate $grade) => $grade->gradeTerms->count())),
                    'classrooms' => $stageTemplates->sum(fn (SchoolDefaultStageTemplate $stage) => $stage->classrooms->count()),
                    'academic_years' => $academicYearTemplates->count(),
                    'holidays' => $holidayTemplates->count(),
                    'leave_types' => $leaveTypeTemplates->count(),
                    'subjects' => $subjectTemplates->count(),
                ],
                'active_template_counts' => [
                    'stages' => $stageTemplates->where('is_active', true)->count(),
                    'stage_terms' => $stageTemplates->sum(fn (SchoolDefaultStageTemplate $stage) => $stage->stageTerms->where('is_active', true)->count()),
                    'stage_grades' => $stageTemplates->sum(fn (SchoolDefaultStageTemplate $stage) => $stage->grades->where('is_active', true)->count()),
                    'grade_terms' => $stageTemplates->sum(fn (SchoolDefaultStageTemplate $stage) => $stage->grades->sum(fn (SchoolDefaultStageGradeTemplate $grade) => $grade->gradeTerms->where('is_active', true)->count())),
                    'classrooms' => $stageTemplates->sum(fn (SchoolDefaultStageTemplate $stage) => $stage->classrooms->where('is_active', true)->count()),
                    'academic_years' => $academicYearTemplates->where('is_active', true)->count(),
                    'holidays' => $holidayTemplates->where('is_active', true)->count(),
                    'leave_types' => $leaveTypeTemplates->where('is_active', true)->count(),
                    'subjects' => $subjectTemplates->where('is_active', true)->count(),
                ],
            ],
            'filters' => [
                'country_id' => $countryId,
                'education_type_id' => $educationTypeId,
            ],
            'countries' => $countries,
            'educationStages' => $educationStages,
            'educationTypes' => $educationTypes,
            'templateScopes' => $this->resolveTemplateScopes($scopeRegistry, $countries, $educationTypes),
            'scopeConfig' => $scopeRegistry->find($countryId, $educationTypeId),
            'stageTemplates' => $stageTemplates,
            'academicYearTemplates' => $academicYearTemplates,
            'holidayTemplates' => $holidayTemplates,
            'leaveTypeTemplates' => $leaveTypeTemplates,
            'subjectTemplates' => $subjectTemplates,
        ];
    }

    private function resolveTemplateScopes(
        SchoolDefaultTemplateScopeRegistry $scopeRegistry,
        $countries,
        $educationTypes
    ): array {
        $countryMap = $countries->keyBy('id');
        $educationTypeMap = $educationTypes->keyBy('id');

        $registryEntries = $scopeRegistry->all()
            ->keyBy(fn (array $entry) => sprintf('%d:%d', (int) $entry['country_id'], (int) $entry['education_type_id']));

        $templateScopeKeys = collect([
            SchoolDefaultStageTemplate::query()->select(['country_id', 'education_type_id'])->get(),
            SchoolDefaultAcademicYearTemplate::query()->select(['country_id', 'education_type_id'])->get(),
            SchoolDefaultHolidayTemplate::query()->select(['country_id', 'education_type_id'])->get(),
            SchoolDefaultLeaveTypeTemplate::query()->select(['country_id', 'education_type_id'])->get(),
            SchoolDefaultSubjectTemplate::query()->select(['country_id', 'education_type_id'])->get(),
        ])->flatten(1)
            ->filter(fn ($row) => $row->country_id !== null && $row->education_type_id !== null)
            ->map(fn ($row) => sprintf('%d:%d', (int) $row->country_id, (int) $row->education_type_id))
            ->merge($registryEntries->keys())
            ->unique()
            ->sort()
            ->values();

        return $templateScopeKeys->map(function (string $scopeKey) use ($countryMap, $educationTypeMap, $registryEntries): array {
            [$countryId, $educationTypeId] = array_map('intval', explode(':', $scopeKey));
            $registryEntry = $registryEntries->get($scopeKey);
            $countryName = (string) ($countryMap->get($countryId)?->name ?? '');
            $educationTypeName = (string) ($educationTypeMap->get($educationTypeId)?->name ?? '');

            return [
                'key' => sprintf('country:%d:education-type:%d', $countryId, $educationTypeId),
                'template_name' => $registryEntry['template_name'] ?? sprintf(
                    'قالب %s - %s',
                    $countryName !== '' ? $countryName : 'غير محدد',
                    $educationTypeName !== '' ? $educationTypeName : 'غير محدد'
                ),
                'country_id' => $countryId,
                'directorate_id' => null,
                'education_type_id' => $educationTypeId,
                'country_name' => $countryName,
                'directorate_name' => '',
                'governorate' => '',
                'education_type_name' => $educationTypeName,
                'reference_snapshot' => $registryEntry['reference_snapshot'] ?? [],
                'updated_at' => $registryEntry['updated_at'] ?? null,
            ];
        })->all();
    }

    /**
     * @return array{directorate_id: int|null, country_id: int|null, education_type_id: int|null, directorate: EducationalDirectorate|null}
     */
    private function resolveScopeContext(
        Request $request,
        ?int $fallbackDirectorateId = null,
        ?int $fallbackCountryId = null,
        ?int $fallbackEducationTypeId = null
    ): array {
        $validated = $request->validate([
            'directorate_id' => ['nullable', 'integer', Rule::exists('educational_directorates', 'id')],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'education_type_id' => ['nullable', 'integer', Rule::exists('education_types', 'id')],
        ], [], [
            'directorate_id' => 'النطاق التعليمي',
            'country_id' => 'الدولة',
            'education_type_id' => 'نوع التعليم',
        ]);

        $directorateId = $this->normalizeNullableInt($validated['directorate_id'] ?? null);
        $countryId = $this->normalizeNullableInt($validated['country_id'] ?? $fallbackCountryId);
        $educationTypeId = $this->normalizeNullableInt($validated['education_type_id'] ?? $fallbackEducationTypeId);

        if ($directorateId !== null) {
            $directorate = EducationalDirectorate::query()
                ->whereKey($directorateId)
                ->first(['id', 'country_id', 'education_type_id']);

            if (!$directorate instanceof EducationalDirectorate) {
                throw ValidationException::withMessages([
                    'directorate_id' => 'تعذر التحقق من النطاق التعليمي المحدد.',
                ]);
            }

            if (!$directorate->country_id || !$directorate->education_type_id) {
                throw ValidationException::withMessages([
                    'directorate_id' => 'النطاق التعليمي المحدد لا يحمل نوع تعليم صالحًا للقالب.',
                ]);
            }

            return [
                'directorate_id' => (int) $directorate->id,
                'country_id' => (int) $directorate->country_id,
                'education_type_id' => (int) $directorate->education_type_id,
                'directorate' => $directorate,
            ];
        }

        if (($countryId === null) xor ($educationTypeId === null)) {
            throw ValidationException::withMessages([
                'country_id' => 'يجب تحديد الدولة ونوع التعليم معًا.',
            ]);
        }

        if ($countryId !== null && $educationTypeId !== null) {
            return [
                'directorate_id' => null,
                'country_id' => $countryId,
                'education_type_id' => $educationTypeId,
                'directorate' => null,
            ];
        }

        $directorateId = $fallbackDirectorateId !== null
            ? $this->normalizeNullableInt($fallbackDirectorateId)
            : null;

        if ($directorateId !== null) {
            $directorate = EducationalDirectorate::query()
                ->whereKey($directorateId)
                ->first(['id', 'country_id', 'education_type_id']);

            if ($directorate instanceof EducationalDirectorate && $directorate->country_id && $directorate->education_type_id) {
                return [
                    'directorate_id' => (int) $directorate->id,
                    'country_id' => (int) $directorate->country_id,
                    'education_type_id' => (int) $directorate->education_type_id,
                    'directorate' => $directorate,
                ];
            }
        }

        return [
            'directorate_id' => null,
            'country_id' => $countryId,
            'education_type_id' => $educationTypeId,
            'directorate' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function safeResolveCountryReference(
        CountryReferenceDataService $countryReferenceDataService,
        Country $country
    ): array {
        try {
            return $countryReferenceDataService->fetchForCountry($country);
        } catch (\Throwable $throwable) {
            report($throwable);

            return [
                'status' => 'error',
                'year' => (int) now()->year,
                'country' => [
                    'id' => (int) $country->id,
                    'name' => (string) $country->name,
                    'code' => null,
                ],
                'requested_data' => [
                    'public_holidays',
                    'academic_year_start',
                    'school_breaks',
                    'seasonal_breaks',
                    'leave_types',
                    'islamic_holidays',
                ],
                'supported_data' => [],
                'unavailable_data' => [
                    'public_holidays',
                    'academic_year_start',
                    'school_breaks',
                    'seasonal_breaks',
                    'leave_types',
                    'islamic_holidays',
                ],
                'available_counts' => [
                    'public_holidays' => 0,
                    'islamic_holidays' => 0,
                    'academic_year_start' => 0,
                ],
                'holidays' => [],
                'source' => [
                    'key' => 'nager_date',
                    'label' => 'Nager.Date Public Holiday API',
                ],
                'fetched_at' => null,
                'message' => 'تعذر جلب مرجعيات الدولة الآن، ويمكنك حفظ القالب وإكمال ضبطه يدويًا.',
            ];
        }
    }

    /**
     * @return array{status: string, template: array<string, mixed>}
     */
    private function ensureCurrentAcademicYearTemplate(
        int $countryId,
        int $educationTypeId,
        int $actorId,
        Request $request,
        array $countryReference = []
    ): array {
        $definition = $this->resolveCurrentAcademicYearDefinition($countryReference);
        $normalizedName = mb_strtolower($definition['name']);

        $existingTemplate = $this->scopeScopedTemplateQuery(
            SchoolDefaultAcademicYearTemplate::query(),
            $countryId,
            $educationTypeId,
            null
        )
            ->where(function ($query) use ($definition, $normalizedName) {
                $query
                    ->where(function ($dateQuery) use ($definition) {
                        $dateQuery
                            ->whereDate('starts_on', $definition['starts_on'])
                            ->whereDate('ends_on', $definition['ends_on']);
                    })
                    ->orWhereRaw('LOWER(name) = ?', [$normalizedName]);
            })
            ->orderByDesc('starts_on')
            ->first();

        if ($existingTemplate instanceof SchoolDefaultAcademicYearTemplate) {
            return [
                'status' => 'existing',
                'template' => $existingTemplate->toArray(),
            ];
        }

        $template = SchoolDefaultAcademicYearTemplate::query()->create([
            'country_id' => $countryId,
            'education_type_id' => $educationTypeId,
            'directorate_id' => null,
            'name' => $definition['name'],
            'starts_on' => $definition['starts_on'],
            'ends_on' => $definition['ends_on'],
            'is_active' => true,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        $this->auditLogger->log(
            'school_default_academic_year_template.auto_created',
            'school_default_academic_year_template',
            (int) $template->id,
            ['payload' => $template->only(['name', 'starts_on', 'ends_on', 'is_active'])],
            $request,
            $actorId
        );

        return [
            'status' => 'created',
            'template' => $template->fresh()->toArray(),
        ];
    }

    /**
     * @return array{name: string, starts_on: string, ends_on: string}
     */
    private function resolveCurrentAcademicYearDefinition(array $countryReference = []): array
    {
        $referenceAcademicYear = is_array($countryReference['academic_year'] ?? null)
            ? $countryReference['academic_year']
            : null;

        $referenceName = trim((string) ($referenceAcademicYear['name'] ?? ''));
        $referenceStartsOn = trim((string) ($referenceAcademicYear['starts_on'] ?? ''));
        $referenceEndsOn = trim((string) ($referenceAcademicYear['ends_on'] ?? ''));

        if ($referenceName !== '' && $referenceStartsOn !== '' && $referenceEndsOn !== '') {
            return [
                'name' => $referenceName,
                'starts_on' => Carbon::parse($referenceStartsOn)->toDateString(),
                'ends_on' => Carbon::parse($referenceEndsOn)->toDateString(),
            ];
        }

        $today = Carbon::now()->startOfDay();
        $academicYearAnchor = Carbon::create($today->year, 8, 15, 0, 0, 0, $today->getTimezone());
        $startYear = $today->lt($academicYearAnchor)
            ? $today->year - 1
            : $today->year;
        $endYear = $startYear + 1;

        return [
            'name' => sprintf('العام الدراسي %d-%d', $startYear, $endYear),
            'starts_on' => Carbon::create($startYear, 8, 15, 0, 0, 0, $today->getTimezone())->toDateString(),
            'ends_on' => Carbon::create($endYear, 6, 30, 0, 0, 0, $today->getTimezone())->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $countryReference
     * @return array{status: string, created: int, updated: int, skipped: int}
     */
    private function syncCountryReferenceHolidayTemplates(
        int $countryId,
        int $educationTypeId,
        int $actorId,
        array $countryReference
    ): array {
        if (($countryReference['status'] ?? null) !== 'success') {
            return [
                'status' => 'not_synced',
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
            ];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($countryReference['holidays'] ?? [] as $holiday) {
            $name = trim((string) ($holiday['name'] ?? ''));
            $date = trim((string) ($holiday['date'] ?? '')) ?: null;
            $referenceKey = trim((string) ($holiday['reference_key'] ?? '')) ?: null;
            $holidayCategory = trim((string) ($holiday['holiday_category'] ?? '')) ?: null;

            if ($name === '' || ($date === null && $referenceKey === null)) {
                continue;
            }

            $existingTemplateQuery = SchoolDefaultHolidayTemplate::query()
                ->where('country_id', $countryId)
                ->where('education_type_id', $educationTypeId)
                ->orderByRaw('CASE WHEN directorate_id IS NULL THEN 0 ELSE 1 END');

            if ($referenceKey !== null) {
                $existingTemplateQuery->where('reference_key', $referenceKey);
            } else {
                $existingTemplateQuery
                    ->whereDate('start_date', $date)
                    ->whereDate('end_date', $date)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
            }

            $existingTemplate = $existingTemplateQuery->first();

            $updates = [
                'country_id' => $countryId,
                'education_type_id' => $educationTypeId,
                'directorate_id' => null,
                'name' => $name,
                'reference_key' => $referenceKey,
                'holiday_category' => $holidayCategory,
                'start_date' => $date,
                'end_date' => $date,
                'return_date' => null,
                'notes' => $this->emptyToNull($holiday['notes'] ?? null),
                'is_active' => true,
                'updated_by' => $actorId,
            ];

            if ($existingTemplate instanceof SchoolDefaultHolidayTemplate) {
                $hasChanges = false;
                foreach ($updates as $field => $value) {
                    $currentValue = $existingTemplate->{$field};

                    if ($currentValue instanceof Carbon) {
                        $currentValue = $currentValue->toDateString();
                    }

                    if ($currentValue !== $value) {
                        $hasChanges = true;
                        break;
                    }
                }

                if ($hasChanges) {
                    $existingTemplate->fill($updates)->save();
                    $updated++;
                } else {
                    $skipped++;
                }

                continue;
            }

            SchoolDefaultHolidayTemplate::query()->create([
                'country_id' => $countryId,
                'education_type_id' => $educationTypeId,
                'directorate_id' => null,
                'name' => $name,
                'reference_key' => $referenceKey,
                'holiday_category' => $holidayCategory,
                'start_date' => $date,
                'end_date' => $date,
                'return_date' => null,
                'notes' => $this->emptyToNull($holiday['notes'] ?? null),
                'is_active' => true,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $created++;
        }

        return [
            'status' => 'synced',
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array{
     *     stages: int,
     *     stage_terms: int,
     *     stage_grades: int,
     *     grade_terms: int,
     *     classrooms: int,
     *     academic_years: int,
     *     holidays: int,
     *     leave_types: int,
     *     subjects: int
     * }
     */
    private function countScopedTemplateRecords(int $countryId, int $educationTypeId): array
    {
        $stageTemplateIds = SchoolDefaultStageTemplate::query()
            ->where('country_id', $countryId)
            ->where('education_type_id', $educationTypeId)
            ->pluck('id');

        return [
            'stages' => $stageTemplateIds->count(),
            'stage_terms' => $stageTemplateIds->isEmpty()
                ? 0
                : SchoolDefaultStageTermTemplate::query()
                    ->whereIn('school_default_stage_template_id', $stageTemplateIds)
                    ->count(),
            'stage_grades' => $stageTemplateIds->isEmpty()
                ? 0
                : SchoolDefaultStageGradeTemplate::query()
                    ->whereIn('school_default_stage_template_id', $stageTemplateIds)
                    ->count(),
            'grade_terms' => $stageTemplateIds->isEmpty()
                ? 0
                : SchoolDefaultStageGradeTermTemplate::query()
                    ->whereHas('grade', fn ($query) => $query->whereIn('school_default_stage_template_id', $stageTemplateIds))
                    ->count(),
            'classrooms' => $stageTemplateIds->isEmpty()
                ? 0
                : SchoolDefaultClassroomTemplate::query()
                    ->whereIn('school_default_stage_template_id', $stageTemplateIds)
                    ->count(),
            'academic_years' => SchoolDefaultAcademicYearTemplate::query()
                ->where('country_id', $countryId)
                ->where('education_type_id', $educationTypeId)
                ->count(),
            'holidays' => SchoolDefaultHolidayTemplate::query()
                ->where('country_id', $countryId)
                ->where('education_type_id', $educationTypeId)
                ->count(),
            'leave_types' => SchoolDefaultLeaveTypeTemplate::query()
                ->where('country_id', $countryId)
                ->where('education_type_id', $educationTypeId)
                ->count(),
            'subjects' => SchoolDefaultSubjectTemplate::query()
                ->where('country_id', $countryId)
                ->where('education_type_id', $educationTypeId)
                ->count(),
        ];
    }

    private function deleteScopedTemplateRecords(int $countryId, int $educationTypeId): void
    {
        SchoolDefaultStageTemplate::query()
            ->where('country_id', $countryId)
            ->where('education_type_id', $educationTypeId)
            ->delete();

        SchoolDefaultAcademicYearTemplate::query()
            ->where('country_id', $countryId)
            ->where('education_type_id', $educationTypeId)
            ->delete();

        SchoolDefaultHolidayTemplate::query()
            ->where('country_id', $countryId)
            ->where('education_type_id', $educationTypeId)
            ->delete();

        SchoolDefaultLeaveTypeTemplate::query()
            ->where('country_id', $countryId)
            ->where('education_type_id', $educationTypeId)
            ->delete();

        SchoolDefaultSubjectTemplate::query()
            ->where('country_id', $countryId)
            ->where('education_type_id', $educationTypeId)
            ->delete();
    }

    private function validateClassroomTemplate(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'school_default_stage_template_id' => ['required', Rule::exists('school_default_stage_templates', 'id')],
            'school_default_stage_grade_template_id' => ['required', Rule::exists('school_default_stage_grade_templates', 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('school_default_classroom_templates', 'name')
                    ->where(fn ($query) => $query
                        ->where('school_default_stage_template_id', (int) $request->input('school_default_stage_template_id'))
                        ->where('school_default_stage_grade_template_id', (int) $request->input('school_default_stage_grade_template_id')))
                    ->ignore($ignoreId),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('school_default_classroom_templates', 'code')->ignore($ignoreId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'اسم الفصل الافتراضي مستخدم بالفعل داخل هذا الصف.',
            'code.unique' => 'كود الفصل الافتراضي مستخدم بالفعل.',
        ]);
    }

    private function validateStageGradeTermTemplate(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'school_default_stage_template_id' => ['required', Rule::exists('school_default_stage_templates', 'id')],
            'school_default_stage_grade_template_id' => ['required', Rule::exists('school_default_stage_grade_templates', 'id')],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('school_default_stage_grade_term_templates', 'name')
                    ->where(fn ($query) => $query
                        ->where('school_default_stage_grade_template_id', (int) $request->input('school_default_stage_grade_template_id')))
                    ->ignore($ignoreId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'اسم الترم الدراسي الافتراضي مستخدم بالفعل داخل هذا الصف.',
        ]);
    }

    private function validateStageTermTemplate(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'school_default_stage_template_id' => ['required', Rule::exists('school_default_stage_templates', 'id')],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('school_default_stage_term_templates', 'name')
                    ->where(fn ($query) => $query
                        ->where('school_default_stage_template_id', (int) $request->input('school_default_stage_template_id')))
                    ->ignore($ignoreId),
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'اسم الفصل الدراسي الافتراضي مستخدم بالفعل داخل هذه المرحلة.',
        ]);

        $this->assertDateRangeOrder(
            startDate: $validated['start_date'] ?? null,
            endDate: $validated['end_date'] ?? null,
            startField: 'start_date',
            message: 'تاريخ نهاية الفصل الدراسي يجب أن يكون بعد تاريخ البداية أو مساويًا له.'
        );

        return $validated;
    }

    private function validateHolidayTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'return_date' => ['nullable', 'date', 'after_or_equal:end_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function assertStageGradeMatchesStage(int $stageTemplateId, int $stageGradeTemplateId): void
    {
        $matches = SchoolDefaultStageGradeTemplate::query()
            ->whereKey($stageGradeTemplateId)
            ->where('school_default_stage_template_id', $stageTemplateId)
            ->exists();

        if (!$matches) {
            throw ValidationException::withMessages([
                'school_default_stage_grade_template_id' => 'الصف المحدد لا يتبع المرحلة الافتراضية المختارة.',
            ]);
        }
    }

    private function assertHolidayTemplatePeriodAvailable(
        string $startDate,
        string $endDate,
        ?int $ignoreId,
        bool $isActive,
        ?int $countryId,
        ?int $educationTypeId,
        ?int $directorateId = null
    ): void {
        if (!$isActive) {
            return;
        }

        $query = $this->scopeScopedTemplateQuery(
            SchoolDefaultHolidayTemplate::query()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate),
            $countryId,
            $educationTypeId,
            $directorateId
        );

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'فترة العطلة الافتراضية تتداخل مع عطلة عامة أخرى مفعلة على مستوى المنصة.',
            ]);
        }
    }

    private function serializeStageTemplate(SchoolDefaultStageTemplate $stageTemplate): array
    {
        return $stageTemplate
            ->fresh([
                'educationStage:id,name,sort_order,is_active',
                'stageTerms' => fn ($query) => $query
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'grades' => fn ($query) => $query
                    ->with([
                        'gradeTerms' => fn ($gradeTerms) => $gradeTerms
                            ->orderBy('sort_order')
                            ->orderBy('name'),
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'classrooms' => fn ($query) => $query->with(['grade'])->orderBy('sort_order')->orderBy('name'),
            ])
            ->toArray();
    }

    private function serializeStageTermTemplate(SchoolDefaultStageTermTemplate $stageTermTemplate): array
    {
        return $stageTermTemplate
            ->fresh(['stage'])
            ->toArray();
    }

    private function serializeStageGradeTermTemplate(SchoolDefaultStageGradeTermTemplate $stageGradeTermTemplate): array
    {
        return $stageGradeTermTemplate
            ->fresh(['grade.stage'])
            ->toArray();
    }

    private function serializeClassroomTemplate(SchoolDefaultClassroomTemplate $classroomTemplate): array
    {
        return $classroomTemplate
            ->fresh(['stage', 'grade'])
            ->toArray();
    }

    private function resolveEducationStage(mixed $educationStageId): ?EducationStage
    {
        $normalizedId = $this->normalizeNullableInt($educationStageId);

        if ($normalizedId === null) {
            return null;
        }

        return EducationStage::query()->find($normalizedId, ['id', 'name', 'sort_order', 'is_active']);
    }

    private function respondSuccess(
        Request $request,
        string $message,
        mixed $data = null,
        int $status = 200
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => $data,
            ], $status);
        }

        return redirect()
            ->route('admin.school_defaults.index', array_filter([
                'country_id' => $request->input('country_id'),
                'education_type_id' => $request->input('education_type_id'),
                'embedded' => $request->boolean('embedded') ? 1 : null,
                'editor' => $request->boolean('editor') ? 1 : null,
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', $message);
    }

    private function scopeScopedTemplateQuery(
        mixed $query,
        ?int $countryId,
        ?int $educationTypeId,
        ?int $directorateId = null,
        ?string $modelClass = null
    ): mixed {
        $resolvedModelClass = $modelClass;

        if ($query instanceof \Illuminate\Database\Eloquent\Builder) {
            $resolvedModelClass ??= $query->getModel()::class;
        }

        if (!is_string($resolvedModelClass) || $resolvedModelClass === '') {
            throw new \InvalidArgumentException('تعذر تحديد موديل القالب المطلوب لتطبيق نطاق التصفية.');
        }

        if ($countryId !== null && $educationTypeId !== null) {
            $countryTypeQuery = $resolvedModelClass::query()
                ->where('country_id', $countryId)
                ->where('education_type_id', $educationTypeId);

            $countryTypeWithoutDirectorateQuery = (clone $countryTypeQuery)
                ->whereNull('directorate_id');

            if ($countryTypeWithoutDirectorateQuery->exists()) {
                return $query
                    ->where('country_id', $countryId)
                    ->where('education_type_id', $educationTypeId)
                    ->whereNull('directorate_id');
            }

            if ($countryTypeQuery->exists()) {
                return $query
                    ->where('country_id', $countryId)
                    ->where('education_type_id', $educationTypeId);
            }
        }

        return $query
            ->whereNull('country_id')
            ->whereNull('education_type_id');
    }

    private function scopedTemplateNameUnique(
        string $table,
        ?int $countryId,
        ?int $educationTypeId,
        ?int $directorateId = null,
        ?int $ignoreId = null
    ): Unique {
        $modelClass = $this->resolveScopedTemplateModelClass($table);

        return Rule::unique($table, 'name')
            ->where(fn ($query) => $this->scopeScopedTemplateQuery($query, $countryId, $educationTypeId, $directorateId, $modelClass))
            ->ignore($ignoreId);
    }

    private function resolveScopedTemplateModelClass(string $table): string
    {
        return match ($table) {
            'school_default_stage_templates' => SchoolDefaultStageTemplate::class,
            'school_default_academic_year_templates' => SchoolDefaultAcademicYearTemplate::class,
            'school_default_holiday_templates' => SchoolDefaultHolidayTemplate::class,
            'school_default_leave_type_templates' => SchoolDefaultLeaveTypeTemplate::class,
            'school_default_subject_templates' => SchoolDefaultSubjectTemplate::class,
            default => throw new \InvalidArgumentException("Unsupported scoped template table [{$table}]."),
        };
    }

    private function resolveTemplateCode(
        string $table,
        string $column,
        ?string $preferredCode,
        ?string $existingCode,
        string $prefix,
        ?int $ignoreId = null
    ): ?string {
        if ($preferredCode !== null) {
            $this->assertTemplateCodeAvailable($table, $column, $preferredCode, $ignoreId);

            return $preferredCode;
        }

        if ($existingCode !== null && $existingCode !== '') {
            $this->assertTemplateCodeAvailable($table, $column, $existingCode, $ignoreId);

            return $existingCode;
        }

        return $this->generateTemplateCode($table, $column, $prefix, $ignoreId);
    }

    private function assertTemplateCodeAvailable(
        string $table,
        string $column,
        ?string $code,
        ?int $ignoreId = null
    ): void {
        if ($code === null || $code === '') {
            return;
        }

        $query = DB::table($table)
            ->where($column, $code)
            ->lockForUpdate();

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'code' => 'هذا الكود مستخدم بالفعل.',
            ]);
        }
    }

    private function generateTemplateCode(
        string $table,
        string $column,
        string $prefix,
        ?int $ignoreId = null,
        int $padLength = 3
    ): string {
        $codes = DB::table($table)
            ->whereNotNull($column)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->lockForUpdate()
            ->pluck($column)
            ->map(fn ($value): string => (string) $value);

        $pattern = '/^' . preg_quote($prefix, '/') . '-(\d+)$/';
        $max = 0;

        foreach ($codes as $code) {
            if (preg_match($pattern, $code, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $next = $max + 1;
        do {
            $candidate = sprintf('%s-%0' . $padLength . 'd', $prefix, $next);
            $exists = DB::table($table)
                ->where($column, $candidate)
                ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists();
            $next++;
        } while ($exists);

        return $candidate;
    }

    private function normalizeCodeInput(mixed $value): ?string
    {
        $normalized = strtoupper(trim((string) ($value ?? '')));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeTimeInput(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $normalized) === 1) {
            return $normalized . ':00';
        }

        return $normalized;
    }

    private function normalizeDateInput(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param array<int, mixed> $branches
     * @return array<int, string>
     */
    private function normalizeSubjectBranches(array $branches): array
    {
        return collect($branches)
            ->map(fn ($branch): string => trim((string) $branch))
            ->filter()
            ->unique(fn ($branch) => mb_strtolower($branch))
            ->values()
            ->all();
    }

    private function emptyToNull(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function assertDateRangeOrder(?string $startDate, ?string $endDate, string $startField, string $message): void
    {
        if ($startDate === null || $endDate === null) {
            return;
        }

        if (Carbon::parse($endDate)->lt(Carbon::parse($startDate))) {
            throw ValidationException::withMessages([
                $startField => $message,
            ]);
        }
    }

    /**
     * @param array<string, string> $fieldMessages
     */
    private function rethrowDuplicateValidation(QueryException $exception, array $fieldMessages): void
    {
        if (!$this->isUniqueConstraintException($exception)) {
            return;
        }

        $error = strtolower($exception->getMessage());
        $messages = [];

        foreach ($fieldMessages as $field => $message) {
            if (str_contains($error, strtolower($field))) {
                $messages[$field] = $message;
            }
        }

        if ($messages === []) {
            $field = array_key_first($fieldMessages);
            if ($field !== null) {
                $messages[$field] = $fieldMessages[$field];
            }
        }

        throw ValidationException::withMessages($messages);
    }

    private function isUniqueConstraintException(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['1062', '19', '2067'], true);
    }
}
