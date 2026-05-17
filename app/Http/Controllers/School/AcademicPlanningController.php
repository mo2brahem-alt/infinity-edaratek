<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolAcademicYear;
use App\Models\SchoolClassSchedule;
use App\Models\SchoolClassroom;
use App\Models\SchoolCoursePlanLesson;
use App\Models\SchoolCoursePlanTopic;
use App\Models\SchoolCoursePlanUnit;
use App\Models\SchoolLeaveType;
use App\Models\SchoolStage;
use App\Models\SchoolStageGrade;
use App\Models\SchoolSubject;
use App\Models\SchoolCourseOffering;
use App\Models\SchoolTeachingAssignment;
use App\Models\SchoolSubjectTeacherAssignment;
use App\Models\SchoolTeacherAvailability;
use App\Models\SchoolTimetableVersion;
use App\Models\SchoolTerm;
use App\Models\User;
use App\Services\Exports\SchoolExportDocumentService;
use App\Services\School\AcademicPlanningValidationService;
use App\Services\School\SchoolCalendarService;
use App\Services\School\SchoolDefaultDataProvisioningService;
use App\Services\School\StudentLeaveService;
use App\Services\Support\AttachmentService;
use App\Services\Support\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AcademicPlanningController extends Controller
{
    private const DEFAULT_STUDY_PLAN_BRANCH_NAME = 'الفرع الرئيسي';

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AcademicPlanningValidationService $academicPlanningValidationService,
        private readonly SchoolCalendarService $schoolCalendarService,
        private readonly StudentLeaveService $studentLeaveService,
        private readonly SchoolDefaultDataProvisioningService $schoolDefaultDataProvisioningService,
        private readonly AttachmentService $attachmentService,
        private readonly SchoolExportDocumentService $exportDocuments,
    ) {
    }

    public function index(Request $request): Response
    {
        $schoolId = $this->resolveSchoolId($request);
        $user = $request->user();
        $actorId = (int) ($user?->id ?? 0);

        $this->studentLeaveService->ensureDefaultLeaveTypes($schoolId, $actorId > 0 ? $actorId : null);
        $calendarSettings = $this->schoolCalendarService->getOrCreateSettings($schoolId, $actorId > 0 ? $actorId : null);

        $school = School::query()
            ->whereKey($schoolId)
            ->with(['defaultDataImporter:id,name'])
            ->first(['id', 'name', 'school_id', 'default_data_imported_at', 'default_data_imported_by']);

        $academicYears = SchoolAcademicYear::query()
            ->where('school_id', $schoolId)
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get(['id', 'school_id', 'name', 'starts_on', 'ends_on', 'is_active']);

        $terms = SchoolTerm::query()
            ->where('school_id', $schoolId)
            ->with(['academicYear:id,name'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get([
                'id',
                'school_id',
                'school_academic_year_id',
                'name',
                'start_date',
                'end_date',
                'is_active',
            ]);

        $stages = SchoolStage::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->with([
                'grades' => fn ($grades) => $grades
                    ->where('school_id', $schoolId)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->select(['id', 'school_id', 'school_stage_id', 'name', 'sort_order', 'is_active']),
                'classrooms' => fn ($classrooms) => $classrooms
                    ->where('school_id', $schoolId)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->select(['id', 'school_id', 'school_stage_id', 'grade_name', 'name', 'sort_order', 'is_active']),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'school_id', 'name', 'sort_order', 'is_active']);

        $structureStages = SchoolStage::query()
            ->where('school_id', $schoolId)
            ->with([
                'grades' => fn ($grades) => $grades
                    ->where('school_id', $schoolId)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->select(['id', 'school_id', 'school_stage_id', 'name', 'sort_order', 'is_active']),
                'classrooms' => fn ($classrooms) => $classrooms
                    ->where('school_id', $schoolId)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->withCount([
                        'students as students_count' => fn ($students) => $students->where('school_id', $schoolId),
                    ])
                    ->select([
                        'id',
                        'school_id',
                        'school_stage_id',
                        'grade_name',
                        'name',
                        'code',
                        'sort_order',
                        'is_active',
                    ]),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'school_id',
                'name',
                'code',
                'sort_order',
                'is_active',
                'school_day_start_time',
                'school_day_end_time',
            ]);

        $teachers = $this->teacherQuery($schoolId)
            ->with(['department:id,name'])
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'school_id',
                'department_id',
                'school_staff_type',
            ]);

        $subjects = SchoolSubject::query()
            ->where('school_id', $schoolId)
            ->with([
                'teacherAssignments' => fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->orderBy('teacher_user_id')
                    ->select(['id', 'school_id', 'school_subject_id', 'teacher_user_id']),
                'teachers:id,name,email,school_id',
            ])
            ->orderBy('name')
            ->get(['id', 'school_id', 'name', 'code', 'branches', 'is_active']);

        $leaveTypes = SchoolLeaveType::query()
            ->where('school_id', $schoolId)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'school_id', 'code', 'name', 'category', 'requires_attachment', 'is_active']);

        $holidayFrom = now()->startOfMonth()->toDateString();
        $holidayTo = now()->addMonths(12)->endOfMonth()->toDateString();
        $holidays = $this->schoolCalendarService
            ->listHolidays($schoolId, [
                'from' => $holidayFrom,
                'to' => $holidayTo,
            ])
            ->values()
            ->all();

        $courseOfferings = SchoolCourseOffering::query()
            ->where('school_id', $schoolId)
            ->with([
                'term:id,name,end_date',
                'stage:id,name',
                'stageGrade:id,school_id,school_stage_id,name,is_active',
                'classroom:id,name,grade_name,school_stage_id',
                'subject:id,name,code',
                'teachingAssignment' => fn ($query) => $query
                    ->select([
                        'id',
                        'school_id',
                        'school_course_offering_id',
                        'teacher_user_id',
                        'is_active',
                        'can_create_exam',
                        'can_update_exam',
                        'can_delete_exam',
                        'can_approve_exam',
                        'can_enter_exam_scores',
                        'can_edit_exam_scores',
                        'can_use_question_bank',
                    ])
                    ->with([
                        'teacher:id,name,email',
                        'classrooms:id,school_id,school_stage_id,grade_name,name,is_active',
                        'attachments' => fn ($attachments) => $attachments
                            ->with(['uploader:id,name'])
                            ->orderByDesc('id'),
                    ]),
                'studyPlanUnits' => fn ($query) => $query
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->with([
                        'lessons' => fn ($lessons) => $lessons
                            ->orderBy('sort_order')
                            ->orderBy('id')
                            ->with([
                                'topics' => fn ($topics) => $topics
                                    ->orderBy('sort_order')
                                    ->orderBy('id')
                                    ->select([
                                        'id',
                                        'school_id',
                                        'school_course_plan_lesson_id',
                                        'name',
                                        'sort_order',
                                        'description',
                                    ]),
                            ])
                            ->select([
                                'id',
                                'school_id',
                                'school_course_plan_unit_id',
                                'name',
                                'sort_order',
                                'description',
                            ]),
                    ])
                    ->select([
                        'id',
                        'school_id',
                        'school_course_offering_id',
                        'branch_name',
                        'name',
                        'sort_order',
                        'start_date',
                        'end_date',
                        'notes',
                    ]),
            ])
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get([
                'id',
                'school_id',
                'school_term_id',
                'school_stage_id',
                'school_stage_grade_id',
                'school_classroom_id',
                'school_subject_id',
                'is_active',
                'usable_in_exams',
                'sort_order',
                'alert_before_term_end_days',
            ]);

        $this->attachTermEndAlertFlags($courseOfferings);
        $approvedCoursesTree = $this->buildApprovedCoursesTree($courseOfferings);
        $courseAssignmentsTree = $this->buildApprovedCoursesTree(
            $courseOfferings->filter(fn ($offering) => (bool) ($offering->is_active ?? false) && (bool) ($offering->usable_in_exams ?? true))
        );

        $teacherAvailabilities = SchoolTeacherAvailability::query()
            ->where('school_id', $schoolId)
            ->whereIn('teacher_user_id', $teachers->pluck('id')->all())
            ->where('is_available', true)
            ->orderBy('teacher_user_id')
            ->orderBy('day_of_week')
            ->orderBy('session_index')
            ->get(['teacher_user_id', 'day_of_week', 'session_index'])
            ->groupBy('teacher_user_id')
            ->map(fn ($rows) => $rows->map(fn ($row) => [
                'day_of_week' => (int) $row->day_of_week,
                'session_index' => (int) $row->session_index,
            ])->values()->all())
            ->all();

        $selectedTerm = $this->resolveSelectedTerm($terms, (int) $request->query('term_id', 0));
        $selectedScope = $this->resolveSelectedScope((string) $request->query('scope', ''));
        $selectedGradeName = trim((string) $request->query('grade_name', ''));
        $requestedStageId = (int) $request->query('stage_id', 0);
        $requestedClassroomId = (int) $request->query('classroom_id', 0);
        $requestedTimetableVersionId = (int) $request->query('version_id', 0);
        $selectedStageId = $requestedStageId > 0 && $stages->contains('id', $requestedStageId)
            ? $requestedStageId
            : 0;
        $selectedClassroomId = 0;

        if ($requestedClassroomId > 0) {
            $selectedClassroom = SchoolClassroom::query()
                ->where('school_id', $schoolId)
                ->whereKey($requestedClassroomId)
                ->first(['id', 'school_stage_id', 'grade_name']);

            if ($selectedClassroom) {
                $selectedClassroomId = (int) $selectedClassroom->id;
                if ($selectedStageId <= 0) {
                    $selectedStageId = (int) $selectedClassroom->school_stage_id;
                }
                if ($selectedGradeName === '') {
                    $selectedGradeName = trim((string) $selectedClassroom->grade_name);
                }
            }
        }

        $selectedTimetableVersionId = null;
        $timetableVersions = collect();
        if ($selectedTerm) {
            $timetableVersions = SchoolTimetableVersion::query()
                ->where('school_id', $schoolId)
                ->where('school_term_id', (int) $selectedTerm->id)
                ->with([
                    'attachments' => fn ($query) => $query
                        ->with(['uploader:id,name'])
                        ->orderByDesc('id'),
                ])
                ->withCount('attachments')
                ->orderByDesc('is_published')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get([
                    'id',
                    'school_id',
                    'school_term_id',
                    'name',
                    'is_published',
                    'published_at',
                    'created_at',
                    'updated_at',
                ]);

            if ($requestedTimetableVersionId > 0 && $timetableVersions->contains('id', $requestedTimetableVersionId)) {
                $selectedTimetableVersionId = $requestedTimetableVersionId;
            }

            $timetableVersions = $timetableVersions
                ->map(fn (SchoolTimetableVersion $version) => [
                    'id' => (int) $version->id,
                    'school_id' => (int) $version->school_id,
                    'school_term_id' => (int) $version->school_term_id,
                    'name' => (string) $version->name,
                    'is_published' => (bool) $version->is_published,
                    'published_at' => optional($version->published_at)->toISOString(),
                    'created_at' => optional($version->created_at)->toISOString(),
                    'updated_at' => optional($version->updated_at)->toISOString(),
                    'attachments_count' => (int) ($version->attachments_count ?? 0),
                    'attachments' => $version->attachments
                        ->map(fn ($attachment) => $this->attachmentService->serializeForUi($attachment))
                        ->values()
                        ->all(),
                ])
                ->values();
        }

        $scheduleEntries = collect();
        if ($selectedTerm) {
            $scheduleEntries = SchoolClassSchedule::query()
                ->where('school_id', $schoolId)
                ->where('school_term_id', (int) $selectedTerm->id)
                ->when($selectedScope !== '', fn ($query) => $query->where('schedule_scope', $selectedScope))
                ->when($selectedStageId > 0, fn ($query) => $query->where('school_stage_id', $selectedStageId))
                ->when($selectedGradeName !== '', fn ($query) => $query->whereHas(
                    'classroom',
                    fn ($classrooms) => $classrooms
                        ->where('school_id', $schoolId)
                        ->where('grade_name', $selectedGradeName)
                ))
                ->when($selectedClassroomId > 0, fn ($query) => $query->where('school_classroom_id', $selectedClassroomId))
                ->when($selectedTimetableVersionId !== null, fn ($query) => $query->where('school_timetable_version_id', $selectedTimetableVersionId))
                ->with([
                    'stage:id,name',
                    'classroom:id,name,grade_name,school_stage_id',
                    'subject:id,name,code',
                    'teacher:id,name,email',
                    'timetableVersion:id,name,is_published',
                ])
                ->orderByRaw('CASE schedule_scope WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END', [
                    SchoolClassSchedule::SCOPE_WEEKLY,
                    SchoolClassSchedule::SCOPE_MONTHLY,
                ])
                ->orderBy('day_of_week')
                ->orderBy('day_of_month')
                ->orderBy('session_date')
                ->orderBy('session_index')
                ->get([
                    'id',
                    'school_id',
                    'school_term_id',
                    'school_timetable_version_id',
                    'school_stage_id',
                    'school_classroom_id',
                    'school_subject_id',
                    'teacher_user_id',
                    'schedule_scope',
                    'day_of_week',
                    'day_of_month',
                    'session_date',
                    'session_index',
                    'starts_at',
                    'ends_at',
                    'notes',
                    'is_active',
                ]);
        }

        $weeklyGridEntries = collect();
        if ($selectedTerm && $selectedStageId > 0 && $selectedClassroomId > 0) {
            $weeklyGridEntries = $this->weeklyGridSchedulesQuery(
                schoolId: $schoolId,
                termId: (int) $selectedTerm->id,
                stageId: $selectedStageId,
                classroomId: $selectedClassroomId,
                timetableVersionId: $selectedTimetableVersionId,
            )
                ->with([
                    'stage:id,name',
                    'classroom:id,name,grade_name,school_stage_id',
                    'subject:id,name,code',
                    'teacher:id,name,email',
                    'timetableVersion:id,name,is_published',
                ])
                ->orderBy('day_of_week')
                ->orderBy('session_index')
                ->get([
                    'id',
                    'school_id',
                    'school_term_id',
                    'school_timetable_version_id',
                    'school_stage_id',
                    'school_classroom_id',
                    'school_subject_id',
                    'teacher_user_id',
                    'schedule_scope',
                    'day_of_week',
                    'day_of_month',
                    'session_date',
                    'session_index',
                    'starts_at',
                    'ends_at',
                    'notes',
                    'is_active',
                ]);
        }

        return Inertia::render('School/AcademicPlanning', [
            'school' => $school,
            'academicYears' => $academicYears,
            'terms' => $terms,
            'stages' => $stages,
            'structureStages' => $structureStages,
            'teachers' => $teachers,
            'teacherAvailabilities' => $teacherAvailabilities,
            'subjects' => $subjects,
            'leaveTypes' => $leaveTypes,
            'calendarSettings' => $calendarSettings,
            'holidays' => $holidays,
            'courseOfferings' => $courseOfferings,
            'approvedCoursesTree' => $approvedCoursesTree,
            'courseAssignmentsTree' => $courseAssignmentsTree,
            'timetableVersions' => $timetableVersions,
            'schedules' => $scheduleEntries,
            'selectedTermId' => $selectedTerm?->id,
            'selectedScope' => $selectedScope,
            'selectedStageId' => $selectedStageId > 0 ? $selectedStageId : null,
            'selectedGradeName' => $selectedGradeName !== '' ? $selectedGradeName : null,
            'selectedClassroomId' => $selectedClassroomId > 0 ? $selectedClassroomId : null,
            'selectedVersionId' => $selectedTimetableVersionId,
            'weeklyGrid' => [
                'entries' => $weeklyGridEntries,
            ],
            'selectedPage' => $this->resolveRequestedPlanningPage($request) ?? 'stages',
            'defaultDataProvisioning' => $school
                ? $this->schoolDefaultDataProvisioningService->schoolProvisioningStatus(
                    $school,
                    $user?->canImportSchoolDefaultData() ?? false
                )
                : null,
            'scopeOptions' => [
                ['value' => SchoolClassSchedule::SCOPE_WEEKLY, 'label' => 'أسبوعي'],
                ['value' => SchoolClassSchedule::SCOPE_MONTHLY, 'label' => 'شهري'],
                ['value' => SchoolClassSchedule::SCOPE_TERM, 'label' => 'ترمي كامل'],
            ],
            'weekDays' => [
                ['value' => 0, 'label' => 'الأحد'],
                ['value' => 1, 'label' => 'الاثنين'],
                ['value' => 2, 'label' => 'الثلاثاء'],
                ['value' => 3, 'label' => 'الأربعاء'],
                ['value' => 4, 'label' => 'الخميس'],
                ['value' => 5, 'label' => 'الجمعة'],
                ['value' => 6, 'label' => 'السبت'],
            ],
            'isManager' => $user?->hasSystemRole('school_manager') ?? false,
            'permissions' => [
                'can_manage_student_structure' => $user?->canManageStudentStructure() ?? false,
                'can_manage_student_attendance' => $user?->canManageStudentAttendance() ?? false,
                'can_manage_academic_planning' => $user?->canManageAcademicPlanning() ?? false,
                'can_manage_student_leaves' => $user?->canManageStudentLeaves() ?? false,
                'can_manage_leave_types' => $user?->canManageLeaveTypes() ?? false,
                'can_manage_school_calendar' => $user?->canManageSchoolCalendar() ?? false,
                'can_manage_school_holidays' => $user?->canManageSchoolHolidays() ?? false,
                'can_manage_teaching_assignments' => $user?->hasSystemRole('school_manager') ?? false,
            ],
            'scheduleRules' => [
                'enforce_course_offerings' => config('features.course_offerings.enforce_for_scheduling', false),
            ],
        ]);
    }

    private function buildApprovedCoursesTree(Collection $courseOfferings): array
    {
        $tree = [];

        $normalizeName = fn ($value, string $fallback = 'غير محدد'): string => trim((string) ($value ?? '')) !== ''
            ? trim((string) $value)
            : $fallback;

        $ensureStage = function (int $stageId, string $stageName) use (&$tree): string {
            $stageKey = $stageId > 0 ? (string) $stageId : 'stage:unknown';

            if (!isset($tree[$stageKey])) {
                $tree[$stageKey] = [
                    'id' => $stageId > 0 ? $stageId : null,
                    'key' => $stageKey,
                    'name' => $stageName,
                    'grades_count' => 0,
                    'terms_count' => 0,
                    'subjects_count' => 0,
                    'courses_count' => 0,
                    'active_courses_count' => 0,
                    'inactive_courses_count' => 0,
                    'assigned_classrooms_count' => 0,
                    'teachers_count' => 0,
                    'grades' => [],
                    '_subject_ids' => [],
                    '_classroom_ids' => [],
                    '_teacher_ids' => [],
                ];
            }

            return $stageKey;
        };

        $ensureGrade = function (string $stageKey, int $gradeId, string $gradeName) use (&$tree): string {
            $gradeKey = $gradeId > 0 ? (string) $gradeId : 'grade:'.$gradeName;

            if (!isset($tree[$stageKey]['grades'][$gradeKey])) {
                $tree[$stageKey]['grades'][$gradeKey] = [
                    'id' => $gradeId > 0 ? $gradeId : null,
                    'key' => $tree[$stageKey]['key'].':'.$gradeKey,
                    'name' => $gradeName,
                    'terms_count' => 0,
                    'subjects_count' => 0,
                    'courses_count' => 0,
                    'active_courses_count' => 0,
                    'inactive_courses_count' => 0,
                    'assigned_classrooms_count' => 0,
                    'teachers_count' => 0,
                    'terms' => [],
                    '_subject_ids' => [],
                    '_classroom_ids' => [],
                    '_teacher_ids' => [],
                ];
            }

            return $gradeKey;
        };

        $ensureTerm = function (string $stageKey, string $gradeKey, int $termId, string $termName) use (&$tree): string {
            $termKey = $termId > 0 ? (string) $termId : 'term:'.$termName;

            if (!isset($tree[$stageKey]['grades'][$gradeKey]['terms'][$termKey])) {
                $tree[$stageKey]['grades'][$gradeKey]['terms'][$termKey] = [
                    'id' => $termId > 0 ? $termId : null,
                    'key' => $tree[$stageKey]['grades'][$gradeKey]['key'].':'.$termKey,
                    'name' => $termName,
                    'subjects_count' => 0,
                    'courses_count' => 0,
                    'active_courses_count' => 0,
                    'inactive_courses_count' => 0,
                    'assigned_classrooms_count' => 0,
                    'teachers_count' => 0,
                    'courses' => [],
                    '_subject_ids' => [],
                    '_classroom_ids' => [],
                    '_teacher_ids' => [],
                ];
            }

            return $termKey;
        };

        $addCourseStats = function (array &$node, bool $isActive, int $subjectId, int $teacherId, array $assignedClassroomIds): void {
            $node['courses_count']++;
            $node['active_courses_count'] += $isActive ? 1 : 0;
            $node['inactive_courses_count'] += $isActive ? 0 : 1;

            if ($subjectId > 0) {
                $node['_subject_ids'][$subjectId] = true;
            }

            if ($teacherId > 0) {
                $node['_teacher_ids'][$teacherId] = true;
            }

            foreach ($assignedClassroomIds as $classroomId) {
                $node['_classroom_ids'][(int) $classroomId] = true;
            }
        };

        foreach ($courseOfferings as $offering) {
            $stageId = (int) ($offering->school_stage_id ?? $offering->stage?->id ?? 0);
            $stageName = $normalizeName($offering->stage?->name ?? null);
            $gradeId = (int) ($offering->school_stage_grade_id ?? $offering->stageGrade?->id ?? 0);
            $gradeName = $normalizeName($offering->stageGrade?->name ?? $offering->classroom?->grade_name ?? null);
            $termId = (int) ($offering->school_term_id ?? $offering->term?->id ?? 0);
            $termName = $normalizeName($offering->term?->name ?? null);
            $subjectId = (int) ($offering->school_subject_id ?? $offering->subject?->id ?? 0);
            $teacherId = (int) ($offering->teachingAssignment?->teacher_user_id ?? 0);
            $assignedClassrooms = $offering->teachingAssignment?->classrooms ?? collect();
            $assignedClassroomIds = $assignedClassrooms
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if (empty($assignedClassroomIds) && (int) ($offering->school_classroom_id ?? 0) > 0) {
                $assignedClassroomIds[] = (int) $offering->school_classroom_id;
            }

            $stageKey = $ensureStage($stageId, $stageName);
            $gradeKey = $ensureGrade($stageKey, $gradeId, $gradeName);
            $termKey = $ensureTerm($stageKey, $gradeKey, $termId, $termName);
            $isActive = (bool) ($offering->is_active ?? false);

            $course = $offering->toArray();
            $course['stage_name'] = $stageName;
            $course['grade_name'] = $gradeName;
            $course['term_name'] = $termName;
            $course['subject_name'] = $normalizeName($offering->subject?->name ?? null);
            $course['teacher_name'] = $normalizeName($offering->teachingAssignment?->teacher?->name ?? null, 'غير مسند');
            $course['assigned_classrooms_count'] = count($assignedClassroomIds);

            $stage = &$tree[$stageKey];
            $grade = &$stage['grades'][$gradeKey];
            $term = &$grade['terms'][$termKey];

            $term['courses'][] = $course;
            $addCourseStats($stage, $isActive, $subjectId, $teacherId, $assignedClassroomIds);
            $addCourseStats($grade, $isActive, $subjectId, $teacherId, $assignedClassroomIds);
            $addCourseStats($term, $isActive, $subjectId, $teacherId, $assignedClassroomIds);
            unset($stage, $grade, $term);
        }

        foreach ($tree as &$stage) {
            foreach ($stage['grades'] as &$grade) {
                foreach ($grade['terms'] as &$term) {
                    $term['subjects_count'] = count($term['_subject_ids']);
                    $term['assigned_classrooms_count'] = count($term['_classroom_ids']);
                    $term['teachers_count'] = count($term['_teacher_ids']);
                    unset($term['_subject_ids'], $term['_classroom_ids'], $term['_teacher_ids']);
                }

                $grade['terms'] = array_values($grade['terms']);
                $grade['terms_count'] = count($grade['terms']);
                $grade['subjects_count'] = count($grade['_subject_ids']);
                $grade['assigned_classrooms_count'] = count($grade['_classroom_ids']);
                $grade['teachers_count'] = count($grade['_teacher_ids']);
                unset($grade['_subject_ids'], $grade['_classroom_ids'], $grade['_teacher_ids']);
            }

            $stage['grades'] = array_values($stage['grades']);
            $stage['grades_count'] = count($stage['grades']);
            $stage['terms_count'] = array_sum(array_column($stage['grades'], 'terms_count'));
            $stage['subjects_count'] = count($stage['_subject_ids']);
            $stage['assigned_classrooms_count'] = count($stage['_classroom_ids']);
            $stage['teachers_count'] = count($stage['_teacher_ids']);
            unset($stage['_subject_ids'], $stage['_classroom_ids'], $stage['_teacher_ids']);
        }
        unset($stage, $grade, $term);

        return array_values($tree);
    }

    public function storeYear(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $userId = (int) $request->user()->id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('school_academic_years', 'name')
                    ->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $startsOn = Carbon::parse($validated['starts_on'])->toDateString();
        $endsOn = Carbon::parse($validated['ends_on'])->toDateString();

        $this->schoolCalendarService->ensureAcademicYearRangeIntegrity($schoolId, $startsOn, $endsOn);

        SchoolAcademicYear::query()->create([
            'school_id' => $schoolId,
            'name' => trim((string) $validated['name']),
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return back();
    }

    public function updateYear(Request $request, SchoolAcademicYear $schoolAcademicYear): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureAcademicYearInSchool($schoolAcademicYear, $schoolId);
        $userId = (int) $request->user()->id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('school_academic_years', 'name')
                    ->where(fn ($query) => $query->where('school_id', $schoolId))
                    ->ignore($schoolAcademicYear->id),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $startsOn = Carbon::parse($validated['starts_on'])->toDateString();
        $endsOn = Carbon::parse($validated['ends_on'])->toDateString();
        $currentStartsOn = $schoolAcademicYear->starts_on?->toDateString();
        $currentEndsOn = $schoolAcademicYear->ends_on?->toDateString();

        if ($startsOn !== $currentStartsOn || $endsOn !== $currentEndsOn) {
            $this->schoolCalendarService->ensureAcademicYearRangeIntegrity(
                $schoolId,
                $startsOn,
                $endsOn,
                (int) $schoolAcademicYear->id
            );
        }

        $schoolAcademicYear->update([
            'name' => trim((string) $validated['name']),
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'updated_by' => $userId,
        ]);

        return back();
    }

    public function destroyYear(Request $request, SchoolAcademicYear $schoolAcademicYear): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureAcademicYearInSchool($schoolAcademicYear, $schoolId);

        $schoolAcademicYear->delete();

        return back();
    }

    public function storeTerm(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);

        $validated = $request->validate([
            'school_academic_year_id' => [
                'nullable',
                Rule::exists('school_academic_years', 'id')
                    ->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('school_terms', 'name')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $startDate = Carbon::parse($validated['start_date'])->toDateString();
        $endDate = Carbon::parse($validated['end_date'])->toDateString();
        $academicYearId = isset($validated['school_academic_year_id']) ? (int) $validated['school_academic_year_id'] : null;

        $this->schoolCalendarService->ensureTermRangeIntegrity(
            $schoolId,
            $academicYearId,
            $startDate,
            $endDate
        );

        SchoolTerm::query()->create([
            'school_id' => $schoolId,
            'school_academic_year_id' => $academicYearId,
            'name' => trim((string) $validated['name']),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back();
    }

    public function updateTerm(Request $request, SchoolTerm $schoolTerm): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureTermInSchool($schoolTerm, $schoolId);

        $validated = $request->validate([
            'school_academic_year_id' => [
                'nullable',
                Rule::exists('school_academic_years', 'id')
                    ->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('school_terms', 'name')
                    ->where(fn ($query) => $query->where('school_id', $schoolId))
                    ->ignore($schoolTerm->id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $startDate = Carbon::parse($validated['start_date'])->toDateString();
        $endDate = Carbon::parse($validated['end_date'])->toDateString();
        $academicYearId = isset($validated['school_academic_year_id']) ? (int) $validated['school_academic_year_id'] : null;
        $currentStartDate = $schoolTerm->start_date?->toDateString();
        $currentEndDate = $schoolTerm->end_date?->toDateString();
        $currentAcademicYearId = $schoolTerm->school_academic_year_id !== null
            ? (int) $schoolTerm->school_academic_year_id
            : null;

        if (
            $startDate !== $currentStartDate
            || $endDate !== $currentEndDate
            || $academicYearId !== $currentAcademicYearId
        ) {
            $this->schoolCalendarService->ensureTermRangeIntegrity(
                $schoolId,
                $academicYearId,
                $startDate,
                $endDate,
                (int) $schoolTerm->id
            );
        }

        $schoolTerm->update([
            'school_academic_year_id' => $academicYearId,
            'name' => trim((string) $validated['name']),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back();
    }

    public function destroyTerm(Request $request, SchoolTerm $schoolTerm): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureTermInSchool($schoolTerm, $schoolId);

        $schoolTerm->delete();

        return back();
    }

    public function storeTimetableVersion(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $user = $request->user();
        $userId = (int) $user->id;

        $validated = $request->validate([
            'school_term_id' => [
                'required',
                Rule::exists('school_terms', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('school_timetable_versions', 'name')
                    ->where(fn ($query) => $query
                        ->where('school_id', $schoolId)
                        ->where('school_term_id', (int) $request->input('school_term_id'))),
            ],
        ]);

        $request->validate(
            $this->attachmentService->uploadValidationRules(),
            $this->attachmentService->uploadValidationMessages()
        );

        $version = DB::transaction(function () use ($schoolId, $validated, $userId, $request, $user): SchoolTimetableVersion {
            $version = SchoolTimetableVersion::query()->create([
                'school_id' => $schoolId,
                'school_term_id' => (int) $validated['school_term_id'],
                'name' => trim((string) $validated['name']),
                'is_published' => false,
                'published_at' => null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->attachmentService->storeManyForAttachable(
                $version,
                $request->file('attachments', []),
                $user,
                [
                    'school_id' => $schoolId,
                    'module' => 'schedules',
                    'action_type' => 'schedule_document',
                    'metadata' => [
                        'school_timetable_version_id' => (int) $version->id,
                        'school_term_id' => (int) $version->school_term_id,
                    ],
                    'request' => $request,
                ]
            );

            return $version;
        });

        return redirect()->route('school.academic_planning.index', $this->planningIndexRouteParams($request, [
            'term_id' => (int) $version->school_term_id,
        ]));
    }

    public function updateTimetableVersion(Request $request, SchoolTimetableVersion $schoolTimetableVersion): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureTimetableVersionInSchool($schoolTimetableVersion, $schoolId);
        $user = $request->user();
        $userId = (int) $user->id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('school_timetable_versions', 'name')
                    ->where(fn ($query) => $query
                        ->where('school_id', $schoolId)
                        ->where('school_term_id', (int) $schoolTimetableVersion->school_term_id))
                    ->ignore((int) $schoolTimetableVersion->id),
            ],
        ]);

        $request->validate(
            $this->attachmentService->uploadValidationRules(),
            $this->attachmentService->uploadValidationMessages()
        );

        DB::transaction(function () use ($schoolTimetableVersion, $validated, $userId, $request, $user, $schoolId): void {
            $schoolTimetableVersion->update([
                'name' => trim((string) $validated['name']),
                'updated_by' => $userId,
            ]);

            $this->attachmentService->storeManyForAttachable(
                $schoolTimetableVersion,
                $request->file('attachments', []),
                $user,
                [
                    'school_id' => $schoolId,
                    'module' => 'schedules',
                    'action_type' => 'schedule_document',
                    'metadata' => [
                        'school_timetable_version_id' => (int) $schoolTimetableVersion->id,
                        'school_term_id' => (int) $schoolTimetableVersion->school_term_id,
                    ],
                    'request' => $request,
                ]
            );
        });

        return back();
    }

    public function publishTimetableVersion(Request $request, SchoolTimetableVersion $schoolTimetableVersion): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureTimetableVersionInSchool($schoolTimetableVersion, $schoolId);
        $userId = (int) $request->user()->id;

        DB::transaction(function () use ($schoolId, $schoolTimetableVersion, $userId): void {
            SchoolTimetableVersion::query()
                ->where('school_id', $schoolId)
                ->where('school_term_id', (int) $schoolTimetableVersion->school_term_id)
                ->update([
                    'is_published' => false,
                    'published_at' => null,
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);

            $schoolTimetableVersion->update([
                'is_published' => true,
                'published_at' => now(),
                'updated_by' => $userId,
            ]);
        });

        return redirect()->route('school.academic_planning.index', $this->planningIndexRouteParams($request, [
            'term_id' => (int) $schoolTimetableVersion->school_term_id,
            'version_id' => (int) $schoolTimetableVersion->id,
        ]));
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $userId = (int) $request->user()->id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('school_subjects', 'name')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('school_subjects', 'code')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'branches' => ['nullable', 'array'],
            'branches.*' => ['nullable', 'string', 'max:150'],
            'teacher_user_ids' => ['nullable', 'array'],
            'teacher_user_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('is_active', true)),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $teacherIds = $this->normalizeTeacherIds($validated['teacher_user_ids'] ?? []);
        $subjectBranches = $this->normalizeSubjectBranches($validated['branches'] ?? []);
        $this->assertTeacherIdsAssignable($schoolId, $teacherIds);

        try {
            $subject = DB::transaction(function () use ($validated, $schoolId, $teacherIds, $subjectBranches): SchoolSubject {
                $providedCode = $this->normalizeSubjectCode($validated['code'] ?? null);
                $resolvedCode = $providedCode ?: $this->generateScopedCode('school_subjects', 'code', 'SUB', $schoolId);

                $subject = SchoolSubject::query()->create([
                    'school_id' => $schoolId,
                    'name' => trim((string) $validated['name']),
                    'code' => $resolvedCode,
                    'branches' => $subjectBranches->all(),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                ]);

                $this->syncSubjectTeacherAssignments($schoolId, (int) $subject->id, $teacherIds);

                return $subject;
            });

            $this->auditLogger->log(
                action: 'academic_planning.subject.created',
                entityType: 'school_subject',
                entityId: (int) $subject->id,
                payload: [
                    'school_id' => $schoolId,
                    'subject' => $subject->only(['name', 'code', 'branches', 'is_active']),
                    'teachers_assigned' => $teacherIds->all(),
                ],
                request: $request,
                userId: $userId
            );
        } catch (QueryException $exception) {
            $this->rethrowDuplicateSubjectCode($exception);
            throw $exception;
        }

        return back();
    }

    public function updateSubject(Request $request, SchoolSubject $schoolSubject): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureSubjectInSchool($schoolSubject, $schoolId);
        $userId = (int) $request->user()->id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('school_subjects', 'name')
                    ->where(fn ($query) => $query->where('school_id', $schoolId))
                    ->ignore($schoolSubject->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('school_subjects', 'code')
                    ->where(fn ($query) => $query->where('school_id', $schoolId))
                    ->ignore($schoolSubject->id),
            ],
            'branches' => ['nullable', 'array'],
            'branches.*' => ['nullable', 'string', 'max:150'],
            'teacher_user_ids' => ['nullable', 'array'],
            'teacher_user_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('is_active', true)),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $before = $schoolSubject->only(['name', 'code', 'branches', 'is_active']);
        $beforeTeacherIds = $this->fetchSubjectTeacherIds($schoolId, (int) $schoolSubject->id);
        $subjectBranches = $this->normalizeSubjectBranches($validated['branches'] ?? ($schoolSubject->branches ?? []));
        $hasTeacherPayload = array_key_exists('teacher_user_ids', $validated);
        $afterTeacherIds = $hasTeacherPayload
            ? $this->normalizeTeacherIds($validated['teacher_user_ids'] ?? [])
            : $beforeTeacherIds;

        if ($hasTeacherPayload) {
            $this->assertTeacherIdsAssignable($schoolId, $afterTeacherIds);
        }

        try {
            DB::transaction(function () use ($validated, $schoolId, $schoolSubject, $subjectBranches, $hasTeacherPayload, $afterTeacherIds): void {
                $providedCode = $this->normalizeSubjectCode($validated['code'] ?? null);
                $resolvedCode = $providedCode ?: ($schoolSubject->code ?: $this->generateScopedCode('school_subjects', 'code', 'SUB', $schoolId));

                $schoolSubject->update([
                    'name' => trim((string) $validated['name']),
                    'code' => $resolvedCode,
                    'branches' => $subjectBranches->all(),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                ]);

                if ($hasTeacherPayload) {
                    $this->syncSubjectTeacherAssignments($schoolId, (int) $schoolSubject->id, $afterTeacherIds);
                }
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateSubjectCode($exception);
            throw $exception;
        }

        $schoolSubject->refresh();
        $teachersAdded = $afterTeacherIds->diff($beforeTeacherIds)->values()->all();
        $teachersRemoved = $beforeTeacherIds->diff($afterTeacherIds)->values()->all();

        $this->auditLogger->log(
            action: 'academic_planning.subject.updated',
            entityType: 'school_subject',
            entityId: (int) $schoolSubject->id,
            payload: [
                'school_id' => $schoolId,
                'before' => $before,
                'after' => $schoolSubject->only(['name', 'code', 'branches', 'is_active']),
                'teachers_before' => $beforeTeacherIds->all(),
                'teachers_after' => $afterTeacherIds->all(),
                'teachers_added' => $teachersAdded,
                'teachers_removed' => $teachersRemoved,
            ],
            request: $request,
            userId: $userId
        );

        return back();
    }

    public function destroySubject(Request $request, SchoolSubject $schoolSubject): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureSubjectInSchool($schoolSubject, $schoolId);

        $schoolSubject->delete();

        return back();
    }

    public function syncSubjectTeachers(Request $request, SchoolSubject $schoolSubject): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureSubjectInSchool($schoolSubject, $schoolId);
        $userId = (int) $request->user()->id;

        $validated = $request->validate([
            'teacher_user_ids' => ['nullable', 'array'],
            'teacher_user_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('is_active', true)),
            ],
        ]);

        $teacherIds = collect($validated['teacher_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($teacherIds->isNotEmpty()) {
            $validCount = $this->teacherQuery($schoolId)
                ->whereIn('id', $teacherIds->all())
                ->count();

            if ($validCount !== $teacherIds->count()) {
                throw ValidationException::withMessages([
                    'teacher_user_ids' => 'لا يمكن حفظ الإسناد لأن بعض المعلمين المحددين غير صالحين أو لا ينتمون إلى نفس المدرسة.',
                ]);
            }
        }

        $beforeTeacherIds = $this->fetchSubjectTeacherIds($schoolId, (int) $schoolSubject->id);

        DB::transaction(function () use ($teacherIds, $schoolId, $schoolSubject): void {
            $this->syncSubjectTeacherAssignments($schoolId, (int) $schoolSubject->id, $teacherIds);
        });

        $teachersAdded = $teacherIds->diff($beforeTeacherIds)->values()->all();
        $teachersRemoved = $beforeTeacherIds->diff($teacherIds)->values()->all();

        $this->auditLogger->log(
            action: 'academic_planning.subject.teachers.synced',
            entityType: 'school_subject',
            entityId: (int) $schoolSubject->id,
            payload: [
                'school_id' => $schoolId,
                'teachers_before' => $beforeTeacherIds->all(),
                'teachers_after' => $teacherIds->all(),
                'teachers_added' => $teachersAdded,
                'teachers_removed' => $teachersRemoved,
            ],
            request: $request,
            userId: $userId
        );

        return back();
    }

    public function syncTeacherAvailability(Request $request, User $teacher): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureTeacherCanBeAssigned($schoolId, (int) $teacher->id);
        $userId = (int) $request->user()->id;

        $validated = $request->validate([
            'slots' => ['nullable', 'array'],
            'slots.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'slots.*.session_index' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $normalizedSlots = collect($validated['slots'] ?? [])
            ->map(function ($slot): array {
                return [
                    'day_of_week' => (int) data_get($slot, 'day_of_week'),
                    'session_index' => (int) data_get($slot, 'session_index'),
                ];
            })
            ->filter(fn ($slot) => $slot['session_index'] > 0)
            ->unique(fn ($slot) => $slot['day_of_week'].':'.$slot['session_index'])
            ->values();

        DB::transaction(function () use ($schoolId, $teacher, $normalizedSlots, $userId): void {
            SchoolTeacherAvailability::query()
                ->where('school_id', $schoolId)
                ->where('teacher_user_id', (int) $teacher->id)
                ->delete();

            if ($normalizedSlots->isEmpty()) {
                return;
            }

            $rows = $normalizedSlots->map(fn ($slot) => [
                'school_id' => $schoolId,
                'teacher_user_id' => (int) $teacher->id,
                'day_of_week' => $slot['day_of_week'],
                'session_index' => $slot['session_index'],
                'is_available' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            SchoolTeacherAvailability::query()->insert($rows);
        });

        return back();
    }

    public function storeCourseOffering(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $userId = (int) $request->user()->id;

        $validated = $this->validateCourseOfferingPayload($request, $schoolId);

        DB::transaction(function () use ($schoolId, $userId, $validated): void {
            $offering = SchoolCourseOffering::query()->create([
                'school_id' => $schoolId,
                'school_term_id' => (int) $validated['school_term_id'],
                'school_stage_id' => (int) $validated['school_stage_id'],
                'school_stage_grade_id' => (int) $validated['school_stage_grade_id'],
                'school_classroom_id' => (int) $validated['school_classroom_id'],
                'school_subject_id' => (int) $validated['school_subject_id'],
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'usable_in_exams' => (bool) ($validated['usable_in_exams'] ?? true),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'alert_before_term_end_days' => (int) ($validated['alert_before_term_end_days'] ?? 0),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->syncCourseOfferingStudyPlan(
                offeringId: (int) $offering->id,
                schoolId: $schoolId,
                userId: $userId,
                units: $validated['study_plan_units'] ?? []
            );
        });

        return back()->with('success', 'تم إنشاء المقرر بنجاح.');
    }

    public function updateCourseOffering(Request $request, SchoolCourseOffering $schoolCourseOffering): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureCourseOfferingInSchool($schoolCourseOffering, $schoolId);
        $userId = (int) $request->user()->id;

        $validated = $this->validateCourseOfferingPayload($request, $schoolId, (int) $schoolCourseOffering->id);

        DB::transaction(function () use ($schoolCourseOffering, $validated, $userId, $schoolId): void {
            $schoolCourseOffering->update([
                'school_term_id' => (int) $validated['school_term_id'],
                'school_stage_id' => (int) $validated['school_stage_id'],
                'school_stage_grade_id' => (int) $validated['school_stage_grade_id'],
                'school_classroom_id' => (int) $validated['school_classroom_id'],
                'school_subject_id' => (int) $validated['school_subject_id'],
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'usable_in_exams' => (bool) ($validated['usable_in_exams'] ?? true),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'alert_before_term_end_days' => (int) ($validated['alert_before_term_end_days'] ?? 0),
                'updated_by' => $userId,
            ]);

            $this->syncCourseOfferingStudyPlan(
                offeringId: (int) $schoolCourseOffering->id,
                schoolId: $schoolId,
                userId: $userId,
                units: $validated['study_plan_units'] ?? []
            );
        });

        return back()->with('success', 'تم تعديل المقرر بنجاح.');
    }

    public function destroyCourseOffering(Request $request, SchoolCourseOffering $schoolCourseOffering): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureCourseOfferingInSchool($schoolCourseOffering, $schoolId);

        $schoolCourseOffering->delete();

        return back();
    }

    public function syncCourseOfferingAssignment(Request $request, SchoolCourseOffering $schoolCourseOffering): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureCourseOfferingInSchool($schoolCourseOffering, $schoolId);
        $userId = (int) $request->user()->id;

        $validated = $request->validate(
            array_merge([
                'teacher_user_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('users', 'id')->where(fn ($query) => $query
                        ->where('school_id', $schoolId)
                        ->where('is_active', true)),
                ],
                'school_classroom_ids' => ['nullable', 'array'],
                'school_classroom_ids.*' => [
                    'integer',
                    'distinct',
                    Rule::exists('school_classrooms', 'id')->where(fn ($query) => $query
                        ->where('school_id', $schoolId)
                        ->where('school_stage_id', (int) $schoolCourseOffering->school_stage_id)
                        ->where('is_active', true)),
                ],
                'can_create_exam' => ['nullable', 'boolean'],
                'can_update_exam' => ['nullable', 'boolean'],
                'can_delete_exam' => ['nullable', 'boolean'],
                'can_approve_exam' => ['nullable', 'boolean'],
                'can_enter_exam_scores' => ['nullable', 'boolean'],
                'can_edit_exam_scores' => ['nullable', 'boolean'],
                'can_use_question_bank' => ['nullable', 'boolean'],
            ], $this->attachmentService->uploadValidationRules()),
            $this->attachmentService->uploadValidationMessages()
        );

        $teacherUserId = (int) ($validated['teacher_user_id'] ?? 0);
        $attachmentFiles = collect($validated['attachments'] ?? [])
            ->filter(fn ($file) => $file instanceof \Illuminate\Http\UploadedFile)
            ->values()
            ->all();
        $availableClassroomIds = $this->classroomsForOfferingScope($schoolId, $schoolCourseOffering)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
        $classroomIds = collect($validated['school_classroom_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($teacherUserId <= 0 && $attachmentFiles !== []) {
            throw ValidationException::withMessages([
                'attachments' => 'يجب اختيار المعلم أولًا قبل رفع مرفقات التحضير.',
            ]);
        }

        if ($teacherUserId > 0) {
            $this->ensureTeacherSubjectAssignment(
                $schoolId,
                (int) $schoolCourseOffering->school_subject_id,
                $teacherUserId
            );

            if ($classroomIds->isEmpty()) {
                $fallbackClassroomId = (int) ($schoolCourseOffering->school_classroom_id ?? 0);
                if ($fallbackClassroomId > 0 && $availableClassroomIds->contains($fallbackClassroomId)) {
                    $classroomIds = collect([$fallbackClassroomId]);
                } elseif ($availableClassroomIds->isNotEmpty()) {
                    $classroomIds = collect([(int) $availableClassroomIds->first()]);
                }
            }

            if ($classroomIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'school_classroom_ids' => 'لا يمكن إسناد المقرر بدون اختيار فصل أو شعبة واحدة على الأقل.',
                ]);
            }

            $invalidClassroomIds = $classroomIds
                ->filter(fn ($id) => !$availableClassroomIds->contains((int) $id))
                ->values()
                ->all();

            if (count($invalidClassroomIds) > 0) {
                throw ValidationException::withMessages([
                    'school_classroom_ids' => 'لا يمكن إسناد المقرر لأن الفصول المختارة لا تنتمي إلى نفس المرحلة والصف.',
                ]);
            }
        }

        DB::transaction(function () use ($request, $schoolId, $schoolCourseOffering, $teacherUserId, $userId, $validated, $classroomIds, $attachmentFiles): void {
            $existingAssignment = SchoolTeachingAssignment::query()
                ->where('school_id', $schoolId)
                ->where('school_course_offering_id', (int) $schoolCourseOffering->id)
                ->with(['attachments'])
                ->first();

            if ($teacherUserId <= 0) {
                if ($existingAssignment instanceof SchoolTeachingAssignment) {
                    $existingAssignment->attachments->each(fn ($attachment) => $this->attachmentService->deleteInstitutionalAttachment(
                        $attachment,
                        $request,
                        $userId
                    ));
                    $existingAssignment->classrooms()->detach();
                    $existingAssignment->delete();
                }

                return;
            }

            $assignment = $existingAssignment instanceof SchoolTeachingAssignment
                ? $existingAssignment
                : new SchoolTeachingAssignment([
                    'school_id' => $schoolId,
                    'school_course_offering_id' => (int) $schoolCourseOffering->id,
                    'created_by' => $userId,
                ]);

            $assignment->fill([
                'teacher_user_id' => $teacherUserId,
                'is_active' => true,
                'can_create_exam' => (bool) ($validated['can_create_exam'] ?? true),
                'can_update_exam' => (bool) ($validated['can_update_exam'] ?? true),
                'can_delete_exam' => (bool) ($validated['can_delete_exam'] ?? true),
                'can_approve_exam' => (bool) ($validated['can_approve_exam'] ?? false),
                'can_enter_exam_scores' => (bool) ($validated['can_enter_exam_scores'] ?? true),
                'can_edit_exam_scores' => (bool) ($validated['can_edit_exam_scores'] ?? true),
                'can_use_question_bank' => (bool) ($validated['can_use_question_bank'] ?? true),
                'updated_by' => $userId,
            ]);
            $assignment->save();

            $assignment->classrooms()->sync(
                $classroomIds->mapWithKeys(fn ($classroomId): array => [
                    (int) $classroomId => ['school_id' => $schoolId],
                ])->all()
            );

            $this->attachmentService->storeManyForAttachable(
                $assignment,
                $attachmentFiles,
                $request->user(),
                [
                    'request' => $request,
                    'school_id' => $schoolId,
                    'module' => 'teacher_preparations',
                    'action_type' => 'course_preparation_attachment',
                    'metadata' => [
                        'school_course_offering_id' => (int) $schoolCourseOffering->id,
                        'teacher_user_id' => $teacherUserId,
                        'school_subject_id' => (int) $schoolCourseOffering->school_subject_id,
                        'school_term_id' => (int) $schoolCourseOffering->school_term_id,
                    ],
                ]
            );
        });

        return back()->with('success', 'تم تحديث إسناد المقرر وصلاحيات الاختبارات بنجاح.');
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $userId = (int) $request->user()->id;

        [$validated, $slot, $term] = $this->validateScheduleRequest($request, $schoolId);

        SchoolClassSchedule::query()->create([
            'school_id' => $schoolId,
            'school_term_id' => (int) $validated['school_term_id'],
            'school_timetable_version_id' => isset($validated['school_timetable_version_id'])
                ? (int) $validated['school_timetable_version_id']
                : null,
            'school_stage_id' => (int) $validated['school_stage_id'],
            'school_classroom_id' => (int) $validated['school_classroom_id'],
            'school_subject_id' => (int) $validated['school_subject_id'],
            'teacher_user_id' => (int) $validated['teacher_user_id'],
            'schedule_scope' => $validated['schedule_scope'],
            'day_of_week' => $slot['day_of_week'],
            'day_of_month' => $slot['day_of_month'],
            'session_date' => $slot['session_date'],
            'session_index' => (int) $validated['session_index'],
            'starts_at' => $this->emptyToNull($validated['starts_at'] ?? null),
            'ends_at' => $this->emptyToNull($validated['ends_at'] ?? null),
            'notes' => $this->emptyToNull($validated['notes'] ?? null),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return redirect()->route('school.academic_planning.index', $this->planningIndexRouteParams($request, [
            'term_id' => $term->id,
            'version_id' => $validated['school_timetable_version_id'] ?? null,
            'scope' => $validated['schedule_scope'],
            'classroom_id' => (int) $validated['school_classroom_id'],
        ]));
    }

    public function updateSchedule(Request $request, SchoolClassSchedule $schoolClassSchedule): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureScheduleInSchool($schoolClassSchedule, $schoolId);
        $userId = (int) $request->user()->id;

        [$validated, $slot, $term] = $this->validateScheduleRequest($request, $schoolId, (int) $schoolClassSchedule->id);

        $schoolClassSchedule->update([
            'school_term_id' => (int) $validated['school_term_id'],
            'school_timetable_version_id' => isset($validated['school_timetable_version_id'])
                ? (int) $validated['school_timetable_version_id']
                : null,
            'school_stage_id' => (int) $validated['school_stage_id'],
            'school_classroom_id' => (int) $validated['school_classroom_id'],
            'school_subject_id' => (int) $validated['school_subject_id'],
            'teacher_user_id' => (int) $validated['teacher_user_id'],
            'schedule_scope' => $validated['schedule_scope'],
            'day_of_week' => $slot['day_of_week'],
            'day_of_month' => $slot['day_of_month'],
            'session_date' => $slot['session_date'],
            'session_index' => (int) $validated['session_index'],
            'starts_at' => $this->emptyToNull($validated['starts_at'] ?? null),
            'ends_at' => $this->emptyToNull($validated['ends_at'] ?? null),
            'notes' => $this->emptyToNull($validated['notes'] ?? null),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'updated_by' => $userId,
        ]);

        return redirect()->route('school.academic_planning.index', $this->planningIndexRouteParams($request, [
            'term_id' => $term->id,
            'version_id' => $validated['school_timetable_version_id'] ?? null,
            'scope' => $validated['schedule_scope'],
            'classroom_id' => (int) $validated['school_classroom_id'],
        ]));
    }

    public function destroySchedule(Request $request, SchoolClassSchedule $schoolClassSchedule): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureScheduleInSchool($schoolClassSchedule, $schoolId);

        $schoolClassSchedule->delete();

        return back();
    }

    public function syncWeeklyGrid(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $userId = (int) $request->user()->id;

        [$validated, $term, $classroom] = $this->validateWeeklyGridRequest($request, $schoolId);
        $stageId = (int) $validated['school_stage_id'];
        $classroomId = (int) $validated['school_classroom_id'];
        $timetableVersionId = isset($validated['school_timetable_version_id'])
            ? (int) $validated['school_timetable_version_id']
            : null;

        $cells = collect($validated['cells'] ?? [])
            ->map(fn (array $cell): array => [
                'day_of_week' => (int) $cell['day_of_week'],
                'session_index' => (int) $cell['session_index'],
                'school_subject_id' => isset($cell['school_subject_id']) && $cell['school_subject_id'] !== ''
                    ? (int) $cell['school_subject_id']
                    : null,
                'teacher_user_id' => isset($cell['teacher_user_id']) && $cell['teacher_user_id'] !== ''
                    ? (int) $cell['teacher_user_id']
                    : null,
                'starts_at' => $this->emptyToNull($cell['starts_at'] ?? null),
                'ends_at' => $this->emptyToNull($cell['ends_at'] ?? null),
                'notes' => $this->emptyToNull($cell['notes'] ?? null),
                'is_active' => array_key_exists('is_active', $cell) ? (bool) $cell['is_active'] : true,
            ])
            ->values();

        $payloadKeys = [];
        foreach ($cells as $index => $cell) {
            $slotKey = $this->weeklyGridSlotKey((int) $cell['day_of_week'], (int) $cell['session_index']);
            if (isset($payloadKeys[$slotKey])) {
                throw ValidationException::withMessages([
                    "cells.$index.session_index" => 'لا يمكن تكرار نفس اليوم ونفس رقم الحصة داخل الجدول.',
                ]);
            }

            $payloadKeys[$slotKey] = true;
        }

        DB::transaction(function () use (
            $cells,
            $schoolId,
            $userId,
            $validated,
            $term,
            $stageId,
            $classroomId,
            $timetableVersionId
        ): void {
            $existingSchedules = $this->weeklyGridSchedulesQuery(
                schoolId: $schoolId,
                termId: (int) $term->id,
                stageId: $stageId,
                classroomId: $classroomId,
                timetableVersionId: $timetableVersionId,
            )
                ->get()
                ->keyBy(fn (SchoolClassSchedule $schedule): string => $this->weeklyGridSlotKey(
                    (int) $schedule->day_of_week,
                    (int) $schedule->session_index
                ));

            foreach ($cells as $index => $cell) {
                $slotKey = $this->weeklyGridSlotKey((int) $cell['day_of_week'], (int) $cell['session_index']);
                $existingSchedule = $existingSchedules->get($slotKey);

                if (!$this->gridCellHasScheduleData($cell)) {
                    if ($existingSchedule instanceof SchoolClassSchedule) {
                        $existingSchedule->delete();
                    }
                    continue;
                }

                if (($cell['school_subject_id'] ?? null) === null) {
                    throw ValidationException::withMessages([
                        "cells.$index.school_subject_id" => 'اختر المادة أولًا قبل حفظ الحصة.',
                    ]);
                }

                if (($cell['teacher_user_id'] ?? null) === null) {
                    throw ValidationException::withMessages([
                        "cells.$index.teacher_user_id" => 'اختر المعلم بعد تحديد المادة.',
                    ]);
                }

                $scheduleInput = [
                    'school_term_id' => (int) $validated['school_term_id'],
                    'school_timetable_version_id' => $timetableVersionId,
                    'school_stage_id' => $stageId,
                    'school_classroom_id' => $classroomId,
                    'school_subject_id' => (int) $cell['school_subject_id'],
                    'teacher_user_id' => (int) $cell['teacher_user_id'],
                    'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
                    'day_of_week' => (int) $cell['day_of_week'],
                    'session_index' => (int) $cell['session_index'],
                    'starts_at' => $cell['starts_at'],
                    'ends_at' => $cell['ends_at'],
                    'notes' => $cell['notes'],
                    'is_active' => (bool) $cell['is_active'],
                ];

                [$validatedSchedule, $slot] = $this->validateScheduleData(
                    $scheduleInput,
                    $schoolId,
                    $existingSchedule instanceof SchoolClassSchedule ? (int) $existingSchedule->id : null
                );

                $payload = [
                    'school_term_id' => (int) $validatedSchedule['school_term_id'],
                    'school_timetable_version_id' => isset($validatedSchedule['school_timetable_version_id'])
                        ? (int) $validatedSchedule['school_timetable_version_id']
                        : null,
                    'school_stage_id' => (int) $validatedSchedule['school_stage_id'],
                    'school_classroom_id' => (int) $validatedSchedule['school_classroom_id'],
                    'school_subject_id' => (int) $validatedSchedule['school_subject_id'],
                    'teacher_user_id' => (int) $validatedSchedule['teacher_user_id'],
                    'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
                    'day_of_week' => $slot['day_of_week'],
                    'day_of_month' => null,
                    'session_date' => null,
                    'session_index' => (int) $validatedSchedule['session_index'],
                    'starts_at' => $this->emptyToNull($validatedSchedule['starts_at'] ?? null),
                    'ends_at' => $this->emptyToNull($validatedSchedule['ends_at'] ?? null),
                    'notes' => $this->emptyToNull($validatedSchedule['notes'] ?? null),
                    'is_active' => (bool) ($validatedSchedule['is_active'] ?? true),
                    'updated_by' => $userId,
                ];

                if ($existingSchedule instanceof SchoolClassSchedule) {
                    $existingSchedule->update($payload);
                    continue;
                }

                SchoolClassSchedule::query()->create([
                    ...$payload,
                    'school_id' => $schoolId,
                    'created_by' => $userId,
                ]);
            }
        });

        return redirect()->route('school.academic_planning.index', $this->planningIndexRouteParams($request, [
            'term_id' => (int) $validated['school_term_id'],
            'scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'stage_id' => $stageId,
            'grade_name' => $validated['grade_name'] ?? null,
            'classroom_id' => $classroomId,
            'version_id' => $timetableVersionId,
            'period_count' => (int) ($validated['period_count'] ?? 8),
        ]));
    }

    public function exportWeeklyGrid(Request $request, string $format)
    {
        $schoolId = $this->resolveSchoolId($request);
        [$validated, $term, $classroom] = $this->validateWeeklyGridRequest($request, $schoolId, requireCells: false);
        $stage = SchoolStage::query()
            ->where('school_id', $schoolId)
            ->whereKey((int) $validated['school_stage_id'])
            ->firstOrFail(['id', 'name']);
        $school = School::query()->whereKey($schoolId)->firstOrFail(['id', 'name', 'school_id', 'phone', 'email', 'logo_path']);
        $calendarSettings = $this->schoolCalendarService->getOrCreateSettings($schoolId);
        $weeklyOffDays = $this->schoolCalendarService->normalizeWeeklyOffDays($calendarSettings->weekly_off_days);
        $weekDays = collect($this->weekDayOptions())
            ->reject(fn (array $day): bool => in_array((int) $day['value'], $weeklyOffDays, true))
            ->values();
        $timetableVersionId = isset($validated['school_timetable_version_id'])
            ? (int) $validated['school_timetable_version_id']
            : null;
        $periodCount = max(
            (int) ($validated['period_count'] ?? 8),
            (int) $this->weeklyGridSchedulesQuery(
                schoolId: $schoolId,
                termId: (int) $term->id,
                stageId: (int) $validated['school_stage_id'],
                classroomId: (int) $validated['school_classroom_id'],
                timetableVersionId: $timetableVersionId,
            )->max('session_index'),
            1
        );

        $entries = $this->weeklyGridSchedulesQuery(
            schoolId: $schoolId,
            termId: (int) $term->id,
            stageId: (int) $validated['school_stage_id'],
            classroomId: (int) $validated['school_classroom_id'],
            timetableVersionId: $timetableVersionId,
        )
            ->with([
                'subject:id,name,code',
                'teacher:id,name,email',
            ])
            ->orderBy('day_of_week')
            ->orderBy('session_index')
            ->get([
                'id',
                'school_id',
                'school_term_id',
                'school_timetable_version_id',
                'school_stage_id',
                'school_classroom_id',
                'school_subject_id',
                'teacher_user_id',
                'schedule_scope',
                'day_of_week',
                'session_index',
                'starts_at',
                'ends_at',
                'notes',
                'is_active',
            ]);

        $periods = collect(range(1, $periodCount));
        $matrix = [];
        foreach ($entries as $entry) {
            $matrix[(int) $entry->day_of_week][(int) $entry->session_index] = [
                'subject_name' => (string) ($entry->subject?->name ?? ''),
                'teacher_name' => (string) ($entry->teacher?->name ?? ''),
                'time_label' => $this->scheduleTimeLabelForExport($entry->starts_at, $entry->ends_at),
                'notes' => (string) ($entry->notes ?? ''),
            ];
        }

        $payload = [
            'school' => $school,
            'term' => $term,
            'stage' => $stage,
            'classroom' => $classroom,
            'grade_name' => $validated['grade_name'] ?? ($classroom->grade_name ?? ''),
            'timetableVersionName' => $this->resolveTimetableVersionName($timetableVersionId, $schoolId),
            'weekDays' => $weekDays,
            'periods' => $periods,
            'matrix' => $matrix,
            'weeklyOffLabels' => collect($this->weekDayOptions())
                ->filter(fn (array $day): bool => in_array((int) $day['value'], $weeklyOffDays, true))
                ->pluck('label')
                ->values(),
            'generatedAt' => now(),
            'exportedBy' => $request->user(),
            'schoolLogoImage' => $this->exportDocuments->schoolLogoDataUri($school),
            'documentTitle' => 'الجدول الدراسي الأسبوعي',
            'documentSubtitle' => 'تصدير رسمي من بيانات الجدول المحفوظة داخل منصة إدارتك.',
        ];

        $filenameBase = $this->weeklyGridExportFileName(
            schoolName: (string) $school->name,
            termName: (string) $term->name,
            classroomName: (string) $classroom->name
        );

        $html = view('exports.school.weekly-timetable', $payload)->render();

        if ($format === 'word') {
            return response($html, 200, [
                ...$this->exportDocuments->wordHeaders($filenameBase . '.doc'),
            ]);
        }

        return $this->exportDocuments->downloadPdfFromHtml($html, $filenameBase . '.pdf', 'landscape');
    }

    private function downloadWeeklyGridPdfViaHeadlessBrowser(string $html, string $filenameBase)
    {
        $browserBinary = $this->resolveHeadlessBrowserBinary();
        if ($browserBinary === null) {
            abort(500, 'تعذر تصدير PDF لعدم توفر محرك توليد PDF على هذه البيئة.');
        }

        $directory = storage_path('app/temp/weekly-grid-exports');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            abort(500, 'تعذر تجهيز مجلد التصدير المؤقت.');
        }

        $token = uniqid('weekly-grid-', true);
        $htmlPath = $directory . DIRECTORY_SEPARATOR . $token . '.html';
        $pdfPath = $directory . DIRECTORY_SEPARATOR . $token . '.pdf';

        file_put_contents($htmlPath, $html);

        $htmlUrl = 'file:///' . str_replace('\\', '/', $htmlPath);
        $command = [
            $browserBinary,
            '--headless=new',
            '--disable-gpu',
            '--no-first-run',
            '--no-default-browser-check',
            '--allow-file-access-from-files',
            '--print-to-pdf=' . $pdfPath,
            $htmlUrl,
        ];

        $result = Process::timeout(90)->run($command);
        @unlink($htmlPath);

        if (!$result->successful() || !is_file($pdfPath)) {
            @unlink($pdfPath);
            abort(500, 'تعذر إنشاء ملف PDF من الجدول الدراسي.');
        }

        return response()->download($pdfPath, $filenameBase . '.pdf')->deleteFileAfterSend(true);
    }

    private function resolveHeadlessBrowserBinary(): ?string
    {
        $windowsCandidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        ];

        foreach ($windowsCandidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $binaryNames = PHP_OS_FAMILY === 'Windows'
            ? ['chrome', 'msedge']
            : ['google-chrome', 'chromium-browser', 'chromium', 'microsoft-edge'];

        $locatorCommand = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';

        foreach ($binaryNames as $binary) {
            $result = Process::timeout(10)->run([$locatorCommand, $binary]);
            if (!$result->successful()) {
                continue;
            }

            $resolvedPath = trim(strtok((string) $result->output(), PHP_EOL));
            if ($resolvedPath !== '') {
                return $resolvedPath;
            }
        }

        return null;
    }

    /**
     * @return array{0: array<string, mixed>, 1: SchoolTerm, 2: SchoolClassroom}
     */
    private function validateWeeklyGridRequest(Request $request, int $schoolId, bool $requireCells = true): array
    {
        $rules = [
            'school_term_id' => [
                'required',
                Rule::exists('school_terms', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_timetable_version_id' => [
                'nullable',
                Rule::exists('school_timetable_versions', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('school_term_id', (int) $request->input('school_term_id'))),
            ],
            'school_stage_id' => [
                'required',
                Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'grade_name' => ['nullable', 'string', 'max:255'],
            'school_classroom_id' => [
                'required',
                Rule::exists('school_classrooms', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('school_stage_id', (int) $request->input('school_stage_id'))),
            ],
            'period_count' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];

        if ($requireCells) {
            $rules['cells'] = ['required', 'array', 'min:1'];
            $rules['cells.*.day_of_week'] = ['required', 'integer', 'min:0', 'max:6'];
            $rules['cells.*.session_index'] = ['required', 'integer', 'min:1', 'max:20'];
            $rules['cells.*.school_subject_id'] = ['nullable', 'integer'];
            $rules['cells.*.teacher_user_id'] = ['nullable', 'integer'];
            $rules['cells.*.starts_at'] = ['nullable', 'date_format:H:i'];
            $rules['cells.*.ends_at'] = ['nullable', 'date_format:H:i'];
            $rules['cells.*.notes'] = ['nullable', 'string', 'max:1000'];
            $rules['cells.*.is_active'] = ['nullable', 'boolean'];
        }

        $validated = $request->validate($rules, [
            'school_term_id.required' => 'الترم مطلوب.',
            'school_stage_id.required' => 'المرحلة مطلوبة.',
            'school_classroom_id.required' => 'الفصل مطلوب.',
            'cells.required' => 'لا يمكن حفظ الجدول بدون خلايا.',
            'cells.array' => 'بيانات الجدول المرسلة غير صالحة.',
            'cells.*.day_of_week.required' => 'اليوم الدراسي مطلوب لكل خلية.',
            'cells.*.session_index.required' => 'رقم الحصة مطلوب لكل خلية.',
        ]);

        $term = SchoolTerm::query()
            ->whereKey((int) $validated['school_term_id'])
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $classroom = SchoolClassroom::query()
            ->whereKey((int) $validated['school_classroom_id'])
            ->where('school_id', $schoolId)
            ->where('school_stage_id', (int) $validated['school_stage_id'])
            ->firstOrFail(['id', 'school_id', 'school_stage_id', 'grade_name', 'name', 'is_active']);

        if (!(bool) $classroom->is_active) {
            throw ValidationException::withMessages([
                'school_classroom_id' => 'لا يمكن استخدام فصل غير نشط داخل محرر الجدول الدراسي.',
            ]);
        }

        $gradeName = trim((string) ($validated['grade_name'] ?? ''));
        if ($gradeName !== '' && $this->normalizeGradeName($gradeName) !== $this->normalizeGradeName((string) $classroom->grade_name)) {
            throw ValidationException::withMessages([
                'grade_name' => 'الصف المحدد لا يتطابق مع الفصل المختار.',
            ]);
        }

        return [$validated, $term, $classroom];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array{day_of_week:?int, day_of_month:?int, session_date:?string}, 2: SchoolTerm}
     */
    private function validateScheduleRequest(Request $request, int $schoolId, ?int $ignoreScheduleId = null): array
    {
        return $this->validateScheduleData($request->all(), $schoolId, $ignoreScheduleId);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: array{day_of_week:?int, day_of_month:?int, session_date:?string}, 2: SchoolTerm}
     */
    private function validateScheduleData(array $input, int $schoolId, ?int $ignoreScheduleId = null): array
    {
        $validated = Validator::make($input, [
            'school_term_id' => [
                'required',
                Rule::exists('school_terms', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_timetable_version_id' => [
                'nullable',
                Rule::exists('school_timetable_versions', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('school_term_id', (int) ($input['school_term_id'] ?? 0))),
            ],
            'school_stage_id' => [
                'required',
                Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_classroom_id' => [
                'required',
                Rule::exists('school_classrooms', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('school_stage_id', (int) ($input['school_stage_id'] ?? 0))),
            ],
            'school_subject_id' => [
                'required',
                Rule::exists('school_subjects', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('is_active', true)),
            ],
            'teacher_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('is_active', true)),
            ],
            'schedule_scope' => ['required', Rule::in(SchoolClassSchedule::allowedScopes())],
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'session_date' => ['nullable', 'date'],
            'session_index' => ['required', 'integer', 'min:1', 'max:20'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'school_term_id.required' => 'الترم مطلوب.',
            'school_term_id.exists' => 'لا يمكن الحفظ لأن الترم لا ينتمي إلى نفس المدرسة.',
            'school_timetable_version_id.exists' => 'لا يمكن الحفظ لأن نسخة الجدول غير صالحة أو لا تنتمي إلى نفس المدرسة/الترم.',
            'school_stage_id.required' => 'المرحلة مطلوبة.',
            'school_stage_id.exists' => 'لا يمكن الحفظ لأن المرحلة لا تنتمي إلى نفس المدرسة.',
            'school_classroom_id.required' => 'الفصل مطلوب.',
            'school_classroom_id.exists' => 'لا يمكن الحفظ لأن الفصل لا ينتمي إلى نفس المدرسة أو لا يتبع المرحلة المحددة.',
            'school_subject_id.required' => 'المادة مطلوبة.',
            'school_subject_id.exists' => 'لا يمكن الحفظ لأن المادة غير صالحة أو غير نشطة داخل نفس المدرسة.',
            'teacher_user_id.required' => 'المعلم مطلوب.',
            'teacher_user_id.exists' => 'لا يمكن الحفظ لأن المعلم غير صالح أو لا ينتمي إلى نفس المدرسة.',
            'schedule_scope.required' => 'نطاق الجدول مطلوب.',
            'schedule_scope.in' => 'نطاق الجدول المحدد غير صالح.',
            'day_of_week.min' => 'اليوم الأسبوعي غير صالح.',
            'day_of_week.max' => 'اليوم الأسبوعي غير صالح.',
            'day_of_month.min' => 'يوم الشهر غير صالح.',
            'day_of_month.max' => 'يوم الشهر غير صالح.',
            'session_date.date' => 'تاريخ الجلسة غير صالح.',
            'session_index.required' => 'رقم الحصة مطلوب.',
            'session_index.min' => 'رقم الحصة يجب أن يكون أكبر من أو يساوي 1.',
            'session_index.max' => 'رقم الحصة يجب ألا يتجاوز 20.',
            'starts_at.date_format' => 'تنسيق وقت البداية غير صالح.',
            'ends_at.date_format' => 'تنسيق وقت النهاية غير صالح.',
        ])->validate();

        $term = SchoolTerm::query()
            ->whereKey((int) $validated['school_term_id'])
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $slot = $this->normalizeScheduleSlot($validated, $term);
        $this->validateTimeRange($validated);
        $this->ensureTeacherCanBeAssigned($schoolId, (int) $validated['teacher_user_id']);
        $this->ensureTeacherSubjectAssignment($schoolId, (int) $validated['school_subject_id'], (int) $validated['teacher_user_id']);
        $this->ensureScheduleMatchesCourseOfferingWhenEnabled($schoolId, $validated);
        $this->ensureScheduleSlotIsAvailable($schoolId, $validated, $slot, $ignoreScheduleId);
        $this->ensureTeacherSlotIsAvailable($schoolId, $validated, $slot, $ignoreScheduleId);
        $this->ensureTeacherWeeklyAvailabilityIfConfigured($schoolId, $validated, $slot);
        $this->academicPlanningValidationService->validateScheduleAgainstSchoolReferences(
            schoolId: $schoolId,
            term: $term,
            validated: $validated,
            slot: $slot,
            ignoreScheduleId: $ignoreScheduleId
        );

        return [$validated, $slot, $term];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array{day_of_week:?int, day_of_month:?int, session_date:?string}
     */
    private function normalizeScheduleSlot(array $validated, SchoolTerm $term): array
    {
        $scope = (string) $validated['schedule_scope'];
        $dayOfWeek = isset($validated['day_of_week']) ? (int) $validated['day_of_week'] : null;
        $dayOfMonth = isset($validated['day_of_month']) ? (int) $validated['day_of_month'] : null;
        $sessionDate = isset($validated['session_date']) && $validated['session_date'] !== null
            ? Carbon::parse((string) $validated['session_date'])->toDateString()
            : null;

        $errors = [];

        if ($scope === SchoolClassSchedule::SCOPE_WEEKLY && $dayOfWeek === null) {
            $errors['day_of_week'] = 'اليوم الأسبوعي مطلوب عند اختيار نطاق أسبوعي.';
        }

        if ($scope === SchoolClassSchedule::SCOPE_MONTHLY && $dayOfMonth === null) {
            $errors['day_of_month'] = 'يوم الشهر مطلوب عند اختيار نطاق شهري.';
        }

        if ($scope === SchoolClassSchedule::SCOPE_TERM && $sessionDate === null) {
            $errors['session_date'] = 'تاريخ الجلسة مطلوب عند اختيار نطاق ترمي.';
        }

        if ($sessionDate !== null) {
            $termStart = $term->start_date?->toDateString();
            $termEnd = $term->end_date?->toDateString();

            if (($termStart && $sessionDate < $termStart) || ($termEnd && $sessionDate > $termEnd)) {
                $errors['session_date'] = 'تاريخ الجلسة يجب أن يكون داخل نطاق الترم.';
            }
        }

        if (count($errors) > 0) {
            throw ValidationException::withMessages($errors);
        }

        if ($scope !== SchoolClassSchedule::SCOPE_WEEKLY) {
            $dayOfWeek = null;
        }

        if ($scope !== SchoolClassSchedule::SCOPE_MONTHLY) {
            $dayOfMonth = null;
        }

        if ($scope !== SchoolClassSchedule::SCOPE_TERM) {
            $sessionDate = null;
        }

        return [
            'day_of_week' => $dayOfWeek,
            'day_of_month' => $dayOfMonth,
            'session_date' => $sessionDate,
        ];
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function validateTimeRange(array $validated): void
    {
        $startsAt = $this->emptyToNull($validated['starts_at'] ?? null);
        $endsAt = $this->emptyToNull($validated['ends_at'] ?? null);

        if (($startsAt === null) xor ($endsAt === null)) {
            throw ValidationException::withMessages([
                'ends_at' => 'وقت البداية ووقت النهاية يجب إدخالهما معًا.',
            ]);
        }

        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            throw ValidationException::withMessages([
                'ends_at' => 'وقت النهاية يجب أن يكون بعد وقت البداية.',
            ]);
        }
    }

    private function ensureTeacherCanBeAssigned(int $schoolId, int $teacherUserId): void
    {
        $exists = $this->teacherQuery($schoolId)
            ->whereKey($teacherUserId)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'teacher_user_id' => 'المعلم المحدد غير متاح كمعلم فعال في هذه المدرسة.',
            ]);
        }
    }

    private function ensureTeacherSubjectAssignment(int $schoolId, int $subjectId, int $teacherUserId): void
    {
        $assigned = SchoolSubjectTeacherAssignment::query()
            ->where('school_id', $schoolId)
            ->where('school_subject_id', $subjectId)
            ->where('teacher_user_id', $teacherUserId)
            ->exists();

        if (!$assigned) {
            throw ValidationException::withMessages([
                'teacher_user_id' => 'هذا المعلم غير مكلّف بتدريس هذه المادة في المدرسة.',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $validated
     * @param array{day_of_week:?int, day_of_month:?int, session_date:?string} $slot
     */
    private function ensureScheduleSlotIsAvailable(int $schoolId, array $validated, array $slot, ?int $ignoreScheduleId = null): void
    {
        $query = SchoolClassSchedule::query()
            ->active()
            ->where('school_id', $schoolId)
            ->where('school_term_id', (int) $validated['school_term_id'])
            ->where('school_classroom_id', (int) $validated['school_classroom_id'])
            ->where('schedule_scope', (string) $validated['schedule_scope'])
            ->where('session_index', (int) $validated['session_index']);

        if ($slot['day_of_week'] !== null) {
            $query->where('day_of_week', $slot['day_of_week']);
        }

        if ($slot['day_of_month'] !== null) {
            $query->where('day_of_month', $slot['day_of_month']);
        }

        if ($slot['session_date'] !== null) {
            $query->whereDate('session_date', $slot['session_date']);
        }

        if ($ignoreScheduleId !== null) {
            $query->whereKeyNot($ignoreScheduleId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'session_index' => 'رقم الحصة محجوز مسبقًا في نفس الفصل ونفس النطاق.',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $validated
     * @param array{day_of_week:?int, day_of_month:?int, session_date:?string} $slot
     */
    private function ensureTeacherSlotIsAvailable(int $schoolId, array $validated, array $slot, ?int $ignoreScheduleId = null): void
    {
        $query = SchoolClassSchedule::query()
            ->active()
            ->where('school_id', $schoolId)
            ->where('school_term_id', (int) $validated['school_term_id'])
            ->where('teacher_user_id', (int) $validated['teacher_user_id'])
            ->where('schedule_scope', (string) $validated['schedule_scope'])
            ->where('session_index', (int) $validated['session_index']);

        if ($slot['day_of_week'] !== null) {
            $query->where('day_of_week', $slot['day_of_week']);
        }

        if ($slot['day_of_month'] !== null) {
            $query->where('day_of_month', $slot['day_of_month']);
        }

        if ($slot['session_date'] !== null) {
            $query->whereDate('session_date', $slot['session_date']);
        }

        if ($ignoreScheduleId !== null) {
            $query->whereKeyNot($ignoreScheduleId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'teacher_user_id' => 'هذا المعلم لديه حصة أخرى في نفس اليوم/النطاق ونفس التوقيت.',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $validated
     * @param array{day_of_week:?int, day_of_month:?int, session_date:?string} $slot
     */
    private function ensureTeacherWeeklyAvailabilityIfConfigured(int $schoolId, array $validated, array $slot): void
    {
        if ((string) $validated['schedule_scope'] !== SchoolClassSchedule::SCOPE_WEEKLY || $slot['day_of_week'] === null) {
            return;
        }

        $teacherUserId = (int) $validated['teacher_user_id'];
        $sessionIndex = (int) $validated['session_index'];
        $dayOfWeek = (int) $slot['day_of_week'];

        $hasAvailabilityConfig = SchoolTeacherAvailability::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', $teacherUserId)
            ->exists();

        if (!$hasAvailabilityConfig) {
            return;
        }

        $isAvailable = SchoolTeacherAvailability::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', $teacherUserId)
            ->where('day_of_week', $dayOfWeek)
            ->where('session_index', $sessionIndex)
            ->where('is_available', true)
            ->exists();

        if (!$isAvailable) {
            throw ValidationException::withMessages([
                'teacher_user_id' => 'هذا المعلم غير متاح وفق جدول الإتاحة الأسبوعي المحدد.',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function ensureScheduleMatchesCourseOfferingWhenEnabled(int $schoolId, array $validated): void
    {
        if (!config('features.course_offerings.enforce_for_scheduling', false)) {
            return;
        }

        $classroomId = (int) $validated['school_classroom_id'];
        $courseOffering = $this->resolveCourseOfferingForClassroomScope(
            schoolId: $schoolId,
            termId: (int) $validated['school_term_id'],
            stageId: (int) $validated['school_stage_id'],
            classroomId: $classroomId,
            subjectId: (int) $validated['school_subject_id']
        );

        if (!$courseOffering) {
            throw ValidationException::withMessages([
                'school_subject_id' => 'لا يوجد مقرر متاح لهذه المادة في الفصل والمرحلة والترم المحددين.',
            ]);
        }

        $assignment = SchoolTeachingAssignment::query()
            ->where('school_id', $schoolId)
            ->where('school_course_offering_id', (int) $courseOffering->id)
            ->where('teacher_user_id', (int) $validated['teacher_user_id'])
            ->where('is_active', true)
            ->first();

        if (!$assignment) {
            throw ValidationException::withMessages([
                'teacher_user_id' => 'لا يوجد إسناد تدريسي نشط لهذا المعلم على هذا المقرر/الفصل.',
            ]);
        }

        $hasScopedClassrooms = DB::table('school_teaching_assignment_classrooms')
            ->where('school_id', $schoolId)
            ->where('school_teaching_assignment_id', (int) $assignment->id)
            ->exists();

        if ($hasScopedClassrooms) {
            $matchesSelectedClassroom = DB::table('school_teaching_assignment_classrooms')
                ->where('school_id', $schoolId)
                ->where('school_teaching_assignment_id', (int) $assignment->id)
                ->where('school_classroom_id', $classroomId)
                ->exists();

            if ($matchesSelectedClassroom) {
                return;
            }
        }

        if ((int) ($courseOffering->school_classroom_id ?? 0) !== $classroomId) {
            throw ValidationException::withMessages([
                'teacher_user_id' => 'لا يوجد إسناد تدريسي نشط لهذا المعلم على هذا المقرر/الفصل.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCourseOfferingPayload(Request $request, int $schoolId, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'school_term_id' => [
                'required',
                Rule::exists('school_terms', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_stage_id' => [
                'required',
                Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_stage_grade_id' => [
                'nullable',
                Rule::exists('school_stage_grades', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('school_stage_id', (int) $request->input('school_stage_id'))),
            ],
            'school_classroom_id' => [
                'nullable',
                Rule::exists('school_classrooms', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('school_stage_id', (int) $request->input('school_stage_id'))),
            ],
            'school_subject_id' => [
                'required',
                Rule::exists('school_subjects', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('is_active', true)),
            ],
            'is_active' => ['nullable', 'boolean'],
            'usable_in_exams' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'alert_before_term_end_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'study_plan_units' => ['nullable', 'array'],
            'study_plan_units.*.name' => ['required', 'string', 'max:150'],
            'study_plan_units.*.branch_name' => ['nullable', 'string', 'max:150'],
            'study_plan_units.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'study_plan_units.*.start_date' => ['required', 'date'],
            'study_plan_units.*.end_date' => ['required', 'date'],
            'study_plan_units.*.notes' => ['nullable', 'string', 'max:3000'],
            'study_plan_units.*.lessons' => ['nullable', 'array'],
            'study_plan_units.*.lessons.*.name' => ['required', 'string', 'max:150'],
            'study_plan_units.*.lessons.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'study_plan_units.*.lessons.*.description' => ['nullable', 'string', 'max:1000'],
            'study_plan_units.*.lessons.*.topics' => ['nullable', 'array'],
            'study_plan_units.*.lessons.*.topics.*.name' => ['required', 'string', 'max:150'],
            'study_plan_units.*.lessons.*.topics.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'study_plan_units.*.lessons.*.topics.*.description' => ['nullable', 'string', 'max:1000'],
        ], [
            'school_term_id.required' => 'الترم مطلوب.',
            'school_term_id.exists' => 'لا يمكن الحفظ لأن الترم لا ينتمي إلى نفس المدرسة.',
            'school_stage_id.required' => 'المرحلة مطلوبة.',
            'school_stage_id.exists' => 'لا يمكن الحفظ لأن المرحلة لا تنتمي إلى نفس المدرسة.',
            'school_stage_grade_id.exists' => 'لا يمكن الحفظ لأن الصف لا ينتمي إلى نفس المدرسة أو لا يتبع المرحلة المحددة.',
            'school_classroom_id.exists' => 'لا يمكن الحفظ لأن الفصل لا ينتمي إلى نفس المدرسة أو لا يتبع المرحلة المحددة.',
            'school_subject_id.required' => 'المادة مطلوبة.',
            'school_subject_id.exists' => 'لا يمكن الحفظ لأن المادة غير صالحة أو غير نشطة داخل نفس المدرسة.',
            'sort_order.integer' => 'الترتيب يجب أن يكون رقمًا صحيحًا.',
            'sort_order.min' => 'الترتيب يجب ألا يكون أقل من صفر.',
            'sort_order.max' => 'الترتيب يجب ألا يتجاوز 9999.',
            'alert_before_term_end_days.integer' => 'عدد أيام التنبيه يجب أن يكون رقمًا صحيحًا.',
            'alert_before_term_end_days.min' => 'عدد أيام التنبيه لا يمكن أن يكون أقل من صفر.',
            'alert_before_term_end_days.max' => 'عدد أيام التنبيه كبير جدًا وغير صالح.',
            'study_plan_units.*.name.required' => 'اسم الوحدة مطلوب.',
            'study_plan_units.*.branch_name.max' => 'اسم الفرع طويل جدًا.',
            'study_plan_units.*.start_date.required' => 'تاريخ بداية الوحدة مطلوب.',
            'study_plan_units.*.end_date.required' => 'تاريخ نهاية الوحدة مطلوب.',
            'study_plan_units.*.lessons.*.name.required' => 'اسم الدرس مطلوب.',
            'study_plan_units.*.lessons.*.topics.*.name.required' => 'اسم الموضوع مطلوب.',
        ]);

        $validated['school_stage_grade_id'] = isset($validated['school_stage_grade_id'])
            ? (int) $validated['school_stage_grade_id']
            : null;
        $validated['school_classroom_id'] = isset($validated['school_classroom_id'])
            ? (int) $validated['school_classroom_id']
            : null;
        $validated['alert_before_term_end_days'] = (int) ($validated['alert_before_term_end_days'] ?? 0);

        $validated = $this->resolveCourseOfferingScopeFromPayload($schoolId, $validated);

        $duplicateQuery = SchoolCourseOffering::query()
            ->where('school_id', $schoolId)
            ->where('school_term_id', (int) $validated['school_term_id'])
            ->where('school_stage_id', (int) $validated['school_stage_id'])
            ->where('school_stage_grade_id', (int) $validated['school_stage_grade_id'])
            ->where('school_subject_id', (int) $validated['school_subject_id']);

        if ($ignoreId !== null) {
            $duplicateQuery->whereKeyNot($ignoreId);
        }

        if ($duplicateQuery->exists()) {
            throw ValidationException::withMessages([
                'school_subject_id' => 'هذه المادة مضافة مسبقًا على نفس المرحلة والصف في هذا الترم.',
            ]);
        }

        $this->academicPlanningValidationService->validateCourseOfferingReferences($schoolId, $validated);
        $term = SchoolTerm::query()
            ->where('school_id', $schoolId)
            ->whereKey((int) $validated['school_term_id'])
            ->firstOrFail();
        $subject = SchoolSubject::query()
            ->where('school_id', $schoolId)
            ->whereKey((int) $validated['school_subject_id'])
            ->firstOrFail();
        $validated['study_plan_units'] = $this->normalizeStudyPlanUnits($validated['study_plan_units'] ?? []);
        $this->assertStudyPlanRequiredOnCreate($validated['study_plan_units'], $ignoreId);
        $this->assertStudyPlanBranchesBelongToSubject($subject, $validated['study_plan_units']);
        $this->assertStudyPlanWithinTermBounds(
            term: $term,
            units: $validated['study_plan_units'],
            alertBeforeTermEndDays: (int) $validated['alert_before_term_end_days']
        );

        return $validated;
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function resolveCourseOfferingScopeFromPayload(int $schoolId, array $validated): array
    {
        $stageId = (int) $validated['school_stage_id'];
        $stageGradeId = (int) ($validated['school_stage_grade_id'] ?? 0);
        $classroomId = (int) ($validated['school_classroom_id'] ?? 0);

        if ($stageGradeId <= 0 && $classroomId <= 0) {
            throw ValidationException::withMessages([
                'school_stage_grade_id' => 'لا يمكن إنشاء المقرر بدون تحديد الصف المرتبط بالمرحلة.',
            ]);
        }

        $classroom = null;
        if ($classroomId > 0) {
            $classroom = SchoolClassroom::query()
                ->where('school_id', $schoolId)
                ->where('school_stage_id', $stageId)
                ->whereKey($classroomId)
                ->first();

            if (!$classroom) {
                throw ValidationException::withMessages([
                    'school_classroom_id' => 'لا يمكن الحفظ لأن الفصل لا ينتمي إلى نفس المدرسة أو لا يتبع المرحلة المحددة.',
                ]);
            }
        }

        if ($stageGradeId <= 0 && $classroom !== null) {
            $stageGradeId = $this->resolveStageGradeIdByName(
                schoolId: $schoolId,
                stageId: $stageId,
                gradeName: (string) $classroom->grade_name,
                createIfMissing: true
            );
        }

        $stageGrade = SchoolStageGrade::query()
            ->where('school_id', $schoolId)
            ->where('school_stage_id', $stageId)
            ->whereKey($stageGradeId)
            ->first();

        if (!$stageGrade) {
            throw ValidationException::withMessages([
                'school_stage_grade_id' => 'لا يمكن الحفظ لأن الصف المحدد غير صالح أو لا يتبع المرحلة المحددة.',
            ]);
        }

        if ($classroom !== null) {
            if (
                $this->normalizeGradeName((string) $classroom->grade_name)
                !== $this->normalizeGradeName((string) $stageGrade->name)
            ) {
                throw ValidationException::withMessages([
                    'school_classroom_id' => 'لا يمكن الحفظ لأن الفصل المحدد لا ينتمي إلى نفس الصف المرتبط بالمقرر.',
                ]);
            }
        } else {
            $classroomId = $this->resolveRepresentativeClassroomIdForStageGrade(
                schoolId: $schoolId,
                stageId: $stageId,
                gradeName: (string) $stageGrade->name
            );
        }

        if ($classroomId <= 0) {
            throw ValidationException::withMessages([
                'school_stage_grade_id' => 'لا يمكن حفظ المقرر لأن الصف المحدد لا يحتوي على فصول أو شعب نشطة.',
            ]);
        }

        $validated['school_stage_grade_id'] = (int) $stageGrade->id;
        $validated['school_classroom_id'] = $classroomId;

        return $validated;
    }

    private function resolveStageGradeIdByName(
        int $schoolId,
        int $stageId,
        string $gradeName,
        bool $createIfMissing = false
    ): int {
        $normalizedGradeName = trim($gradeName);
        if ($normalizedGradeName === '') {
            return 0;
        }

        $existingId = SchoolStageGrade::query()
            ->where('school_id', $schoolId)
            ->where('school_stage_id', $stageId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($normalizedGradeName)])
            ->value('id');

        if ($existingId) {
            return (int) $existingId;
        }

        if (!$createIfMissing) {
            return 0;
        }

        $sortOrder = ((int) SchoolStageGrade::query()
            ->where('school_id', $schoolId)
            ->where('school_stage_id', $stageId)
            ->max('sort_order')) + 1;

        $grade = SchoolStageGrade::query()->create([
            'school_id' => $schoolId,
            'school_stage_id' => $stageId,
            'name' => $normalizedGradeName,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);

        return (int) $grade->id;
    }

    private function resolveRepresentativeClassroomIdForStageGrade(int $schoolId, int $stageId, string $gradeName): int
    {
        $classroom = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->where('school_stage_id', $stageId)
            ->where('is_active', true)
            ->whereRaw('LOWER(TRIM(grade_name)) = ?', [mb_strtolower(trim($gradeName))])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first(['id']);

        return $classroom ? (int) $classroom->id : 0;
    }

    /**
     * @param array<int, mixed> $units
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStudyPlanUnits(array $units): array
    {
        return collect($units)
            ->values()
            ->map(function ($unit, int $unitIndex): array {
                $lessons = collect(data_get($unit, 'lessons', []))
                    ->values()
                    ->map(function ($lesson, int $lessonIndex): array {
                        $topics = collect(data_get($lesson, 'topics', []))
                            ->values()
                            ->map(function ($topic, int $topicIndex): array {
                                return [
                                    'name' => trim((string) data_get($topic, 'name')),
                                    'sort_order' => (int) data_get($topic, 'sort_order', $topicIndex + 1),
                                    'description' => $this->emptyToNull(data_get($topic, 'description')),
                                ];
                            })
                            ->all();

                        return [
                            'name' => trim((string) data_get($lesson, 'name')),
                            'sort_order' => (int) data_get($lesson, 'sort_order', $lessonIndex + 1),
                            'description' => $this->emptyToNull(data_get($lesson, 'description')),
                            'topics' => $topics,
                        ];
                    })
                    ->all();

                return [
                    'branch_name' => $this->normalizeStudyPlanBranchName((string) data_get($unit, 'branch_name', '')),
                    'name' => trim((string) data_get($unit, 'name')),
                    'sort_order' => (int) data_get($unit, 'sort_order', $unitIndex + 1),
                    'start_date' => Carbon::parse((string) data_get($unit, 'start_date'))->toDateString(),
                    'end_date' => Carbon::parse((string) data_get($unit, 'end_date'))->toDateString(),
                    'notes' => $this->emptyToNull(data_get($unit, 'notes')),
                    'lessons' => $lessons,
                ];
            })
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $units
     */
    private function assertStudyPlanRequiredOnCreate(array $units, ?int $ignoreId): void
    {
        if ($ignoreId !== null) {
            return;
        }

        if (count($units) > 0) {
            return;
        }

        throw ValidationException::withMessages([
            'study_plan_units' => 'لا يمكن إضافة المقرر بدون إضافة خطة دراسية واحدة على الأقل.',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $units
     */
    private function assertStudyPlanBranchesBelongToSubject(SchoolSubject $subject, array $units): void
    {
        $allowedBranches = $this->subjectBranchNamesForSelection($subject)
            ->map(fn (string $branch) => mb_strtolower(trim($branch)))
            ->values();

        $errors = [];
        foreach ($units as $unitIndex => $unit) {
            $unitBranch = $this->normalizeStudyPlanBranchName((string) ($unit['branch_name'] ?? ''));
            $unitBranchKey = mb_strtolower(trim($unitBranch));

            if (!$allowedBranches->contains($unitBranchKey)) {
                $errors["study_plan_units.$unitIndex.branch_name"] = 'لا يمكن حفظ الوحدة لأن الفرع المحدد لا يتبع المادة المختارة.';
            }
        }

        if (count($errors) > 0) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $units
     */
    private function assertStudyPlanWithinTermBounds(SchoolTerm $term, array $units, int $alertBeforeTermEndDays): void
    {
        $termStart = $term->start_date?->toDateString();
        $termEnd = $term->end_date?->toDateString();

        if (!$termStart || !$termEnd) {
            throw ValidationException::withMessages([
                'school_term_id' => 'لا يمكن حفظ الخطة الدراسية لأن الترم المحدد لا يحتوي على فترة زمنية صالحة.',
            ]);
        }

        $termDays = Carbon::parse($termStart)->diffInDays(Carbon::parse($termEnd));
        if ($alertBeforeTermEndDays > $termDays) {
            throw ValidationException::withMessages([
                'alert_before_term_end_days' => 'عدد أيام التنبيه لا يمكن أن يتجاوز طول الترم المحدد.',
            ]);
        }

        $errors = [];
        foreach ($units as $unitIndex => $unit) {
            $unitStart = (string) ($unit['start_date'] ?? '');
            $unitEnd = (string) ($unit['end_date'] ?? '');

            if ($unitStart === '' || $unitEnd === '') {
                continue;
            }

            if ($unitEnd < $unitStart) {
                $errors["study_plan_units.$unitIndex.end_date"] = 'تاريخ نهاية الوحدة يجب أن يكون بعد أو يساوي تاريخ البداية.';
                continue;
            }

            $startsOutsideTerm = $unitStart < $termStart;
            $endsOutsideTerm = $unitEnd > $termEnd;
            if ($startsOutsideTerm || $endsOutsideTerm) {
                $errors["study_plan_units.$unitIndex.start_date"] = 'لا يمكن حفظ الوحدة لأن تاريخ البداية أو النهاية خارج الترم المحدد.';
            }
        }

        if (count($errors) > 0) {
            $errors['study_plan_units'] = 'لا يمكن حفظ الخطة الدراسية لأن تواريخ الوحدات تتجاوز حدود الترم.';
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $units
     */
    private function syncCourseOfferingStudyPlan(int $offeringId, int $schoolId, int $userId, array $units): void
    {
        SchoolCoursePlanUnit::query()
            ->where('school_id', $schoolId)
            ->where('school_course_offering_id', $offeringId)
            ->delete();

        foreach ($units as $unit) {
            $unitModel = SchoolCoursePlanUnit::query()->create([
                'school_id' => $schoolId,
                'school_course_offering_id' => $offeringId,
                'branch_name' => $this->normalizeStudyPlanBranchName((string) ($unit['branch_name'] ?? '')),
                'name' => (string) ($unit['name'] ?? ''),
                'sort_order' => (int) ($unit['sort_order'] ?? 0),
                'start_date' => (string) ($unit['start_date'] ?? ''),
                'end_date' => (string) ($unit['end_date'] ?? ''),
                'notes' => $this->emptyToNull($unit['notes'] ?? null),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach (($unit['lessons'] ?? []) as $lesson) {
                $lessonModel = SchoolCoursePlanLesson::query()->create([
                    'school_id' => $schoolId,
                    'school_course_plan_unit_id' => (int) $unitModel->id,
                    'name' => (string) ($lesson['name'] ?? ''),
                    'sort_order' => (int) ($lesson['sort_order'] ?? 0),
                    'description' => $this->emptyToNull($lesson['description'] ?? null),
                ]);

                foreach (($lesson['topics'] ?? []) as $topic) {
                    SchoolCoursePlanTopic::query()->create([
                        'school_id' => $schoolId,
                        'school_course_plan_lesson_id' => (int) $lessonModel->id,
                        'name' => (string) ($topic['name'] ?? ''),
                        'sort_order' => (int) ($topic['sort_order'] ?? 0),
                        'description' => $this->emptyToNull($topic['description'] ?? null),
                    ]);
                }
            }
        }
    }

    private function normalizeStudyPlanBranchName(?string $value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return self::DEFAULT_STUDY_PLAN_BRANCH_NAME;
        }

        return $normalized;
    }

    /**
     * @return Collection<int, SchoolClassroom>
     */
    private function classroomsForOfferingScope(int $schoolId, SchoolCourseOffering $offering): Collection
    {
        $query = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->where('school_stage_id', (int) $offering->school_stage_id)
            ->where('is_active', true);

        $stageGradeId = (int) ($offering->school_stage_grade_id ?? 0);
        if ($stageGradeId > 0) {
            $stageGrade = SchoolStageGrade::query()
                ->where('school_id', $schoolId)
                ->where('school_stage_id', (int) $offering->school_stage_id)
                ->whereKey($stageGradeId)
                ->first(['id', 'name']);

            if ($stageGrade) {
                $query->whereRaw('LOWER(TRIM(grade_name)) = ?', [mb_strtolower(trim((string) $stageGrade->name))]);
            } elseif ((int) ($offering->school_classroom_id ?? 0) > 0) {
                $query->whereKey((int) $offering->school_classroom_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ((int) ($offering->school_classroom_id ?? 0) > 0) {
            $query->whereKey((int) $offering->school_classroom_id);
        }

        return $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'school_id', 'school_stage_id', 'grade_name', 'name', 'is_active']);
    }

    private function resolveCourseOfferingForClassroomScope(
        int $schoolId,
        int $termId,
        int $stageId,
        int $classroomId,
        int $subjectId
    ): ?SchoolCourseOffering {
        $classroom = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->where('school_stage_id', $stageId)
            ->whereKey($classroomId)
            ->first(['id', 'grade_name']);

        if (!$classroom) {
            return null;
        }

        $stageGradeId = $this->resolveStageGradeIdByName(
            schoolId: $schoolId,
            stageId: $stageId,
            gradeName: (string) $classroom->grade_name
        );

        return SchoolCourseOffering::query()
            ->where('school_id', $schoolId)
            ->where('school_term_id', $termId)
            ->where('school_stage_id', $stageId)
            ->where('school_subject_id', $subjectId)
            ->where('is_active', true)
            ->where(function ($query) use ($classroomId, $stageGradeId): void {
                $query->where('school_classroom_id', $classroomId);
                if ($stageGradeId > 0) {
                    $query->orWhere('school_stage_grade_id', $stageGradeId);
                }
            })
            ->orderByDesc('school_stage_grade_id')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param Collection<int, SchoolCourseOffering> $courseOfferings
     */
    private function attachTermEndAlertFlags(Collection $courseOfferings): void
    {
        $today = now()->startOfDay();

        $courseOfferings->each(function (SchoolCourseOffering $offering) use ($today): void {
            $alertDays = max(0, (int) ($offering->alert_before_term_end_days ?? 0));
            $termEndDate = $offering->term?->end_date?->toDateString();

            $daysRemaining = null;
            $isNearEnd = false;

            if ($termEndDate !== null) {
                $daysRemaining = $today->diffInDays(Carbon::parse($termEndDate), false);
                $isNearEnd = $daysRemaining >= 0 && $daysRemaining <= $alertDays;
            }

            $offering->setAttribute('term_end_alert', [
                'is_near_end' => $isNearEnd,
                'days_remaining' => $daysRemaining,
                'alert_days' => $alertDays,
            ]);
        });
    }

    private function resolveSchoolId(Request $request): int
    {
        $schoolId = (int) $request->attributes->get('school_context_id', (int) ($request->user()?->school_id ?? 0));

        if ($schoolId <= 0) {
            abort(403, 'لا يمكن تنفيذ العملية بدون تحديد المدرسة الحالية.');
        }

        return $schoolId;
    }

    /**
     * @param Collection<int, SchoolTerm> $terms
     */
    private function resolveSelectedTerm(Collection $terms, int $requestedTermId): ?SchoolTerm
    {
        if ($requestedTermId > 0) {
            $requested = $terms->firstWhere('id', $requestedTermId);
            if ($requested instanceof SchoolTerm) {
                return $requested;
            }
        }

        $today = now()->toDateString();
        $coversToday = static function (SchoolTerm $term) use ($today): bool {
            $start = $term->start_date?->toDateString();
            $end = $term->end_date?->toDateString();

            return $start !== null && $end !== null && $today >= $start && $today <= $end;
        };

        $matchingActive = $terms->first(fn (SchoolTerm $term): bool => (bool) $term->is_active && $coversToday($term));
        if ($matchingActive instanceof SchoolTerm) {
            return $matchingActive;
        }

        $matchingByDate = $terms->first($coversToday);
        if ($matchingByDate instanceof SchoolTerm) {
            return $matchingByDate;
        }

        $upcomingActive = $terms
            ->filter(fn (SchoolTerm $term): bool => (bool) $term->is_active && $term->start_date?->toDateString() !== null && $term->start_date->toDateString() >= $today)
            ->sortBy(fn (SchoolTerm $term) => $term->start_date?->toDateString() ?? '9999-12-31')
            ->first();

        if ($upcomingActive instanceof SchoolTerm) {
            return $upcomingActive;
        }

        $recentActive = $terms
            ->filter(fn (SchoolTerm $term): bool => (bool) $term->is_active && $term->end_date?->toDateString() !== null && $term->end_date->toDateString() < $today)
            ->sortByDesc(fn (SchoolTerm $term) => $term->end_date?->toDateString() ?? '0000-00-00')
            ->first();

        if ($recentActive instanceof SchoolTerm) {
            return $recentActive;
        }

        $firstActive = $terms->first(fn (SchoolTerm $term) => (bool) $term->is_active);

        return $firstActive instanceof SchoolTerm ? $firstActive : $terms->first();
    }

    private function resolveSelectedScope(string $requestedScope): string
    {
        $scope = strtoupper(trim($requestedScope));
        if (in_array($scope, SchoolClassSchedule::allowedScopes(), true)) {
            return $scope;
        }

        return SchoolClassSchedule::SCOPE_WEEKLY;
    }

    private function resolveRequestedPlanningPage(Request $request): ?string
    {
        $requestedPage = trim((string) $request->query('page', $request->input('page', '')));
        if ($requestedPage === '') {
            return null;
        }

        $allowedPages = [
            'stages',
            'years',
            'terms',
            'calendar',
            'subjects',
            'schedules',
            'classrooms',
        ];

        return in_array($requestedPage, $allowedPages, true) ? $requestedPage : null;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function planningIndexRouteParams(Request $request, array $params): array
    {
        $requestedPage = $this->resolveRequestedPlanningPage($request);
        if ($requestedPage !== null && $requestedPage !== 'stages') {
            $params['page'] = $requestedPage;
        }

        return $params;
    }

    private function normalizeSubjectCode(mixed $value): ?string
    {
        $normalized = strtoupper(trim((string) ($value ?? '')));
        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param array<int, mixed> $branches
     * @return Collection<int, string>
     */
    private function normalizeSubjectBranches(array $branches): Collection
    {
        return collect($branches)
            ->map(fn ($branch) => trim((string) $branch))
            ->filter(fn ($branch) => $branch !== '')
            ->unique(fn ($branch) => mb_strtolower($branch))
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function subjectBranchNamesForSelection(SchoolSubject $subject): Collection
    {
        $configured = $this->normalizeSubjectBranches((array) ($subject->branches ?? []))->all();

        return collect([self::DEFAULT_STUDY_PLAN_BRANCH_NAME, ...$configured])
            ->map(fn ($branch) => trim((string) $branch))
            ->filter(fn ($branch) => $branch !== '')
            ->unique(fn ($branch) => mb_strtolower($branch))
            ->values();
    }

    private function generateScopedCode(string $table, string $column, string $prefix, int $schoolId, int $padLength = 4): string
    {
        $this->lockSchoolRowForCodeGeneration($schoolId);

        $codes = DB::table($table)
            ->where('school_id', $schoolId)
            ->whereNotNull($column)
            ->pluck($column);

        $pattern = '/^' . preg_quote($prefix, '/') . '-(\d+)$/';
        $max = 0;

        foreach ($codes as $code) {
            if (preg_match($pattern, (string) $code, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $next = $max + 1;
        while (true) {
            $candidate = sprintf('%s-%0' . $padLength . 'd', $prefix, $next);
            $exists = DB::table($table)
                ->where('school_id', $schoolId)
                ->where($column, $candidate)
                ->exists();

            if (!$exists) {
                return $candidate;
            }

            $next++;
        }
    }

    private function lockSchoolRowForCodeGeneration(int $schoolId): void
    {
        School::query()
            ->whereKey($schoolId)
            ->lockForUpdate()
            ->first();
    }

    private function rethrowDuplicateSubjectCode(QueryException $exception): void
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (string) ($exception->errorInfo[1] ?? '');
        $isDuplicate = $sqlState === '23000'
            || $sqlState === '23505'
            || in_array($driverCode, ['1062', '19', '2067'], true);

        if (!$isDuplicate) {
            return;
        }

        throw ValidationException::withMessages([
            'code' => 'Subject code already exists in this school.',
        ]);
    }

    /**
     * @param array<int, mixed> $teacherIds
     * @return Collection<int, int>
     */
    private function normalizeTeacherIds(array $teacherIds): Collection
    {
        return collect($teacherIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
    }

    /**
     * @param Collection<int, int> $teacherIds
     */
    private function assertTeacherIdsAssignable(int $schoolId, Collection $teacherIds): void
    {
        if ($teacherIds->isEmpty()) {
            return;
        }

        $validCount = $this->teacherQuery($schoolId)
            ->whereIn('id', $teacherIds->all())
            ->count();

        if ($validCount !== $teacherIds->count()) {
            throw ValidationException::withMessages([
                'teacher_user_ids' => 'لا يمكن حفظ الإسناد لأن بعض المعلمين المحددين غير صالحين أو لا ينتمون إلى نفس المدرسة.',
            ]);
        }
    }

    /**
     * @return Collection<int, int>
     */
    private function fetchSubjectTeacherIds(int $schoolId, int $subjectId): Collection
    {
        return SchoolSubjectTeacherAssignment::query()
            ->where('school_id', $schoolId)
            ->where('school_subject_id', $subjectId)
            ->orderBy('teacher_user_id')
            ->pluck('teacher_user_id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    /**
     * @param Collection<int, int> $teacherIds
     */
    private function syncSubjectTeacherAssignments(int $schoolId, int $subjectId, Collection $teacherIds): void
    {
        SchoolSubjectTeacherAssignment::query()
            ->where('school_id', $schoolId)
            ->where('school_subject_id', $subjectId)
            ->delete();

        if ($teacherIds->isEmpty()) {
            return;
        }

        $rows = $teacherIds->map(function (int $teacherId) use ($schoolId, $subjectId): array {
            return [
                'school_id' => $schoolId,
                'school_subject_id' => $subjectId,
                'teacher_user_id' => $teacherId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->all();

        SchoolSubjectTeacherAssignment::query()->insert($rows);
    }

    private function ensureTermInSchool(SchoolTerm $term, int $schoolId): void
    {
        if ((int) $term->school_id !== $schoolId) {
            abort(403, 'لا يمكن الوصول إلى هذا الترم لأنه لا ينتمي إلى نفس المدرسة.');
        }
    }

    private function ensureTimetableVersionInSchool(SchoolTimetableVersion $version, int $schoolId): void
    {
        if ((int) $version->school_id !== $schoolId) {
            abort(403, 'لا يمكن الوصول إلى نسخة الجدول لأنها لا تنتمي إلى نفس المدرسة.');
        }
    }

    private function ensureAcademicYearInSchool(SchoolAcademicYear $year, int $schoolId): void
    {
        if ((int) $year->school_id !== $schoolId) {
            abort(403, 'لا يمكن الوصول إلى العام الدراسي لأنه لا ينتمي إلى نفس المدرسة.');
        }
    }

    private function ensureCourseOfferingInSchool(SchoolCourseOffering $courseOffering, int $schoolId): void
    {
        if ((int) $courseOffering->school_id !== $schoolId) {
            abort(403, 'لا يمكن الوصول إلى هذا المقرر لأنه لا ينتمي إلى نفس المدرسة.');
        }
    }

    private function ensureSubjectInSchool(SchoolSubject $subject, int $schoolId): void
    {
        if ((int) $subject->school_id !== $schoolId) {
            abort(403, 'لا يمكن الوصول إلى هذه المادة لأنها لا تنتمي إلى نفس المدرسة.');
        }
    }

    private function ensureScheduleInSchool(SchoolClassSchedule $schedule, int $schoolId): void
    {
        if ((int) $schedule->school_id !== $schoolId) {
            abort(403, 'لا يمكن الوصول إلى هذا الجدول لأنه لا ينتمي إلى نفس المدرسة.');
        }
    }

    private function teacherQuery(int $schoolId)
    {
        return User::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query
                    ->where('school_staff_type', User::SCHOOL_STAFF_EDUCATIONAL)
                    ->orWhere('role', 'teacher')
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'teacher'))
                    ->orWhereHas('department', function ($departmentQuery): void {
                        $departmentQuery->where(function ($departmentTypeOrNameQuery): void {
                            $departmentTypeOrNameQuery
                                ->where('staff_type', User::SCHOOL_STAFF_EDUCATIONAL)
                                ->orWhereRaw('LOWER(name) LIKE ?', ['%teacher%'])
                                ->orWhere('name', 'like', '%معلم%');
                        });
                    })
                    ->orWhereHas('departmentRole', function ($departmentRoleQuery): void {
                        $departmentRoleQuery
                            ->where('is_active', true)
                            ->where(function ($roleNameOrTemplateQuery): void {
                                $roleNameOrTemplateQuery
                                    ->whereRaw('LOWER(name) LIKE ?', ['%teacher%'])
                                    ->orWhere('name', 'like', '%معلم%')
                                    ->orWhereHas('orgStructureRoleTemplate', function ($templateQuery): void {
                                        $templateQuery
                                            ->where('is_active', true)
                                            ->where(function ($templateNameOrCodeQuery): void {
                                                $templateNameOrCodeQuery
                                                    ->whereRaw('LOWER(name) LIKE ?', ['%teacher%'])
                                                    ->orWhereRaw('LOWER(code) LIKE ?', ['%teacher%'])
                                                    ->orWhere('name', 'like', '%معلم%')
                                                    ->orWhere('code', 'like', '%معلم%');
                                            });
                                    });
                            });
                    });
            });
    }

    private function weeklyGridSchedulesQuery(
        int $schoolId,
        int $termId,
        int $stageId,
        int $classroomId,
        ?int $timetableVersionId = null
    )
    {
        return SchoolClassSchedule::query()
            ->where('school_id', $schoolId)
            ->where('school_term_id', $termId)
            ->where('school_stage_id', $stageId)
            ->where('school_classroom_id', $classroomId)
            ->where('schedule_scope', SchoolClassSchedule::SCOPE_WEEKLY)
            ->when(
                $timetableVersionId !== null,
                fn ($query) => $query->where('school_timetable_version_id', $timetableVersionId),
                fn ($query) => $query->whereNull('school_timetable_version_id')
            );
    }

    private function weeklyGridSlotKey(int $dayOfWeek, int $sessionIndex): string
    {
        return $dayOfWeek . ':' . $sessionIndex;
    }

    /**
     * @param array<string, mixed> $cell
     */
    private function gridCellHasScheduleData(array $cell): bool
    {
        if (Arr::get($cell, 'school_subject_id') !== null || Arr::get($cell, 'teacher_user_id') !== null) {
            return true;
        }

        foreach (['starts_at', 'ends_at', 'notes'] as $field) {
            $value = Arr::get($cell, $field);
            if (trim((string) ($value ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function scheduleTimeLabelForExport(mixed $startsAt, mixed $endsAt): string
    {
        $start = $this->emptyToNull($startsAt);
        $end = $this->emptyToNull($endsAt);

        if ($start !== null && $end !== null) {
            return $start . ' - ' . $end;
        }

        if ($start !== null) {
            return 'من ' . $start;
        }

        if ($end !== null) {
            return 'حتى ' . $end;
        }

        return '';
    }

    private function resolveTimetableVersionName(?int $timetableVersionId, int $schoolId): string
    {
        if ($timetableVersionId === null) {
            return '';
        }

        return (string) (SchoolTimetableVersion::query()
            ->where('school_id', $schoolId)
            ->whereKey($timetableVersionId)
            ->value('name') ?? '');
    }

    private function weeklyGridExportFileName(string $schoolName, string $termName, string $classroomName): string
    {
        $timestamp = now()->format('Ymd-His');

        return 'weekly-timetable-' . $timestamp;
    }

    /**
     * @return array<int, array{value:int, label:string}>
     */
    private function weekDayOptions(): array
    {
        return [
            ['value' => 0, 'label' => 'الأحد'],
            ['value' => 1, 'label' => 'الاثنين'],
            ['value' => 2, 'label' => 'الثلاثاء'],
            ['value' => 3, 'label' => 'الأربعاء'],
            ['value' => 4, 'label' => 'الخميس'],
            ['value' => 5, 'label' => 'الجمعة'],
            ['value' => 6, 'label' => 'السبت'],
        ];
    }

    private function emptyToNull(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeGradeName(string $value): string
    {
        return trim(mb_strtolower($value));
    }
}
