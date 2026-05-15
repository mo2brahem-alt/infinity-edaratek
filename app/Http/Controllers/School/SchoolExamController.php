<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolClassroom;
use App\Models\SchoolClassSchedule;
use App\Models\SchoolCourseOffering;
use App\Models\SchoolExam;
use App\Models\SchoolExamQuestion;
use App\Models\SchoolExamSetting;
use App\Models\SchoolExamStatusLog;
use App\Models\SchoolExamStudentScore;
use App\Models\SchoolExamTemplate;
use App\Models\SchoolQuestionBankItem;
use App\Models\SchoolQuestionOption;
use App\Models\SchoolStage;
use App\Models\SchoolStageGrade;
use App\Models\SchoolStudent;
use App\Models\SchoolSubject;
use App\Models\SchoolSubjectTeacherAssignment;
use App\Models\SchoolTeachingAssignment;
use App\Models\SchoolTerm;
use App\Models\User;
use App\Services\School\ExamSchedulingValidationService;
use App\Services\Support\AttachmentService;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SchoolExamController extends Controller
{
    private const DEFAULT_STUDY_PLAN_BRANCH_NAME = 'الفرع الرئيسي';

    public function __construct(
        private readonly ExamSchedulingValidationService $examSchedulingValidationService,
        private readonly AuditLogger $auditLogger,
        private readonly AttachmentService $attachmentService,
    ) {
    }

    public function index(Request $request): Response
    {
        $schoolId = $this->resolveSchoolId($request);
        $user = $request->user();
        $teacherScoped = $this->isTeacherScoped($user);
        $assignmentScope = $teacherScoped && $user
            ? $this->teacherAssignmentScope($schoolId, $user)
            : null;
        $teacherHasAssignmentScope = (bool) ($assignmentScope['has_assignments'] ?? false);
        $teacherSubjectIds = $teacherScoped ? ($assignmentScope['subject_ids'] ?? []) : [];
        $teacherClassroomIds = $teacherScoped ? ($assignmentScope['classroom_ids'] ?? []) : [];
        $teacherTermIds = $teacherScoped ? ($assignmentScope['term_ids'] ?? []) : [];
        $teacherStageIds = $teacherScoped ? ($assignmentScope['stage_ids'] ?? []) : [];
        $permissions = $this->permissions($user, $schoolId);

        $school = School::query()
            ->whereKey($schoolId)
            ->first(['id', 'name', 'school_id']);

        $settings = SchoolExamSetting::query()
            ->where('school_id', $schoolId)
            ->orderByDesc('id')
            ->first();

        $templates = SchoolExamTemplate::query()
            ->where('school_id', $schoolId)
            ->when($teacherScoped, fn ($query) => $query->where('is_active', true))
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'school_id',
                'name',
                'exam_type',
                'default_max_score',
                'default_passing_score',
                'requires_approval',
                'teacher_can_override_max_score',
                'teacher_can_override_passing_score',
                'affects_final_result',
                'is_active',
                'sort_order',
                'notes',
            ]);

        $subjects = SchoolSubject::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->when($teacherScoped, fn ($query) => $query->whereIn('id', $teacherSubjectIds))
            ->with([
                'teacherAssignments' => fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->select(['id', 'school_id', 'school_subject_id', 'teacher_user_id']),
            ])
            ->orderBy('name')
            ->get([
                'id',
                'school_id',
                'name',
                'code',
                'is_active',
            ]);

        $subjectIds = $subjects
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $subjectTeacherAssignmentRows = collect();
        $teachingAssignmentRows = collect();
        if (count($subjectIds) > 0) {
            $subjectTeacherAssignmentRows = SchoolSubjectTeacherAssignment::query()
                ->where('school_id', $schoolId)
                ->whereIn('school_subject_id', $subjectIds)
                ->when($teacherScoped, fn ($query) => $query->where('teacher_user_id', (int) $user->id))
                ->orderBy('school_subject_id')
                ->get(['school_subject_id', 'teacher_user_id']);

            $teachingAssignmentRows = SchoolTeachingAssignment::query()
                ->where('school_id', $schoolId)
                ->where('is_active', true)
                ->when($teacherScoped, fn ($query) => $query->where('teacher_user_id', (int) $user->id))
                ->whereHas('courseOffering', fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->whereIn('school_subject_id', $subjectIds)
                    ->where('is_active', true))
                ->with([
                    'courseOffering:id,school_subject_id',
                ])
                ->get([
                    'id',
                    'school_id',
                    'school_course_offering_id',
                    'teacher_user_id',
                    'is_active',
                ]);
        }

        $assignedTeacherIds = $subjectTeacherAssignmentRows
            ->pluck('teacher_user_id')
            ->merge($teachingAssignmentRows->pluck('teacher_user_id'))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $assignedTeachers = collect();
        if (count($assignedTeacherIds) > 0) {
            $assignedTeachers = User::query()
                ->where('school_id', $schoolId)
                ->where('is_active', true)
                ->whereIn('id', $assignedTeacherIds)
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'email',
                    'school_id',
                    'school_staff_type',
                ]);
        }

        $assignedTeachersById = $assignedTeachers
            ->keyBy(fn (User $teacher): int => (int) $teacher->id);

        $subjectTeacherOptions = collect($subjectIds)
            ->mapWithKeys(fn ($id): array => [(string) $id => []])
            ->all();
        $subjectTeacherSeen = [];
        $appendSubjectTeacherOption = function (int $subjectId, int $teacherId) use (&$subjectTeacherOptions, &$subjectTeacherSeen, $assignedTeachersById): void {
            if ($subjectId <= 0 || $teacherId <= 0) {
                return;
            }

            $teacher = $assignedTeachersById->get($teacherId);
            if (!$teacher) {
                return;
            }

            $mapKey = $subjectId.':'.$teacherId;
            if (isset($subjectTeacherSeen[$mapKey])) {
                return;
            }

            $subjectTeacherSeen[$mapKey] = true;

            if (!isset($subjectTeacherOptions[(string) $subjectId])) {
                $subjectTeacherOptions[(string) $subjectId] = [];
            }

            $subjectTeacherOptions[(string) $subjectId][] = [
                'id' => $teacherId,
                'name' => (string) ($teacher->name ?? ''),
                'email' => $teacher->email,
            ];
        };

        foreach ($subjectTeacherAssignmentRows as $assignment) {
            $appendSubjectTeacherOption(
                (int) $assignment->school_subject_id,
                (int) $assignment->teacher_user_id
            );
        }

        foreach ($teachingAssignmentRows as $assignment) {
            $appendSubjectTeacherOption(
                (int) ($assignment->courseOffering?->school_subject_id ?? 0),
                (int) $assignment->teacher_user_id
            );
        }

        $stages = SchoolStage::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->when($teacherScoped, function ($query) use ($teacherHasAssignmentScope, $teacherStageIds): void {
                if (!$teacherHasAssignmentScope || count($teacherStageIds) === 0) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->whereIn('id', $teacherStageIds);
            })
            ->with([
                'classrooms' => fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('is_active', true)
                    ->when($teacherScoped, fn ($inner) => $inner->whereIn('id', $teacherClassroomIds))
                    ->orderBy('sort_order')
                    ->orderBy('name')
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
            ]);

        $terms = SchoolTerm::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->when($teacherScoped, function ($query) use ($teacherHasAssignmentScope, $teacherTermIds): void {
                if (!$teacherHasAssignmentScope || count($teacherTermIds) === 0) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->whereIn('id', $teacherTermIds);
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get([
                'id',
                'school_id',
                'name',
                'start_date',
                'end_date',
                'is_active',
            ]);

        $teachers = User::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->when(
                $teacherScoped,
                fn ($query) => $query->whereKey((int) $user->id),
                fn ($query) => $query->where(function ($teacherLikeQuery): void {
                    $teacherLikeQuery
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
                })
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'school_id',
                'school_staff_type',
            ]);

        if (!$teacherScoped && $assignedTeachers->isNotEmpty()) {
            $teachers = $teachers
                ->concat($assignedTeachers)
                ->unique('id')
                ->sortBy('name')
                ->values();
        }

        $supportsInstitutionalAttachments = $this->supportsInstitutionalAttachments();

        $examCounts = [
            'questions as questions_count',
            'scores as scores_count',
        ];

        if ($supportsInstitutionalAttachments) {
            array_unshift($examCounts, 'attachments as attachments_count');
        }

        $exams = SchoolExam::query()
            ->where('school_id', $schoolId)
            ->when($teacherScoped, function ($query) use (
                $user,
                $teacherHasAssignmentScope,
                $teacherTermIds,
                $teacherStageIds,
                $teacherClassroomIds,
                $teacherSubjectIds
            ): void {
                $query->where('teacher_user_id', (int) $user->id);

                if (
                    !$teacherHasAssignmentScope
                    || count($teacherTermIds) === 0
                    || count($teacherStageIds) === 0
                    || count($teacherClassroomIds) === 0
                    || count($teacherSubjectIds) === 0
                ) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query
                    ->whereIn('school_term_id', $teacherTermIds)
                    ->whereIn('school_stage_id', $teacherStageIds)
                    ->whereIn('school_classroom_id', $teacherClassroomIds)
                    ->whereIn('school_subject_id', $teacherSubjectIds);
            })
            ->with([
                'template:id,name,exam_type',
                'term:id,name',
                'stage:id,name',
                'classroom:id,name,grade_name,school_stage_id',
                'subject:id,name,code',
                'teacher:id,name,email',
                'statusLogs' => fn ($query) => $query
                    ->orderByDesc('id')
                    ->limit(5)
                    ->select([
                        'id',
                        'school_id',
                        'school_exam_id',
                        'old_status',
                        'new_status',
                        'reason',
                        'changed_by',
                        'changed_at',
                        'created_at',
                    ]),
            ])
            ->withCount($examCounts)
            ->orderByDesc('exam_date')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->limit(250)
            ->get([
                'id',
                'school_id',
                'school_exam_template_id',
                'school_term_id',
                'school_stage_id',
                'school_classroom_id',
                'school_subject_id',
                'teacher_user_id',
                'title',
                'exam_date',
                'starts_at',
                'ends_at',
                'duration_minutes',
                'max_score',
                'passing_score',
                'status',
                'requires_approval',
                'allow_subject_schedule_overlap',
                'affects_final_result',
                'room_label',
                'notes',
                'approved_by',
                'approved_at',
                'published_at',
                'completed_at',
                'closed_at',
                'postpone_reason',
                'cancel_reason',
                'is_active',
                'created_by',
                'updated_by',
            ]);

        if (!$supportsInstitutionalAttachments) {
            $exams->each(fn (SchoolExam $exam) => $exam->setAttribute('attachments_count', 0));
        }

        $questionBankCourseOfferings = SchoolCourseOffering::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where('usable_in_exams', true)
            ->when($teacherScoped, fn ($query) => $query->whereHas('teachingAssignment', fn ($assignmentQuery) => $assignmentQuery
                ->where('school_id', $schoolId)
                ->where('teacher_user_id', (int) $user->id)
                ->where('is_active', true)
                ->where('can_use_question_bank', true)))
            ->with([
                'term:id,name',
                'stage:id,name',
                'classroom:id,name,grade_name',
                'subject:id,name,code',
                'studyPlanUnits' => fn ($query) => $query
                    ->select([
                        'id',
                        'school_id',
                        'school_course_offering_id',
                        'branch_name',
                        'name',
                        'sort_order',
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->with([
                        'lessons' => fn ($lessons) => $lessons
                            ->select([
                                'id',
                                'school_id',
                                'school_course_plan_unit_id',
                                'name',
                                'sort_order',
                            ])
                            ->orderBy('sort_order')
                            ->orderBy('id')
                            ->with([
                                'topics' => fn ($topics) => $topics
                                    ->select([
                                        'id',
                                        'school_id',
                                        'school_course_plan_lesson_id',
                                        'name',
                                        'sort_order',
                                    ])
                                    ->orderBy('sort_order')
                                    ->orderBy('id'),
                            ]),
                    ]),
            ])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get([
                'id',
                'school_id',
                'school_term_id',
                'school_stage_id',
                'school_classroom_id',
                'school_subject_id',
                'is_active',
                'usable_in_exams',
                'sort_order',
            ]);

        $questionBank = SchoolQuestionBankItem::query()
            ->where('school_id', $schoolId)
            ->when($teacherScoped, fn ($query) => $query->whereIn('school_subject_id', $teacherSubjectIds))
            ->when(
                $teacherScoped && !((bool) ($permissions['can_use_question_bank'] ?? false)),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->with([
                'subject:id,name,code',
                'stage:id,name',
                'term:id,name',
                'courseOffering:id,school_id,school_term_id,school_stage_id,school_classroom_id,school_subject_id',
                'options:id,school_id,school_question_bank_item_id,option_text,is_correct,sort_order',
            ])
            ->orderByDesc('id')
            ->limit(400)
            ->get([
                'id',
                'school_id',
                'school_course_offering_id',
                'school_subject_id',
                'school_stage_id',
                'school_term_id',
                'unit_name',
                'chapter_name',
                'lesson_name',
                'question_text',
                'question_type',
                'question_score',
                'selection_mode',
                'difficulty',
                'learning_outcome',
                'model_answer',
                'answer_explanation',
                'status',
                'tags',
                'attachment_path',
                'created_by',
                'updated_by',
            ]);

        $selectedExamId = (int) $request->query('exam_id', 0);
        if ($selectedExamId <= 0 && $exams->isNotEmpty()) {
            $selectedExamId = (int) $exams->first()->id;
        }

        $selectedExam = null;
        $selectedExamQuestions = collect();
        $selectedExamScores = collect();
        $selectedExamAttachments = collect();
        if ($selectedExamId > 0) {
            $selectedExam = $exams->firstWhere('id', $selectedExamId);
            if ($selectedExam !== null) {
                $selectedExamQuestions = SchoolExamQuestion::query()
                    ->where('school_id', $schoolId)
                    ->where('school_exam_id', $selectedExamId)
                    ->with([
                        'question:id,school_id,school_subject_id,question_text,question_type,question_score,selection_mode,difficulty,status',
                        'question.options:id,school_id,school_question_bank_item_id,option_text,is_correct,sort_order',
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get([
                        'id',
                        'school_id',
                        'school_exam_id',
                        'school_question_bank_item_id',
                        'sort_order',
                        'score',
                        'is_required',
                        'created_by',
                        'updated_by',
                    ]);

                $selectedExamScores = SchoolExamStudentScore::query()
                    ->where('school_id', $schoolId)
                    ->where('school_exam_id', $selectedExamId)
                    ->with([
                        'student:id,school_id,school_classroom_id,full_name,student_code',
                    ])
                    ->orderBy('school_student_id')
                    ->get([
                        'id',
                        'school_id',
                        'school_exam_id',
                        'school_student_id',
                        'score',
                        'attendance_status',
                        'notes',
                        'recorded_by',
                        'recorded_at',
                        'updated_by',
                        'is_finalized',
                        'finalized_by',
                        'finalized_at',
                    ]);

                if ($supportsInstitutionalAttachments) {
                    $selectedExamAttachments = $selectedExam->attachments()
                        ->with(['uploader:id,name'])
                        ->orderByDesc('id')
                        ->get()
                        ->map(fn ($attachment) => $this->attachmentService->serializeForUi($attachment));
                }
            }
        }

        $studentsForSelectedExam = collect();
        if ($selectedExam !== null) {
            $studentsForSelectedExam = SchoolStudent::query()
                ->where('school_id', $schoolId)
                ->where('school_classroom_id', (int) $selectedExam->school_classroom_id)
                ->where('is_active', true)
                ->orderBy('full_name')
                ->get([
                    'id',
                    'school_id',
                    'school_classroom_id',
                    'full_name',
                    'student_code',
                    'national_id',
                    'is_active',
                ]);
        }

        return Inertia::render('School/Exams', [
            'school' => $school,
            'settings' => [
                'allow_subject_schedule_slot_overlap' => (bool) (
                    $settings?->allow_subject_schedule_slot_overlap
                    ?? $settings?->allow_subject_schedule_overlap
                    ?? false
                ),
                'exam_day_start_time' => $settings?->exam_day_start_time,
                'exam_day_end_time' => $settings?->exam_day_end_time,
            ],
            'templates' => $templates,
            'terms' => $terms,
            'stages' => $stages,
            'subjects' => $subjects,
            'subjectTeacherOptions' => $subjectTeacherOptions,
            'teachers' => $teachers,
            'exams' => $exams,
            'questionBankCourseOfferings' => $questionBankCourseOfferings,
            'questionBank' => $questionBank,
            'selectedExamId' => $selectedExamId > 0 ? $selectedExamId : null,
            'selectedExamQuestions' => $selectedExamQuestions,
            'selectedExamScores' => $selectedExamScores,
            'selectedExamAttachments' => $selectedExamAttachments,
            'studentsForSelectedExam' => $studentsForSelectedExam,
            'templateTypes' => collect(SchoolExamTemplate::allowedTypes())
                ->map(fn ($value) => ['value' => $value, 'label' => $this->templateTypeLabel($value)])
                ->values()
                ->all(),
            'questionTypes' => collect(SchoolQuestionBankItem::allowedTypes())
                ->map(fn ($value) => ['value' => $value, 'label' => $this->questionTypeLabel($value)])
                ->values()
                ->all(),
            'questionDifficulties' => collect(SchoolQuestionBankItem::allowedDifficulties())
                ->map(fn ($value) => ['value' => $value, 'label' => $this->difficultyLabel($value)])
                ->values()
                ->all(),
            'questionSelectionModes' => collect(SchoolQuestionBankItem::allowedSelectionModes())
                ->map(fn ($value) => ['value' => $value, 'label' => $value === SchoolQuestionBankItem::SELECTION_REQUIRED ? 'إجباري' : 'اختياري'])
                ->values()
                ->all(),
            'examStatuses' => collect(SchoolExam::allowedStatuses())
                ->map(fn ($value) => ['value' => $value, 'label' => $this->examStatusLabel($value)])
                ->values()
                ->all(),
            'scoreAttendanceStatuses' => collect(SchoolExamStudentScore::allowedAttendanceStatuses())
                ->map(fn ($value) => ['value' => $value, 'label' => $this->scoreAttendanceStatusLabel($value)])
                ->values()
                ->all(),
            'isManager' => $user?->hasSystemRole('school_manager') ?? false,
            'permissions' => $permissions,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureManagerCanManageTemplates($request->user());
        $userId = (int) ($request->user()?->id ?? 0);

        $validated = $request->validate([
            'allow_subject_schedule_slot_overlap' => ['nullable', 'boolean'],
            'exam_day_start_time' => ['nullable', 'date_format:H:i', 'required_with:exam_day_end_time', 'before:exam_day_end_time'],
            'exam_day_end_time' => ['nullable', 'date_format:H:i', 'required_with:exam_day_start_time', 'after:exam_day_start_time'],
        ]);

        $settings = SchoolExamSetting::query()->firstOrNew([
            'school_id' => $schoolId,
        ]);

        $settings->fill([
            'allow_subject_schedule_slot_overlap' => (bool) ($validated['allow_subject_schedule_slot_overlap'] ?? false),
            'exam_day_start_time' => $this->normalizeTimeInput($validated['exam_day_start_time'] ?? null),
            'exam_day_end_time' => $this->normalizeTimeInput($validated['exam_day_end_time'] ?? null),
            'updated_by' => $userId > 0 ? $userId : null,
        ]);

        if (!$settings->exists) {
            $settings->created_by = $userId > 0 ? $userId : null;
        }

        $settings->save();

        $this->auditLogger->log(
            'school_exams.settings.updated',
            'school_exam_setting',
            (int) $settings->id,
            [
                'school_id' => $schoolId,
                'payload' => $settings->only([
                    'allow_subject_schedule_slot_overlap',
                    'exam_day_start_time',
                    'exam_day_end_time',
                ]),
            ],
            $request,
            $userId > 0 ? $userId : null
        );

        return back()->with('success', 'تم تحديث إعدادات الاختبارات بنجاح.');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureManagerCanManageTemplates($request->user());
        $userId = (int) ($request->user()?->id ?? 0);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('school_exam_templates', 'name')->where(
                    fn ($query) => $query->where('school_id', $schoolId)
                ),
            ],
            'exam_type' => ['required', Rule::in(SchoolExamTemplate::allowedTypes())],
            'default_max_score' => ['required', 'numeric', 'gt:0'],
            'default_passing_score' => ['required', 'numeric', 'gte:0', 'lte:default_max_score'],
            'requires_approval' => ['nullable', 'boolean'],
            'teacher_can_override_max_score' => ['nullable', 'boolean'],
            'teacher_can_override_passing_score' => ['nullable', 'boolean'],
            'affects_final_result' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $template = SchoolExamTemplate::query()->create([
            'school_id' => $schoolId,
            'name' => trim((string) $validated['name']),
            'exam_type' => (string) $validated['exam_type'],
            'default_max_score' => (float) $validated['default_max_score'],
            'default_passing_score' => (float) $validated['default_passing_score'],
            'requires_approval' => (bool) ($validated['requires_approval'] ?? false),
            'teacher_can_override_max_score' => (bool) ($validated['teacher_can_override_max_score'] ?? true),
            'teacher_can_override_passing_score' => (bool) ($validated['teacher_can_override_passing_score'] ?? true),
            'affects_final_result' => (bool) ($validated['affects_final_result'] ?? true),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'notes' => $this->nullIfEmpty($validated['notes'] ?? null),
            'created_by' => $userId > 0 ? $userId : null,
            'updated_by' => $userId > 0 ? $userId : null,
        ]);

        $this->auditLogger->log(
            'school_exams.template.created',
            'school_exam_template',
            (int) $template->id,
            [
                'school_id' => $schoolId,
                'payload' => $template->only([
                    'name',
                    'exam_type',
                    'default_max_score',
                    'default_passing_score',
                    'requires_approval',
                    'teacher_can_override_max_score',
                    'teacher_can_override_passing_score',
                    'affects_final_result',
                    'is_active',
                    'sort_order',
                ]),
            ],
            $request,
            $userId > 0 ? $userId : null
        );

        return back()->with('success', 'تم إنشاء مسمى الاختبار بنجاح.');
    }

    public function updateTemplate(Request $request, SchoolExamTemplate $schoolExamTemplate): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureManagerCanManageTemplates($request->user());
        $this->ensureTemplateInSchool($schoolExamTemplate, $schoolId);
        $userId = (int) ($request->user()?->id ?? 0);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('school_exam_templates', 'name')
                    ->where(fn ($query) => $query->where('school_id', $schoolId))
                    ->ignore($schoolExamTemplate->id),
            ],
            'exam_type' => ['required', Rule::in(SchoolExamTemplate::allowedTypes())],
            'default_max_score' => ['required', 'numeric', 'gt:0'],
            'default_passing_score' => ['required', 'numeric', 'gte:0', 'lte:default_max_score'],
            'requires_approval' => ['nullable', 'boolean'],
            'teacher_can_override_max_score' => ['nullable', 'boolean'],
            'teacher_can_override_passing_score' => ['nullable', 'boolean'],
            'affects_final_result' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $before = $schoolExamTemplate->only([
            'name',
            'exam_type',
            'default_max_score',
            'default_passing_score',
            'requires_approval',
            'teacher_can_override_max_score',
            'teacher_can_override_passing_score',
            'affects_final_result',
            'is_active',
            'sort_order',
            'notes',
        ]);

        $schoolExamTemplate->update([
            'name' => trim((string) $validated['name']),
            'exam_type' => (string) $validated['exam_type'],
            'default_max_score' => (float) $validated['default_max_score'],
            'default_passing_score' => (float) $validated['default_passing_score'],
            'requires_approval' => (bool) ($validated['requires_approval'] ?? false),
            'teacher_can_override_max_score' => (bool) ($validated['teacher_can_override_max_score'] ?? true),
            'teacher_can_override_passing_score' => (bool) ($validated['teacher_can_override_passing_score'] ?? true),
            'affects_final_result' => (bool) ($validated['affects_final_result'] ?? true),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'notes' => $this->nullIfEmpty($validated['notes'] ?? null),
            'updated_by' => $userId > 0 ? $userId : null,
        ]);

        $this->auditLogger->log(
            'school_exams.template.updated',
            'school_exam_template',
            (int) $schoolExamTemplate->id,
            [
                'school_id' => $schoolId,
                'before' => $before,
                'after' => $schoolExamTemplate->only(array_keys($before)),
            ],
            $request,
            $userId > 0 ? $userId : null
        );

        return back()->with('success', 'تم تعديل مسمى الاختبار بنجاح.');
    }

    public function destroyTemplate(Request $request, SchoolExamTemplate $schoolExamTemplate): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureManagerCanManageTemplates($request->user());
        $this->ensureTemplateInSchool($schoolExamTemplate, $schoolId);
        $userId = (int) ($request->user()?->id ?? 0);

        $hasExams = SchoolExam::query()
            ->where('school_id', $schoolId)
            ->where('school_exam_template_id', (int) $schoolExamTemplate->id)
            ->exists();

        if ($hasExams) {
            throw ValidationException::withMessages([
                'template' => 'لا يمكن حذف مسمى الاختبار لوجود اختبارات مرتبطة به.',
            ]);
        }

        $snapshot = $schoolExamTemplate->only([
            'name',
            'exam_type',
            'default_max_score',
            'default_passing_score',
            'requires_approval',
            'teacher_can_override_max_score',
            'teacher_can_override_passing_score',
            'affects_final_result',
            'is_active',
            'sort_order',
        ]);

        $schoolExamTemplate->delete();

        $this->auditLogger->log(
            'school_exams.template.deleted',
            'school_exam_template',
            (int) $schoolExamTemplate->id,
            [
                'school_id' => $schoolId,
                'before' => $snapshot,
            ],
            $request,
            $userId > 0 ? $userId : null
        );

        return back()->with('success', 'تم حذف مسمى الاختبار بنجاح.');
    }

    public function storeExam(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $user = $request->user();
        $userId = (int) ($user?->id ?? 0);

        $validated = $this->validateExamPayload($request, $schoolId);
        $request->validate(
            $this->attachmentService->uploadValidationRules(),
            $this->attachmentService->uploadValidationMessages()
        );
        $this->ensureInstitutionalAttachmentsSchemaIfUploading($request);
        $template = $this->resolveTemplate($schoolId, $validated['school_exam_template_id'] ?? null);
        $settings = $this->loadExamSettings($schoolId);
        $teacherScoped = $this->isTeacherScoped($user);

        $resolvedTeacherId = $teacherScoped ? $userId : (int) $validated['teacher_user_id'];
        $allowSubjectScheduleOverlap = (bool) ($validated['allow_subject_schedule_overlap'] ?? false);
        if ($allowSubjectScheduleOverlap && !$settings['allow_subject_schedule_slot_overlap']) {
            throw ValidationException::withMessages([
                'exam_date' => 'لا يمكن حفظ الاختبار لأن إعداد المدرسة لا يسمح بالتعارض مع حصة المادة نفسها.',
            ]);
        }

        if ($teacherScoped) {
            $this->assertTeacherSubjectAndClassroomScope(
                $schoolId,
                $user,
                (int) $validated['school_subject_id'],
                (int) $validated['school_classroom_id'],
                (int) $validated['school_term_id'],
                (int) $validated['school_stage_id'],
                'can_create_exam'
            );
        }

        $resolvedMaxScore = $this->resolveExamMaxScore($template, $validated);
        $resolvedPassingScore = $this->resolveExamPassingScore($template, $validated, $resolvedMaxScore);
        $resolvedRequiresApproval = $this->resolveExamRequiresApproval($template, $validated);
        $resolvedAffectsFinalResult = $this->resolveExamAffectsFinalResult($template, $validated);
        $resolvedStatus = $resolvedRequiresApproval ? SchoolExam::STATUS_PENDING_APPROVAL : SchoolExam::STATUS_DRAFT;

        $schedulingPayload = array_merge($validated, [
            'teacher_user_id' => $resolvedTeacherId,
            'max_score' => $resolvedMaxScore,
            'passing_score' => $resolvedPassingScore,
        ]);

        $this->examSchedulingValidationService->validateForScheduling(
            $schoolId,
            $schedulingPayload,
            $allowSubjectScheduleOverlap
        );

        $exam = DB::transaction(function () use (
            $schoolId,
            $userId,
            $validated,
            $resolvedTeacherId,
            $resolvedMaxScore,
            $resolvedPassingScore,
            $resolvedRequiresApproval,
            $allowSubjectScheduleOverlap,
            $resolvedAffectsFinalResult,
            $resolvedStatus,
            $request,
            $user
        ): SchoolExam {
            $exam = SchoolExam::query()->create([
                'school_id' => $schoolId,
                'school_exam_template_id' => $validated['school_exam_template_id'] ?? null,
                'school_term_id' => (int) $validated['school_term_id'],
                'school_stage_id' => (int) $validated['school_stage_id'],
                'school_classroom_id' => (int) $validated['school_classroom_id'],
                'school_subject_id' => (int) $validated['school_subject_id'],
                'teacher_user_id' => $resolvedTeacherId,
                'title' => trim((string) $validated['title']),
                'exam_date' => Carbon::parse((string) $validated['exam_date'])->toDateString(),
                'starts_at' => $this->normalizeTimeInput($validated['starts_at'] ?? null),
                'ends_at' => $this->normalizeTimeInput($validated['ends_at'] ?? null),
                'duration_minutes' => $this->resolvedDurationMinutes($validated['duration_minutes'] ?? null, $validated['starts_at'], $validated['ends_at']),
                'max_score' => $resolvedMaxScore,
                'passing_score' => $resolvedPassingScore,
                'status' => $resolvedStatus,
                'requires_approval' => $resolvedRequiresApproval,
                'allow_subject_schedule_overlap' => $allowSubjectScheduleOverlap,
                'affects_final_result' => $resolvedAffectsFinalResult,
                'room_label' => $this->nullIfEmpty($validated['room_label'] ?? null),
                'notes' => $this->nullIfEmpty($validated['notes'] ?? null),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'created_by' => $userId > 0 ? $userId : null,
                'updated_by' => $userId > 0 ? $userId : null,
            ]);

            SchoolExamStatusLog::query()->create([
                'school_id' => $schoolId,
                'school_exam_id' => (int) $exam->id,
                'old_status' => null,
                'new_status' => $resolvedStatus,
                'reason' => 'إنشاء الاختبار',
                'changed_by' => $userId > 0 ? $userId : null,
                'changed_at' => now(),
            ]);

            $this->attachmentService->storeManyForAttachable(
                $exam,
                $request->file('attachments', []),
                $user,
                [
                    'school_id' => $schoolId,
                    'module' => 'exams',
                    'action_type' => 'exam_attachment',
                    'metadata' => [
                        'exam_id' => (int) $exam->id,
                        'exam_date' => (string) $exam->exam_date,
                        'school_term_id' => (int) $exam->school_term_id,
                        'school_stage_id' => (int) $exam->school_stage_id,
                        'school_classroom_id' => (int) $exam->school_classroom_id,
                        'school_subject_id' => (int) $exam->school_subject_id,
                    ],
                    'request' => $request,
                ]
            );

            return $exam;
        });

        $this->auditLogger->log(
            'school_exams.exam.created',
            'school_exam',
            (int) $exam->id,
            [
                'school_id' => $schoolId,
                'payload' => $exam->only([
                    'school_exam_template_id',
                    'school_term_id',
                    'school_stage_id',
                    'school_classroom_id',
                    'school_subject_id',
                    'teacher_user_id',
                    'title',
                    'exam_date',
                    'starts_at',
                    'ends_at',
                    'max_score',
                    'passing_score',
                    'status',
                    'requires_approval',
                    'allow_subject_schedule_overlap',
                    'affects_final_result',
                    'room_label',
                ]),
            ],
            $request,
            $userId > 0 ? $userId : null
        );

        return back()->with('success', 'تم إنشاء الاختبار والمرفقات بنجاح.');
    }

    public function updateExam(Request $request, SchoolExam $schoolExam): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureExamInSchool($schoolExam, $schoolId);

        $user = $request->user();
        $userId = (int) ($user?->id ?? 0);
        $teacherScoped = $this->isTeacherScoped($user);
        $canApprove = $this->canApproveExams($user);

        $this->assertExamCanBeManagedByUser($schoolExam, $user);
        $this->ensureTeacherHasExamPermission(
            $user,
            $schoolExam,
            'can_update_exam',
            'لا يمكن تنفيذ العملية لأنك لا تملك صلاحية تعديل الاختبارات ضمن الإسناد المعتمد.'
        );

        if ((string) $schoolExam->status === SchoolExam::STATUS_CLOSED && !$canApprove) {
            throw ValidationException::withMessages([
                'exam' => 'لا يمكن تعديل اختبار مغلق بدون صلاحية أعلى.',
            ]);
        }

        $validated = $this->validateExamPayload($request, $schoolId, (int) $schoolExam->id);
        $request->validate(
            $this->attachmentService->uploadValidationRules(),
            $this->attachmentService->uploadValidationMessages()
        );
        $this->ensureInstitutionalAttachmentsSchemaIfUploading($request);
        $template = $this->resolveTemplate($schoolId, $validated['school_exam_template_id'] ?? null);
        $settings = $this->loadExamSettings($schoolId);

        $resolvedTeacherId = $teacherScoped ? $userId : (int) $validated['teacher_user_id'];
        $allowSubjectScheduleOverlap = (bool) ($validated['allow_subject_schedule_overlap'] ?? false);
        if ($allowSubjectScheduleOverlap && !$settings['allow_subject_schedule_slot_overlap']) {
            throw ValidationException::withMessages([
                'exam_date' => 'لا يمكن حفظ الاختبار لأن إعداد المدرسة لا يسمح بالتعارض مع حصة المادة نفسها.',
            ]);
        }

        if ($teacherScoped) {
            $this->assertTeacherSubjectAndClassroomScope(
                $schoolId,
                $user,
                (int) $validated['school_subject_id'],
                (int) $validated['school_classroom_id'],
                (int) $validated['school_term_id'],
                (int) $validated['school_stage_id'],
                'can_update_exam'
            );
        }

        $resolvedMaxScore = $this->resolveExamMaxScore($template, $validated);
        $resolvedPassingScore = $this->resolveExamPassingScore($template, $validated, $resolvedMaxScore);
        $resolvedRequiresApproval = $this->resolveExamRequiresApproval($template, $validated);
        $resolvedAffectsFinalResult = $this->resolveExamAffectsFinalResult($template, $validated);

        $schedulingPayload = array_merge($validated, [
            'teacher_user_id' => $resolvedTeacherId,
            'max_score' => $resolvedMaxScore,
            'passing_score' => $resolvedPassingScore,
        ]);

        $this->examSchedulingValidationService->validateForScheduling(
            $schoolId,
            $schedulingPayload,
            $allowSubjectScheduleOverlap,
            (int) $schoolExam->id
        );

        $before = $schoolExam->only([
            'school_exam_template_id',
            'school_term_id',
            'school_stage_id',
            'school_classroom_id',
            'school_subject_id',
            'teacher_user_id',
            'title',
            'exam_date',
            'starts_at',
            'ends_at',
            'duration_minutes',
            'max_score',
            'passing_score',
            'status',
            'requires_approval',
            'allow_subject_schedule_overlap',
            'affects_final_result',
            'room_label',
            'notes',
            'is_active',
        ]);

        DB::transaction(function () use (
            $schoolExam,
            $validated,
            $resolvedTeacherId,
            $resolvedMaxScore,
            $resolvedPassingScore,
            $resolvedRequiresApproval,
            $allowSubjectScheduleOverlap,
            $resolvedAffectsFinalResult,
            $userId,
            $request,
            $user,
            $schoolId
        ): void {
            $schoolExam->update([
                'school_exam_template_id' => $validated['school_exam_template_id'] ?? null,
                'school_term_id' => (int) $validated['school_term_id'],
                'school_stage_id' => (int) $validated['school_stage_id'],
                'school_classroom_id' => (int) $validated['school_classroom_id'],
                'school_subject_id' => (int) $validated['school_subject_id'],
                'teacher_user_id' => $resolvedTeacherId,
                'title' => trim((string) $validated['title']),
                'exam_date' => Carbon::parse((string) $validated['exam_date'])->toDateString(),
                'starts_at' => $this->normalizeTimeInput($validated['starts_at'] ?? null),
                'ends_at' => $this->normalizeTimeInput($validated['ends_at'] ?? null),
                'duration_minutes' => $this->resolvedDurationMinutes($validated['duration_minutes'] ?? null, $validated['starts_at'], $validated['ends_at']),
                'max_score' => $resolvedMaxScore,
                'passing_score' => $resolvedPassingScore,
                'requires_approval' => $resolvedRequiresApproval,
                'allow_subject_schedule_overlap' => $allowSubjectScheduleOverlap,
                'affects_final_result' => $resolvedAffectsFinalResult,
                'room_label' => $this->nullIfEmpty($validated['room_label'] ?? null),
                'notes' => $this->nullIfEmpty($validated['notes'] ?? null),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'updated_by' => $userId > 0 ? $userId : null,
            ]);

            $this->attachmentService->storeManyForAttachable(
                $schoolExam,
                $request->file('attachments', []),
                $user,
                [
                    'school_id' => $schoolId,
                    'module' => 'exams',
                    'action_type' => 'exam_attachment',
                    'metadata' => [
                        'exam_id' => (int) $schoolExam->id,
                        'exam_date' => (string) $schoolExam->exam_date,
                        'school_term_id' => (int) $schoolExam->school_term_id,
                        'school_stage_id' => (int) $schoolExam->school_stage_id,
                        'school_classroom_id' => (int) $schoolExam->school_classroom_id,
                        'school_subject_id' => (int) $schoolExam->school_subject_id,
                    ],
                    'request' => $request,
                ]
            );
        });

        $this->auditLogger->log(
            'school_exams.exam.updated',
            'school_exam',
            (int) $schoolExam->id,
            [
                'school_id' => $schoolId,
                'before' => $before,
                'after' => $schoolExam->only(array_keys($before)),
            ],
            $request,
            $userId > 0 ? $userId : null
        );

        return back()->with('success', 'تم تعديل الاختبار بنجاح.');
    }

    public function destroyExam(Request $request, SchoolExam $schoolExam): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureExamInSchool($schoolExam, $schoolId);
        $this->assertExamCanBeManagedByUser($schoolExam, $request->user());
        $this->ensureTeacherHasExamPermission(
            $request->user(),
            $schoolExam,
            'can_delete_exam',
            'لا يمكن تنفيذ العملية لأنك لا تملك صلاحية حذف الاختبارات ضمن الإسناد المعتمد.'
        );
        $userId = (int) ($request->user()?->id ?? 0);

        $hasScores = SchoolExamStudentScore::query()
            ->where('school_id', $schoolId)
            ->where('school_exam_id', (int) $schoolExam->id)
            ->exists();

        if ($hasScores) {
            throw ValidationException::withMessages([
                'exam' => 'لا يمكن حذف الاختبار لوجود درجات مسجلة عليه.',
            ]);
        }

        $snapshot = $schoolExam->only([
            'school_exam_template_id',
            'school_term_id',
            'school_stage_id',
            'school_classroom_id',
            'school_subject_id',
            'teacher_user_id',
            'title',
            'exam_date',
            'starts_at',
            'ends_at',
            'max_score',
            'passing_score',
            'status',
        ]);

        DB::transaction(function () use ($schoolExam, $request, $userId): void {
            if ($this->supportsInstitutionalAttachments()) {
                foreach ($schoolExam->attachments()->get() as $attachment) {
                    $this->attachmentService->deleteInstitutionalAttachment(
                        $attachment,
                        $request,
                        $userId > 0 ? $userId : null
                    );
                }
            }

            SchoolExamQuestion::query()
                ->where('school_exam_id', (int) $schoolExam->id)
                ->delete();

            SchoolExamStatusLog::query()
                ->where('school_exam_id', (int) $schoolExam->id)
                ->delete();

            $schoolExam->delete();
        });

        $this->auditLogger->log(
            'school_exams.exam.deleted',
            'school_exam',
            (int) $schoolExam->id,
            [
                'school_id' => $schoolId,
                'before' => $snapshot,
            ],
            $request,
            $userId > 0 ? $userId : null
        );

        return back()->with('success', 'تم حذف الاختبار بنجاح.');
    }

    public function updateExamStatus(Request $request, SchoolExam $schoolExam): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureExamInSchool($schoolExam, $schoolId);

        $user = $request->user();
        $userId = (int) ($user?->id ?? 0);
        $canApprove = $this->canApproveExams($user);
        $this->assertExamCanBeManagedByUser($schoolExam, $user);
        $this->ensureTeacherHasExamPermission(
            $user,
            $schoolExam,
            'can_update_exam',
            'لا يمكن تنفيذ العملية لأنك لا تملك صلاحية تعديل حالة الاختبار ضمن الإسناد المعتمد.'
        );

        $validated = $request->validate([
            'status' => ['required', Rule::in(SchoolExam::allowedStatuses())],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $newStatus = (string) $validated['status'];
        $reason = $this->nullIfEmpty($validated['reason'] ?? null);
        $oldStatus = (string) $schoolExam->status;

        if (in_array($newStatus, [
            SchoolExam::STATUS_APPROVED,
            SchoolExam::STATUS_PUBLISHED,
            SchoolExam::STATUS_CLOSED,
        ], true)) {
            $this->ensureTeacherHasExamPermission(
                $user,
                $schoolExam,
                'can_approve_exam',
                'لا يمكن تنفيذ العملية لأنك لا تملك صلاحية اعتماد الاختبارات ضمن الإسناد المعتمد.'
            );
        }

        if ($newStatus === $oldStatus) {
            return back()->with('success', 'تم تحديث حالة الاختبار بنجاح.');
        }

        if (!$canApprove && in_array($newStatus, [
            SchoolExam::STATUS_APPROVED,
            SchoolExam::STATUS_PUBLISHED,
            SchoolExam::STATUS_CLOSED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'لا تملك صلاحية تغيير الاختبار إلى هذه الحالة.',
            ]);
        }

        if ((string) $schoolExam->status === SchoolExam::STATUS_CLOSED && !$canApprove) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن تعديل حالة اختبار مغلق بدون صلاحية أعلى.',
            ]);
        }

        if (in_array($newStatus, [SchoolExam::STATUS_POSTPONED, SchoolExam::STATUS_CANCELED], true) && $reason === null) {
            throw ValidationException::withMessages([
                'reason' => 'يرجى إدخال سبب التأجيل أو الإلغاء.',
            ]);
        }

        if (in_array($newStatus, [SchoolExam::STATUS_APPROVED, SchoolExam::STATUS_PUBLISHED], true)) {
            $this->assertExamQuestionScoreMatchesMaxScore($schoolId, (int) $schoolExam->id, (float) $schoolExam->max_score);
        }

        if ($newStatus === SchoolExam::STATUS_GRADES_RECORDED) {
            $this->assertExamScoresExist($schoolId, (int) $schoolExam->id);
        }

        $update = [
            'status' => $newStatus,
            'updated_by' => $userId > 0 ? $userId : null,
            'postpone_reason' => $newStatus === SchoolExam::STATUS_POSTPONED ? $reason : $schoolExam->postpone_reason,
            'cancel_reason' => $newStatus === SchoolExam::STATUS_CANCELED ? $reason : $schoolExam->cancel_reason,
        ];

        if ($newStatus === SchoolExam::STATUS_APPROVED) {
            $update['approved_by'] = $userId > 0 ? $userId : null;
            $update['approved_at'] = now();
        }

        if ($newStatus === SchoolExam::STATUS_PUBLISHED) {
            $update['published_at'] = now();
        }

        if ($newStatus === SchoolExam::STATUS_COMPLETED) {
            $update['completed_at'] = now();
        }

        if ($newStatus === SchoolExam::STATUS_CLOSED) {
            $update['closed_at'] = now();
        }

        $schoolExam->update($update);

        SchoolExamStatusLog::query()->create([
            'school_id' => $schoolId,
            'school_exam_id' => (int) $schoolExam->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'changed_by' => $userId > 0 ? $userId : null,
            'changed_at' => now(),
        ]);

        $this->auditLogger->log(
            'school_exams.exam.status_changed',
            'school_exam',
            (int) $schoolExam->id,
            [
                'school_id' => $schoolId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
            ],
            $request,
            $userId > 0 ? $userId : null
        );

        return back()->with('success', $this->statusSuccessMessage($newStatus));
    }

    public function storeQuestion(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $user = $request->user();
        $userId = (int) ($user?->id ?? 0);
        $teacherScoped = $this->isTeacherScoped($user);

        $validated = $this->validateQuestionPayload($request, $schoolId);
        $validated = $this->hydrateQuestionScopeFromCourseOffering($schoolId, $validated);

        if ($teacherScoped && !$this->teacherCanUseSubject($schoolId, $user, (int) $validated['school_subject_id'])) {
            throw ValidationException::withMessages([
                'school_subject_id' => 'لا يمكن استخدام هذه المادة لأنها غير مسندة لهذا المعلم.',
            ]);
        }

        if ($teacherScoped && (int) ($validated['school_course_offering_id'] ?? 0) > 0) {
            $this->ensureTeacherCanUseQuestionBankCourseOffering(
                $schoolId,
                $user,
                (int) $validated['school_course_offering_id']
            );
        }

        $this->ensureTeacherHasQuestionBankPermission(
            $schoolId,
            $user,
            (int) $validated['school_subject_id']
        );

        $question = DB::transaction(function () use ($schoolId, $userId, $validated): SchoolQuestionBankItem {
            $question = SchoolQuestionBankItem::query()->create([
                'school_id' => $schoolId,
                'school_course_offering_id' => $validated['school_course_offering_id'] ?? null,
                'school_subject_id' => (int) $validated['school_subject_id'],
                'school_stage_id' => $validated['school_stage_id'] ?? null,
                'school_term_id' => $validated['school_term_id'] ?? null,
                'unit_name' => $this->nullIfEmpty($validated['unit_name'] ?? null),
                'chapter_name' => $this->nullIfEmpty($validated['chapter_name'] ?? null),
                'lesson_name' => $this->nullIfEmpty($validated['lesson_name'] ?? null),
                'question_text' => trim((string) $validated['question_text']),
                'question_type' => (string) $validated['question_type'],
                'question_score' => (float) $validated['question_score'],
                'selection_mode' => (string) $validated['selection_mode'],
                'difficulty' => (string) $validated['difficulty'],
                'learning_outcome' => $this->nullIfEmpty($validated['learning_outcome'] ?? null),
                'model_answer' => $this->nullIfEmpty($validated['model_answer'] ?? null),
                'answer_explanation' => $this->nullIfEmpty($validated['answer_explanation'] ?? null),
                'status' => (string) $validated['status'],
                'tags' => $this->normalizeTags($validated['tags'] ?? []),
                'attachment_path' => $this->nullIfEmpty($validated['attachment_path'] ?? null),
                'created_by' => $userId > 0 ? $userId : null,
                'updated_by' => $userId > 0 ? $userId : null,
            ]);

            $this->syncQuestionOptions($question, $validated['options'] ?? []);

            return $question;
        });

        $this->auditLogger->log(
            'school_exams.question.created',
            'school_question_bank_item',
            (int) $question->id,
            [
                'school_id' => $schoolId,
                'subject_id' => (int) $question->school_subject_id,
                'question_type' => (string) $question->question_type,
                'selection_mode' => (string) $question->selection_mode,
                'difficulty' => (string) $question->difficulty,
            ],
            $request,
            $userId > 0 ? $userId : null
        );

        return back()->with('success', 'تم إنشاء السؤال في بنك الأسئلة بنجاح.');
    }

    public function updateQuestion(Request $request, SchoolQuestionBankItem $schoolQuestionBankItem): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureQuestionInSchool($schoolQuestionBankItem, $schoolId);
        $user = $request->user();
        $userId = (int) ($user?->id ?? 0);
        $teacherScoped = $this->isTeacherScoped($user);

        if ($teacherScoped && !$this->teacherCanUseSubject($schoolId, $user, (int) $schoolQuestionBankItem->school_subject_id)) {
            abort(403, 'لا تملك صلاحية تعديل هذا السؤال.');
        }

        $this->ensureTeacherHasQuestionBankPermission(
            $schoolId,
            $user,
            (int) $schoolQuestionBankItem->school_subject_id
        );

        $validated = $this->validateQuestionPayload($request, $schoolId, (int) $schoolQuestionBankItem->id);
        $validated = $this->hydrateQuestionScopeFromCourseOffering($schoolId, $validated);

        if ($teacherScoped && !$this->teacherCanUseSubject($schoolId, $user, (int) $validated['school_subject_id'])) {
            throw ValidationException::withMessages([
                'school_subject_id' => 'لا يمكن استخدام هذه المادة لأنها غير مسندة لهذا المعلم.',
            ]);
        }

        if ($teacherScoped && (int) ($validated['school_course_offering_id'] ?? 0) > 0) {
            $this->ensureTeacherCanUseQuestionBankCourseOffering(
                $schoolId,
                $user,
                (int) $validated['school_course_offering_id']
            );
        }

        $this->ensureTeacherHasQuestionBankPermission(
            $schoolId,
            $user,
            (int) $validated['school_subject_id']
        );

        $before = $schoolQuestionBankItem->only([
            'school_course_offering_id',
            'school_subject_id',
            'school_stage_id',
            'school_term_id',
            'unit_name',
            'chapter_name',
            'lesson_name',
            'question_text',
            'question_type',
            'question_score',
            'selection_mode',
            'difficulty',
            'status',
        ]);

        DB::transaction(function () use ($schoolQuestionBankItem, $validated, $userId): void {
            $schoolQuestionBankItem->update([
                'school_course_offering_id' => $validated['school_course_offering_id'] ?? null,
                'school_subject_id' => (int) $validated['school_subject_id'],
                'school_stage_id' => $validated['school_stage_id'] ?? null,
                'school_term_id' => $validated['school_term_id'] ?? null,
                'unit_name' => $this->nullIfEmpty($validated['unit_name'] ?? null),
                'chapter_name' => $this->nullIfEmpty($validated['chapter_name'] ?? null),
                'lesson_name' => $this->nullIfEmpty($validated['lesson_name'] ?? null),
                'question_text' => trim((string) $validated['question_text']),
                'question_type' => (string) $validated['question_type'],
                'question_score' => (float) $validated['question_score'],
                'selection_mode' => (string) $validated['selection_mode'],
                'difficulty' => (string) $validated['difficulty'],
                'learning_outcome' => $this->nullIfEmpty($validated['learning_outcome'] ?? null),
                'model_answer' => $this->nullIfEmpty($validated['model_answer'] ?? null),
                'answer_explanation' => $this->nullIfEmpty($validated['answer_explanation'] ?? null),
                'status' => (string) $validated['status'],
                'tags' => $this->normalizeTags($validated['tags'] ?? []),
                'attachment_path' => $this->nullIfEmpty($validated['attachment_path'] ?? null),
                'updated_by' => $userId > 0 ? $userId : null,
            ]);

            $this->syncQuestionOptions($schoolQuestionBankItem, $validated['options'] ?? []);
        });

        $this->auditLogger->log(
            'school_exams.question.updated',
            'school_question_bank_item',
            (int) $schoolQuestionBankItem->id,
            [
                'school_id' => $schoolId,
                'before' => $before,
                'after' => $schoolQuestionBankItem->only(array_keys($before)),
            ],
            $request,
            $userId > 0 ? $userId : null
        );

        return back()->with('success', 'تم تعديل السؤال بنجاح.');
    }

    public function destroyQuestion(Request $request, SchoolQuestionBankItem $schoolQuestionBankItem): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureQuestionInSchool($schoolQuestionBankItem, $schoolId);
        $user = $request->user();
        $userId = (int) ($user?->id ?? 0);
        $teacherScoped = $this->isTeacherScoped($user);

        if ($teacherScoped && !$this->teacherCanUseSubject($schoolId, $user, (int) $schoolQuestionBankItem->school_subject_id)) {
            abort(403, 'لا تملك صلاحية حذف هذا السؤال.');
        }

        $this->ensureTeacherHasQuestionBankPermission(
            $schoolId,
            $user,
            (int) $schoolQuestionBankItem->school_subject_id
        );

        $usedInExams = SchoolExamQuestion::query()
            ->where('school_id', $schoolId)
            ->where('school_question_bank_item_id', (int) $schoolQuestionBankItem->id)
            ->exists();

        if ($usedInExams) {
            throw ValidationException::withMessages([
                'question' => 'لا يمكن حذف السؤال لأنه مستخدم في اختبار فعلي.',
            ]);
        }

        $snapshot = $schoolQuestionBankItem->only([
            'school_subject_id',
            'question_type',
            'selection_mode',
            'difficulty',
            'status',
        ]);

        DB::transaction(function () use ($schoolQuestionBankItem): void {
            SchoolQuestionOption::query()
                ->where('school_question_bank_item_id', (int) $schoolQuestionBankItem->id)
                ->delete();

            $schoolQuestionBankItem->delete();
        });

        $this->auditLogger->log(
            'school_exams.question.deleted',
            'school_question_bank_item',
            (int) $schoolQuestionBankItem->id,
            [
                'school_id' => $schoolId,
                'before' => $snapshot,
            ],
            $request,
            $userId > 0 ? $userId : null
        );

        return back()->with('success', 'تم حذف السؤال بنجاح.');
    }

    public function syncExamQuestions(Request $request, SchoolExam $schoolExam): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureExamInSchool($schoolExam, $schoolId);
        $user = $request->user();
        $this->assertExamCanBeManagedByUser($schoolExam, $user);
        $this->ensureTeacherHasExamPermission(
            $user,
            $schoolExam,
            'can_update_exam',
            'لا يمكن تنفيذ العملية لأنك لا تملك صلاحية تعديل أسئلة الاختبار ضمن الإسناد المعتمد.'
        );
        $this->ensureTeacherHasExamPermission(
            $user,
            $schoolExam,
            'can_use_question_bank',
            'لا يمكن تنفيذ العملية لأنك لا تملك صلاحية استخدام بنك الأسئلة ضمن الإسناد المعتمد.'
        );
        $userId = (int) ($user?->id ?? 0);

        if ((string) $schoolExam->status === SchoolExam::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'exam' => 'لا يمكن تعديل أسئلة اختبار مغلق.',
            ]);
        }

        $validated = $request->validate([
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.school_question_bank_item_id' => ['required', 'integer'],
            'questions.*.score' => ['required', 'numeric', 'gt:0'],
            'questions.*.is_required' => ['nullable', 'boolean'],
            'questions.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'questions.required' => 'يرجى اختيار أسئلة الاختبار.',
            'questions.min' => 'يرجى اختيار سؤال واحد على الأقل.',
        ]);

        $questionRows = collect($validated['questions'])
            ->map(function (array $row): array {
                return [
                    'school_question_bank_item_id' => (int) $row['school_question_bank_item_id'],
                    'score' => (float) $row['score'],
                    'is_required' => (bool) ($row['is_required'] ?? true),
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                ];
            })
            ->values();

        $questionIds = $questionRows->pluck('school_question_bank_item_id')->unique()->values();
        $availableQuestions = SchoolQuestionBankItem::query()
            ->where('school_id', $schoolId)
            ->whereIn('id', $questionIds->all())
            ->get([
                'id',
                'school_id',
                'school_subject_id',
                'status',
            ])
            ->keyBy('id');

        foreach ($questionRows as $row) {
            $question = $availableQuestions->get($row['school_question_bank_item_id']);
            if (!$question) {
                throw ValidationException::withMessages([
                    'questions' => 'لا يمكن استخدام هذا السؤال لأنه لا ينتمي إلى نفس المادة أو المدرسة.',
                ]);
            }

            if ((int) $question->school_subject_id !== (int) $schoolExam->school_subject_id) {
                throw ValidationException::withMessages([
                    'questions' => 'لا يمكن استخدام هذا السؤال لأنه لا ينتمي إلى نفس المادة أو المدرسة.',
                ]);
            }

            if ((string) $question->status === SchoolQuestionBankItem::STATUS_ARCHIVED) {
                throw ValidationException::withMessages([
                    'questions' => 'لا يمكن إضافة سؤال مؤرشف إلى الاختبار.',
                ]);
            }
        }

        DB::transaction(function () use ($questionRows, $schoolExam, $schoolId, $userId): void {
            SchoolExamQuestion::query()
                ->where('school_id', $schoolId)
                ->where('school_exam_id', (int) $schoolExam->id)
                ->delete();

            $insertRows = $questionRows->map(function (array $row) use ($schoolExam, $schoolId, $userId): array {
                return [
                    'school_id' => $schoolId,
                    'school_exam_id' => (int) $schoolExam->id,
                    'school_question_bank_item_id' => $row['school_question_bank_item_id'],
                    'sort_order' => $row['sort_order'],
                    'score' => $row['score'],
                    'is_required' => $row['is_required'],
                    'created_by' => $userId > 0 ? $userId : null,
                    'updated_by' => $userId > 0 ? $userId : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->all();

            SchoolExamQuestion::query()->insert($insertRows);

            $schoolExam->update([
                'updated_by' => $userId > 0 ? $userId : null,
            ]);
        });

        $this->auditLogger->log(
            'school_exams.exam_questions.synced',
            'school_exam',
            (int) $schoolExam->id,
            [
                'school_id' => $schoolId,
                'questions_count' => $questionRows->count(),
                'total_score' => (float) $questionRows->sum('score'),
            ],
            $request,
            $userId > 0 ? $userId : null
        );

        return back()->with('success', 'تم حفظ أسئلة الاختبار بنجاح.');
    }

    public function upsertScores(Request $request, SchoolExam $schoolExam): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureExamInSchool($schoolExam, $schoolId);
        $user = $request->user();
        $this->assertExamCanBeManagedByUser($schoolExam, $user);
        $hasExistingScores = SchoolExamStudentScore::query()
            ->where('school_id', $schoolId)
            ->where('school_exam_id', (int) $schoolExam->id)
            ->exists();
        $this->ensureTeacherHasExamPermission(
            $user,
            $schoolExam,
            $hasExistingScores ? 'can_edit_exam_scores' : 'can_enter_exam_scores',
            $hasExistingScores
                ? 'لا يمكن تنفيذ العملية لأنك لا تملك صلاحية تعديل درجات الطلاب ضمن الإسناد المعتمد.'
                : 'لا يمكن تنفيذ العملية لأنك لا تملك صلاحية إدخال درجات الطلاب ضمن الإسناد المعتمد.'
        );
        $userId = (int) ($user?->id ?? 0);

        if (!in_array((string) $schoolExam->status, SchoolExam::statusesAllowScoreRecording(), true)) {
            throw ValidationException::withMessages([
                'exam' => 'لا يمكن إدخال الدرجات قبل اعتماد الاختبار أو نشره.',
            ]);
        }

        $validated = $request->validate([
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.school_student_id' => ['required', 'integer'],
            'scores.*.score' => ['nullable', 'numeric', 'gte:0'],
            'scores.*.attendance_status' => ['required', Rule::in(SchoolExamStudentScore::allowedAttendanceStatuses())],
            'scores.*.notes' => ['nullable', 'string', 'max:2000'],
            'scores.*.is_finalized' => ['nullable', 'boolean'],
        ], [
            'scores.required' => 'يرجى إدخال درجات الطلاب.',
            'scores.min' => 'يرجى إدخال طالب واحد على الأقل.',
        ]);

        $rows = collect($validated['scores'])
            ->map(function (array $row) use ($schoolExam): array {
                $score = $row['score'] ?? null;
                if ($score !== null && (float) $score > (float) $schoolExam->max_score) {
                    throw ValidationException::withMessages([
                        'scores' => 'لا يمكن حفظ الدرجة لأن قيمة الدرجة أكبر من الدرجة النهائية للاختبار.',
                    ]);
                }

                return [
                    'school_student_id' => (int) $row['school_student_id'],
                    'score' => $score !== null ? (float) $score : null,
                    'attendance_status' => (string) $row['attendance_status'],
                    'notes' => $this->nullIfEmpty($row['notes'] ?? null),
                    'is_finalized' => (bool) ($row['is_finalized'] ?? false),
                ];
            })
            ->values();

        $studentIds = $rows->pluck('school_student_id')->unique()->values();
        $validStudentIds = SchoolStudent::query()
            ->where('school_id', $schoolId)
            ->where('school_classroom_id', (int) $schoolExam->school_classroom_id)
            ->whereIn('id', $studentIds->all())
            ->pluck('id')
            ->map(fn ($value) => (int) $value)
            ->all();

        if (count($validStudentIds) !== $studentIds->count()) {
            throw ValidationException::withMessages([
                'scores' => 'لا يمكن الحفظ لأن بعض الطلاب لا ينتمون إلى نفس المدرسة أو الصف المستهدف.',
            ]);
        }

        $allFinalized = true;
        DB::transaction(function () use ($rows, $schoolExam, $schoolId, $userId, &$allFinalized): void {
            foreach ($rows as $row) {
                $score = SchoolExamStudentScore::query()->firstOrNew([
                    'school_id' => $schoolId,
                    'school_exam_id' => (int) $schoolExam->id,
                    'school_student_id' => $row['school_student_id'],
                ]);

                if (!$score->exists) {
                    $score->recorded_by = $userId > 0 ? $userId : null;
                    $score->recorded_at = now();
                }

                $isFinalized = (bool) $row['is_finalized'];
                $allFinalized = $allFinalized && $isFinalized;

                $score->fill([
                    'score' => $row['score'],
                    'attendance_status' => $row['attendance_status'],
                    'notes' => $row['notes'],
                    'updated_by' => $userId > 0 ? $userId : null,
                    'is_finalized' => $isFinalized,
                    'finalized_by' => $isFinalized ? ($userId > 0 ? $userId : null) : null,
                    'finalized_at' => $isFinalized ? now() : null,
                ]);

                $score->save();
            }

            if ($allFinalized && (string) $schoolExam->status !== SchoolExam::STATUS_GRADES_RECORDED) {
                $oldStatus = (string) $schoolExam->status;
                $schoolExam->update([
                    'status' => SchoolExam::STATUS_GRADES_RECORDED,
                    'updated_by' => $userId > 0 ? $userId : null,
                ]);

                SchoolExamStatusLog::query()->create([
                    'school_id' => $schoolId,
                    'school_exam_id' => (int) $schoolExam->id,
                    'old_status' => $oldStatus,
                    'new_status' => SchoolExam::STATUS_GRADES_RECORDED,
                    'reason' => 'تسجيل الدرجات النهائية',
                    'changed_by' => $userId > 0 ? $userId : null,
                    'changed_at' => now(),
                ]);
            }
        });

        $this->auditLogger->log(
            'school_exams.scores.upserted',
            'school_exam',
            (int) $schoolExam->id,
            [
                'school_id' => $schoolId,
                'rows_count' => $rows->count(),
                'finalized_rows_count' => $rows->filter(fn (array $row) => $row['is_finalized'])->count(),
            ],
            $request,
            $userId > 0 ? $userId : null
        );

        return back()->with('success', 'تم تسجيل الدرجات بنجاح.');
    }

    private function supportsInstitutionalAttachments(): bool
    {
        return Schema::hasTable('attachments')
            && Schema::hasColumn('attachments', 'school_id')
            && Schema::hasColumn('attachments', 'attachable_type')
            && Schema::hasColumn('attachments', 'attachable_id')
            && Schema::hasColumn('attachments', 'module')
            && Schema::hasColumn('attachments', 'deleted_at');
    }

    private function ensureInstitutionalAttachmentsSchemaIfUploading(Request $request): void
    {
        if (!$this->requestHasAttachmentUploads($request) || $this->supportsInstitutionalAttachments()) {
            return;
        }

        throw ValidationException::withMessages([
            'attachments' => 'لا يمكن رفع مرفقات الاختبارات قبل تحديث بنية جدول المرفقات. يرجى تشغيل ترحيلات قاعدة البيانات ثم إعادة المحاولة.',
        ]);
    }

    private function requestHasAttachmentUploads(Request $request): bool
    {
        $files = $request->file('attachments', []);
        $normalizedFiles = is_array($files) ? $files : [$files];

        return collect($normalizedFiles)
            ->flatten()
            ->contains(fn ($file) => $file instanceof UploadedFile);
    }

    private function validateExamPayload(Request $request, int $schoolId, ?int $ignoreExamId = null): array
    {
        $validated = $request->validate([
            'school_exam_template_id' => [
                'nullable',
                Rule::exists('school_exam_templates', 'id')
                    ->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_term_id' => [
                'required',
                Rule::exists('school_terms', 'id')
                    ->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_stage_id' => [
                'required',
                Rule::exists('school_stages', 'id')
                    ->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_classroom_id' => [
                'required',
                Rule::exists('school_classrooms', 'id')
                    ->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_subject_id' => [
                'required',
                Rule::exists('school_subjects', 'id')
                    ->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'teacher_user_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'title' => ['required', 'string', 'max:191'],
            'exam_date' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'max_score' => ['nullable', 'numeric', 'gt:0'],
            'passing_score' => ['nullable', 'numeric', 'gte:0'],
            'requires_approval' => ['nullable', 'boolean'],
            'allow_subject_schedule_overlap' => ['nullable', 'boolean'],
            'affects_final_result' => ['nullable', 'boolean'],
            'room_label' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'school_exam_template_id.exists' => 'لا يمكن حفظ الاختبار لأن مسمى الاختبار لا ينتمي إلى نفس المدرسة.',
            'school_term_id.exists' => 'لا يمكن حفظ الاختبار لأن الترم لا ينتمي إلى نفس المدرسة.',
            'school_stage_id.exists' => 'لا يمكن حفظ الاختبار لأن المرحلة لا تنتمي إلى نفس المدرسة.',
            'school_classroom_id.exists' => 'لا يمكن حفظ الاختبار لأن الصف أو الشعبة لا تنتمي إلى نفس المدرسة.',
            'school_subject_id.exists' => 'لا يمكن حفظ الاختبار لأن المادة لا تنتمي إلى نفس المدرسة.',
            'teacher_user_id.exists' => 'لا يمكن حفظ الاختبار لأن المعلم لا ينتمي إلى نفس المدرسة.',
        ]);

        if (
            isset($validated['passing_score'], $validated['max_score'])
            && (float) $validated['passing_score'] > (float) $validated['max_score']
        ) {
            throw ValidationException::withMessages([
                'passing_score' => 'لا يمكن الحفظ لأن درجة النجاح أكبر من الدرجة النهائية.',
            ]);
        }

        if ($ignoreExamId !== null) {
            $exam = SchoolExam::query()
                ->where('school_id', $schoolId)
                ->whereKey($ignoreExamId)
                ->first();

            if ($exam !== null && (string) $exam->status === SchoolExam::STATUS_CLOSED) {
                throw ValidationException::withMessages([
                    'exam' => 'لا يمكن تعديل اختبار مغلق.',
                ]);
            }
        }

        return $validated;
    }

    private function validateQuestionPayload(Request $request, int $schoolId, ?int $questionId = null): array
    {
        $validated = $request->validate([
            'school_course_offering_id' => [
                'required',
                Rule::exists('school_course_offerings', 'id')->where(
                    fn ($query) => $query->where('school_id', $schoolId)
                ),
            ],
            'school_subject_id' => [
                'nullable',
                Rule::exists('school_subjects', 'id')->where(
                    fn ($query) => $query->where('school_id', $schoolId)
                ),
            ],
            'school_stage_id' => [
                'nullable',
                Rule::exists('school_stages', 'id')->where(
                    fn ($query) => $query->where('school_id', $schoolId)
                ),
            ],
            'school_term_id' => [
                'nullable',
                Rule::exists('school_terms', 'id')->where(
                    fn ($query) => $query->where('school_id', $schoolId)
                ),
            ],
            'branch_name' => ['required_with:school_course_offering_id', 'nullable', 'string', 'max:150'],
            'unit_name' => ['required_with:school_course_offering_id', 'nullable', 'string', 'max:150'],
            'chapter_name' => ['required_with:school_course_offering_id', 'nullable', 'string', 'max:150'],
            'lesson_name' => ['required_with:school_course_offering_id', 'nullable', 'string', 'max:150'],
            'question_text' => ['required', 'string', 'max:20000'],
            'question_type' => ['required', Rule::in(SchoolQuestionBankItem::allowedTypes())],
            'question_score' => ['required', 'numeric', 'gt:0'],
            'selection_mode' => ['required', Rule::in(SchoolQuestionBankItem::allowedSelectionModes())],
            'difficulty' => ['required', Rule::in(SchoolQuestionBankItem::allowedDifficulties())],
            'learning_outcome' => ['nullable', 'string', 'max:255'],
            'model_answer' => ['nullable', 'string', 'max:20000'],
            'answer_explanation' => ['nullable', 'string', 'max:20000'],
            'status' => ['required', Rule::in(SchoolQuestionBankItem::allowedStatuses())],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'string', 'max:100'],
            'attachment_path' => ['nullable', 'string', 'max:500'],
            'options' => ['nullable', 'array'],
            'options.*.option_text' => ['required_with:options', 'string', 'max:5000'],
            'options.*.is_correct' => ['nullable', 'boolean'],
            'options.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'school_course_offering_id.exists' => 'لا يمكن حفظ السؤال لأن المقرر لا ينتمي إلى نفس المدرسة.',
            'school_course_offering_id.required' => 'لا يمكن حفظ السؤال بدون اختيار المقرر.',
            'school_subject_id.exists' => 'لا يمكن حفظ السؤال لأن المادة لا تنتمي إلى نفس المدرسة.',
            'school_stage_id.exists' => 'لا يمكن حفظ السؤال لأن المرحلة لا تنتمي إلى نفس المدرسة.',
            'school_term_id.exists' => 'لا يمكن حفظ السؤال لأن الترم لا ينتمي إلى نفس المدرسة.',
            'branch_name.required_with' => 'يرجى اختيار الفرع للمقرر المحدد قبل حفظ السؤال.',
            'unit_name.required_with' => 'يرجى اختيار الوحدة للمقرر المحدد قبل حفظ السؤال.',
            'chapter_name.required_with' => 'يرجى اختيار الموضوع للمقرر المحدد قبل حفظ السؤال.',
            'lesson_name.required_with' => 'يرجى اختيار الدرس للمقرر المحدد قبل حفظ السؤال.',
        ]);

        $questionType = (string) ($validated['question_type'] ?? '');
        $options = collect($validated['options'] ?? []);

        if ($questionType === SchoolQuestionBankItem::TYPE_MULTIPLE_CHOICE) {
            if ($options->count() < 2) {
                throw ValidationException::withMessages([
                    'options' => 'يرجى إدخال خيارين على الأقل لسؤال الاختيار من متعدد.',
                ]);
            }

            $correctCount = $options->filter(fn (array $option) => (bool) ($option['is_correct'] ?? false))->count();
            if ($correctCount === 0) {
                throw ValidationException::withMessages([
                    'options' => 'يرجى تحديد إجابة صحيحة واحدة على الأقل.',
                ]);
            }
        }

        if ($questionType === SchoolQuestionBankItem::TYPE_TRUE_FALSE && $options->isEmpty()) {
            $validated['options'] = [
                ['option_text' => 'صح', 'is_correct' => true, 'sort_order' => 1],
                ['option_text' => 'خطأ', 'is_correct' => false, 'sort_order' => 2],
            ];
        }

        if ($questionType !== SchoolQuestionBankItem::TYPE_MULTIPLE_CHOICE && $questionType !== SchoolQuestionBankItem::TYPE_TRUE_FALSE) {
            $validated['options'] = [];
        }

        if ($questionId !== null) {
            $question = SchoolQuestionBankItem::query()
                ->where('school_id', $schoolId)
                ->whereKey($questionId)
                ->first();
            if (!$question) {
                abort(403, 'لا تملك صلاحية الوصول إلى هذا السؤال.');
            }
        }

        return $validated;
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function hydrateQuestionScopeFromCourseOffering(int $schoolId, array $validated): array
    {
        $courseOfferingId = (int) ($validated['school_course_offering_id'] ?? 0);
        if ($courseOfferingId <= 0) {
            $validated['school_course_offering_id'] = null;
            return $validated;
        }

        $courseOffering = SchoolCourseOffering::query()
            ->where('school_id', $schoolId)
            ->whereKey($courseOfferingId)
            ->where('is_active', true)
            ->with([
                'studyPlanUnits' => fn ($query) => $query
                    ->select([
                        'id',
                        'school_id',
                        'school_course_offering_id',
                        'branch_name',
                        'name',
                        'sort_order',
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->with([
                        'lessons' => fn ($lessons) => $lessons
                            ->select([
                                'id',
                                'school_id',
                                'school_course_plan_unit_id',
                                'name',
                                'sort_order',
                            ])
                            ->orderBy('sort_order')
                            ->orderBy('id')
                            ->with([
                                'topics' => fn ($topics) => $topics
                                    ->select([
                                        'id',
                                        'school_id',
                                        'school_course_plan_lesson_id',
                                        'name',
                                        'sort_order',
                                    ])
                                    ->orderBy('sort_order')
                                    ->orderBy('id'),
                            ]),
                    ]),
            ])
            ->first();

        if (!$courseOffering) {
            throw ValidationException::withMessages([
                'school_course_offering_id' => 'لا يمكن حفظ السؤال لأن المقرر غير صالح أو غير نشط داخل نفس المدرسة.',
            ]);
        }

        $hasUsageFlag = array_key_exists('usable_in_exams', $courseOffering->getAttributes());
        if ($hasUsageFlag && !(bool) $courseOffering->usable_in_exams) {
            throw ValidationException::withMessages([
                'school_course_offering_id' => 'لا يمكن استخدام هذا المقرر في بنك الأسئلة لأنه غير مفعّل للاختبارات.',
            ]);
        }

        $subjectId = (int) ($validated['school_subject_id'] ?? 0);
        if ($subjectId > 0 && $subjectId !== (int) $courseOffering->school_subject_id) {
            throw ValidationException::withMessages([
                'school_subject_id' => 'لا يمكن حفظ السؤال لأن المادة لا تتطابق مع المقرر المختار.',
            ]);
        }

        $stageId = (int) ($validated['school_stage_id'] ?? 0);
        if ($stageId > 0 && $stageId !== (int) $courseOffering->school_stage_id) {
            throw ValidationException::withMessages([
                'school_stage_id' => 'لا يمكن حفظ السؤال لأن المرحلة لا تتطابق مع المقرر المختار.',
            ]);
        }

        $termId = (int) ($validated['school_term_id'] ?? 0);
        if ($termId > 0 && $termId !== (int) $courseOffering->school_term_id) {
            throw ValidationException::withMessages([
                'school_term_id' => 'لا يمكن حفظ السؤال لأن الترم لا يتطابق مع المقرر المختار.',
            ]);
        }

        $validated['school_course_offering_id'] = (int) $courseOffering->id;
        $validated['school_subject_id'] = (int) $courseOffering->school_subject_id;
        $validated['school_stage_id'] = (int) $courseOffering->school_stage_id;
        $validated['school_term_id'] = (int) $courseOffering->school_term_id;
        $validated = $this->ensureQuestionTaxonomyMatchesCourseOffering($courseOffering, $validated);

        return $validated;
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function ensureQuestionTaxonomyMatchesCourseOffering(SchoolCourseOffering $courseOffering, array $validated): array
    {
        $branchName = trim((string) ($validated['branch_name'] ?? ''));
        $unitName = trim((string) ($validated['unit_name'] ?? ''));
        $lessonName = trim((string) ($validated['lesson_name'] ?? ''));
        $topicName = trim((string) ($validated['chapter_name'] ?? ''));

        $units = $courseOffering->studyPlanUnits ?? collect();
        if ($units->isEmpty()) {
            throw ValidationException::withMessages([
                'unit_name' => 'لا يمكن حفظ السؤال لأن الخطة الدراسية للمقرر غير مضافة بعد.',
            ]);
        }

        $normalizedBranchName = $this->normalizeComparableText($branchName);
        $unitsInBranch = $units
            ->filter(function ($item) use ($normalizedBranchName): bool {
                $unitBranch = $this->normalizeStudyPlanBranchName((string) ($item->branch_name ?? ''));

                return $this->normalizeComparableText($unitBranch) === $normalizedBranchName;
            })
            ->values();
        if ($unitsInBranch->isEmpty()) {
            throw ValidationException::withMessages([
                'branch_name' => 'لا يمكن حفظ السؤال لأن الفرع المحدد لا يتبع الخطة الدراسية لهذا المقرر.',
            ]);
        }

        $normalizedUnitName = $this->normalizeComparableText($unitName);
        $unit = $unitsInBranch->first(
            fn ($item) => $this->normalizeComparableText((string) ($item->name ?? '')) === $normalizedUnitName
        );
        if ($unit === null) {
            throw ValidationException::withMessages([
                'unit_name' => 'لا يمكن حفظ السؤال لأن الوحدة المحددة لا تتبع الخطة الدراسية لهذا المقرر.',
            ]);
        }

        $lessons = collect($unit->lessons ?? []);
        if ($lessons->isEmpty()) {
            throw ValidationException::withMessages([
                'lesson_name' => 'لا يمكن حفظ السؤال لأن الوحدة المحددة لا تحتوي على دروس في خطة المقرر.',
            ]);
        }

        $normalizedLessonName = $this->normalizeComparableText($lessonName);
        $lesson = $lessons->first(
            fn ($item) => $this->normalizeComparableText((string) ($item->name ?? '')) === $normalizedLessonName
        );
        if ($lesson === null) {
            throw ValidationException::withMessages([
                'lesson_name' => 'لا يمكن حفظ السؤال لأن الدرس المحدد لا يتبع الوحدة المختارة في خطة المقرر.',
            ]);
        }

        $topics = collect($lesson->topics ?? []);
        if ($topics->isEmpty()) {
            throw ValidationException::withMessages([
                'chapter_name' => 'لا يمكن حفظ السؤال لأن الدرس المحدد لا يحتوي على موضوعات في خطة المقرر.',
            ]);
        }

        $normalizedTopicName = $this->normalizeComparableText($topicName);
        $topic = $topics->first(
            fn ($item) => $this->normalizeComparableText((string) ($item->name ?? '')) === $normalizedTopicName
        );
        if ($topic === null) {
            throw ValidationException::withMessages([
                'chapter_name' => 'لا يمكن حفظ السؤال لأن الموضوع المحدد لا يتبع الدرس المختار في خطة المقرر.',
            ]);
        }

        $validated['branch_name'] = $this->normalizeStudyPlanBranchName((string) ($unit->branch_name ?? $branchName));
        $validated['unit_name'] = trim((string) ($unit->name ?? $unitName));
        $validated['lesson_name'] = trim((string) ($lesson->name ?? $lessonName));
        $validated['chapter_name'] = trim((string) ($topic->name ?? $topicName));

        return $validated;
    }

    private function normalizeComparableText(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function normalizeStudyPlanBranchName(?string $value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return self::DEFAULT_STUDY_PLAN_BRANCH_NAME;
        }

        return $normalized;
    }

    private function ensureTeacherCanUseQuestionBankCourseOffering(int $schoolId, User $user, int $courseOfferingId): void
    {
        $allowed = SchoolTeachingAssignment::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', (int) $user->id)
            ->where('is_active', true)
            ->where('can_use_question_bank', true)
            ->whereHas('courseOffering', fn ($query) => $query
                ->where('school_id', $schoolId)
                ->whereKey($courseOfferingId)
                ->where('is_active', true))
            ->exists();

        if (!$allowed) {
            throw ValidationException::withMessages([
                'school_course_offering_id' => 'لا يمكن استخدام هذا المقرر في بنك الأسئلة لأنه غير مسند للمعلم أو صلاحية بنك الأسئلة غير مفعّلة.',
            ]);
        }
    }

    private function assertTeacherSubjectAndClassroomScope(
        int $schoolId,
        User $user,
        int $subjectId,
        int $classroomId,
        int $termId,
        int $stageId,
        string $requiredPermission
    ): void
    {
        if (!$this->teacherCanUseSubject($schoolId, $user, $subjectId)) {
            throw ValidationException::withMessages([
                'school_subject_id' => 'لا يمكن إنشاء الاختبار لأن هذه المادة غير مسندة إلى المعلم.',
            ]);
        }

        if (!$this->teacherCanUseClassroom($schoolId, $user, $classroomId, $subjectId, $termId)) {
            throw ValidationException::withMessages([
                'school_classroom_id' => 'لا يمكن إنشاء الاختبار لأن الفصل المحدد غير مسند إلى المعلم.',
            ]);
        }

        if (!$this->classroomBelongsToStage($schoolId, $classroomId, $stageId)) {
            throw ValidationException::withMessages([
                'school_stage_id' => 'لا يمكن إنشاء الاختبار لأن المرحلة التعليمية المحددة غير مرتبطة بالفصل المختار.',
            ]);
        }

        if (!$this->teacherHasScopePermission($schoolId, $user, $classroomId, $subjectId, $termId, $requiredPermission)) {
            throw ValidationException::withMessages([
                'exam' => 'لا يمكن تنفيذ العملية لأنك لا تملك الصلاحية المطلوبة ضمن الإسناد المعتمد.',
            ]);
        }
    }

    private function teacherCanUseSubject(int $schoolId, User $user, int $subjectId): bool
    {
        return SchoolSubjectTeacherAssignment::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', (int) $user->id)
            ->where('school_subject_id', $subjectId)
            ->exists();
    }

    private function teacherCanUseClassroom(
        int $schoolId,
        User $user,
        int $classroomId,
        int $subjectId,
        int $termId
    ): bool
    {
        $hasTeachingAssignments = SchoolTeachingAssignment::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', (int) $user->id)
            ->where('is_active', true)
            ->exists();

        if (!$hasTeachingAssignments) {
            return false;
        }

        $classroom = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->whereKey($classroomId)
            ->first(['id', 'school_stage_id', 'grade_name']);
        if (!$classroom) {
            return false;
        }

        $stageGradeId = $this->resolveStageGradeIdForClassroom(
            schoolId: $schoolId,
            stageId: (int) $classroom->school_stage_id,
            classroomId: $classroomId
        );

        $assignments = SchoolTeachingAssignment::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', (int) $user->id)
            ->where('is_active', true)
            ->whereHas('courseOffering', function ($query) use ($schoolId, $classroomId, $stageGradeId, $subjectId, $termId): void {
                $query
                    ->where('school_id', $schoolId)
                    ->where('school_term_id', $termId)
                    ->where('school_subject_id', $subjectId)
                    ->where('is_active', true)
                    ->where(function ($scopeQuery) use ($classroomId, $stageGradeId): void {
                        $scopeQuery->where('school_classroom_id', $classroomId);
                        if ($stageGradeId > 0) {
                            $scopeQuery->orWhere('school_stage_grade_id', $stageGradeId);
                        }
                    });
            })
            ->with([
                'courseOffering:id,school_stage_grade_id,school_classroom_id',
                'classrooms:id',
            ])
            ->get();

        return $assignments->contains(fn (SchoolTeachingAssignment $assignment) => $this->assignmentAllowsClassroom(
            $assignment,
            $classroomId,
            $stageGradeId
        ));
    }

    private function classroomBelongsToStage(int $schoolId, int $classroomId, int $stageId): bool
    {
        return SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->whereKey($classroomId)
            ->where('school_stage_id', $stageId)
            ->exists();
    }

    private function resolveTeacherAssignmentForScope(
        int $schoolId,
        User $user,
        int $classroomId,
        int $subjectId,
        int $termId
    ): ?SchoolTeachingAssignment {
        $classroom = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->whereKey($classroomId)
            ->first(['id', 'school_stage_id']);
        if (!$classroom) {
            return null;
        }

        $stageGradeId = $this->resolveStageGradeIdForClassroom(
            schoolId: $schoolId,
            stageId: (int) $classroom->school_stage_id,
            classroomId: $classroomId
        );

        $assignments = SchoolTeachingAssignment::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', (int) $user->id)
            ->where('is_active', true)
            ->whereHas('courseOffering', function ($query) use ($schoolId, $classroomId, $stageGradeId, $subjectId, $termId): void {
                $query
                    ->where('school_id', $schoolId)
                    ->where('school_term_id', $termId)
                    ->where('school_subject_id', $subjectId)
                    ->where('is_active', true)
                    ->where(function ($scopeQuery) use ($classroomId, $stageGradeId): void {
                        $scopeQuery->where('school_classroom_id', $classroomId);
                        if ($stageGradeId > 0) {
                            $scopeQuery->orWhere('school_stage_grade_id', $stageGradeId);
                        }
                    });
            })
            ->with([
                'courseOffering:id,school_stage_grade_id,school_classroom_id',
                'classrooms:id',
            ])
            ->get();

        return $assignments->first(fn (SchoolTeachingAssignment $assignment) => $this->assignmentAllowsClassroom(
            $assignment,
            $classroomId,
            $stageGradeId
        ));
    }

    private function assignmentAllowsClassroom(
        SchoolTeachingAssignment $assignment,
        int $classroomId,
        int $stageGradeId = 0
    ): bool {
        if ($assignment->classrooms->isNotEmpty()) {
            return $assignment->classrooms->contains(fn ($classroom) => (int) $classroom->id === $classroomId);
        }

        $legacyClassroomId = (int) ($assignment->courseOffering?->school_classroom_id ?? 0);
        if ($legacyClassroomId > 0) {
            return $legacyClassroomId === $classroomId;
        }

        $offeringStageGradeId = (int) ($assignment->courseOffering?->school_stage_grade_id ?? 0);
        return $stageGradeId > 0 && $offeringStageGradeId > 0 && $offeringStageGradeId === $stageGradeId;
    }

    private function resolveStageGradeIdForClassroom(int $schoolId, int $stageId, int $classroomId): int
    {
        $classroom = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->where('school_stage_id', $stageId)
            ->whereKey($classroomId)
            ->first(['grade_name']);

        if (!$classroom) {
            return 0;
        }

        $normalizedGradeName = mb_strtolower(trim((string) $classroom->grade_name));
        if ($normalizedGradeName === '') {
            return 0;
        }

        $stageGradeId = SchoolStageGrade::query()
            ->where('school_id', $schoolId)
            ->where('school_stage_id', $stageId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedGradeName])
            ->value('id');

        return $stageGradeId ? (int) $stageGradeId : 0;
    }

    private function teacherHasScopePermission(
        int $schoolId,
        User $user,
        int $classroomId,
        int $subjectId,
        int $termId,
        string $permissionField
    ): bool {
        $assignment = $this->resolveTeacherAssignmentForScope(
            $schoolId,
            $user,
            $classroomId,
            $subjectId,
            $termId
        );

        if ($assignment !== null) {
            return (bool) ($assignment->{$permissionField} ?? false);
        }

        return false;
    }

    private function ensureTeacherHasExamPermission(
        ?User $user,
        SchoolExam $exam,
        string $permissionField,
        string $message
    ): void {
        if (!$user || !$this->isTeacherScoped($user)) {
            return;
        }

        if (!$this->teacherHasScopePermission(
            (int) $exam->school_id,
            $user,
            (int) $exam->school_classroom_id,
            (int) $exam->school_subject_id,
            (int) $exam->school_term_id,
            $permissionField
        )) {
            throw ValidationException::withMessages([
                'exam' => $message,
            ]);
        }
    }

    private function ensureTeacherHasQuestionBankPermission(int $schoolId, ?User $user, int $subjectId): void
    {
        if (!$user || !$this->isTeacherScoped($user)) {
            return;
        }

        $hasAnyTeachingAssignments = SchoolTeachingAssignment::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', (int) $user->id)
            ->where('is_active', true)
            ->exists();

        $hasSubjectLevelPermission = SchoolTeachingAssignment::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', (int) $user->id)
            ->where('is_active', true)
            ->where('can_use_question_bank', true)
            ->whereHas('courseOffering', fn ($query) => $query
                ->where('school_id', $schoolId)
                ->where('school_subject_id', $subjectId)
                ->where('is_active', true))
            ->exists();

        if ($hasSubjectLevelPermission) {
            return;
        }

        if (!$hasAnyTeachingAssignments) {
            throw ValidationException::withMessages([
                'question' => 'لا يمكن حفظ السؤال لأن المعلم غير مرتبط بأي إسناد تدريسي معتمد.',
            ]);
        }

        throw ValidationException::withMessages([
            'question' => 'لا يمكن حفظ السؤال لأن صلاحية استخدام بنك الأسئلة غير مفعلة ضمن الإسناد المعتمد.',
        ]);
    }

    /**
     * @return array{allow_subject_schedule_slot_overlap:bool,exam_day_start_time:?string,exam_day_end_time:?string}
     */
    private function loadExamSettings(int $schoolId): array
    {
        $settings = SchoolExamSetting::query()
            ->where('school_id', $schoolId)
            ->orderByDesc('id')
            ->first();

        $allowSubjectScheduleSlotOverlap = false;
        if ($settings !== null) {
            $allowSubjectScheduleSlotOverlap = (bool) (
                $settings->allow_subject_schedule_slot_overlap
                ?? $settings->allow_subject_schedule_overlap
                ?? false
            );
        }

        return [
            'allow_subject_schedule_slot_overlap' => $allowSubjectScheduleSlotOverlap,
            'exam_day_start_time' => $settings?->exam_day_start_time,
            'exam_day_end_time' => $settings?->exam_day_end_time,
        ];
    }

    private function resolveTemplate(int $schoolId, mixed $templateId): ?SchoolExamTemplate
    {
        $id = (int) ($templateId ?? 0);
        if ($id <= 0) {
            return null;
        }

        return SchoolExamTemplate::query()
            ->where('school_id', $schoolId)
            ->whereKey($id)
            ->first();
    }

    private function resolveExamMaxScore(?SchoolExamTemplate $template, array $validated): float
    {
        $provided = isset($validated['max_score']) ? (float) $validated['max_score'] : null;
        if ($template === null) {
            if ($provided === null || $provided <= 0) {
                throw ValidationException::withMessages([
                    'max_score' => 'الدرجة النهائية مطلوبة.',
                ]);
            }

            return $provided;
        }

        if (!(bool) $template->teacher_can_override_max_score || $provided === null) {
            return (float) $template->default_max_score;
        }

        if ($provided <= 0) {
            throw ValidationException::withMessages([
                'max_score' => 'الدرجة النهائية يجب أن تكون أكبر من صفر.',
            ]);
        }

        return $provided;
    }

    private function resolveExamPassingScore(?SchoolExamTemplate $template, array $validated, float $maxScore): float
    {
        $provided = isset($validated['passing_score']) ? (float) $validated['passing_score'] : null;
        if ($template === null) {
            if ($provided === null || $provided < 0) {
                throw ValidationException::withMessages([
                    'passing_score' => 'درجة النجاح مطلوبة.',
                ]);
            }

            if ($provided > $maxScore) {
                throw ValidationException::withMessages([
                    'passing_score' => 'لا يمكن الحفظ لأن درجة النجاح أكبر من الدرجة النهائية.',
                ]);
            }

            return $provided;
        }

        $resolved = (!(bool) $template->teacher_can_override_passing_score || $provided === null)
            ? (float) $template->default_passing_score
            : $provided;

        if ($resolved > $maxScore) {
            throw ValidationException::withMessages([
                'passing_score' => 'لا يمكن الحفظ لأن درجة النجاح أكبر من الدرجة النهائية.',
            ]);
        }

        return $resolved;
    }

    private function resolveExamRequiresApproval(?SchoolExamTemplate $template, array $validated): bool
    {
        if ($template !== null) {
            return (bool) $template->requires_approval;
        }

        return (bool) ($validated['requires_approval'] ?? false);
    }

    private function resolveExamAffectsFinalResult(?SchoolExamTemplate $template, array $validated): bool
    {
        if ($template !== null) {
            return (bool) $template->affects_final_result;
        }

        return (bool) ($validated['affects_final_result'] ?? true);
    }

    private function assertExamCanBeManagedByUser(SchoolExam $exam, User $user): void
    {
        if ($this->canApproveExams($user)) {
            return;
        }

        if (!$this->isTeacherScoped($user)) {
            abort(403, 'لا تملك صلاحية الوصول إلى هذا الاختبار.');
        }

        if ((int) $exam->teacher_user_id !== (int) $user->id) {
            abort(403, 'لا تملك صلاحية الوصول إلى هذا الاختبار.');
        }

        $scope = $this->teacherAssignmentScope((int) $exam->school_id, $user);
        if (!(bool) ($scope['has_assignments'] ?? false)) {
            abort(403, 'لا تملك صلاحية الوصول إلى هذا الاختبار لأنه خارج نطاق الإسنادات التدريسية المعتمدة.');
        }

        $assignment = $this->resolveTeacherAssignmentForScope(
            (int) $exam->school_id,
            $user,
            (int) $exam->school_classroom_id,
            (int) $exam->school_subject_id,
            (int) $exam->school_term_id
        );

        if ($assignment !== null) {
            return;
        }

        abort(403, 'لا تملك صلاحية الوصول إلى هذا الاختبار ضمن الإسنادات المعتمدة.');
    }

    private function syncQuestionOptions(SchoolQuestionBankItem $question, array $options): void
    {
        SchoolQuestionOption::query()
            ->where('school_id', (int) $question->school_id)
            ->where('school_question_bank_item_id', (int) $question->id)
            ->delete();

        if (count($options) === 0) {
            return;
        }

        $rows = collect($options)->values()->map(function (array $option, int $index) use ($question): array {
            return [
                'school_id' => (int) $question->school_id,
                'school_question_bank_item_id' => (int) $question->id,
                'option_text' => trim((string) ($option['option_text'] ?? '')),
                'is_correct' => (bool) ($option['is_correct'] ?? false),
                'sort_order' => (int) ($option['sort_order'] ?? ($index + 1)),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->filter(fn (array $row) => $row['option_text'] !== '')->values()->all();

        if (count($rows) > 0) {
            SchoolQuestionOption::query()->insert($rows);
        }
    }

    private function assertExamQuestionScoreMatchesMaxScore(int $schoolId, int $examId, float $maxScore): void
    {
        $sum = (float) (SchoolExamQuestion::query()
            ->where('school_id', $schoolId)
            ->where('school_exam_id', $examId)
            ->sum('score') ?? 0);

        if (abs($sum - $maxScore) > 0.0001) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن اعتماد الاختبار لأن مجموع درجات الأسئلة لا يطابق الدرجة النهائية.',
            ]);
        }
    }

    private function assertExamScoresExist(int $schoolId, int $examId): void
    {
        $hasScores = SchoolExamStudentScore::query()
            ->where('school_id', $schoolId)
            ->where('school_exam_id', $examId)
            ->exists();

        if (!$hasScores) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن تحديث الحالة إلى "تم رصد الدرجات" قبل إدخال درجات الطلاب.',
            ]);
        }
    }

    private function ensureExamInSchool(SchoolExam $schoolExam, int $schoolId): void
    {
        if ((int) $schoolExam->school_id !== $schoolId) {
            abort(403, 'لا يمكن الوصول إلى اختبار خارج نطاق المدرسة الحالية.');
        }
    }

    private function ensureTemplateInSchool(SchoolExamTemplate $schoolExamTemplate, int $schoolId): void
    {
        if ((int) $schoolExamTemplate->school_id !== $schoolId) {
            abort(403, 'لا يمكن الوصول إلى مسمى اختبار خارج نطاق المدرسة الحالية.');
        }
    }

    private function ensureQuestionInSchool(SchoolQuestionBankItem $question, int $schoolId): void
    {
        if ((int) $question->school_id !== $schoolId) {
            abort(403, 'لا يمكن الوصول إلى سؤال خارج نطاق المدرسة الحالية.');
        }
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
     * @return array{
     *     can_manage_school_exams:bool,
     *     can_manage_exam_templates:bool,
     *     can_approve_exams:bool,
     *     can_manage_question_bank:bool,
     *     can_record_exam_scores:bool,
     *     can_create_exam:bool,
     *     can_update_exam:bool,
     *     can_delete_exam:bool,
     *     can_approve_exam:bool,
     *     can_enter_exam_scores:bool,
     *     can_edit_exam_scores:bool,
     *     can_use_question_bank:bool
     * }
     */
    private function permissions(?User $user, ?int $schoolId = null): array
    {
        $canManageTemplates = $this->canManageTemplates($user);
        $canApproveExams = $this->canApproveExams($user);
        $canManageSchoolExams = (bool) ($user?->canManageSchoolExams() ?? false);

        $permissions = [
            'can_manage_school_exams' => $canManageSchoolExams,
            'can_manage_exam_templates' => $canManageTemplates,
            'can_approve_exams' => $canApproveExams,
            'can_manage_question_bank' => $canManageSchoolExams,
            'can_record_exam_scores' => $canManageSchoolExams,
            'can_create_exam' => $canManageSchoolExams,
            'can_update_exam' => $canManageSchoolExams,
            'can_delete_exam' => $canManageSchoolExams,
            'can_approve_exam' => $canApproveExams,
            'can_enter_exam_scores' => $canManageSchoolExams,
            'can_edit_exam_scores' => $canManageSchoolExams,
            'can_use_question_bank' => $canManageSchoolExams,
        ];

        if (!$user || !$this->isTeacherScoped($user)) {
            return $permissions;
        }

        if ($schoolId === null || $schoolId <= 0) {
            return $permissions;
        }

        $scope = $this->teacherAssignmentScope($schoolId, $user);
        if (!(bool) ($scope['has_assignments'] ?? false)) {
            $permissions['can_manage_school_exams'] = false;
            $permissions['can_manage_question_bank'] = false;
            $permissions['can_record_exam_scores'] = false;
            $permissions['can_create_exam'] = false;
            $permissions['can_update_exam'] = false;
            $permissions['can_delete_exam'] = false;
            $permissions['can_approve_exam'] = false;
            $permissions['can_enter_exam_scores'] = false;
            $permissions['can_edit_exam_scores'] = false;
            $permissions['can_use_question_bank'] = false;
            $permissions['can_approve_exams'] = false;
            return $permissions;
        }

        $assignmentPermissions = $scope['permissions'] ?? [];
        $canCreateExam = (bool) ($assignmentPermissions['can_create_exam'] ?? false);
        $canUpdateExam = (bool) ($assignmentPermissions['can_update_exam'] ?? false);
        $canDeleteExam = (bool) ($assignmentPermissions['can_delete_exam'] ?? false);
        $canApproveExam = (bool) ($assignmentPermissions['can_approve_exam'] ?? false);
        $canEnterScores = (bool) ($assignmentPermissions['can_enter_exam_scores'] ?? false);
        $canEditScores = (bool) ($assignmentPermissions['can_edit_exam_scores'] ?? false);
        $canUseQuestionBank = (bool) ($assignmentPermissions['can_use_question_bank'] ?? false);

        $permissions['can_manage_school_exams'] = $canCreateExam
            || $canUpdateExam
            || $canDeleteExam
            || $canApproveExam
            || $canEnterScores
            || $canEditScores
            || $canUseQuestionBank;
        $permissions['can_manage_question_bank'] = $canUseQuestionBank;
        $permissions['can_record_exam_scores'] = $canEnterScores || $canEditScores;
        $permissions['can_create_exam'] = $canCreateExam;
        $permissions['can_update_exam'] = $canUpdateExam;
        $permissions['can_delete_exam'] = $canDeleteExam;
        $permissions['can_approve_exam'] = $canApproveExam;
        $permissions['can_enter_exam_scores'] = $canEnterScores;
        $permissions['can_edit_exam_scores'] = $canEditScores;
        $permissions['can_use_question_bank'] = $canUseQuestionBank;
        $permissions['can_approve_exams'] = $canApproveExam || $canApproveExams;

        return $permissions;
    }

    private function canManageTemplates(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasSystemRole('school_manager');
    }

    private function canApproveExams(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->hasSystemRole('school_manager')) {
            return true;
        }

        return $user->canManageAcademicPlanning();
    }

    private function ensureManagerCanManageTemplates(?User $user): void
    {
        if (!$this->canManageTemplates($user)) {
            abort(403, 'لا تملك صلاحية إدارة مسميات الاختبارات.');
        }
    }

    private function isTeacherScoped(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->hasSystemRole('school_manager')) {
            return false;
        }

        if ($user->canManageAcademicPlanning()) {
            return false;
        }

        return ($user->school_staff_type ?? null) === User::SCHOOL_STAFF_EDUCATIONAL;
    }

    /**
     * @return array<int, int>
     */
    private function teacherSubjectIds(int $schoolId, ?User $user): array
    {
        if (!$user) {
            return [];
        }

        return SchoolSubjectTeacherAssignment::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', (int) $user->id)
            ->pluck('school_subject_id')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function teacherClassroomIds(int $schoolId, ?User $user, ?array $teacherSubjectIds = null): array
    {
        if (!$user) {
            return [];
        }

        $teacherSubjectIds = $teacherSubjectIds ?? $this->teacherSubjectIds($schoolId, $user);
        if (count($teacherSubjectIds) === 0) {
            return [];
        }

        $fromAssignments = SchoolTeachingAssignment::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', (int) $user->id)
            ->where('is_active', true)
            ->whereHas('courseOffering', fn ($query) => $query
                ->where('school_id', $schoolId)
                ->whereIn('school_subject_id', $teacherSubjectIds)
                ->where('is_active', true))
            ->with([
                'courseOffering:id,school_classroom_id',
                'classrooms:id',
            ])
            ->get();

        $fromAssignments = $fromAssignments
            ->flatMap(function (SchoolTeachingAssignment $assignment) {
                if ($assignment->classrooms->isNotEmpty()) {
                    return $assignment->classrooms
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id);
                }

                $legacyClassroomId = (int) ($assignment->courseOffering?->school_classroom_id ?? 0);
                return $legacyClassroomId > 0 ? [$legacyClassroomId] : [];
            })
            ->unique()
            ->values()
            ->all();

        if (count($fromAssignments) > 0) {
            return $fromAssignments;
        }

        return SchoolClassSchedule::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', (int) $user->id)
            ->whereIn('school_subject_id', $teacherSubjectIds)
            ->where('is_active', true)
            ->pluck('school_classroom_id')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     has_assignments:bool,
     *     subject_ids:array<int, int>,
     *     classroom_ids:array<int, int>,
     *     stage_ids:array<int, int>,
     *     term_ids:array<int, int>,
     *     permissions:array{
     *         can_create_exam:bool,
     *         can_update_exam:bool,
     *         can_delete_exam:bool,
     *         can_approve_exam:bool,
     *         can_enter_exam_scores:bool,
     *         can_edit_exam_scores:bool,
     *         can_use_question_bank:bool
     *     }
     * }
     */
    private function teacherAssignmentScope(int $schoolId, User $user): array
    {
        $rows = SchoolTeachingAssignment::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', (int) $user->id)
            ->where('is_active', true)
            ->whereHas('courseOffering', fn ($query) => $query
                ->where('school_id', $schoolId)
                ->where('is_active', true))
            ->with([
                'courseOffering:id,school_term_id,school_stage_id,school_stage_grade_id,school_classroom_id,school_subject_id,is_active',
                'classrooms:id',
            ])
            ->get([
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
            ]);

        if ($rows->isEmpty()) {
            return [
                'has_assignments' => false,
                'subject_ids' => [],
                'classroom_ids' => [],
                'stage_ids' => [],
                'term_ids' => [],
                'permissions' => [
                    'can_create_exam' => false,
                    'can_update_exam' => false,
                    'can_delete_exam' => false,
                    'can_approve_exam' => false,
                    'can_enter_exam_scores' => false,
                    'can_edit_exam_scores' => false,
                    'can_use_question_bank' => false,
                ],
            ];
        }

        $permissionMap = [
            'can_create_exam' => false,
            'can_update_exam' => false,
            'can_delete_exam' => false,
            'can_approve_exam' => false,
            'can_enter_exam_scores' => false,
            'can_edit_exam_scores' => false,
            'can_use_question_bank' => false,
        ];

        foreach ($rows as $row) {
            $permissionMap['can_create_exam'] = $permissionMap['can_create_exam'] || (bool) $row->can_create_exam;
            $permissionMap['can_update_exam'] = $permissionMap['can_update_exam'] || (bool) $row->can_update_exam;
            $permissionMap['can_delete_exam'] = $permissionMap['can_delete_exam'] || (bool) $row->can_delete_exam;
            $permissionMap['can_approve_exam'] = $permissionMap['can_approve_exam'] || (bool) $row->can_approve_exam;
            $permissionMap['can_enter_exam_scores'] = $permissionMap['can_enter_exam_scores'] || (bool) $row->can_enter_exam_scores;
            $permissionMap['can_edit_exam_scores'] = $permissionMap['can_edit_exam_scores'] || (bool) $row->can_edit_exam_scores;
            $permissionMap['can_use_question_bank'] = $permissionMap['can_use_question_bank'] || (bool) $row->can_use_question_bank;
        }

        return [
            'has_assignments' => true,
            'subject_ids' => $rows
                ->pluck('courseOffering.school_subject_id')
                ->filter()
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values()
                ->all(),
            'classroom_ids' => $rows
                ->flatMap(function (SchoolTeachingAssignment $assignment) {
                    if ($assignment->classrooms->isNotEmpty()) {
                        return $assignment->classrooms
                            ->pluck('id')
                            ->map(fn ($id) => (int) $id);
                    }

                    $legacyClassroomId = (int) ($assignment->courseOffering?->school_classroom_id ?? 0);
                    return $legacyClassroomId > 0 ? [$legacyClassroomId] : [];
                })
                ->unique()
                ->values()
                ->all(),
            'stage_ids' => $rows
                ->pluck('courseOffering.school_stage_id')
                ->filter()
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values()
                ->all(),
            'term_ids' => $rows
                ->pluck('courseOffering.school_term_id')
                ->filter()
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values()
                ->all(),
            'permissions' => $permissionMap,
        ];
    }

    private function templateTypeLabel(string $type): string
    {
        return match ($type) {
            SchoolExamTemplate::TYPE_WEEKLY => 'اختبار أسبوعي',
            SchoolExamTemplate::TYPE_MONTHLY => 'اختبار شهري',
            SchoolExamTemplate::TYPE_MIDTERM => 'اختبار منتصف الترم',
            SchoolExamTemplate::TYPE_FINAL_TERM => 'اختبار نهائي الترم',
            SchoolExamTemplate::TYPE_FINAL_YEAR => 'اختبار نهائي العام',
            SchoolExamTemplate::TYPE_ORAL => 'اختبار شفهي',
            SchoolExamTemplate::TYPE_PRACTICAL => 'اختبار عملي',
            SchoolExamTemplate::TYPE_CUSTOM => 'اختبار مخصص',
            default => $type,
        };
    }

    private function questionTypeLabel(string $type): string
    {
        return match ($type) {
            SchoolQuestionBankItem::TYPE_MULTIPLE_CHOICE => 'اختيار من متعدد',
            SchoolQuestionBankItem::TYPE_TRUE_FALSE => 'صح / خطأ',
            SchoolQuestionBankItem::TYPE_SHORT_ANSWER => 'إجابة قصيرة',
            SchoolQuestionBankItem::TYPE_ESSAY => 'مقالي',
            SchoolQuestionBankItem::TYPE_FILL_IN_BLANK => 'أكمل الفراغ',
            SchoolQuestionBankItem::TYPE_MATCHING => 'توصيل',
            SchoolQuestionBankItem::TYPE_ORDERING => 'ترتيب',
            SchoolQuestionBankItem::TYPE_ORAL => 'شفهي',
            SchoolQuestionBankItem::TYPE_PRACTICAL => 'عملي',
            default => $type,
        };
    }

    private function difficultyLabel(string $value): string
    {
        return match ($value) {
            SchoolQuestionBankItem::DIFFICULTY_EASY => 'سهل',
            SchoolQuestionBankItem::DIFFICULTY_MEDIUM => 'متوسط',
            SchoolQuestionBankItem::DIFFICULTY_HARD => 'صعب',
            default => $value,
        };
    }

    private function examStatusLabel(string $value): string
    {
        return match ($value) {
            SchoolExam::STATUS_DRAFT => 'مسودة',
            SchoolExam::STATUS_PENDING_APPROVAL => 'بانتظار الاعتماد',
            SchoolExam::STATUS_APPROVED => 'معتمد',
            SchoolExam::STATUS_PUBLISHED => 'منشور',
            SchoolExam::STATUS_COMPLETED => 'مكتمل',
            SchoolExam::STATUS_GRADES_RECORDED => 'تم رصد الدرجات',
            SchoolExam::STATUS_CLOSED => 'مغلق',
            SchoolExam::STATUS_POSTPONED => 'مؤجل',
            SchoolExam::STATUS_CANCELED => 'ملغي',
            default => $value,
        };
    }

    private function scoreAttendanceStatusLabel(string $value): string
    {
        return match ($value) {
            SchoolExamStudentScore::STATUS_PRESENT => 'حاضر',
            SchoolExamStudentScore::STATUS_ABSENT => 'غائب',
            SchoolExamStudentScore::STATUS_DEPRIVED => 'محروم',
            SchoolExamStudentScore::STATUS_POSTPONED => 'مؤجل',
            SchoolExamStudentScore::STATUS_RETAKE => 'معاد',
            default => $value,
        };
    }

    private function statusSuccessMessage(string $status): string
    {
        return match ($status) {
            SchoolExam::STATUS_APPROVED => 'تم اعتماد الاختبار بنجاح.',
            SchoolExam::STATUS_PUBLISHED => 'تم نشر الاختبار بنجاح.',
            SchoolExam::STATUS_COMPLETED => 'تم إنهاء الاختبار بنجاح.',
            SchoolExam::STATUS_GRADES_RECORDED => 'تم تحديث حالة الاختبار إلى تم رصد الدرجات.',
            SchoolExam::STATUS_CLOSED => 'تم إغلاق الاختبار بنجاح.',
            SchoolExam::STATUS_POSTPONED => 'تم تأجيل الاختبار بنجاح.',
            SchoolExam::STATUS_CANCELED => 'تم إلغاء الاختبار بنجاح.',
            default => 'تم تحديث حالة الاختبار بنجاح.',
        };
    }

    /**
     * @param array<int, mixed>|string|null $tags
     * @return array<int, string>|null
     */
    private function normalizeTags(array|string|null $tags): ?array
    {
        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        if (!is_array($tags)) {
            return null;
        }

        $normalized = collect($tags)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();

        return count($normalized) > 0 ? $normalized : null;
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

    private function resolvedDurationMinutes(mixed $duration, mixed $startsAt, mixed $endsAt): ?int
    {
        if ($duration !== null && (int) $duration > 0) {
            return (int) $duration;
        }

        $start = trim((string) ($startsAt ?? ''));
        $end = trim((string) ($endsAt ?? ''));
        if ($start === '' || $end === '') {
            return null;
        }

        try {
            $startAt = Carbon::createFromFormat('H:i', $start);
            $endAt = Carbon::createFromFormat('H:i', $end);

            return max(1, $startAt->diffInMinutes($endAt));
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        return $normalized === '' ? null : $normalized;
    }
}

