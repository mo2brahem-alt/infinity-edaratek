<?php

namespace App\Services\School;

use App\Models\School;
use App\Models\SchoolAcademicYear;
use App\Models\SchoolClassroom;
use App\Models\SchoolClassSchedule;
use App\Models\SchoolExam;
use App\Models\SchoolExamStudentScore;
use App\Models\SchoolStage;
use App\Models\SchoolStageGrade;
use App\Models\SchoolStudent;
use App\Models\SchoolStudentAttendance;
use App\Models\SchoolStudentLeaveRequest;
use App\Models\SchoolSubject;
use App\Models\SchoolTerm;
use App\Models\Subscription;
use App\Models\Subtask;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SchoolDashboardAnalyticsService
{
    private const DEFAULT_PERIOD = 'last_30_days';

    private const DELEGATION_COLUMNS = [
        'can_manage_student_structure',
        'can_manage_student_attendance',
        'can_manage_academic_planning',
        'can_manage_student_leaves',
        'can_manage_leave_types',
        'can_manage_school_calendar',
        'can_manage_school_holidays',
    ];

    public function build(School $school, array $inputFilters = []): array
    {
        $school->loadMissing([
            'manager:id,name,email',
            'supervisor:id,name,email',
        ]);

        $filters = $this->normalizeFilters($school, $inputFilters);
        $classroomIds = $this->classroomIdsForFilters($school, $filters);

        $students = $this->studentsAnalytics($school, $filters, $classroomIds);
        $attendance = $this->attendanceAnalytics($school, $filters, $classroomIds);
        $leaves = $this->leavesAnalytics($school, $filters, $classroomIds);
        $exams = $this->examsAnalytics($school, $filters, $classroomIds);
        $teachers = $this->teachersAnalytics($school, $filters);
        $schedules = $this->schedulesAnalytics($school, $filters, $classroomIds);
        $subscription = $this->subscriptionAnalytics($school);
        $staff = $this->staffAnalytics($school);
        $alerts = $this->alerts($attendance, $leaves, $exams, $teachers, $schedules, $subscription, $staff);

        return [
            'school' => [
                'id' => (int) $school->id,
                'name' => (string) $school->name,
                'school_id' => (string) ($school->school_id ?? ''),
                'status' => (string) $school->status,
                'status_label' => $this->schoolStatusLabel((string) $school->status),
                'supervision_status' => (string) ($school->supervision_status ?? ''),
                'supervision_status_label' => $this->supervisionStatusLabel((string) ($school->supervision_status ?? '')),
                'manager' => $school->manager ? [
                    'id' => (int) $school->manager->id,
                    'name' => (string) $school->manager->name,
                    'email' => (string) $school->manager->email,
                ] : null,
                'supervisor' => $school->supervisor ? [
                    'id' => (int) $school->supervisor->id,
                    'name' => (string) $school->supervisor->name,
                    'email' => (string) $school->supervisor->email,
                ] : null,
            ],
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($school),
            'generatedAt' => now()->format('Y-m-d H:i'),
            'kpis' => $this->kpis($students, $attendance, $leaves, $exams, $teachers, $schedules, $subscription, $staff),
            'charts' => $this->charts($students, $attendance, $leaves, $exams, $teachers, $schedules),
            'summary' => [
                'students' => $students['summary'],
                'attendance' => $attendance['summary'],
                'leaves' => $leaves['summary'],
                'exams' => $exams['summary'],
                'teachers' => $teachers['summary'],
                'schedules' => $schedules['summary'],
                'subscription' => $subscription,
                'alerts' => array_slice($alerts, 0, 3),
            ],
            'students' => $students,
            'attendance' => $attendance,
            'leaves' => $leaves,
            'exams' => $exams,
            'teachers' => $teachers,
            'schedules' => $schedules,
            'staff' => $staff,
            'subscription' => $subscription,
            'alerts' => $alerts,
        ];
    }

    private function normalizeFilters(School $school, array $input): array
    {
        $period = (string) ($input['period'] ?? self::DEFAULT_PERIOD);
        if (! array_key_exists($period, $this->periodLabels())) {
            $period = self::DEFAULT_PERIOD;
        }

        [$start, $end] = $this->periodRange($school, $period);

        $stage = $this->resolveModelId(SchoolStage::query()->where('school_id', $school->id), $input['stage_id'] ?? null);
        $grade = $this->resolveModel(SchoolStageGrade::query()->where('school_id', $school->id), $input['grade_id'] ?? null);
        if ($grade && $stage && (int) $grade->school_stage_id !== (int) $stage) {
            $grade = null;
        }

        $classroomQuery = SchoolClassroom::query()->where('school_id', $school->id);
        if ($stage) {
            $classroomQuery->where('school_stage_id', $stage);
        }
        if ($grade) {
            $classroomQuery->where('grade_name', (string) $grade->name);
        }
        $classroom = $this->resolveModelId($classroomQuery, $input['classroom_id'] ?? null);

        $subject = $this->resolveModelId(SchoolSubject::query()->where('school_id', $school->id), $input['subject_id'] ?? null);
        $teacher = $this->resolveModelId($this->teacherQuery($school->id), $input['teacher_id'] ?? null);

        return [
            'period' => $period,
            'period_label' => $this->periodLabels()[$period],
            'date_from' => $start->toDateString(),
            'date_to' => $end->toDateString(),
            'stage_id' => $stage,
            'grade_id' => $grade ? (int) $grade->id : null,
            'grade_name' => $grade ? (string) $grade->name : null,
            'classroom_id' => $classroom,
            'subject_id' => $subject,
            'teacher_id' => $teacher,
        ];
    }

    private function periodLabels(): array
    {
        return [
            'today' => 'اليوم',
            'last_7_days' => 'آخر 7 أيام',
            'last_30_days' => 'آخر 30 يومًا',
            'current_month' => 'الشهر الحالي',
            'current_term' => 'الترم الحالي',
            'current_academic_year' => 'العام الدراسي الحالي',
        ];
    }

    private function periodRange(School $school, string $period): array
    {
        $today = now()->startOfDay();

        if ($period === 'today') {
            return [$today->copy(), $today->copy()->endOfDay()];
        }

        if ($period === 'last_7_days') {
            return [$today->copy()->subDays(6), $today->copy()->endOfDay()];
        }

        if ($period === 'current_month') {
            return [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()];
        }

        if ($period === 'current_term') {
            $term = SchoolTerm::query()
                ->where('school_id', $school->id)
                ->where('is_active', true)
                ->whereDate('start_date', '<=', $today->toDateString())
                ->whereDate('end_date', '>=', $today->toDateString())
                ->orderByDesc('start_date')
                ->first(['start_date', 'end_date']);

            if ($term?->start_date && $term?->end_date) {
                return [$term->start_date->copy()->startOfDay(), $term->end_date->copy()->endOfDay()];
            }
        }

        if ($period === 'current_academic_year') {
            $year = SchoolAcademicYear::query()
                ->where('school_id', $school->id)
                ->where('is_active', true)
                ->whereDate('starts_on', '<=', $today->toDateString())
                ->whereDate('ends_on', '>=', $today->toDateString())
                ->orderByDesc('starts_on')
                ->first(['starts_on', 'ends_on']);

            if ($year?->starts_on && $year?->ends_on) {
                return [$year->starts_on->copy()->startOfDay(), $year->ends_on->copy()->endOfDay()];
            }
        }

        return [$today->copy()->subDays(29), $today->copy()->endOfDay()];
    }

    private function filterOptions(School $school): array
    {
        return [
            'periods' => collect($this->periodLabels())
                ->map(fn (string $label, string $key): array => ['value' => $key, 'label' => $label])
                ->values()
                ->all(),
            'stages' => SchoolStage::query()
                ->where('school_id', $school->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (SchoolStage $stage): array => ['id' => (int) $stage->id, 'name' => (string) $stage->name])
                ->all(),
            'grades' => SchoolStageGrade::query()
                ->where('school_id', $school->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'school_stage_id', 'name'])
                ->map(fn (SchoolStageGrade $grade): array => [
                    'id' => (int) $grade->id,
                    'stage_id' => (int) $grade->school_stage_id,
                    'name' => (string) $grade->name,
                ])
                ->all(),
            'classrooms' => SchoolClassroom::query()
                ->where('school_id', $school->id)
                ->orderBy('school_stage_id')
                ->orderBy('grade_name')
                ->orderBy('sort_order')
                ->get(['id', 'school_stage_id', 'grade_name', 'name'])
                ->map(fn (SchoolClassroom $classroom): array => [
                    'id' => (int) $classroom->id,
                    'stage_id' => (int) $classroom->school_stage_id,
                    'grade_name' => (string) $classroom->grade_name,
                    'name' => trim((string) $classroom->grade_name . ' - ' . (string) $classroom->name, ' -'),
                ])
                ->all(),
            'subjects' => SchoolSubject::query()
                ->where('school_id', $school->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (SchoolSubject $subject): array => ['id' => (int) $subject->id, 'name' => (string) $subject->name])
                ->all(),
            'teachers' => $this->teacherQuery($school->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $teacher): array => ['id' => (int) $teacher->id, 'name' => (string) $teacher->name])
                ->all(),
        ];
    }

    private function studentsAnalytics(School $school, array $filters, ?array $classroomIds): array
    {
        $studentQuery = $this->applyClassroomIds(
            SchoolStudent::query()->where('school_id', $school->id),
            $classroomIds,
            'school_classroom_id'
        );

        $total = (clone $studentQuery)->count();
        $active = (clone $studentQuery)->where('is_active', true)->count();
        $inactive = max($total - $active, 0);
        $newStudents = (clone $studentQuery)
            ->whereBetween('created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59'])
            ->count();
        $classroomsTotal = $this->applyClassroomFilters(
            SchoolClassroom::query()->where('school_classrooms.school_id', $school->id),
            $filters
        )->count();

        $classrooms = $this->applyClassroomFilters(
            SchoolClassroom::query()->where('school_classrooms.school_id', $school->id),
            $filters
        );

        $studentsByStage = (clone $classrooms)
            ->join('school_stages', 'school_stages.id', '=', 'school_classrooms.school_stage_id')
            ->leftJoin('school_students', function ($join): void {
                $join->on('school_students.school_classroom_id', '=', 'school_classrooms.id')
                    ->whereColumn('school_students.school_id', 'school_classrooms.school_id');
            })
            ->select('school_stages.name as label', DB::raw('COUNT(school_students.id) as value'))
            ->groupBy('school_stages.id', 'school_stages.name')
            ->orderByDesc('value')
            ->get();

        $studentsByGrade = $this->applyClassroomFilters(
            SchoolClassroom::query()->where('school_classrooms.school_id', $school->id),
            $filters
        )
            ->leftJoin('school_students', function ($join): void {
                $join->on('school_students.school_classroom_id', '=', 'school_classrooms.id')
                    ->whereColumn('school_students.school_id', 'school_classrooms.school_id');
            })
            ->select('school_classrooms.grade_name as label', DB::raw('COUNT(school_students.id) as value'))
            ->groupBy('school_classrooms.grade_name')
            ->orderByDesc('value')
            ->get();

        $studentsByClassroom = $this->applyClassroomFilters(
            SchoolClassroom::query()->where('school_classrooms.school_id', $school->id),
            $filters
        )
            ->leftJoin('school_students', function ($join): void {
                $join->on('school_students.school_classroom_id', '=', 'school_classrooms.id')
                    ->whereColumn('school_students.school_id', 'school_classrooms.school_id');
            })
            ->select('school_classrooms.grade_name', 'school_classrooms.name', DB::raw('COUNT(school_students.id) as value'))
            ->groupBy('school_classrooms.id', 'school_classrooms.grade_name', 'school_classrooms.name')
            ->orderByDesc('value')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'label' => trim((string) $row->grade_name . ' - ' . (string) $row->name, ' -'),
                'value' => (int) $row->value,
            ]);

        $topDenseClassrooms = $this->applyClassroomFilters(
            SchoolClassroom::query()->where('school_id', $school->id),
            $filters
        )
            ->withCount(['students as students_count' => fn (Builder $query) => $query->where('school_id', $school->id)])
            ->orderByDesc('students_count')
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'grade_name', 'name'])
            ->map(fn (SchoolClassroom $classroom): array => [
                'id' => (int) $classroom->id,
                'label' => trim((string) $classroom->grade_name . ' - ' . (string) $classroom->name, ' -'),
                'value' => (int) $classroom->students_count,
            ])
            ->all();

        $newStudentsTrend = (clone $studentQuery)
            ->whereBetween('created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59'])
            ->select(DB::raw('DATE(created_at) as label'), DB::raw('COUNT(*) as value'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('label')
            ->get();

        return [
            'summary' => [
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive,
                'new_students' => $newStudents,
                'classrooms_total' => $classroomsTotal,
            ],
            'studentsByStage' => $this->series($studentsByStage),
            'studentsByGrade' => $this->series($studentsByGrade),
            'studentsByClassroom' => $this->series($studentsByClassroom),
            'topDenseClassrooms' => $topDenseClassrooms,
            'newStudentsTrend' => $this->series($newStudentsTrend),
            'activeBreakdown' => [
                ['label' => 'نشطون', 'value' => $active],
                ['label' => 'غير نشطين', 'value' => $inactive],
            ],
        ];
    }

    private function attendanceAnalytics(School $school, array $filters, ?array $classroomIds): array
    {
        $today = now()->toDateString();
        $todayQuery = $this->applyClassroomIds(
            SchoolStudentAttendance::query()
                ->where('school_student_attendances.school_id', $school->id)
                ->whereDate('attendance_date', $today),
            $classroomIds,
            'school_student_attendances.school_classroom_id'
        );

        $todayTotal = (clone $todayQuery)->count();
        $todayPresent = (clone $todayQuery)->where('status', SchoolStudentAttendance::STATUS_PRESENT)->count();
        $todayAbsent = (clone $todayQuery)->where('status', SchoolStudentAttendance::STATUS_ABSENT)->count();
        $todayExcused = (clone $todayQuery)->where('status', SchoolStudentAttendance::STATUS_EXCUSED)->count();
        $todayLeave = (clone $todayQuery)->where('status', SchoolStudentAttendance::STATUS_LEAVE)->count();
        $todayAttendanceRate = $todayTotal > 0 ? round(($todayPresent / $todayTotal) * 100, 1) : null;

        $periodQuery = $this->applyClassroomIds(
            SchoolStudentAttendance::query()
                ->where('school_student_attendances.school_id', $school->id)
                ->whereBetween('attendance_date', [$filters['date_from'], $filters['date_to']]),
            $classroomIds,
            'school_student_attendances.school_classroom_id'
        );

        $periodTotal = (clone $periodQuery)->count();
        $periodPresent = (clone $periodQuery)->where('status', SchoolStudentAttendance::STATUS_PRESENT)->count();
        $periodAttendanceRate = $periodTotal > 0 ? round(($periodPresent / $periodTotal) * 100, 1) : null;

        $attendanceTrend = (clone $periodQuery)
            ->select(
                'attendance_date as label',
                DB::raw("SUM(CASE WHEN status = '" . SchoolStudentAttendance::STATUS_PRESENT . "' THEN 1 ELSE 0 END) as present"),
                DB::raw("SUM(CASE WHEN status = '" . SchoolStudentAttendance::STATUS_ABSENT . "' THEN 1 ELSE 0 END) as absent"),
                DB::raw("SUM(CASE WHEN status = '" . SchoolStudentAttendance::STATUS_EXCUSED . "' THEN 1 ELSE 0 END) as excused"),
                DB::raw("SUM(CASE WHEN status = '" . SchoolStudentAttendance::STATUS_LEAVE . "' THEN 1 ELSE 0 END) as leave"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('attendance_date')
            ->orderBy('attendance_date')
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->label,
                'present' => (int) $row->present,
                'absent' => (int) $row->absent,
                'excused' => (int) $row->excused,
                'leave' => (int) $row->leave,
                'total' => (int) $row->total,
                'rate' => (int) $row->total > 0 ? round(((int) $row->present / (int) $row->total) * 100, 1) : 0,
            ])
            ->all();

        $statusDistribution = (clone $periodQuery)
            ->select('status as label', DB::raw('COUNT(*) as value'))
            ->groupBy('status')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row): array => [
                'label' => $this->attendanceStatusLabel((string) $row->label),
                'value' => (int) $row->value,
            ])
            ->all();

        $absenceByClassroom = (clone $periodQuery)
            ->where('status', SchoolStudentAttendance::STATUS_ABSENT)
            ->join('school_classrooms', 'school_classrooms.id', '=', 'school_student_attendances.school_classroom_id')
            ->select('school_classrooms.grade_name', 'school_classrooms.name', DB::raw('COUNT(*) as value'))
            ->groupBy('school_classrooms.id', 'school_classrooms.grade_name', 'school_classrooms.name')
            ->orderByDesc('value')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'label' => trim((string) $row->grade_name . ' - ' . (string) $row->name, ' -'),
                'value' => (int) $row->value,
            ]);

        $topAbsentStudents = (clone $periodQuery)
            ->where('status', SchoolStudentAttendance::STATUS_ABSENT)
            ->join('school_students', 'school_students.id', '=', 'school_student_attendances.school_student_id')
            ->select('school_students.full_name as label', DB::raw('COUNT(*) as value'))
            ->groupBy('school_students.id', 'school_students.full_name')
            ->orderByDesc('value')
            ->limit(5)
            ->get();

        $topExcusedStudents = (clone $periodQuery)
            ->where('status', SchoolStudentAttendance::STATUS_EXCUSED)
            ->join('school_students', 'school_students.id', '=', 'school_student_attendances.school_student_id')
            ->select('school_students.full_name as label', DB::raw('COUNT(*) as value'))
            ->groupBy('school_students.id', 'school_students.full_name')
            ->orderByDesc('value')
            ->limit(5)
            ->get();

        return [
            'summary' => [
                'today_total' => $todayTotal,
                'today_present' => $todayPresent,
                'today_absent' => $todayAbsent,
                'today_excused' => $todayExcused,
                'today_leave' => $todayLeave,
                'today_attendance_rate' => $todayAttendanceRate,
                'period_total' => $periodTotal,
                'period_attendance_rate' => $periodAttendanceRate,
            ],
            'attendanceTrend' => $attendanceTrend,
            'attendanceStatusDistribution' => $statusDistribution,
            'absenceByClassroom' => $this->series($absenceByClassroom),
            'topAbsentStudents' => $this->series($topAbsentStudents),
            'topLateStudents' => $this->series($topExcusedStudents),
        ];
    }

    private function leavesAnalytics(School $school, array $filters, ?array $classroomIds): array
    {
        $base = SchoolStudentLeaveRequest::query()
            ->where('school_student_leave_requests.school_id', $school->id)
            ->whereDate('start_date', '<=', $filters['date_to'])
            ->whereDate('end_date', '>=', $filters['date_from']);

        if ($classroomIds !== null) {
            $base->whereHas('student', function (Builder $query) use ($classroomIds): void {
                if ($classroomIds === []) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->whereIn('school_classroom_id', $classroomIds);
            });
        }

        $activeLeaves = (clone $base)
            ->where('status', SchoolStudentLeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->count();

        $byStatus = (clone $base)
            ->select('status as label', DB::raw('COUNT(*) as value'))
            ->groupBy('status')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row): array => [
                'label' => $this->leaveStatusLabel((string) $row->label),
                'value' => (int) $row->value,
            ])
            ->all();

        $byType = (clone $base)
            ->join('school_leave_types', 'school_leave_types.id', '=', 'school_student_leave_requests.school_leave_type_id')
            ->select('school_leave_types.name as label', DB::raw('COUNT(*) as value'))
            ->groupBy('school_leave_types.id', 'school_leave_types.name')
            ->orderByDesc('value')
            ->limit(8)
            ->get();

        $trend = (clone $base)
            ->select(DB::raw('DATE(start_date) as label'), DB::raw('COUNT(*) as value'))
            ->groupBy(DB::raw('DATE(start_date)'))
            ->orderBy('label')
            ->get();

        $topLeaveStudents = (clone $base)
            ->join('school_students', 'school_students.id', '=', 'school_student_leave_requests.school_student_id')
            ->select('school_students.full_name as label', DB::raw('COUNT(*) as value'))
            ->groupBy('school_students.id', 'school_students.full_name')
            ->orderByDesc('value')
            ->limit(5)
            ->get();

        return [
            'summary' => [
                'total' => (clone $base)->count(),
                'active' => $activeLeaves,
                'approved' => (clone $base)->where('status', SchoolStudentLeaveRequest::STATUS_APPROVED)->count(),
                'pending' => (clone $base)->where('status', SchoolStudentLeaveRequest::STATUS_PENDING)->count(),
                'rejected' => (clone $base)->where('status', SchoolStudentLeaveRequest::STATUS_REJECTED)->count(),
            ],
            'leavesByStatus' => $byStatus,
            'leavesByType' => $this->series($byType),
            'leavesTrend' => $this->series($trend),
            'topLeaveStudents' => $this->series($topLeaveStudents),
        ];
    }

    private function examsAnalytics(School $school, array $filters, ?array $classroomIds): array
    {
        $examBase = $this->applyExamFilters(
            SchoolExam::query()->where('school_exams.school_id', $school->id),
            $filters,
            $classroomIds
        );

        $periodExams = (clone $examBase)->whereBetween('exam_date', [$filters['date_from'], $filters['date_to']]);
        $upcoming = (clone $examBase)
            ->whereDate('exam_date', '>=', now()->toDateString())
            ->whereNotIn('status', [SchoolExam::STATUS_CANCELED, SchoolExam::STATUS_CLOSED])
            ->count();

        $allScoresBase = SchoolExamStudentScore::query()
            ->where('school_exam_student_scores.school_id', $school->id)
            ->join('school_exams', 'school_exams.id', '=', 'school_exam_student_scores.school_exam_id')
            ->whereBetween('school_exams.exam_date', [$filters['date_from'], $filters['date_to']]);

        $allScoresBase = $this->applyExamScoreFilters($allScoresBase, $filters, $classroomIds);
        $scoresBase = (clone $allScoresBase)->whereNotNull('school_exam_student_scores.score');

        $scoreSummary = (clone $scoresBase)
            ->selectRaw('COUNT(*) as scores_count')
            ->selectRaw('AVG(CASE WHEN school_exams.max_score > 0 THEN (school_exam_student_scores.score / school_exams.max_score) * 100 ELSE NULL END) as average_percent')
            ->selectRaw('SUM(CASE WHEN school_exam_student_scores.score >= school_exams.passing_score THEN 1 ELSE 0 END) as passed_count')
            ->first();

        $scoresCount = (int) ($scoreSummary->scores_count ?? 0);
        $averagePercent = $scoresCount > 0 ? round((float) $scoreSummary->average_percent, 1) : null;
        $passRate = $scoresCount > 0 ? round(((int) $scoreSummary->passed_count / $scoresCount) * 100, 1) : null;
        $incompleteScores = (clone $allScoresBase)->whereNull('school_exam_student_scores.score')->count();

        $resultsBySubject = (clone $scoresBase)
            ->join('school_subjects', 'school_subjects.id', '=', 'school_exams.school_subject_id')
            ->select('school_subjects.name as label')
            ->selectRaw('AVG(CASE WHEN school_exams.max_score > 0 THEN (school_exam_student_scores.score / school_exams.max_score) * 100 ELSE NULL END) as value')
            ->groupBy('school_subjects.id', 'school_subjects.name')
            ->orderBy('value')
            ->limit(8)
            ->get();

        $gradesDistribution = (clone $scoresBase)
            ->selectRaw("
                CASE
                    WHEN school_exams.max_score <= 0 THEN 'غير محدد'
                    WHEN (school_exam_student_scores.score / school_exams.max_score) * 100 < 50 THEN '0-49'
                    WHEN (school_exam_student_scores.score / school_exams.max_score) * 100 < 60 THEN '50-59'
                    WHEN (school_exam_student_scores.score / school_exams.max_score) * 100 < 70 THEN '60-69'
                    WHEN (school_exam_student_scores.score / school_exams.max_score) * 100 < 80 THEN '70-79'
                    WHEN (school_exam_student_scores.score / school_exams.max_score) * 100 < 90 THEN '80-89'
                    ELSE '90-100'
                END as label
            ")
            ->selectRaw('COUNT(*) as value')
            ->groupBy('label')
            ->orderByRaw("
                CASE label
                    WHEN '0-49' THEN 1
                    WHEN '50-59' THEN 2
                    WHEN '60-69' THEN 3
                    WHEN '70-79' THEN 4
                    WHEN '80-89' THEN 5
                    WHEN '90-100' THEN 6
                    ELSE 7
                END
            ")
            ->get();

        $passFailDistribution = collect([
            ['label' => 'ناجح', 'value' => (int) ($scoreSummary->passed_count ?? 0)],
            ['label' => 'راسب', 'value' => max($scoresCount - (int) ($scoreSummary->passed_count ?? 0), 0)],
            ['label' => 'غير مكتمل', 'value' => (int) $incompleteScores],
        ])->filter(fn (array $item): bool => (int) $item['value'] > 0)->values();

        $resultsByGrade = (clone $scoresBase)
            ->join('school_classrooms', 'school_classrooms.id', '=', 'school_exams.school_classroom_id')
            ->select('school_classrooms.grade_name as label')
            ->selectRaw('AVG(CASE WHEN school_exams.max_score > 0 THEN (school_exam_student_scores.score / school_exams.max_score) * 100 ELSE NULL END) as value')
            ->groupBy('school_classrooms.grade_name')
            ->orderBy('value')
            ->limit(8)
            ->get();

        $upcomingList = (clone $examBase)
            ->with(['subject:id,name', 'classroom:id,name,grade_name'])
            ->whereDate('exam_date', '>=', now()->toDateString())
            ->whereNotIn('status', [SchoolExam::STATUS_CANCELED, SchoolExam::STATUS_CLOSED])
            ->orderBy('exam_date')
            ->orderBy('starts_at')
            ->limit(5)
            ->get(['id', 'school_subject_id', 'school_classroom_id', 'title', 'exam_date', 'starts_at', 'status'])
            ->map(fn (SchoolExam $exam): array => [
                'id' => (int) $exam->id,
                'title' => (string) $exam->title,
                'date' => $exam->exam_date?->toDateString(),
                'time' => (string) ($exam->starts_at ?? ''),
                'subject' => (string) ($exam->subject?->name ?? '-'),
                'classroom' => trim((string) ($exam->classroom?->grade_name ?? '') . ' - ' . (string) ($exam->classroom?->name ?? ''), ' -'),
                'status_label' => $this->examStatusLabel((string) $exam->status),
            ])
            ->all();

        return [
            'summary' => [
                'period_total' => (clone $periodExams)->count(),
                'upcoming' => $upcoming,
                'completed' => (clone $periodExams)->whereIn('status', [SchoolExam::STATUS_COMPLETED, SchoolExam::STATUS_GRADES_RECORDED, SchoolExam::STATUS_CLOSED])->count(),
                'scores_count' => $scoresCount,
                'average_percent' => $averagePercent,
                'pass_rate' => $passRate,
            ],
            'resultsBySubject' => $this->numericSeries($resultsBySubject),
            'resultsByGrade' => $this->numericSeries($resultsByGrade),
            'gradesDistribution' => $this->series($gradesDistribution),
            'passFailDistribution' => $this->series($passFailDistribution),
            'upcomingExams' => $upcomingList,
            'lowPerformingSubjects' => $this->numericSeries($resultsBySubject)->filter(fn ($item) => (float) $item['value'] < 60)->values()->all(),
        ];
    }

    private function charts(array $students, array $attendance, array $leaves, array $exams, array $teachers, array $schedules): array
    {
        return [
            'attendanceTrend' => $this->trendChart(
                $attendance['attendanceTrend'] ?? [],
                [
                    'present' => 'حاضر',
                    'absent' => 'غائب',
                    'excused' => 'مأذون',
                    'leave' => 'إجازة',
                ],
                'area'
            ),
            'attendanceStatusDistribution' => $this->donutChart($attendance['attendanceStatusDistribution'] ?? []),
            'studentsByStage' => $this->barChart($students['studentsByStage'] ?? []),
            'studentsByGrade' => $this->barChart($students['studentsByGrade'] ?? []),
            'classroomDensity' => $this->barChart($students['topDenseClassrooms'] ?? [], true),
            'absenceByClassroom' => $this->barChart($attendance['absenceByClassroom'] ?? [], true),
            'leavesByStatus' => $this->donutChart($leaves['leavesByStatus'] ?? []),
            'leavesByType' => $this->barChart($leaves['leavesByType'] ?? []),
            'leavesTrend' => $this->singleTrendChart($leaves['leavesTrend'] ?? [], 'الإجازات'),
            'examResultsBySubject' => $this->barChart($exams['resultsBySubject'] ?? []),
            'gradesDistribution' => $this->barChart($exams['gradesDistribution'] ?? []),
            'passFailDistribution' => $this->donutChart($exams['passFailDistribution'] ?? []),
            'teacherWeeklyLoad' => $this->barChart($teachers['teacherWeeklyLoad'] ?? [], true),
            'teachersByDepartment' => $this->donutChart($teachers['teachersByDepartment'] ?? []),
            'lessonsByDay' => $this->barChart($schedules['lessonsByDay'] ?? []),
            'lessonsBySubject' => $this->barChart($schedules['lessonsBySubject'] ?? [], true),
        ];
    }

    private function trendChart(array|Collection $rows, array $seriesMap, string $type = 'line'): array
    {
        $rows = collect($rows);

        return [
            'type' => $type,
            'categories' => $rows->pluck('label')->map(fn ($label) => (string) $label)->values()->all(),
            'series' => collect($seriesMap)->map(fn (string $label, string $key): array => [
                'name' => $label,
                'data' => $rows->map(fn ($row) => (int) (is_array($row) ? ($row[$key] ?? 0) : ($row->{$key} ?? 0)))->values()->all(),
            ])->values()->all(),
        ];
    }

    private function singleTrendChart(array|Collection $rows, string $name): array
    {
        $rows = collect($rows);

        return [
            'type' => 'area',
            'categories' => $rows->pluck('label')->map(fn ($label) => (string) $label)->values()->all(),
            'series' => [[
                'name' => $name,
                'data' => $rows->map(fn ($row) => (int) (is_array($row) ? ($row['value'] ?? 0) : ($row->value ?? 0)))->values()->all(),
            ]],
        ];
    }

    private function barChart(array|Collection $rows, bool $horizontal = false): array
    {
        $rows = collect($rows);

        return [
            'type' => 'bar',
            'horizontal' => $horizontal,
            'categories' => $rows->pluck('label')->map(fn ($label) => (string) $label)->values()->all(),
            'series' => [[
                'name' => 'القيمة',
                'data' => $rows->map(fn ($row) => round((float) (is_array($row) ? ($row['value'] ?? 0) : ($row->value ?? 0)), 1))->values()->all(),
            ]],
        ];
    }

    private function donutChart(array|Collection $rows): array
    {
        $rows = collect($rows);

        return [
            'type' => 'donut',
            'labels' => $rows->pluck('label')->map(fn ($label) => (string) $label)->values()->all(),
            'series' => $rows->map(fn ($row) => (int) (is_array($row) ? ($row['value'] ?? 0) : ($row->value ?? 0)))->values()->all(),
        ];
    }

    private function teachersAnalytics(School $school, array $filters): array
    {
        $teacherQuery = $this->teacherQuery($school->id);

        $total = (clone $teacherQuery)->count();
        $active = (clone $teacherQuery)->where('users.is_active', true)->count();
        $inactive = max($total - $active, 0);

        $byDepartment = (clone $teacherQuery)
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->select(DB::raw("COALESCE(departments.name, 'غير محدد') as label"), DB::raw('COUNT(users.id) as value'))
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('value')
            ->get();

        $byRole = (clone $teacherQuery)
            ->leftJoin('department_roles', 'department_roles.id', '=', 'users.department_role_id')
            ->select(DB::raw("COALESCE(department_roles.name, 'غير محدد') as label"), DB::raw('COUNT(users.id) as value'))
            ->groupBy('department_roles.id', 'department_roles.name')
            ->orderByDesc('value')
            ->get();

        $teacherWeeklyLoad = SchoolClassSchedule::query()
            ->where('school_class_schedules.school_id', $school->id)
            ->where('school_class_schedules.schedule_scope', SchoolClassSchedule::SCOPE_WEEKLY)
            ->where('school_class_schedules.is_active', true)
            ->when($filters['subject_id'], fn (Builder $query) => $query->where('school_class_schedules.school_subject_id', (int) $filters['subject_id']))
            ->when($filters['teacher_id'], fn (Builder $query) => $query->where('school_class_schedules.teacher_user_id', (int) $filters['teacher_id']))
            ->join('users', 'users.id', '=', 'school_class_schedules.teacher_user_id')
            ->select('users.name as label', DB::raw('COUNT(school_class_schedules.id) as value'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->limit(10)
            ->get();

        $lowLoadedTeachers = (clone $teacherQuery)
            ->where('users.is_active', true)
            ->withCount(['classSchedulesAsTeacher as weekly_lessons_count' => fn (Builder $query) => $query
                ->where('school_class_schedules.school_id', $school->id)
                ->where('school_class_schedules.schedule_scope', SchoolClassSchedule::SCOPE_WEEKLY)
                ->where('school_class_schedules.is_active', true)])
            ->orderBy('weekly_lessons_count')
            ->orderBy('users.name')
            ->limit(5)
            ->get(['users.id', 'users.name'])
            ->map(fn (User $teacher): array => [
                'id' => (int) $teacher->id,
                'label' => (string) $teacher->name,
                'value' => (int) $teacher->weekly_lessons_count,
            ])
            ->all();

        $withoutSchedules = array_values(array_filter($lowLoadedTeachers, fn (array $teacher): bool => (int) $teacher['value'] === 0));

        $teachersWithUpcomingExams = (clone $teacherQuery)
            ->whereHas('examsAsTeacher', fn (Builder $query) => $query
                ->where('school_exams.school_id', $school->id)
                ->whereDate('exam_date', '>=', now()->toDateString())
                ->whereNotIn('status', [SchoolExam::STATUS_CANCELED, SchoolExam::STATUS_CLOSED]))
            ->count();

        $delegatedTeachers = (clone $teacherQuery)
            ->where(function (Builder $query): void {
                foreach (self::DELEGATION_COLUMNS as $column) {
                    $query->orWhere('users.' . $column, true);
                }
            })
            ->count();

        $withoutDelegations = (clone $teacherQuery)
            ->where('users.is_active', true)
            ->where(function (Builder $query): void {
                foreach (self::DELEGATION_COLUMNS as $column) {
                    $query->where(function (Builder $permission) use ($column): void {
                        $permission->whereNull('users.' . $column)->orWhere('users.' . $column, false);
                    });
                }
            })
            ->orderBy('users.name')
            ->limit(5)
            ->get(['users.id', 'users.name'])
            ->map(fn (User $teacher): array => [
                'id' => (int) $teacher->id,
                'label' => (string) $teacher->name,
            ])
            ->all();

        $openSubtasks = Subtask::query()
            ->where('subtasks.school_id', $school->id)
            ->whereIn('subtasks.status', [Subtask::STATUS_OPEN, Subtask::STATUS_IN_PROGRESS, Subtask::STATUS_SUBMITTED])
            ->join('users', 'users.id', '=', 'subtasks.assigned_to')
            ->where('users.school_id', $school->id)
            ->where('users.school_staff_type', User::SCHOOL_STAFF_EDUCATIONAL)
            ->select('users.name as label', DB::raw('COUNT(subtasks.id) as value'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->limit(5)
            ->get();

        return [
            'summary' => [
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive,
                'delegated' => $delegatedTeachers,
                'without_delegations' => count($withoutDelegations),
                'with_upcoming_exams' => $teachersWithUpcomingExams,
            ],
            'teachersByDepartment' => $this->series($byDepartment),
            'teachersByRole' => $this->series($byRole),
            'teacherWeeklyLoad' => $this->series($teacherWeeklyLoad),
            'topLoadedTeachers' => $this->series($teacherWeeklyLoad)->take(5)->values()->all(),
            'lowLoadedTeachers' => $lowLoadedTeachers,
            'teachersWithoutSchedules' => $withoutSchedules,
            'teachersWithUpcomingExams' => $teachersWithUpcomingExams,
            'delegatedTeachersCount' => $delegatedTeachers,
            'teachersWithoutDelegations' => $withoutDelegations,
            'teachersOpenTickets' => $this->series($openSubtasks),
        ];
    }

    private function schedulesAnalytics(School $school, array $filters, ?array $classroomIds): array
    {
        $base = $this->applyScheduleFilters(
            SchoolClassSchedule::query()
                ->where('school_class_schedules.school_id', $school->id)
                ->where('school_class_schedules.is_active', true),
            $filters,
            $classroomIds
        );

        $weekly = (clone $base)->where('school_class_schedules.schedule_scope', SchoolClassSchedule::SCOPE_WEEKLY);

        $lessonsByDay = (clone $weekly)
            ->select('day_of_week as label', DB::raw('COUNT(*) as value'))
            ->groupBy('day_of_week')
            ->orderBy('day_of_week')
            ->get()
            ->map(fn ($row): array => [
                'label' => $this->dayLabel((int) $row->label),
                'value' => (int) $row->value,
            ])
            ->all();

        $lessonsBySubject = (clone $base)
            ->join('school_subjects', 'school_subjects.id', '=', 'school_class_schedules.school_subject_id')
            ->select('school_subjects.name as label', DB::raw('COUNT(*) as value'))
            ->groupBy('school_subjects.id', 'school_subjects.name')
            ->orderByDesc('value')
            ->limit(8)
            ->get();

        $lessonsByGrade = (clone $base)
            ->join('school_classrooms', 'school_classrooms.id', '=', 'school_class_schedules.school_classroom_id')
            ->select('school_classrooms.grade_name as label', DB::raw('COUNT(*) as value'))
            ->groupBy('school_classrooms.grade_name')
            ->orderByDesc('value')
            ->limit(8)
            ->get();

        $subjectsWithSchedules = (clone $base)
            ->distinct('school_class_schedules.school_subject_id')
            ->pluck('school_class_schedules.school_subject_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();
        $unscheduledSubjects = SchoolSubject::query()
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->when($filters['subject_id'], fn (Builder $query) => $query->whereKey((int) $filters['subject_id']))
            ->when(count($subjectsWithSchedules) > 0, fn (Builder $query) => $query->whereNotIn('id', $subjectsWithSchedules))
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name'])
            ->map(fn (SchoolSubject $subject): array => [
                'id' => (int) $subject->id,
                'label' => (string) $subject->name,
            ])
            ->all();

        return [
            'summary' => [
                'weekly_lessons' => (clone $weekly)->count(),
                'total_active_lessons' => (clone $base)->count(),
                'unscheduled_subjects' => count($unscheduledSubjects),
                'unassigned_lessons' => 0,
            ],
            'lessonsByDay' => $lessonsByDay,
            'lessonsBySubject' => $this->series($lessonsBySubject),
            'lessonsByGrade' => $this->series($lessonsByGrade),
            'unscheduledSubjects' => $unscheduledSubjects,
            'scheduleWarnings' => count($unscheduledSubjects) > 0 ? [[
                'title' => 'مواد بدون حصص مجدولة',
                'description' => 'توجد مواد نشطة لم تظهر في الجداول الحالية.',
                'severity' => 'warning',
            ]] : [],
        ];
    }

    private function staffAnalytics(School $school): array
    {
        $staffQuery = $this->staffQuery($school->id);
        $administrative = (clone $staffQuery)->where('users.school_staff_type', User::SCHOOL_STAFF_ADMINISTRATIVE)->count();
        $educational = (clone $staffQuery)->where('users.school_staff_type', User::SCHOOL_STAFF_EDUCATIONAL)->count();
        $withoutDelegations = (clone $staffQuery)
            ->where('users.is_active', true)
            ->where(function (Builder $query): void {
                foreach (self::DELEGATION_COLUMNS as $column) {
                    $query->where(function (Builder $permission) use ($column): void {
                        $permission->whereNull('users.' . $column)->orWhere('users.' . $column, false);
                    });
                }
            })
            ->count();

        return [
            'summary' => [
                'total' => (clone $staffQuery)->count(),
                'active' => (clone $staffQuery)->where('users.is_active', true)->count(),
                'administrative' => $administrative,
                'educational' => $educational,
                'without_delegations' => $withoutDelegations,
            ],
        ];
    }

    private function subscriptionAnalytics(School $school): array
    {
        $subscription = Subscription::query()
            ->currentForSchool((int) $school->id)
            ->with('plan:id,name,included_users_count')
            ->orderByDesc('ends_at')
            ->first();

        if (! $subscription) {
            return [
                'status' => 'missing',
                'status_label' => 'لا يوجد اشتراك سارٍ',
                'plan_name' => null,
                'ends_at' => null,
                'days_remaining' => null,
                'included_users' => null,
                'used_users' => null,
                'remaining_users' => null,
            ];
        }

        $includedUsers = (int) ($subscription->included_users_count ?? $subscription->plan?->included_users_count ?? 0);
        $usedUsers = User::query()->where('users.school_id', $school->id)->where('users.is_active', true)->count();

        return [
            'status' => 'active',
            'status_label' => 'نشط',
            'plan_name' => (string) ($subscription->plan?->name ?? 'اشتراك المدرسة'),
            'ends_at' => $subscription->ends_at?->toDateString(),
            'days_remaining' => $subscription->ends_at ? max(now()->startOfDay()->diffInDays($subscription->ends_at, false), 0) : null,
            'included_users' => $includedUsers > 0 ? $includedUsers : null,
            'used_users' => $usedUsers,
            'remaining_users' => $includedUsers > 0 ? max($includedUsers - $usedUsers, 0) : null,
        ];
    }

    private function kpis(array $students, array $attendance, array $leaves, array $exams, array $teachers, array $schedules, array $subscription, array $staff): array
    {
        return [
            ['key' => 'students_total', 'label' => 'إجمالي الطلاب', 'value' => $students['summary']['total'], 'description' => 'كل الطلاب المسجلين في المدرسة', 'icon' => 'users', 'status' => 'info'],
            ['key' => 'students_active', 'label' => 'الطلاب النشطون', 'value' => $students['summary']['active'], 'description' => 'طلاب يمكن إدارتهم داخل الوحدات', 'icon' => 'user-check', 'status' => 'success'],
            ['key' => 'teachers_total', 'label' => 'المعلمون', 'value' => $teachers['summary']['total'], 'description' => 'الطاقم التعليمي المرتبط بالمدرسة', 'icon' => 'graduation-cap', 'status' => 'primary'],
            ['key' => 'administrative_staff', 'label' => 'الإداريون', 'value' => $staff['summary']['administrative'], 'description' => 'أعضاء الطاقم الإداري', 'icon' => 'briefcase', 'status' => 'muted'],
            ['key' => 'classrooms_total', 'label' => 'الفصول', 'value' => $students['summary']['classrooms_total'], 'description' => 'الفصول النشطة ضمن نطاق التحليل', 'icon' => 'layout-grid', 'status' => 'info'],
            ['key' => 'today_attendance_rate', 'label' => 'حضور اليوم', 'value' => $this->percentText($attendance['summary']['today_attendance_rate']), 'description' => 'من سجلات حضور اليوم', 'icon' => 'activity', 'status' => $this->rateStatus($attendance['summary']['today_attendance_rate'])],
            ['key' => 'today_absent', 'label' => 'غياب اليوم', 'value' => $attendance['summary']['today_absent'], 'description' => 'عدد الطلاب الغائبين اليوم', 'icon' => 'user-x', 'status' => $attendance['summary']['today_absent'] > 0 ? 'warning' : 'success'],
            ['key' => 'today_excused', 'label' => 'مأذون اليوم', 'value' => $attendance['summary']['today_excused'], 'description' => 'حالات الإذن المسجلة اليوم', 'icon' => 'clock', 'status' => 'warning'],
            ['key' => 'active_leaves', 'label' => 'الإجازات النشطة', 'value' => $leaves['summary']['active'], 'description' => 'إجازات معتمدة تشمل اليوم', 'icon' => 'calendar-clock', 'status' => 'info'],
            ['key' => 'upcoming_exams', 'label' => 'اختبارات قادمة', 'value' => $exams['summary']['upcoming'], 'description' => 'اختبارات قادمة وغير مغلقة', 'icon' => 'clipboard-list', 'status' => 'primary'],
            ['key' => 'average_results', 'label' => 'متوسط النتائج', 'value' => $this->percentText($exams['summary']['average_percent']), 'description' => 'متوسط النتائج خلال الفترة', 'icon' => 'chart', 'status' => $this->rateStatus($exams['summary']['average_percent'])],
            ['key' => 'pass_rate', 'label' => 'نسبة النجاح', 'value' => $this->percentText($exams['summary']['pass_rate']), 'description' => 'حسب الدرجات المسجلة', 'icon' => 'award', 'status' => $this->rateStatus($exams['summary']['pass_rate'])],
            ['key' => 'weekly_lessons', 'label' => 'حصص أسبوعية', 'value' => $schedules['summary']['weekly_lessons'], 'description' => 'الحصص الأسبوعية النشطة', 'icon' => 'calendar-days', 'status' => 'info'],
            ['key' => 'subscription_days', 'label' => 'الاشتراك', 'value' => $subscription['days_remaining'] !== null ? $subscription['days_remaining'] . ' يوم' : $subscription['status_label'], 'description' => $subscription['plan_name'] ?: 'حالة الاشتراك الحالية', 'icon' => 'badge-check', 'status' => $subscription['status'] === 'active' ? 'success' : 'danger'],
        ];
    }

    private function alerts(array $attendance, array $leaves, array $exams, array $teachers, array $schedules, array $subscription, array $staff): array
    {
        $alerts = [];

        $todayRate = $attendance['summary']['today_attendance_rate'];
        if ($todayRate !== null && $todayRate < 85) {
            $alerts[] = [
                'title' => 'انخفاض حضور اليوم',
                'description' => 'نسبة حضور اليوم أقل من 85%. راجع الفصول ذات الغياب الأعلى.',
                'severity' => 'danger',
                'icon' => 'activity',
            ];
        }

        if ($attendance['summary']['today_absent'] > 0) {
            $alerts[] = [
                'title' => 'غياب مسجل اليوم',
                'description' => 'يوجد ' . $attendance['summary']['today_absent'] . ' طالبًا مسجلين كغياب اليوم.',
                'severity' => 'warning',
                'icon' => 'user-x',
            ];
        }

        if ($leaves['summary']['pending'] > 0) {
            $alerts[] = [
                'title' => 'طلبات إجازة بانتظار المراجعة',
                'description' => 'هناك ' . $leaves['summary']['pending'] . ' طلب إجازة يحتاج قرارًا إداريًا.',
                'severity' => 'warning',
                'icon' => 'calendar-clock',
            ];
        }

        if ($exams['summary']['upcoming'] > 0) {
            $alerts[] = [
                'title' => 'اختبارات قادمة',
                'description' => 'يوجد ' . $exams['summary']['upcoming'] . ' اختبارًا قادمًا ضمن جدول المدرسة.',
                'severity' => 'info',
                'icon' => 'clipboard-list',
            ];
        }

        if ($exams['summary']['average_percent'] !== null && $exams['summary']['average_percent'] < 60) {
            $alerts[] = [
                'title' => 'متوسط نتائج منخفض',
                'description' => 'متوسط النتائج خلال الفترة أقل من 60%.',
                'severity' => 'danger',
                'icon' => 'chart',
            ];
        }

        if (count($teachers['teachersWithoutSchedules']) > 0) {
            $alerts[] = [
                'title' => 'معلمون بلا جدول أسبوعي',
                'description' => 'يوجد معلمون نشطون لم تظهر لهم حصص أسبوعية.',
                'severity' => 'warning',
                'icon' => 'calendar-days',
            ];
        }

        if ($teachers['summary']['without_delegations'] > 0) {
            $alerts[] = [
                'title' => 'معلمون بدون تفويضات تشغيلية',
                'description' => 'راجع صلاحيات المعلمين لضمان وضوح المسؤوليات.',
                'severity' => 'info',
                'icon' => 'shield',
            ];
        }

        if ($schedules['summary']['unscheduled_subjects'] > 0) {
            $alerts[] = [
                'title' => 'مواد غير مجدولة',
                'description' => 'هناك مواد نشطة لم تظهر في الجداول الحالية.',
                'severity' => 'warning',
                'icon' => 'book-open',
            ];
        }

        if ($staff['summary']['without_delegations'] > 0) {
            $alerts[] = [
                'title' => 'موظفون بدون صلاحيات تشغيلية',
                'description' => 'يوجد موظفون نشطون بلا تفويض واضح داخل المدرسة.',
                'severity' => 'info',
                'icon' => 'users',
            ];
        }

        if ($subscription['status'] !== 'active') {
            $alerts[] = [
                'title' => 'لا يوجد اشتراك سارٍ',
                'description' => 'راجع حالة الاشتراك حتى لا تتأثر وحدات المدرسة.',
                'severity' => 'danger',
                'icon' => 'badge-alert',
            ];
        } elseif ($subscription['days_remaining'] !== null && $subscription['days_remaining'] <= 15) {
            $alerts[] = [
                'title' => 'الاشتراك قريب من الانتهاء',
                'description' => 'تبقى ' . $subscription['days_remaining'] . ' يومًا على انتهاء الاشتراك الحالي.',
                'severity' => 'warning',
                'icon' => 'badge-alert',
            ];
        }

        return $alerts;
    }

    private function classroomIdsForFilters(School $school, array $filters): ?array
    {
        if (! $filters['stage_id'] && ! $filters['grade_id'] && ! $filters['classroom_id']) {
            return null;
        }

        $query = SchoolClassroom::query()->where('school_id', $school->id);
        if ($filters['stage_id']) {
            $query->where('school_stage_id', (int) $filters['stage_id']);
        }
        if ($filters['grade_name']) {
            $query->where('grade_name', (string) $filters['grade_name']);
        }
        if ($filters['classroom_id']) {
            $query->whereKey((int) $filters['classroom_id']);
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function applyClassroomFilters(Builder $query, array $filters): Builder
    {
        if ($filters['stage_id']) {
            $query->where('school_classrooms.school_stage_id', (int) $filters['stage_id']);
        }
        if ($filters['grade_name']) {
            $query->where('school_classrooms.grade_name', (string) $filters['grade_name']);
        }
        if ($filters['classroom_id']) {
            $query->where('school_classrooms.id', (int) $filters['classroom_id']);
        }

        return $query;
    }

    private function applyClassroomIds(Builder $query, ?array $classroomIds, string $column): Builder
    {
        if ($classroomIds === null) {
            return $query;
        }

        if ($classroomIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $classroomIds);
    }

    private function applyExamFilters(Builder $query, array $filters, ?array $classroomIds): Builder
    {
        $this->applyClassroomIds($query, $classroomIds, 'school_classroom_id');
        if ($filters['subject_id']) {
            $query->where('school_exams.school_subject_id', (int) $filters['subject_id']);
        }
        if ($filters['teacher_id']) {
            $query->where('school_exams.teacher_user_id', (int) $filters['teacher_id']);
        }

        return $query;
    }

    private function applyExamScoreFilters(Builder $query, array $filters, ?array $classroomIds): Builder
    {
        if ($classroomIds !== null) {
            if ($classroomIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('school_exams.school_classroom_id', $classroomIds);
            }
        }
        if ($filters['subject_id']) {
            $query->where('school_exams.school_subject_id', (int) $filters['subject_id']);
        }
        if ($filters['teacher_id']) {
            $query->where('school_exams.teacher_user_id', (int) $filters['teacher_id']);
        }

        return $query;
    }

    private function applyScheduleFilters(Builder $query, array $filters, ?array $classroomIds): Builder
    {
        $this->applyClassroomIds($query, $classroomIds, 'school_classroom_id');
        if ($filters['subject_id']) {
            $query->where('school_class_schedules.school_subject_id', (int) $filters['subject_id']);
        }
        if ($filters['teacher_id']) {
            $query->where('school_class_schedules.teacher_user_id', (int) $filters['teacher_id']);
        }

        return $query;
    }

    private function teacherQuery(int $schoolId): Builder
    {
        return User::query()
            ->where('users.school_id', $schoolId)
            ->where(function (Builder $query): void {
                $query->where('users.role', 'staff')
                    ->orWhereHas('roles', fn (Builder $roles) => $roles->where('name', 'staff'));
            })
            ->where('users.school_staff_type', User::SCHOOL_STAFF_EDUCATIONAL);
    }

    private function staffQuery(int $schoolId): Builder
    {
        return User::query()
            ->where('users.school_id', $schoolId)
            ->where(function (Builder $query): void {
                $query->where('users.role', 'staff')
                    ->orWhereHas('roles', fn (Builder $roles) => $roles->where('name', 'staff'));
            });
    }

    private function resolveModelId(Builder $query, mixed $value): ?int
    {
        $model = $this->resolveModel($query, $value);

        return $model ? (int) $model->getKey() : null;
    }

    private function resolveModel(Builder $query, mixed $value): ?object
    {
        $id = (int) $value;
        if ($id <= 0) {
            return null;
        }

        return $query->whereKey($id)->first();
    }

    private function series(Collection $rows): Collection
    {
        return $rows->map(fn ($row): array => [
            'label' => (string) (is_array($row) ? ($row['label'] ?? '-') : ($row->label ?? '-')),
            'value' => (int) (is_array($row) ? ($row['value'] ?? 0) : ($row->value ?? 0)),
        ]);
    }

    private function numericSeries(Collection $rows): Collection
    {
        return $rows->map(fn ($row): array => [
            'label' => (string) (is_array($row) ? ($row['label'] ?? '-') : ($row->label ?? '-')),
            'value' => round((float) (is_array($row) ? ($row['value'] ?? 0) : ($row->value ?? 0)), 1),
        ]);
    }

    private function percentText(?float $value): string
    {
        return $value === null ? 'لا توجد بيانات' : $value . '%';
    }

    private function rateStatus(?float $value): string
    {
        if ($value === null) {
            return 'muted';
        }

        return match (true) {
            $value >= 90 => 'success',
            $value >= 75 => 'info',
            $value >= 60 => 'warning',
            default => 'danger',
        };
    }

    private function schoolStatusLabel(string $status): string
    {
        return match ($status) {
            School::STATUS_ACTIVE => 'مفعلة',
            School::STATUS_SUSPENDED => 'موقوفة',
            default => $status ?: '-',
        };
    }

    private function supervisionStatusLabel(string $status): string
    {
        return match ($status) {
            School::SUPERVISION_STATUS_WAITING_MANAGER_APPROVAL => 'بانتظار موافقة المدير',
            School::SUPERVISION_STATUS_WAITING_SUPERVISOR_CONFIRM => 'بانتظار تأكيد المشرف',
            School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION => 'ارتباط إشرافي نشط',
            School::SUPERVISION_STATUS_SUSPENDED => 'إشراف موقوف',
            default => $status ?: '-',
        };
    }

    private function attendanceStatusLabel(string $status): string
    {
        return match ($status) {
            SchoolStudentAttendance::STATUS_PRESENT => 'حاضر',
            SchoolStudentAttendance::STATUS_ABSENT => 'غائب',
            SchoolStudentAttendance::STATUS_EXCUSED => 'مأذون',
            SchoolStudentAttendance::STATUS_LEAVE => 'إجازة',
            default => $status ?: '-',
        };
    }

    private function leaveStatusLabel(string $status): string
    {
        return match ($status) {
            SchoolStudentLeaveRequest::STATUS_PENDING => 'معلق',
            SchoolStudentLeaveRequest::STATUS_APPROVED => 'معتمد',
            SchoolStudentLeaveRequest::STATUS_REJECTED => 'مرفوض',
            SchoolStudentLeaveRequest::STATUS_CANCELLED => 'ملغي',
            default => $status ?: '-',
        };
    }

    private function examStatusLabel(string $status): string
    {
        return match ($status) {
            SchoolExam::STATUS_DRAFT => 'مسودة',
            SchoolExam::STATUS_PENDING_APPROVAL => 'بانتظار الاعتماد',
            SchoolExam::STATUS_APPROVED => 'معتمد',
            SchoolExam::STATUS_PUBLISHED => 'منشور',
            SchoolExam::STATUS_COMPLETED => 'منتهي',
            SchoolExam::STATUS_GRADES_RECORDED => 'تم تسجيل الدرجات',
            SchoolExam::STATUS_CLOSED => 'مغلق',
            SchoolExam::STATUS_POSTPONED => 'مؤجل',
            SchoolExam::STATUS_CANCELED => 'ملغي',
            default => $status ?: '-',
        };
    }

    private function dayLabel(int $day): string
    {
        return match ($day) {
            1 => 'الأحد',
            2 => 'الإثنين',
            3 => 'الثلاثاء',
            4 => 'الأربعاء',
            5 => 'الخميس',
            6 => 'الجمعة',
            7 => 'السبت',
            default => 'غير محدد',
        };
    }
}
