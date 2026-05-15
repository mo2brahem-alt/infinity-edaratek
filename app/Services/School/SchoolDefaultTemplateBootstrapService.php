<?php

namespace App\Services\School;

use App\Models\SchoolDefaultClassroomTemplate;
use App\Models\SchoolDefaultLeaveTypeTemplate;
use App\Models\SchoolDefaultStageGradeTemplate;
use App\Models\SchoolDefaultStageGradeTermTemplate;
use App\Models\SchoolDefaultStageTemplate;
use App\Models\SchoolDefaultStageTermTemplate;
use App\Models\SchoolDefaultSubjectTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SchoolDefaultTemplateBootstrapService
{
    /**
     * @return array{
     *     stages: array{created: int, total: int, labels: array<int, string>},
     *     stage_terms: array{created: int, total: int, labels: array<int, string>},
     *     stage_grades: array{created: int, total: int, labels: array<int, string>},
     *     stage_grade_terms: array{created: int, total: int, labels: array<int, string>},
     *     classrooms: array{created: int, total: int, labels: array<int, string>},
     *     leave_types: array{created: int, total: int, labels: array<int, string>},
     *     subjects: array{created: int, total: int, labels: array<int, string>},
     *     has_generated_defaults: bool
     * }
     */
    public function ensureFallbackDefaults(
        int $countryId,
        int $educationTypeId,
        int $actorId,
        ?int $directorateId = null,
        array $countryReference = []
    ): array {
        return DB::transaction(function () use ($countryId, $educationTypeId, $actorId, $directorateId, $countryReference): array {
            $stageHierarchy = $this->ensureStageHierarchy(
                countryId: $countryId,
                educationTypeId: $educationTypeId,
                actorId: $actorId,
                directorateId: $directorateId,
                countryReference: $countryReference,
            );

            $leaveTypes = $this->ensureLeaveTypes(
                countryId: $countryId,
                educationTypeId: $educationTypeId,
                actorId: $actorId,
                directorateId: $directorateId,
            );

            $subjects = $this->ensureSubjects(
                countryId: $countryId,
                educationTypeId: $educationTypeId,
                actorId: $actorId,
                directorateId: $directorateId,
            );

            $createdCounts = collect([
                $stageHierarchy['stages']['created'],
                $stageHierarchy['stage_terms']['created'],
                $stageHierarchy['stage_grades']['created'],
                $stageHierarchy['stage_grade_terms']['created'],
                $stageHierarchy['classrooms']['created'],
                $leaveTypes['created'],
                $subjects['created'],
            ]);

            return [
                'stages' => $stageHierarchy['stages'],
                'stage_terms' => $stageHierarchy['stage_terms'],
                'stage_grades' => $stageHierarchy['stage_grades'],
                'stage_grade_terms' => $stageHierarchy['stage_grade_terms'],
                'classrooms' => $stageHierarchy['classrooms'],
                'leave_types' => $leaveTypes,
                'subjects' => $subjects,
                'has_generated_defaults' => $createdCounts->sum() > 0,
            ];
        });
    }

    /**
     * @return array{
     *     stages: array{created: int, total: int, labels: array<int, string>},
     *     stage_terms: array{created: int, total: int, labels: array<int, string>},
     *     stage_grades: array{created: int, total: int, labels: array<int, string>},
     *     stage_grade_terms: array{created: int, total: int, labels: array<int, string>},
     *     classrooms: array{created: int, total: int, labels: array<int, string>}
     * }
     */
    private function ensureStageHierarchy(
        int $countryId,
        int $educationTypeId,
        int $actorId,
        ?int $directorateId = null,
        array $countryReference = []
    ): array {
        $createdStages = [];
        $createdStageTerms = [];
        $createdGrades = [];
        $createdGradeTerms = [];
        $createdClassrooms = [];

        $stageTemplates = $this->loadScopedStageTemplates($countryId, $educationTypeId, $directorateId);

        foreach ($this->stageBlueprints() as $blueprint) {
            if ($this->findMatchingStageTemplate($stageTemplates, $blueprint) !== null) {
                continue;
            }

            $stage = SchoolDefaultStageTemplate::query()->create([
                'country_id' => $countryId,
                'education_type_id' => $educationTypeId,
                'directorate_id' => $directorateId,
                'name' => $blueprint['name'],
                'code' => $this->generateTemplateCode('school_default_stage_templates', 'code', 'STG'),
                'sort_order' => $blueprint['sort_order'],
                'is_active' => true,
                'school_day_start_time' => $blueprint['school_day_start_time'],
                'school_day_end_time' => $blueprint['school_day_end_time'],
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $createdStages[] = (string) $stage->name;
        }

        if ($createdStages !== []) {
            $stageTemplates = $this->loadScopedStageTemplates($countryId, $educationTypeId, $directorateId);
        }

        foreach ($stageTemplates as $stageTemplate) {
            foreach ($this->resolveStageTermBlueprintsForStage($stageTemplate, $countryReference) as $index => $termBlueprint) {
                $matchingStageTerm = $this->findMatchingStageTermTemplate($stageTemplate, $termBlueprint['name']);

                if ($matchingStageTerm instanceof SchoolDefaultStageTermTemplate) {
                    $updates = [];
                    $normalizedStartDate = $this->normalizeDateValue($termBlueprint['start_date'] ?? null);
                    $normalizedEndDate = $this->normalizeDateValue($termBlueprint['end_date'] ?? null);

                    if (($matchingStageTerm->start_date?->toDateString() ?? null) === null && $normalizedStartDate !== null) {
                        $updates['start_date'] = $normalizedStartDate;
                    }

                    if (($matchingStageTerm->end_date?->toDateString() ?? null) === null && $normalizedEndDate !== null) {
                        $updates['end_date'] = $normalizedEndDate;
                    }

                    if (($matchingStageTerm->source ?? 'default') !== 'manual'
                        && ($termBlueprint['source'] ?? 'default') === 'api'
                        && $updates !== []) {
                        $updates['source'] = 'api';
                    }

                    if ($updates !== []) {
                        $matchingStageTerm->fill($updates);
                        $matchingStageTerm->save();
                    }

                    continue;
                }

                $stageTerm = SchoolDefaultStageTermTemplate::query()->create([
                    'school_default_stage_template_id' => (int) $stageTemplate->id,
                    'name' => $termBlueprint['name'],
                    'start_date' => $this->normalizeDateValue($termBlueprint['start_date'] ?? null),
                    'end_date' => $this->normalizeDateValue($termBlueprint['end_date'] ?? null),
                    'source' => $termBlueprint['source'] ?? 'default',
                    'sort_order' => (int) ($termBlueprint['sort_order'] ?? (($index + 1) * 10)),
                    'is_active' => true,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);

                $createdStageTerms[] = sprintf(
                    '%s / %s',
                    (string) $stageTemplate->name,
                    (string) $stageTerm->name
                );
            }
        }

        if ($createdStageTerms !== []) {
            $stageTemplates = $this->loadScopedStageTemplates($countryId, $educationTypeId, $directorateId);
        }

        $recognizedBlueprints = $this->resolveRecognizedStageBlueprints($stageTemplates);

        foreach ($stageTemplates as $stageTemplate) {
            $blueprint = $recognizedBlueprints[(int) $stageTemplate->id] ?? null;

            if ($blueprint === null) {
                continue;
            }

            foreach ($blueprint['grades'] as $index => $gradeName) {
                if ($this->findMatchingGradeTemplate($stageTemplate, $gradeName) !== null) {
                    continue;
                }

                $grade = SchoolDefaultStageGradeTemplate::query()->create([
                    'school_default_stage_template_id' => (int) $stageTemplate->id,
                    'name' => $gradeName,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);

                $createdGrades[] = sprintf('%s / %s', (string) $stageTemplate->name, (string) $grade->name);
            }
        }

        if ($createdGrades !== []) {
            $stageTemplates = $this->loadScopedStageTemplates($countryId, $educationTypeId, $directorateId);
        }

        foreach ($stageTemplates as $stageTemplate) {
            foreach ($stageTemplate->grades as $gradeTemplate) {
                foreach ($this->defaultGradeTerms() as $index => $termBlueprint) {
                    if ($this->findMatchingGradeTermTemplate($gradeTemplate, $termBlueprint['name']) !== null) {
                        continue;
                    }

                    $gradeTerm = SchoolDefaultStageGradeTermTemplate::query()->create([
                        'school_default_stage_grade_template_id' => (int) $gradeTemplate->id,
                        'name' => $termBlueprint['name'],
                        'sort_order' => (int) ($termBlueprint['sort_order'] ?? (($index + 1) * 10)),
                        'is_active' => true,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);

                    $createdGradeTerms[] = sprintf(
                        '%s / %s / %s',
                        (string) $stageTemplate->name,
                        (string) $gradeTemplate->name,
                        (string) $gradeTerm->name
                    );
                }
            }
        }

        if ($createdGradeTerms !== []) {
            $stageTemplates = $this->loadScopedStageTemplates($countryId, $educationTypeId, $directorateId);
        }

        foreach ($stageTemplates as $stageTemplate) {
            foreach ($stageTemplate->grades as $index => $gradeTemplate) {
                if ($this->hasClassroomForGrade($stageTemplate, (int) $gradeTemplate->id)) {
                    continue;
                }

                $classroom = SchoolDefaultClassroomTemplate::query()->create([
                    'school_default_stage_template_id' => (int) $stageTemplate->id,
                    'school_default_stage_grade_template_id' => (int) $gradeTemplate->id,
                    'name' => 'الشعبة أ',
                    'code' => $this->generateTemplateCode('school_default_classroom_templates', 'code', 'CLS'),
                    'sort_order' => (int) ($gradeTemplate->sort_order ?: (($index + 1) * 10)),
                    'is_active' => true,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);

                $createdClassrooms[] = sprintf(
                    '%s / %s / %s',
                    (string) $stageTemplate->name,
                    (string) $gradeTemplate->name,
                    (string) $classroom->name
                );
            }
        }

        if ($createdClassrooms !== []) {
            $stageTemplates = $this->loadScopedStageTemplates($countryId, $educationTypeId, $directorateId);
        }

        $totalGrades = $stageTemplates->sum(fn (SchoolDefaultStageTemplate $stage) => $stage->grades->count());
        $totalStageTerms = $stageTemplates->sum(fn (SchoolDefaultStageTemplate $stage) => $stage->stageTerms->count());
        $totalGradeTerms = $stageTemplates->sum(
            fn (SchoolDefaultStageTemplate $stage) => $stage->grades->sum(
                fn (SchoolDefaultStageGradeTemplate $grade) => $grade->gradeTerms->count()
            )
        );
        $totalClassrooms = $stageTemplates->sum(fn (SchoolDefaultStageTemplate $stage) => $stage->classrooms->count());

        return [
            'stages' => [
                'created' => count($createdStages),
                'total' => $stageTemplates->count(),
                'labels' => $createdStages,
            ],
            'stage_terms' => [
                'created' => count($createdStageTerms),
                'total' => $totalStageTerms,
                'labels' => $createdStageTerms,
            ],
            'stage_grades' => [
                'created' => count($createdGrades),
                'total' => $totalGrades,
                'labels' => $createdGrades,
            ],
            'stage_grade_terms' => [
                'created' => count($createdGradeTerms),
                'total' => $totalGradeTerms,
                'labels' => $createdGradeTerms,
            ],
            'classrooms' => [
                'created' => count($createdClassrooms),
                'total' => $totalClassrooms,
                'labels' => $createdClassrooms,
            ],
        ];
    }

    /**
     * @return array{created: int, total: int, labels: array<int, string>}
     */
    private function ensureLeaveTypes(
        int $countryId,
        int $educationTypeId,
        int $actorId,
        ?int $directorateId = null
    ): array {
        $query = $this->scopedNamedTemplateQuery(
            SchoolDefaultLeaveTypeTemplate::query(),
            $countryId,
            $educationTypeId,
            $directorateId,
        );

        $existingNames = $query
            ->pluck('name')
            ->map(fn ($name): string => $this->normalizeForLookup((string) $name))
            ->filter()
            ->unique()
            ->all();

        $created = [];

        foreach ($this->defaultLeaveTypes() as $blueprint) {
            if (in_array($this->normalizeForLookup($blueprint['name']), $existingNames, true)) {
                continue;
            }

            $leaveType = SchoolDefaultLeaveTypeTemplate::query()->create([
                'country_id' => $countryId,
                'education_type_id' => $educationTypeId,
                'directorate_id' => $directorateId,
                'name' => $blueprint['name'],
                'code' => $this->generateTemplateCode('school_default_leave_type_templates', 'code', 'LEAVE'),
                'requires_attachment' => $blueprint['requires_attachment'],
                'is_active' => true,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $created[] = (string) $leaveType->name;
            $existingNames[] = $this->normalizeForLookup((string) $leaveType->name);
        }

        return [
            'created' => count($created),
            'total' => $this->scopedNamedTemplateQuery(
                SchoolDefaultLeaveTypeTemplate::query(),
                $countryId,
                $educationTypeId,
                $directorateId,
            )->count(),
            'labels' => $created,
        ];
    }

    /**
     * @return array{created: int, total: int, labels: array<int, string>}
     */
    private function ensureSubjects(
        int $countryId,
        int $educationTypeId,
        int $actorId,
        ?int $directorateId = null
    ): array {
        $query = $this->scopedNamedTemplateQuery(
            SchoolDefaultSubjectTemplate::query(),
            $countryId,
            $educationTypeId,
            $directorateId,
        );

        $existingNames = $query
            ->pluck('name')
            ->map(fn ($name): string => $this->normalizeForLookup((string) $name))
            ->filter()
            ->unique()
            ->all();

        $created = [];

        foreach ($this->defaultSubjects() as $blueprint) {
            if (in_array($this->normalizeForLookup($blueprint['name']), $existingNames, true)) {
                continue;
            }

            $subject = SchoolDefaultSubjectTemplate::query()->create([
                'country_id' => $countryId,
                'education_type_id' => $educationTypeId,
                'directorate_id' => $directorateId,
                'name' => $blueprint['name'],
                'code' => $this->generateTemplateCode('school_default_subject_templates', 'code', 'SUB'),
                'branches' => $blueprint['branches'],
                'is_active' => true,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $created[] = (string) $subject->name;
            $existingNames[] = $this->normalizeForLookup((string) $subject->name);
        }

        return [
            'created' => count($created),
            'total' => $this->scopedNamedTemplateQuery(
                SchoolDefaultSubjectTemplate::query(),
                $countryId,
                $educationTypeId,
                $directorateId,
            )->count(),
            'labels' => $created,
        ];
    }

    /**
     * @param  Collection<int, SchoolDefaultStageTemplate>  $stageTemplates
     * @return array<int, array{name: string, aliases: array<int, string>, sort_order: int, school_day_start_time: string, school_day_end_time: string, grades: array<int, string>}>
     */
    private function resolveRecognizedStageBlueprints(Collection $stageTemplates): array
    {
        $resolved = [];

        foreach ($stageTemplates as $stageTemplate) {
            $blueprint = $this->resolveStageBlueprintByName((string) $stageTemplate->name);

            if ($blueprint === null) {
                continue;
            }

            $resolved[(int) $stageTemplate->id] = $blueprint;
        }

        return $resolved;
    }

    /**
     * @param  Collection<int, SchoolDefaultStageTemplate>  $stageTemplates
     * @param  array{name: string, aliases: array<int, string>, sort_order: int, school_day_start_time: string, school_day_end_time: string, grades: array<int, string>}  $blueprint
     */
    private function findMatchingStageTemplate(Collection $stageTemplates, array $blueprint): ?SchoolDefaultStageTemplate
    {
        $normalizedAliases = $this->normalizeLookupValues(
            collect($blueprint['aliases'] ?? [])
                ->push($blueprint['name'])
                ->all()
        );

        return $stageTemplates->first(function (SchoolDefaultStageTemplate $stageTemplate) use ($normalizedAliases): bool {
            return in_array($this->normalizeForLookup((string) $stageTemplate->name), $normalizedAliases, true);
        });
    }

    private function findMatchingGradeTemplate(SchoolDefaultStageTemplate $stageTemplate, string $gradeName): ?SchoolDefaultStageGradeTemplate
    {
        $normalizedGradeName = $this->normalizeForLookup($gradeName);

        return $stageTemplate->grades->first(function (SchoolDefaultStageGradeTemplate $gradeTemplate) use ($normalizedGradeName): bool {
            return $this->normalizeForLookup((string) $gradeTemplate->name) === $normalizedGradeName;
        });
    }

    private function findMatchingGradeTermTemplate(
        SchoolDefaultStageGradeTemplate $gradeTemplate,
        string $termName
    ): ?SchoolDefaultStageGradeTermTemplate {
        $normalizedTermName = $this->normalizeForLookup($termName);

        return $gradeTemplate->gradeTerms->first(
            fn (SchoolDefaultStageGradeTermTemplate $gradeTermTemplate): bool
                => $this->normalizeForLookup((string) $gradeTermTemplate->name) === $normalizedTermName
        );
    }

    private function findMatchingStageTermTemplate(
        SchoolDefaultStageTemplate $stageTemplate,
        string $termName
    ): ?SchoolDefaultStageTermTemplate {
        $normalizedTermName = $this->normalizeForLookup($termName);

        return $stageTemplate->stageTerms->first(
            fn (SchoolDefaultStageTermTemplate $stageTermTemplate): bool
                => $this->normalizeForLookup((string) $stageTermTemplate->name) === $normalizedTermName
        );
    }

    private function hasClassroomForGrade(SchoolDefaultStageTemplate $stageTemplate, int $gradeId): bool
    {
        return $stageTemplate->classrooms->contains(function (SchoolDefaultClassroomTemplate $classroomTemplate) use ($gradeId): bool {
            return (int) $classroomTemplate->school_default_stage_grade_template_id === $gradeId;
        });
    }

    /**
     * @return array{name: string, aliases: array<int, string>, sort_order: int, school_day_start_time: string, school_day_end_time: string, grades: array<int, string>}|null
     */
    private function resolveStageBlueprintByName(string $name): ?array
    {
        $normalizedName = $this->normalizeForLookup($name);

        foreach ($this->stageBlueprints() as $blueprint) {
            $aliases = $this->normalizeLookupValues(
                collect($blueprint['aliases'] ?? [])
                    ->push($blueprint['name'])
                    ->all()
            );

            if (in_array($normalizedName, $aliases, true)) {
                return $blueprint;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{name: string, aliases: array<int, string>, sort_order: int, school_day_start_time: string, school_day_end_time: string, grades: array<int, string>}>
     */
    private function stageBlueprints(): array
    {
        return [
            [
                'name' => 'روضة',
                'aliases' => ['الروضة', 'رياض الأطفال'],
                'sort_order' => 10,
                'school_day_start_time' => '08:00:00',
                'school_day_end_time' => '12:00:00',
                'grades' => ['المستوى الأول', 'المستوى الثاني', 'المستوى الثالث'],
            ],
            [
                'name' => 'ابتدائي',
                'aliases' => ['المرحلة الابتدائية', 'ابتدائية'],
                'sort_order' => 20,
                'school_day_start_time' => '07:00:00',
                'school_day_end_time' => '13:00:00',
                'grades' => ['الصف الأول', 'الصف الثاني', 'الصف الثالث', 'الصف الرابع', 'الصف الخامس', 'الصف السادس'],
            ],
            [
                'name' => 'متوسط',
                'aliases' => ['المرحلة المتوسطة', 'متوسطة'],
                'sort_order' => 30,
                'school_day_start_time' => '07:00:00',
                'school_day_end_time' => '13:00:00',
                'grades' => ['الصف الأول المتوسط', 'الصف الثاني المتوسط', 'الصف الثالث المتوسط'],
            ],
            [
                'name' => 'ثانوي',
                'aliases' => ['المرحلة الثانوية', 'ثانوية'],
                'sort_order' => 40,
                'school_day_start_time' => '07:00:00',
                'school_day_end_time' => '13:00:00',
                'grades' => ['الصف الأول الثانوي', 'الصف الثاني الثانوي', 'الصف الثالث الثانوي'],
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, requires_attachment: bool}>
     */
    private function defaultLeaveTypes(): array
    {
        return [
            ['name' => 'إجازة مرضية', 'requires_attachment' => true],
            ['name' => 'إجازة طارئة', 'requires_attachment' => false],
            ['name' => 'إجازة أسرية', 'requires_attachment' => false],
            ['name' => 'إجازة استثنائية', 'requires_attachment' => false],
        ];
    }

    /**
     * @return array<int, array{name: string, sort_order: int}>
     */
    private function defaultGradeTerms(): array
    {
        return [
            ['name' => 'الفصل الدراسي الأول', 'sort_order' => 10],
            ['name' => 'الفصل الدراسي الثاني', 'sort_order' => 20],
        ];
    }

    /**
     * @return array<int, array{name: string, branches: array<int, string>}>
     */
    /**
     * @return array<int, array{name: string, sort_order: int, start_date: ?string, end_date: ?string, source: string}>
     */
    private function defaultStageTerms(): array
    {
        return [
            [
                'name' => 'الفصل الدراسي الأول',
                'sort_order' => 10,
                'start_date' => null,
                'end_date' => null,
                'source' => 'default',
            ],
            [
                'name' => 'الفصل الدراسي الثاني',
                'sort_order' => 20,
                'start_date' => null,
                'end_date' => null,
                'source' => 'default',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $countryReference
     * @return array<int, array{name: string, sort_order: int, start_date: ?string, end_date: ?string, source: string}>
     */
    private function resolveStageTermBlueprintsForStage(
        SchoolDefaultStageTemplate $stageTemplate,
        array $countryReference = []
    ): array {
        $defaults = $this->defaultStageTerms();
        $apiTerms = collect($countryReference['stage_terms'] ?? $countryReference['academic_terms'] ?? [])
            ->filter(fn ($term) => is_array($term))
            ->map(fn (array $term): array => [
                'stage_name' => trim((string) ($term['stage_name'] ?? $term['stage'] ?? '')),
                'name' => trim((string) ($term['name'] ?? $term['label'] ?? '')),
                'start_date' => $this->normalizeDateValue($term['start_date'] ?? $term['starts_on'] ?? null),
                'end_date' => $this->normalizeDateValue($term['end_date'] ?? $term['ends_on'] ?? null),
                'sort_order' => max(0, (int) ($term['sort_order'] ?? 0)),
                'source' => 'api',
            ])
            ->filter(function (array $term) use ($stageTemplate): bool {
                if ($term['name'] === '') {
                    return false;
                }

                if ($term['stage_name'] === '') {
                    return true;
                }

                return $this->normalizeForLookup($term['stage_name']) === $this->normalizeForLookup((string) $stageTemplate->name);
            })
            ->values();

        $resolved = [];
        $usedApiIndexes = [];

        foreach ($defaults as $defaultIndex => $defaultBlueprint) {
            $matchingApiIndex = $apiTerms->search(function (array $apiBlueprint) use ($defaultBlueprint): bool {
                return $this->normalizeForLookup($apiBlueprint['name']) === $this->normalizeForLookup($defaultBlueprint['name']);
            });

            if ($matchingApiIndex !== false) {
                $apiBlueprint = $apiTerms->get($matchingApiIndex);
                $usedApiIndexes[] = $matchingApiIndex;

                $resolved[] = [
                    'name' => $apiBlueprint['name'],
                    'sort_order' => (int) ($apiBlueprint['sort_order'] ?: $defaultBlueprint['sort_order']),
                    'start_date' => $apiBlueprint['start_date'],
                    'end_date' => $apiBlueprint['end_date'],
                    'source' => 'api',
                ];

                continue;
            }

            $resolved[] = [
                'name' => $defaultBlueprint['name'],
                'sort_order' => (int) ($defaultBlueprint['sort_order'] ?? (($defaultIndex + 1) * 10)),
                'start_date' => $defaultBlueprint['start_date'] ?? null,
                'end_date' => $defaultBlueprint['end_date'] ?? null,
                'source' => 'default',
            ];
        }

        foreach ($apiTerms as $index => $apiBlueprint) {
            if (in_array($index, $usedApiIndexes, true)) {
                continue;
            }

            $resolved[] = [
                'name' => $apiBlueprint['name'],
                'sort_order' => (int) ($apiBlueprint['sort_order'] ?: ((count($resolved) + 1) * 10)),
                'start_date' => $apiBlueprint['start_date'],
                'end_date' => $apiBlueprint['end_date'],
                'source' => 'api',
            ];
        }

        return $resolved;
    }

    private function defaultSubjects(): array
    {
        return [
            ['name' => 'اللغة العربية', 'branches' => []],
            ['name' => 'اللغة الإنجليزية', 'branches' => []],
            ['name' => 'الرياضيات', 'branches' => []],
            ['name' => 'العلوم', 'branches' => []],
            ['name' => 'الدراسات الاجتماعية', 'branches' => []],
            ['name' => 'التربية الإسلامية', 'branches' => []],
        ];
    }

    private function loadScopedStageTemplates(int $countryId, int $educationTypeId, ?int $directorateId = null): Collection
    {
        return $this->scopedStageTemplates($countryId, $educationTypeId, $directorateId)
            ->with([
                'stageTerms' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
                'grades' => fn ($query) => $query
                    ->with([
                        'gradeTerms' => fn ($gradeTerms) => $gradeTerms->orderBy('sort_order')->orderBy('name'),
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'classrooms' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function scopedStageTemplates(int $countryId, int $educationTypeId, ?int $directorateId = null): Builder
    {
        return $this->applyScope(
            SchoolDefaultStageTemplate::query(),
            $countryId,
            $educationTypeId,
            $directorateId,
        );
    }

    private function scopedNamedTemplateQuery(
        Builder $query,
        int $countryId,
        int $educationTypeId,
        ?int $directorateId = null
    ): Builder {
        return $this->applyScope($query, $countryId, $educationTypeId, $directorateId);
    }

    private function applyScope(
        Builder $query,
        int $countryId,
        int $educationTypeId,
        ?int $directorateId = null
    ): Builder {
        return $query
            ->where('country_id', $countryId)
            ->where('education_type_id', $educationTypeId)
            ->when(
                $directorateId !== null,
                fn (Builder $scopeQuery) => $scopeQuery->where('directorate_id', $directorateId),
                fn (Builder $scopeQuery) => $scopeQuery->whereNull('directorate_id')
            );
    }

    private function generateTemplateCode(
        string $table,
        string $column,
        string $prefix,
        int $padLength = 3
    ): string {
        $codes = DB::table($table)
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
            $exists = DB::table($table)->where($column, $candidate)->exists();
            $next++;
        } while ($exists);

        return $candidate;
    }

    private function normalizeForLookup(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($value)));
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function normalizeLookupValues(array $values): array
    {
        return collect($values)
            ->map(fn (string $value): string => $this->normalizeForLookup($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
