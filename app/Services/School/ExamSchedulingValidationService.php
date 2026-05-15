<?php

namespace App\Services\School;

use App\Models\SchoolClassSchedule;
use App\Models\SchoolClassroom;
use App\Models\SchoolCourseOffering;
use App\Models\SchoolExam;
use App\Models\SchoolExamTemplate;
use App\Models\SchoolStage;
use App\Models\SchoolStageGrade;
use App\Models\SchoolSubject;
use App\Models\SchoolSubjectTeacherAssignment;
use App\Models\SchoolTeachingAssignment;
use App\Models\SchoolTerm;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ExamSchedulingValidationService
{
    public function __construct(
        private readonly SchoolCalendarService $schoolCalendarService,
    ) {
    }

    /**
     * @param array<string, mixed> $validated
     */
    public function validateForScheduling(
        int $schoolId,
        array $validated,
        bool $allowSubjectScheduleOverlap,
        ?int $ignoreExamId = null
    ): void {
        $term = $this->ensureTermInSchool($schoolId, (int) $validated['school_term_id']);
        $this->ensureStageInSchool($schoolId, (int) $validated['school_stage_id']);
        $this->ensureClassroomInSchool($schoolId, (int) $validated['school_classroom_id'], (int) $validated['school_stage_id']);
        $this->ensureSubjectInSchool($schoolId, (int) $validated['school_subject_id']);
        $this->ensureCourseOfferingIsExamUsable(
            $schoolId,
            (int) $validated['school_term_id'],
            (int) $validated['school_stage_id'],
            (int) $validated['school_classroom_id'],
            (int) $validated['school_subject_id']
        );
        $this->ensureTeacherInSchool($schoolId, (int) $validated['teacher_user_id']);

        if (!empty($validated['school_exam_template_id'])) {
            $this->ensureTemplateIsUsable($schoolId, (int) $validated['school_exam_template_id']);
        }

        $this->ensureTeacherSubjectAssignment(
            $schoolId,
            (int) $validated['teacher_user_id'],
            (int) $validated['school_subject_id']
        );

        $this->ensureTeacherClassroomAssignmentWhenConfigured(
            $schoolId,
            (int) $validated['teacher_user_id'],
            (int) $validated['school_term_id'],
            (int) $validated['school_classroom_id'],
            (int) $validated['school_subject_id']
        );

        $examDate = Carbon::parse((string) $validated['exam_date'])->toDateString();
        $startsAt = $this->normalizeTime((string) $validated['starts_at']);
        $endsAt = $this->normalizeTime((string) $validated['ends_at']);
        $roomLabel = trim((string) ($validated['room_label'] ?? ''));

        $this->ensureDateInsideTerm($term, $examDate);
        $this->ensureDateIsWorkingSchoolDay($schoolId, $examDate);
        $this->ensureTimeInsideExamWindow($schoolId, $startsAt, $endsAt);
        $this->ensureExamConflicts($schoolId, $validated, $examDate, $startsAt, $endsAt, $roomLabel, $ignoreExamId);
        $this->ensureScheduleConflicts(
            $schoolId,
            $validated,
            $examDate,
            $startsAt,
            $endsAt,
            $allowSubjectScheduleOverlap
        );
    }

    private function ensureTemplateIsUsable(int $schoolId, int $templateId): void
    {
        $template = SchoolExamTemplate::query()
            ->where('school_id', $schoolId)
            ->whereKey($templateId)
            ->first();

        if (!$template) {
            throw ValidationException::withMessages([
                'school_exam_template_id' => 'لا يمكن حفظ الاختبار لأن مسمى الاختبار لا ينتمي إلى نفس المدرسة.',
            ]);
        }

        if (!(bool) $template->is_active) {
            throw ValidationException::withMessages([
                'school_exam_template_id' => 'لا يمكن حفظ الاختبار لأن مسمى الاختبار غير فعال.',
            ]);
        }
    }

    private function ensureTermInSchool(int $schoolId, int $termId): SchoolTerm
    {
        $term = SchoolTerm::query()
            ->where('school_id', $schoolId)
            ->whereKey($termId)
            ->first();

        if (!$term || !(bool) $term->is_active) {
            throw ValidationException::withMessages([
                'school_term_id' => 'لا يمكن حفظ الاختبار لأن الترم المحدد غير صالح أو غير نشط ضمن نفس المدرسة.',
            ]);
        }

        return $term;
    }

    private function ensureStageInSchool(int $schoolId, int $stageId): void
    {
        $stage = SchoolStage::query()
            ->where('school_id', $schoolId)
            ->whereKey($stageId)
            ->first();

        if (!$stage || !(bool) $stage->is_active) {
            throw ValidationException::withMessages([
                'school_stage_id' => 'لا يمكن حفظ الاختبار لأن المرحلة المحددة غير صالحة أو غير نشطة ضمن نفس المدرسة.',
            ]);
        }
    }

    private function ensureClassroomInSchool(int $schoolId, int $classroomId, int $stageId): void
    {
        $classroom = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->whereKey($classroomId)
            ->where('school_stage_id', $stageId)
            ->first();

        if (!$classroom || !(bool) $classroom->is_active) {
            throw ValidationException::withMessages([
                'school_classroom_id' => 'لا يمكن حفظ الاختبار لأن الفصل أو الشعبة لا ينتمي إلى نفس المدرسة أو غير نشط.',
            ]);
        }
    }

    private function ensureSubjectInSchool(int $schoolId, int $subjectId): void
    {
        $subject = SchoolSubject::query()
            ->where('school_id', $schoolId)
            ->whereKey($subjectId)
            ->first();

        if (!$subject || !(bool) $subject->is_active) {
            throw ValidationException::withMessages([
                'school_subject_id' => 'لا يمكن حفظ الاختبار لأن المادة غير صالحة أو غير نشطة داخل نفس المدرسة.',
            ]);
        }
    }

    private function ensureCourseOfferingIsExamUsable(
        int $schoolId,
        int $termId,
        int $stageId,
        int $classroomId,
        int $subjectId
    ): void {
        $stageGradeId = $this->resolveStageGradeIdByClassroom($schoolId, $stageId, $classroomId);

        $offering = SchoolCourseOffering::query()
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
            ->first();

        if (!$offering) {
            throw ValidationException::withMessages([
                'school_subject_id' => 'لا يمكن حفظ الاختبار لأن هذا المقرر غير مرتبط بالمرحلة والصف المحددين ضمن نفس المدرسة.',
            ]);
        }

        $hasUsageFlag = array_key_exists('usable_in_exams', $offering->getAttributes());
        if ($hasUsageFlag && !(bool) $offering->usable_in_exams) {
            throw ValidationException::withMessages([
                'school_subject_id' => 'لا يمكن استخدام هذا المقرر في الاختبارات لأنه غير مفعّل للاختبارات.',
            ]);
        }
    }

    private function ensureTeacherInSchool(int $schoolId, int $teacherUserId): void
    {
        $teacher = User::query()
            ->where('school_id', $schoolId)
            ->whereKey($teacherUserId)
            ->where('is_active', true)
            ->first();

        if (!$teacher) {
            throw ValidationException::withMessages([
                'teacher_user_id' => 'لا يمكن حفظ الاختبار لأن المعلم غير صالح أو لا ينتمي إلى نفس المدرسة.',
            ]);
        }
    }

    private function ensureTeacherSubjectAssignment(int $schoolId, int $teacherUserId, int $subjectId): void
    {
        $assigned = SchoolSubjectTeacherAssignment::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', $teacherUserId)
            ->where('school_subject_id', $subjectId)
            ->exists();

        if (!$assigned) {
            throw ValidationException::withMessages([
                'school_subject_id' => 'لا يمكن حفظ الاختبار لأن المعلم غير مكلف بتدريس هذه المادة.',
            ]);
        }
    }

    private function ensureTeacherClassroomAssignmentWhenConfigured(
        int $schoolId,
        int $teacherUserId,
        int $termId,
        int $classroomId,
        int $subjectId
    ): void {
        $hasAssignments = SchoolTeachingAssignment::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', $teacherUserId)
            ->where('is_active', true)
            ->exists();

        if (!$hasAssignments) {
            throw ValidationException::withMessages([
                'school_classroom_id' => 'لا يمكن حفظ الاختبار لأن المعلم غير مرتبط بإسناد تدريسي معتمد لهذه المادة والصف.',
            ]);
        }

        $classroom = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->whereKey($classroomId)
            ->first(['id', 'school_stage_id', 'grade_name']);

        $stageGradeId = 0;
        if ($classroom) {
            $stageGradeId = $this->resolveStageGradeIdByClassroom(
                schoolId: $schoolId,
                stageId: (int) $classroom->school_stage_id,
                classroomId: $classroomId
            );
        }

        $matchingAssignments = SchoolTeachingAssignment::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', $teacherUserId)
            ->where('is_active', true)
            ->whereHas('courseOffering', function ($query) use ($schoolId, $termId, $classroomId, $stageGradeId, $subjectId): void {
                $query->where('school_id', $schoolId)
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

        $hasMatchingAssignment = $matchingAssignments->contains(function (SchoolTeachingAssignment $assignment) use ($classroomId, $stageGradeId): bool {
            if ($assignment->classrooms->isNotEmpty()) {
                return $assignment->classrooms->contains(fn ($classroom) => (int) $classroom->id === $classroomId);
            }

            $offeringClassroomId = (int) ($assignment->courseOffering?->school_classroom_id ?? 0);
            if ($offeringClassroomId > 0 && $offeringClassroomId === $classroomId) {
                return true;
            }

            $offeringStageGradeId = (int) ($assignment->courseOffering?->school_stage_grade_id ?? 0);
            return $stageGradeId > 0 && $offeringStageGradeId > 0 && $offeringStageGradeId === $stageGradeId;
        });

        if (!$hasMatchingAssignment) {
            throw ValidationException::withMessages([
                'school_classroom_id' => 'لا يمكن حفظ الاختبار لأن الصف أو الشعبة غير مرتبطة بهذا المعلم لهذه المادة.',
            ]);
        }
    }

    private function resolveStageGradeIdByClassroom(int $schoolId, int $stageId, int $classroomId): int
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

        $gradeId = SchoolStageGrade::query()
            ->where('school_id', $schoolId)
            ->where('school_stage_id', $stageId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedGradeName])
            ->value('id');

        return $gradeId ? (int) $gradeId : 0;
    }

    private function ensureDateInsideTerm(SchoolTerm $term, string $examDate): void
    {
        $start = $term->start_date?->toDateString();
        $end = $term->end_date?->toDateString();

        if (($start !== null && $examDate < $start) || ($end !== null && $examDate > $end)) {
            throw ValidationException::withMessages([
                'exam_date' => 'لا يمكن حفظ الاختبار لأن التاريخ المحدد خارج نطاق الترم الدراسي.',
            ]);
        }
    }

    private function ensureDateIsWorkingSchoolDay(int $schoolId, string $examDate): void
    {
        $settings = $this->schoolCalendarService->getOrCreateSettings($schoolId);
        $weeklyOffDays = $this->schoolCalendarService->normalizeWeeklyOffDays($settings->weekly_off_days);

        $dayOfWeek = Carbon::parse($examDate)->dayOfWeek;
        if (in_array((int) $dayOfWeek, $weeklyOffDays, true)) {
            throw ValidationException::withMessages([
                'exam_date' => 'لا يمكن حفظ الاختبار لأن التاريخ المحدد يوافق عطلة أسبوعية.',
            ]);
        }

        $dayState = $this->schoolCalendarService->resolveDayTypeForDate($schoolId, $examDate);
        $dayType = strtoupper((string) ($dayState['day_type'] ?? 'SCHOOL_DAY'));

        if ($dayType === 'WEEKLY_OFF') {
            throw ValidationException::withMessages([
                'exam_date' => 'لا يمكن حفظ الاختبار لأن التاريخ المحدد يوافق عطلة أسبوعية.',
            ]);
        }

        if ($dayType === 'HOLIDAY') {
            throw ValidationException::withMessages([
                'exam_date' => 'لا يمكن حفظ الاختبار لأن التاريخ المحدد يوافق عطلة رسمية.',
            ]);
        }
    }

    private function ensureTimeInsideExamWindow(int $schoolId, string $startsAt, string $endsAt): void
    {
        $settings = \App\Models\SchoolExamSetting::query()
            ->where('school_id', $schoolId)
            ->first();

        if (!$settings) {
            return;
        }

        $allowedStart = $this->normalizeTime((string) ($settings->exam_day_start_time ?? ''));
        $allowedEnd = $this->normalizeTime((string) ($settings->exam_day_end_time ?? ''));

        if ($allowedStart === '' || $allowedEnd === '') {
            return;
        }

        if ($startsAt < $allowedStart || $endsAt > $allowedEnd) {
            throw ValidationException::withMessages([
                'starts_at' => 'لا يمكن حفظ الاختبار لأن الوقت خارج مواعيد اليوم الدراسي المعتمدة.',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function ensureExamConflicts(
        int $schoolId,
        array $validated,
        string $examDate,
        string $startsAt,
        string $endsAt,
        string $roomLabel,
        ?int $ignoreExamId
    ): void {
        $base = SchoolExam::query()
            ->where('school_id', $schoolId)
            ->whereDate('exam_date', $examDate)
            ->whereNotIn('status', [SchoolExam::STATUS_CANCELED])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($ignoreExamId !== null) {
            $base->whereKeyNot($ignoreExamId);
        }

        $classroomConflict = (clone $base)
            ->where('school_classroom_id', (int) $validated['school_classroom_id'])
            ->exists();

        if ($classroomConflict) {
            throw ValidationException::withMessages([
                'school_classroom_id' => 'لا يمكن حفظ الاختبار لوجود تعارض مع اختبار آخر.',
            ]);
        }

        $teacherConflict = (clone $base)
            ->where('teacher_user_id', (int) $validated['teacher_user_id'])
            ->exists();

        if ($teacherConflict) {
            throw ValidationException::withMessages([
                'teacher_user_id' => 'لا يمكن حفظ الاختبار لوجود تعارض مع اختبار آخر.',
            ]);
        }

        if ($roomLabel !== '') {
            $roomConflict = (clone $base)
                ->where('room_label', $roomLabel)
                ->exists();

            if ($roomConflict) {
                throw ValidationException::withMessages([
                    'room_label' => 'لا يمكن حفظ الاختبار لأن القاعة مشغولة خلال هذه الفترة.',
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function ensureScheduleConflicts(
        int $schoolId,
        array $validated,
        string $examDate,
        string $startsAt,
        string $endsAt,
        bool $allowSubjectScheduleOverlap
    ): void {
        $day = Carbon::parse($examDate);
        $dayOfWeek = (int) $day->dayOfWeek;
        $dayOfMonth = (int) $day->day;

        $overlaps = SchoolClassSchedule::query()
            ->where('school_id', $schoolId)
            ->where('school_term_id', (int) $validated['school_term_id'])
            ->where('school_classroom_id', (int) $validated['school_classroom_id'])
            ->where('is_active', true)
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->where(function ($query) use ($dayOfWeek, $dayOfMonth, $examDate): void {
                $query
                    ->where(function ($weekly) use ($dayOfWeek): void {
                        $weekly
                            ->where('schedule_scope', SchoolClassSchedule::SCOPE_WEEKLY)
                            ->where('day_of_week', $dayOfWeek);
                    })
                    ->orWhere(function ($monthly) use ($dayOfMonth): void {
                        $monthly
                            ->where('schedule_scope', SchoolClassSchedule::SCOPE_MONTHLY)
                            ->where('day_of_month', $dayOfMonth);
                    })
                    ->orWhere(function ($term) use ($examDate): void {
                        $term
                            ->where('schedule_scope', SchoolClassSchedule::SCOPE_TERM)
                            ->whereDate('session_date', $examDate);
                    });
            })
            ->get(['id', 'school_subject_id']);

        if ($overlaps->isEmpty()) {
            return;
        }

        $subjectId = (int) $validated['school_subject_id'];
        $hasNonSubjectOverlap = $overlaps->contains(fn (SchoolClassSchedule $schedule) => (int) $schedule->school_subject_id !== $subjectId);

        if ($allowSubjectScheduleOverlap && !$hasNonSubjectOverlap) {
            return;
        }

        if ($allowSubjectScheduleOverlap && $hasNonSubjectOverlap) {
            throw ValidationException::withMessages([
                'exam_date' => 'لا يمكن حفظ الاختبار لأن الموعد يتعارض مع حصة أخرى غير حصة المادة نفسها.',
            ]);
        }

        throw ValidationException::withMessages([
            'exam_date' => 'لا يمكن حفظ الاختبار لوجود تعارض مع جدول الحصص.',
        ]);
    }

    private function normalizeTime(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        return substr($trimmed, 0, 5);
    }
}
