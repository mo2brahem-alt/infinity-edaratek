<?php

namespace App\Services\School;

use App\Models\Country;
use App\Models\EducationStage;
use App\Models\EducationType;
use App\Models\School;
use App\Models\SchoolAcademicYear;
use App\Models\SchoolClassroom;
use App\Models\SchoolDefaultAcademicYearTemplate;
use App\Models\SchoolDefaultClassroomTemplate;
use App\Models\SchoolDefaultHolidayTemplate;
use App\Models\SchoolDefaultLeaveTypeTemplate;
use App\Models\SchoolDefaultStageGradeTemplate;
use App\Models\SchoolDefaultStageGradeTermTemplate;
use App\Models\SchoolDefaultStageTemplate;
use App\Models\SchoolDefaultStageTermTemplate;
use App\Models\SchoolDefaultSubjectTemplate;
use App\Models\SchoolHoliday;
use App\Models\SchoolLeaveType;
use App\Models\SchoolStage;
use App\Models\SchoolStageGrade;
use App\Models\SchoolStageGradeTerm;
use App\Models\SchoolStageTerm;
use App\Models\SchoolSubject;
use App\Models\SchoolTerm;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SchoolDefaultDataProvisioningService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SchoolDefaultTemplateScopeRegistry $scopeRegistry,
    ) {
    }

    /**
     * @return array{counts: array<string, int>, has_any_templates: bool}
     */
    public function templateAvailability(?School $school = null): array
    {
        [$countryId, $educationTypeId, $directorateId] = $this->resolveTemplateScope($school);
        $counts = $this->buildTemplateCounts($countryId, $educationTypeId, $directorateId);

        return [
            'counts' => $counts,
            'has_any_templates' => array_sum($counts) > 0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function templateOptions(?int $countryId, ?int $educationTypeId): array
    {
        if ($countryId === null || $educationTypeId === null) {
            return [];
        }

        $counts = $this->buildTemplateCounts($countryId, $educationTypeId, null);

        if (array_sum($counts) === 0) {
            return [];
        }

        $countryName = (string) (Country::query()->whereKey($countryId)->value('name') ?? '');
        $educationTypeName = (string) (EducationType::query()->whereKey($educationTypeId)->value('name') ?? '');
        $registryEntry = $this->scopeRegistry->find($countryId, $educationTypeId);

        return [[
            'key' => sprintf('country:%d:education-type:%d', $countryId, $educationTypeId),
            'template_name' => $registryEntry['template_name'] ?? sprintf(
                'قالب %s - %s',
                $countryName !== '' ? $countryName : 'غير محددة',
                $educationTypeName !== '' ? $educationTypeName : 'غير محدد'
            ),
            'country_id' => $countryId,
            'education_type_id' => $educationTypeId,
            'country_name' => $countryName,
            'education_type_name' => $educationTypeName,
            'available_counts' => $counts,
            'total_items' => array_sum($counts),
            'updated_at' => $registryEntry['updated_at'] ?? null,
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    public function schoolProvisioningStatus(School $school, bool $canImport): array
    {
        $availability = $this->templateAvailability($school);
        $isImported = $school->default_data_imported_at !== null;

        return [
            'is_imported' => $isImported,
            'imported_at' => optional($school->default_data_imported_at)->toISOString(),
            'imported_by' => $school->relationLoaded('defaultDataImporter') && $school->defaultDataImporter
                ? [
                    'id' => (int) $school->defaultDataImporter->id,
                    'name' => (string) $school->defaultDataImporter->name,
                ]
                : null,
            'can_import' => $canImport && (bool) $availability['has_any_templates'],
            'has_any_templates' => (bool) $availability['has_any_templates'],
            'available_counts' => $availability['counts'],
        ];
    }

    /**
     * @return array{counts: array<string, int>, imported_at: string|null, was_previously_imported: bool}
     */
    public function importForSchool(int $schoolId, int $actorId, ?Request $request = null, array $options = []): array
    {
        return DB::transaction(function () use ($schoolId, $actorId, $request, $options): array {
            $school = School::query()
                ->with(['directorate', 'educationStages:id,name,sort_order,is_active'])
                ->whereKey($schoolId)
                ->lockForUpdate()
                ->firstOrFail();
            $wasPreviouslyImported = $school->default_data_imported_at !== null;
            $selectedEducationStages = $this->resolveSelectedEducationStages(
                $school,
                $options['education_stage_ids'] ?? null
            );

            $availability = $this->templateAvailability($school);
            if (!($availability['has_any_templates'] ?? false)) {
                throw ValidationException::withMessages([
                    'default_data' => 'لا توجد قوالب افتراضية مناسبة لهذا النطاق التعليمي ليتم استيرادها إلى المدرسة.',
                ]);
            }

            [$countryId, $educationTypeId, $directorateId] = $this->resolveTemplateScope($school);
            $counts = [
                'stages' => 0,
                'stage_terms' => 0,
                'stage_grades' => 0,
                'grade_terms' => 0,
                'classrooms' => 0,
                'academic_years' => 0,
                'terms' => 0,
                'holidays' => 0,
                'leave_types' => 0,
                'subjects' => 0,
            ];

            $stageTemplates = $this->scopeStageTemplates(
                SchoolDefaultStageTemplate::query()
                    ->where('is_active', true)
                    ->with([
                        'stageTerms' => fn ($query) => $query
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->orderBy('name'),
                        'grades' => fn ($query) => $query
                            ->where('is_active', true)
                            ->with([
                                'gradeTerms' => fn ($gradeTerms) => $gradeTerms
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->orderBy('name'),
                            ])
                            ->orderBy('sort_order')
                            ->orderBy('name'),
                        'classrooms' => fn ($query) => $query
                            ->where('is_active', true)
                            ->with(['grade'])
                            ->orderBy('sort_order')
                            ->orderBy('name'),
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                $countryId,
                $educationTypeId,
                $directorateId
            )->get();

            $stageTemplates = $this->filterStageTemplatesByEducationStages($stageTemplates, $selectedEducationStages);

            $resolvedStages = [];
            foreach ($stageTemplates as $stageTemplate) {
                $existingStage = SchoolStage::query()
                    ->where('school_id', $schoolId)
                    ->whereRaw('LOWER(name) = ?', [$this->normalizeForLookup($stageTemplate->name)])
                    ->first();

                if ($existingStage instanceof SchoolStage) {
                    $resolvedStages[(int) $stageTemplate->id] = $existingStage;
                    continue;
                }

                $createdStage = SchoolStage::query()->create([
                    'school_id' => $schoolId,
                    'name' => (string) $stageTemplate->name,
                    'code' => $this->resolveScopedCode(
                        table: 'school_stages',
                        column: 'code',
                        schoolId: $schoolId,
                        preferredCode: $this->normalizeCode((string) ($stageTemplate->code ?? '')),
                        prefix: 'STG'
                    ),
                    'sort_order' => (int) $stageTemplate->sort_order,
                    'is_active' => (bool) $stageTemplate->is_active,
                    'school_day_start_time' => $this->normalizeTime($stageTemplate->school_day_start_time),
                    'school_day_end_time' => $this->normalizeTime($stageTemplate->school_day_end_time),
                ]);

                $resolvedStages[(int) $stageTemplate->id] = $createdStage;
                $counts['stages']++;
            }

            foreach ($stageTemplates as $stageTemplate) {
                $schoolStage = $resolvedStages[(int) $stageTemplate->id] ?? null;
                if (!$schoolStage instanceof SchoolStage) {
                    continue;
                }

                foreach ($stageTemplate->stageTerms as $stageTermTemplate) {
                    $existingStageTerm = SchoolStageTerm::query()
                        ->where('school_id', $schoolId)
                        ->where('school_stage_id', (int) $schoolStage->id)
                        ->whereRaw('LOWER(name) = ?', [$this->normalizeForLookup($stageTermTemplate->name)])
                        ->first();

                    if ($existingStageTerm instanceof SchoolStageTerm) {
                        $updates = [];

                        if ($existingStageTerm->start_date === null && $stageTermTemplate->start_date !== null) {
                            $updates['start_date'] = $stageTermTemplate->start_date?->toDateString();
                        }

                        if ($existingStageTerm->end_date === null && $stageTermTemplate->end_date !== null) {
                            $updates['end_date'] = $stageTermTemplate->end_date?->toDateString();
                        }

                        if ($updates !== []) {
                            $existingStageTerm->fill($updates);
                            $existingStageTerm->save();
                        }

                        continue;
                    }

                    SchoolStageTerm::query()->create([
                        'school_id' => $schoolId,
                        'school_stage_id' => (int) $schoolStage->id,
                        'name' => (string) $stageTermTemplate->name,
                        'start_date' => $stageTermTemplate->start_date?->toDateString(),
                        'end_date' => $stageTermTemplate->end_date?->toDateString(),
                        'source' => (string) ($stageTermTemplate->source ?: 'default'),
                        'sort_order' => (int) $stageTermTemplate->sort_order,
                        'is_active' => (bool) $stageTermTemplate->is_active,
                    ]);

                    $counts['stage_terms']++;
                }
            }

            $resolvedGrades = [];
            foreach ($stageTemplates as $stageTemplate) {
                $schoolStage = $resolvedStages[(int) $stageTemplate->id] ?? null;
                if (!$schoolStage instanceof SchoolStage) {
                    continue;
                }

                foreach ($stageTemplate->grades as $gradeTemplate) {
                    $existingGrade = SchoolStageGrade::query()
                        ->where('school_id', $schoolId)
                        ->where('school_stage_id', (int) $schoolStage->id)
                        ->whereRaw('LOWER(name) = ?', [$this->normalizeForLookup($gradeTemplate->name)])
                        ->first();

                    if ($existingGrade instanceof SchoolStageGrade) {
                        $resolvedGrades[(int) $gradeTemplate->id] = $existingGrade;
                        continue;
                    }

                    $createdGrade = SchoolStageGrade::query()->create([
                        'school_id' => $schoolId,
                        'school_stage_id' => (int) $schoolStage->id,
                        'name' => (string) $gradeTemplate->name,
                        'sort_order' => (int) $gradeTemplate->sort_order,
                        'is_active' => (bool) $gradeTemplate->is_active,
                    ]);

                    $resolvedGrades[(int) $gradeTemplate->id] = $createdGrade;
                    $counts['stage_grades']++;
                }
            }

            foreach ($stageTemplates as $stageTemplate) {
                foreach ($stageTemplate->grades as $gradeTemplate) {
                    $schoolGrade = $resolvedGrades[(int) $gradeTemplate->id] ?? null;
                    if (!$schoolGrade instanceof SchoolStageGrade) {
                        continue;
                    }

                    foreach ($gradeTemplate->gradeTerms as $gradeTermTemplate) {
                        $existingGradeTerm = SchoolStageGradeTerm::query()
                            ->where('school_id', $schoolId)
                            ->where('school_stage_grade_id', (int) $schoolGrade->id)
                            ->whereRaw('LOWER(name) = ?', [$this->normalizeForLookup($gradeTermTemplate->name)])
                            ->first();

                        if ($existingGradeTerm instanceof SchoolStageGradeTerm) {
                            continue;
                        }

                        SchoolStageGradeTerm::query()->create([
                            'school_id' => $schoolId,
                            'school_stage_grade_id' => (int) $schoolGrade->id,
                            'name' => (string) $gradeTermTemplate->name,
                            'sort_order' => (int) $gradeTermTemplate->sort_order,
                            'is_active' => (bool) $gradeTermTemplate->is_active,
                        ]);

                        $counts['grade_terms']++;
                    }
                }
            }

            foreach ($stageTemplates as $stageTemplate) {
                $schoolStage = $resolvedStages[(int) $stageTemplate->id] ?? null;
                if (!$schoolStage instanceof SchoolStage) {
                    continue;
                }

                foreach ($stageTemplate->classrooms as $classroomTemplate) {
                    $schoolGrade = $resolvedGrades[(int) $classroomTemplate->school_default_stage_grade_template_id] ?? null;
                    if (!$schoolGrade instanceof SchoolStageGrade) {
                        continue;
                    }

                    $existingClassroom = SchoolClassroom::query()
                        ->where('school_id', $schoolId)
                        ->where('school_stage_id', (int) $schoolStage->id)
                        ->where('grade_name', (string) $schoolGrade->name)
                        ->whereRaw('LOWER(name) = ?', [$this->normalizeForLookup($classroomTemplate->name)])
                        ->first();

                    if ($existingClassroom instanceof SchoolClassroom) {
                        continue;
                    }

                    SchoolClassroom::query()->create([
                        'school_id' => $schoolId,
                        'school_stage_id' => (int) $schoolStage->id,
                        'grade_name' => (string) $schoolGrade->name,
                        'name' => (string) $classroomTemplate->name,
                        'code' => $this->resolveScopedCode(
                            table: 'school_classrooms',
                            column: 'code',
                            schoolId: $schoolId,
                            preferredCode: $this->normalizeCode((string) ($classroomTemplate->code ?? '')),
                            prefix: 'CLS'
                        ),
                        'sort_order' => (int) $classroomTemplate->sort_order,
                        'is_active' => (bool) $classroomTemplate->is_active,
                    ]);

                    $counts['classrooms']++;
                }
            }

            $yearTemplates = $this->scopeScopedTemplate(
                SchoolDefaultAcademicYearTemplate::query()
                    ->where('is_active', true)
                    ->orderByDesc('starts_on')
                    ->orderBy('name'),
                $countryId,
                $educationTypeId,
                $directorateId
            )->get();

            $resolvedAcademicYears = [];

            foreach ($yearTemplates as $yearTemplate) {
                $existingYear = SchoolAcademicYear::query()
                    ->where('school_id', $schoolId)
                    ->whereRaw('LOWER(name) = ?', [$this->normalizeForLookup($yearTemplate->name)])
                    ->first();

                if ($existingYear instanceof SchoolAcademicYear) {
                    $resolvedAcademicYears[] = $existingYear;
                    continue;
                }

                $createdYear = SchoolAcademicYear::query()->create([
                    'school_id' => $schoolId,
                    'name' => (string) $yearTemplate->name,
                    'starts_on' => $yearTemplate->starts_on?->toDateString(),
                    'ends_on' => $yearTemplate->ends_on?->toDateString(),
                    'is_active' => (bool) $yearTemplate->is_active,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);

                $resolvedAcademicYears[] = $createdYear;
                $counts['academic_years']++;
            }

            foreach ($resolvedAcademicYears as $schoolAcademicYear) {
                $counts['terms'] += $this->ensureDefaultTermsForAcademicYear($schoolAcademicYear, $schoolId);
            }

            $holidayTemplates = $this->scopeScopedTemplate(
                SchoolDefaultHolidayTemplate::query()
                    ->where('is_active', true)
                    ->orderBy('start_date')
                    ->orderBy('name'),
                $countryId,
                $educationTypeId,
                $directorateId
            )->get();

            foreach ($holidayTemplates as $holidayTemplate) {
                $startDate = $holidayTemplate->start_date?->toDateString();
                $endDate = $holidayTemplate->end_date?->toDateString();

                if (!$startDate || !$endDate) {
                    continue;
                }

                $existingHoliday = SchoolHoliday::query()
                    ->where('school_id', $schoolId)
                    ->whereRaw('LOWER(name) = ?', [$this->normalizeForLookup($holidayTemplate->name)])
                    ->whereDate('start_date', $startDate)
                    ->whereDate('end_date', $endDate)
                    ->first();

                if ($existingHoliday instanceof SchoolHoliday) {
                    $updates = [];

                    if ($existingHoliday->reference_key === null && $holidayTemplate->reference_key !== null) {
                        $updates['reference_key'] = $this->emptyToNull($holidayTemplate->reference_key);
                    }

                    if ($existingHoliday->holiday_category === null && $holidayTemplate->holiday_category !== null) {
                        $updates['holiday_category'] = $this->emptyToNull($holidayTemplate->holiday_category);
                    }

                    if ($updates !== []) {
                        $existingHoliday->fill($updates)->save();
                    }

                    continue;
                }

                $hasOverlappingHoliday = SchoolHoliday::query()
                    ->where('school_id', $schoolId)
                    ->whereDate('start_date', '<=', $endDate)
                    ->whereDate('end_date', '>=', $startDate)
                    ->exists();

                if ($hasOverlappingHoliday) {
                    continue;
                }

                SchoolHoliday::query()->create([
                    'school_id' => $schoolId,
                    'name' => (string) $holidayTemplate->name,
                    'reference_key' => $this->emptyToNull($holidayTemplate->reference_key),
                    'holiday_category' => $this->emptyToNull($holidayTemplate->holiday_category),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'return_date' => $holidayTemplate->return_date?->toDateString(),
                    'notes' => $this->emptyToNull($holidayTemplate->notes),
                    'is_active' => (bool) $holidayTemplate->is_active,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);

                $counts['holidays']++;
            }

            $leaveTypeTemplates = $this->scopeScopedTemplate(
                SchoolDefaultLeaveTypeTemplate::query()
                    ->where('is_active', true)
                    ->orderBy('name'),
                $countryId,
                $educationTypeId,
                $directorateId
            )->get();

            foreach ($leaveTypeTemplates as $leaveTypeTemplate) {
                $preferredCode = $this->normalizeCode((string) ($leaveTypeTemplate->code ?? ''));
                $existingLeaveType = SchoolLeaveType::query()
                    ->where('school_id', $schoolId)
                    ->when(
                        $preferredCode !== null,
                        fn ($query) => $query->where('code', $preferredCode),
                        fn ($query) => $query->whereRaw('LOWER(name) = ?', [$this->normalizeForLookup($leaveTypeTemplate->name)])
                    )
                    ->first();

                if ($existingLeaveType instanceof SchoolLeaveType) {
                    continue;
                }

                SchoolLeaveType::query()->create([
                    'school_id' => $schoolId,
                    'code' => $this->resolveScopedCode(
                        table: 'school_leave_types',
                        column: 'code',
                        schoolId: $schoolId,
                        preferredCode: $preferredCode,
                        prefix: 'LEAVE'
                    ),
                    'name' => (string) $leaveTypeTemplate->name,
                    'category' => SchoolLeaveType::CATEGORY_STUDENT,
                    'requires_attachment' => (bool) $leaveTypeTemplate->requires_attachment,
                    'is_active' => (bool) $leaveTypeTemplate->is_active,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);

                $counts['leave_types']++;
            }

            $subjectTemplates = $this->scopeScopedTemplate(
                SchoolDefaultSubjectTemplate::query()
                    ->where('is_active', true)
                    ->orderBy('name'),
                $countryId,
                $educationTypeId,
                $directorateId
            )->get();

            foreach ($subjectTemplates as $subjectTemplate) {
                $preferredCode = $this->normalizeCode((string) ($subjectTemplate->code ?? ''));
                $existingSubject = SchoolSubject::query()
                    ->where('school_id', $schoolId)
                    ->when(
                        $preferredCode !== null,
                        fn ($query) => $query->where('code', $preferredCode),
                        fn ($query) => $query->whereRaw('LOWER(name) = ?', [$this->normalizeForLookup($subjectTemplate->name)])
                    )
                    ->first();

                if ($existingSubject instanceof SchoolSubject) {
                    continue;
                }

                SchoolSubject::query()->create([
                    'school_id' => $schoolId,
                    'name' => (string) $subjectTemplate->name,
                    'code' => $this->resolveScopedCode(
                        table: 'school_subjects',
                        column: 'code',
                        schoolId: $schoolId,
                        preferredCode: $preferredCode,
                        prefix: 'SUB'
                    ),
                    'branches' => $this->normalizeBranches((array) ($subjectTemplate->branches ?? [])),
                    'is_active' => (bool) $subjectTemplate->is_active,
                ]);

                $counts['subjects']++;
            }

            $importedAt = $school->default_data_imported_at;
            if (!$wasPreviouslyImported) {
                $importedAt = now();
                $school->update([
                    'default_data_imported_at' => $importedAt,
                    'default_data_imported_by' => $actorId,
                ]);
            }

            $this->auditLogger->log(
                'school_default_data.imported',
                'school',
                $schoolId,
                [
                    'school_id' => $schoolId,
                    'counts' => $counts,
                    'available_counts' => $availability['counts'] ?? [],
                    'country_id' => $countryId,
                    'education_type_id' => $educationTypeId,
                    'directorate_id' => $directorateId,
                    'selected_education_stages' => $selectedEducationStages['names'],
                    'was_previously_imported' => $wasPreviouslyImported,
                ],
                $request,
                $actorId
            );

            return [
                'counts' => $counts,
                'imported_at' => $importedAt?->toISOString(),
                'was_previously_imported' => $wasPreviouslyImported,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    private function buildTemplateCounts(?int $countryId, ?int $educationTypeId, ?int $directorateId): array
    {
        $academicYearsCount = $this->scopeScopedTemplate(
            SchoolDefaultAcademicYearTemplate::query()->where('is_active', true),
            $countryId,
            $educationTypeId,
            $directorateId
        )->count();

        return [
            'stages' => $this->scopeStageTemplates(
                SchoolDefaultStageTemplate::query()->where('is_active', true),
                $countryId,
                $educationTypeId,
                $directorateId
            )->count(),
            'stage_terms' => $this->scopeStageTermTemplates(
                SchoolDefaultStageTermTemplate::query()->where('is_active', true),
                $countryId,
                $educationTypeId,
                $directorateId
            )->count(),
            'stage_grades' => $this->scopeStageGradeTemplates(
                SchoolDefaultStageGradeTemplate::query()->where('is_active', true),
                $countryId,
                $educationTypeId,
                $directorateId
            )->count(),
            'grade_terms' => $this->scopeStageGradeTermTemplates(
                SchoolDefaultStageGradeTermTemplate::query()->where('is_active', true),
                $countryId,
                $educationTypeId,
                $directorateId
            )->count(),
            'classrooms' => $this->scopeClassroomTemplates(
                SchoolDefaultClassroomTemplate::query()->where('is_active', true),
                $countryId,
                $educationTypeId,
                $directorateId
            )->count(),
            'academic_years' => $academicYearsCount,
            'terms' => $academicYearsCount * 2,
            'holidays' => $this->scopeScopedTemplate(
                SchoolDefaultHolidayTemplate::query()->where('is_active', true),
                $countryId,
                $educationTypeId,
                $directorateId
            )->count(),
            'leave_types' => $this->scopeScopedTemplate(
                SchoolDefaultLeaveTypeTemplate::query()->where('is_active', true),
                $countryId,
                $educationTypeId,
                $directorateId
            )->count(),
            'subjects' => $this->scopeScopedTemplate(
                SchoolDefaultSubjectTemplate::query()->where('is_active', true),
                $countryId,
                $educationTypeId,
                $directorateId
            )->count(),
        ];
    }

    private function resolveScopedCode(
        string $table,
        string $column,
        int $schoolId,
        ?string $preferredCode,
        string $prefix
    ): ?string {
        if ($preferredCode !== null && !$this->scopedCodeExists($table, $column, $schoolId, $preferredCode)) {
            return $preferredCode;
        }

        return $this->generateScopedCode($table, $column, $schoolId, $prefix);
    }

    private function scopedCodeExists(string $table, string $column, int $schoolId, string $code): bool
    {
        return DB::table($table)
            ->where('school_id', $schoolId)
            ->where($column, $code)
            ->exists();
    }

    private function generateScopedCode(string $table, string $column, int $schoolId, string $prefix, int $padLength = 3): string
    {
        $codes = DB::table($table)
            ->where('school_id', $schoolId)
            ->whereNotNull($column)
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
                ->where('school_id', $schoolId)
                ->where($column, $candidate)
                ->exists();
            $next++;
        } while ($exists);

        return $candidate;
    }

    private function ensureDefaultTermsForAcademicYear(SchoolAcademicYear $academicYear, int $schoolId): int
    {
        if (!$academicYear->starts_on || !$academicYear->ends_on) {
            return 0;
        }

        $existingTermsCount = SchoolTerm::query()
            ->where('school_id', $schoolId)
            ->where('school_academic_year_id', (int) $academicYear->id)
            ->count();

        if ($existingTermsCount > 0) {
            return 0;
        }

        $created = 0;

        foreach ($this->resolveAcademicYearTermBlueprints($academicYear) as $termBlueprint) {
            $termName = $this->resolveGeneratedTermName(
                schoolId: $schoolId,
                baseName: (string) $termBlueprint['name'],
                academicYearName: (string) $academicYear->name,
            );

            $existingTerm = SchoolTerm::query()
                ->where('school_id', $schoolId)
                ->whereRaw('LOWER(name) = ?', [$this->normalizeForLookup($termName)])
                ->first();

            if ($existingTerm instanceof SchoolTerm) {
                continue;
            }

            SchoolTerm::query()->create([
                'school_id' => $schoolId,
                'school_academic_year_id' => (int) $academicYear->id,
                'name' => $termName,
                'start_date' => $termBlueprint['start_date'],
                'end_date' => $termBlueprint['end_date'],
                'is_active' => (bool) $academicYear->is_active,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * @return array<int, array{name: string, start_date: string, end_date: string}>
     */
    private function resolveAcademicYearTermBlueprints(SchoolAcademicYear $academicYear): array
    {
        if (!$academicYear->starts_on || !$academicYear->ends_on) {
            return [];
        }

        $start = Carbon::parse($academicYear->starts_on->toDateString())->startOfDay();
        $end = Carbon::parse($academicYear->ends_on->toDateString())->startOfDay();

        if ($end->lessThan($start)) {
            return [];
        }

        $totalDays = $start->diffInDays($end) + 1;
        $firstTermDays = max(1, (int) floor($totalDays / 2));
        $firstTermEnd = $start->copy()->addDays($firstTermDays - 1);

        if ($firstTermEnd->greaterThanOrEqualTo($end)) {
            return [[
                'name' => 'الترم الأول',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]];
        }

        $secondTermStart = $firstTermEnd->copy()->addDay();

        return [
            [
                'name' => 'الترم الأول',
                'start_date' => $start->toDateString(),
                'end_date' => $firstTermEnd->toDateString(),
            ],
            [
                'name' => 'الترم الثاني',
                'start_date' => $secondTermStart->toDateString(),
                'end_date' => $end->toDateString(),
            ],
        ];
    }

    private function resolveGeneratedTermName(int $schoolId, string $baseName, string $academicYearName): string
    {
        $plainNameExists = SchoolTerm::query()
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(name) = ?', [$this->normalizeForLookup($baseName)])
            ->exists();

        if (!$plainNameExists) {
            return $baseName;
        }

        return sprintf('%s - %s', $baseName, $academicYearName);
    }

    private function scopeStageTemplates(Builder $query, ?int $countryId, ?int $educationTypeId, ?int $directorateId = null): Builder
    {
        return $this->scopeScopedTemplate($query, $countryId, $educationTypeId, $directorateId);
    }

    private function scopeStageTermTemplates(Builder $query, ?int $countryId, ?int $educationTypeId, ?int $directorateId = null): Builder
    {
        return $query->whereHas('stage', fn (Builder $stageQuery) => $this->scopeStageTemplates(
            $stageQuery->where('is_active', true),
            $countryId,
            $educationTypeId,
            $directorateId
        ));
    }

    private function scopeStageGradeTemplates(Builder $query, ?int $countryId, ?int $educationTypeId, ?int $directorateId = null): Builder
    {
        return $query->whereHas('stage', fn (Builder $stageQuery) => $this->scopeStageTemplates(
            $stageQuery->where('is_active', true),
            $countryId,
            $educationTypeId,
            $directorateId
        ));
    }

    private function scopeStageGradeTermTemplates(Builder $query, ?int $countryId, ?int $educationTypeId, ?int $directorateId = null): Builder
    {
        return $query->whereHas('grade.stage', fn (Builder $stageQuery) => $this->scopeStageTemplates(
            $stageQuery->where('is_active', true),
            $countryId,
            $educationTypeId,
            $directorateId
        ))->whereHas('grade', fn (Builder $gradeQuery) => $gradeQuery->where('is_active', true));
    }

    private function scopeClassroomTemplates(Builder $query, ?int $countryId, ?int $educationTypeId, ?int $directorateId = null): Builder
    {
        return $query
            ->whereHas('stage', fn (Builder $stageQuery) => $this->scopeStageTemplates(
                $stageQuery->where('is_active', true),
                $countryId,
                $educationTypeId,
                $directorateId
            ))
            ->whereHas('grade', fn (Builder $gradeQuery) => $gradeQuery->where('is_active', true));
    }

    private function scopeScopedTemplate(Builder $query, ?int $countryId, ?int $educationTypeId, ?int $directorateId = null): Builder
    {
        $modelClass = $query->getModel()::class;

        if ($countryId !== null && $educationTypeId !== null) {
            $countryTypeQuery = $modelClass::query()
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

    /**
     * @return array{0: int|null, 1: int|null, 2: int|null}
     */
    private function resolveTemplateScope(?School $school): array
    {
        if (!$school) {
            return [null, null, null];
        }

        $directorate = $school->relationLoaded('directorate')
            ? $school->directorate
            : $school->directorate()->first(['id', 'country_id', 'education_type_id']);

        $countryId = $directorate?->country_id ? (int) $directorate->country_id : null;
        $educationTypeId = $directorate?->education_type_id ? (int) $directorate->education_type_id : null;

        if ($countryId !== null && $educationTypeId !== null) {
            return [$countryId, $educationTypeId, null];
        }

        return [null, null, null];
    }

    /**
     * @param  array<int, mixed>|null  $educationStageIds
     * @return array{
     *     ids: array<int, int>,
     *     names: array<int, string>,
     *     normalized_names: array<int, string>,
     *     canonical_names: array<int, string>
     * }
     */
    private function resolveSelectedEducationStages(School $school, ?array $educationStageIds = null): array
    {
        if (is_array($educationStageIds) && $educationStageIds !== []) {
            $selectedStages = EducationStage::query()
                ->whereIn('id', collect($educationStageIds)->map(fn ($value) => (int) $value)->filter()->all())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']);
        } else {
            $selectedStages = $school->relationLoaded('educationStages')
                ? $school->educationStages
                : $school->educationStages()->get(['education_stages.id', 'education_stages.name']);
        }

        $names = $selectedStages
            ->pluck('name')
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->values()
            ->all();

        return [
            'ids' => $selectedStages
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all(),
            'names' => $names,
            'normalized_names' => collect($names)
                ->map(fn (string $name): string => $this->normalizeForLookup($name))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'canonical_names' => collect($names)
                ->map(fn (string $name): string => $this->canonicalizeEducationStageName($name))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\SchoolDefaultStageTemplate>  $stageTemplates
     * @param  array{
     *     ids: array<int, int>,
     *     names: array<int, string>,
     *     normalized_names: array<int, string>,
     *     canonical_names: array<int, string>
     * }  $selectedEducationStages
     * @return \Illuminate\Support\Collection<int, \App\Models\SchoolDefaultStageTemplate>
     */
    private function filterStageTemplatesByEducationStages($stageTemplates, array $selectedEducationStages)
    {
        if (($selectedEducationStages['ids'] ?? []) === [] && ($selectedEducationStages['names'] ?? []) === []) {
            return $stageTemplates;
        }

        $allowedStageIds = $selectedEducationStages['ids'] ?? [];
        $allowedNames = $selectedEducationStages['normalized_names'] ?? [];
        $allowedCanonicalNames = $selectedEducationStages['canonical_names'] ?? [];

        return $stageTemplates
            ->filter(function (SchoolDefaultStageTemplate $stageTemplate) use ($allowedStageIds, $allowedNames, $allowedCanonicalNames): bool {
                $linkedEducationStageId = (int) ($stageTemplate->education_stage_id ?? 0);

                if ($linkedEducationStageId > 0) {
                    return in_array($linkedEducationStageId, $allowedStageIds, true);
                }

                $normalizedName = $this->normalizeForLookup((string) $stageTemplate->name);
                if (in_array($normalizedName, $allowedNames, true)) {
                    return true;
                }

                $canonicalName = $this->canonicalizeEducationStageName((string) $stageTemplate->name);

                return $canonicalName !== '' && in_array($canonicalName, $allowedCanonicalNames, true);
            })
            ->values();
    }

    private function normalizeForLookup(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function canonicalizeEducationStageName(?string $value): string
    {
        $normalized = $this->normalizeArabicForStageLookup($value);

        if ($normalized === '') {
            return '';
        }

        if (str_contains($normalized, 'رياضالاطفال') || str_contains($normalized, 'روض')) {
            return 'kindergarten';
        }

        if (str_contains($normalized, 'ابتد')) {
            return 'primary';
        }

        if (str_contains($normalized, 'متوسط')) {
            return 'middle';
        }

        if (str_contains($normalized, 'ثانو')) {
            return 'secondary';
        }

        $stripped = str_replace(
            ['المرحله', 'مرحله', 'التعليم', 'تعليم', 'المدرسه', 'مدرسه', 'المدارس', 'مدارس'],
            '',
            $normalized
        );

        return $stripped !== '' ? $stripped : $normalized;
    }

    private function normalizeArabicForStageLookup(?string $value): string
    {
        $normalized = $this->normalizeForLookup($value);
        $normalized = str_replace(
            ['أ', 'إ', 'آ', 'ٱ', 'ى', 'ئ', 'ؤ', 'ة', 'ـ'],
            ['ا', 'ا', 'ا', 'ا', 'ي', 'ي', 'و', 'ه', ''],
            $normalized
        );

        return preg_replace('/[^\p{Arabic}\p{N}]+/u', '', $normalized) ?? '';
    }

    private function normalizeCode(?string $value): ?string
    {
        $normalized = strtoupper(trim((string) $value));

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeTime(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return null;
        }

        return preg_match('/^\d{2}:\d{2}$/', $normalized) === 1
            ? $normalized . ':00'
            : $normalized;
    }

    /**
     * @param array<int, mixed> $branches
     * @return array<int, string>
     */
    private function normalizeBranches(array $branches): array
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
}
