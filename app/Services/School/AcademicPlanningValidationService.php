<?php

namespace App\Services\School;

use App\Models\SchoolClassSchedule;
use App\Models\SchoolClassroom;
use App\Models\SchoolStage;
use App\Models\SchoolStageGrade;
use App\Models\SchoolTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class AcademicPlanningValidationService
{
    public function __construct(
        private readonly SchoolCalendarService $schoolCalendarService,
    ) {
    }

    /**
     * @param array<string, mixed> $validated
     * @param array{day_of_week:?int, day_of_month:?int, session_date:?string} $slot
     */
    public function validateScheduleAgainstSchoolReferences(
        int $schoolId,
        SchoolTerm $term,
        array $validated,
        array $slot,
        ?int $ignoreScheduleId = null
    ): void {
        $stageId = (int) $validated['school_stage_id'];
        $classroomId = (int) $validated['school_classroom_id'];
        $teacherUserId = (int) $validated['teacher_user_id'];
        $scope = (string) $validated['schedule_scope'];
        $startsAt = $this->normalizeTime((string) ($validated['starts_at'] ?? ''));
        $endsAt = $this->normalizeTime((string) ($validated['ends_at'] ?? ''));

        if (!(bool) $term->is_active) {
            throw ValidationException::withMessages([
                'school_term_id' => 'لا يمكن الحفظ لأن الترم المحدد غير نشط.',
            ]);
        }

        $stage = SchoolStage::query()
            ->where('school_id', $schoolId)
            ->whereKey($stageId)
            ->first();

        if (!$stage || !(bool) $stage->is_active) {
            throw ValidationException::withMessages([
                'school_stage_id' => 'لا يمكن الحفظ لأن المرحلة المحددة غير صالحة أو غير نشطة ضمن نفس المدرسة.',
            ]);
        }

        $classroom = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->whereKey($classroomId)
            ->where('school_stage_id', $stageId)
            ->first();

        if (!$classroom || !(bool) $classroom->is_active) {
            throw ValidationException::withMessages([
                'school_classroom_id' => 'لا يمكن الحفظ لأن الفصل المحدد غير صالح أو غير نشط ضمن نفس المدرسة والمرحلة.',
            ]);
        }

        $this->validateScheduleDayTypePolicy($schoolId, $scope, $slot);
        $this->validateScheduleWithinSchoolDayWindow($stage, $startsAt, $endsAt);
        $this->validateTeacherAndClassroomTimeConflicts(
            schoolId: $schoolId,
            termId: (int) $validated['school_term_id'],
            scope: $scope,
            slot: $slot,
            classroomId: $classroomId,
            teacherUserId: $teacherUserId,
            startsAt: $startsAt,
            endsAt: $endsAt,
            ignoreScheduleId: $ignoreScheduleId
        );
    }

    /**
     * @param array<string, mixed> $validated
     */
    public function validateCourseOfferingReferences(int $schoolId, array $validated): void
    {
        $term = SchoolTerm::query()
            ->where('school_id', $schoolId)
            ->whereKey((int) $validated['school_term_id'])
            ->first();

        if (!$term || !(bool) $term->is_active) {
            throw ValidationException::withMessages([
                'school_term_id' => 'لا يمكن الحفظ لأن الترم المحدد غير صالح أو غير نشط ضمن نفس المدرسة.',
            ]);
        }

        $stage = SchoolStage::query()
            ->where('school_id', $schoolId)
            ->whereKey((int) $validated['school_stage_id'])
            ->first();

        if (!$stage || !(bool) $stage->is_active) {
            throw ValidationException::withMessages([
                'school_stage_id' => 'لا يمكن الحفظ لأن المرحلة المحددة غير صالحة أو غير نشطة ضمن نفس المدرسة.',
            ]);
        }

        $stageGrade = SchoolStageGrade::query()
            ->where('school_id', $schoolId)
            ->whereKey((int) $validated['school_stage_grade_id'])
            ->where('school_stage_id', (int) $validated['school_stage_id'])
            ->first();

        if (!$stageGrade || !(bool) $stageGrade->is_active) {
            throw ValidationException::withMessages([
                'school_stage_grade_id' => 'لا يمكن الحفظ لأن الصف المحدد غير صالح أو غير نشط ضمن نفس المدرسة والمرحلة.',
            ]);
        }

        $hasActiveClassroom = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->where('school_stage_id', (int) $validated['school_stage_id'])
            ->where('is_active', true)
            ->whereRaw('LOWER(TRIM(grade_name)) = ?', [$this->normalizeGradeName((string) $stageGrade->name)])
            ->exists();

        if (!$hasActiveClassroom) {
            throw ValidationException::withMessages([
                'school_stage_grade_id' => 'لا يمكن حفظ المقرر لأن الصف المحدد لا يحتوي على فصول أو شعب نشطة.',
            ]);
        }

        $classroomId = isset($validated['school_classroom_id']) ? (int) $validated['school_classroom_id'] : 0;
        if ($classroomId <= 0) {
            return;
        }

        $classroom = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->whereKey($classroomId)
            ->where('school_stage_id', (int) $validated['school_stage_id'])
            ->first();

        if (!$classroom || !(bool) $classroom->is_active) {
            throw ValidationException::withMessages([
                'school_classroom_id' => 'لا يمكن الحفظ لأن الفصل المحدد غير صالح أو غير نشط ضمن نفس المدرسة والمرحلة.',
            ]);
        }

        if (
            $this->normalizeGradeName((string) $classroom->grade_name)
            !== $this->normalizeGradeName((string) $stageGrade->name)
        ) {
            throw ValidationException::withMessages([
                'school_classroom_id' => 'لا يمكن الحفظ لأن الفصل المحدد لا يتبع الصف المرتبط بالمقرر.',
            ]);
        }
    }

    /**
     * @param array{day_of_week:?int, day_of_month:?int, session_date:?string} $slot
     */
    private function validateScheduleDayTypePolicy(int $schoolId, string $scope, array $slot): void
    {
        if ($scope === SchoolClassSchedule::SCOPE_WEEKLY && $slot['day_of_week'] !== null) {
            $settings = $this->schoolCalendarService->getOrCreateSettings($schoolId);
            $weeklyOffDays = $this->schoolCalendarService->normalizeWeeklyOffDays($settings->weekly_off_days);

            if (in_array((int) $slot['day_of_week'], $weeklyOffDays, true)) {
                throw ValidationException::withMessages([
                    'day_of_week' => 'لا يمكن إضافة الجدول الدراسي لأن اليوم المحدد يوافق عطلة أسبوعية.',
                ]);
            }

            return;
        }

        if ($scope !== SchoolClassSchedule::SCOPE_TERM || $slot['session_date'] === null) {
            return;
        }

        $dayState = $this->schoolCalendarService->resolveDayTypeForDate($schoolId, $slot['session_date']);
        $dayType = strtoupper((string) ($dayState['day_type'] ?? 'SCHOOL_DAY'));

        if ($dayType === 'WEEKLY_OFF') {
            throw ValidationException::withMessages([
                'session_date' => 'لا يمكن إضافة الجدول الدراسي لأن اليوم المحدد يوافق عطلة أسبوعية.',
            ]);
        }

        if ($dayType === 'HOLIDAY') {
            $holidayName = trim((string) ($dayState['holiday_name'] ?? ''));
            $suffix = $holidayName !== '' ? ' (' . $holidayName . ')' : '';

            throw ValidationException::withMessages([
                'session_date' => 'لا يمكن إضافة الجدول الدراسي لأن التاريخ المحدد يوافق عطلة رسمية أو استثنائية' . $suffix . '.',
            ]);
        }
    }

    private function validateScheduleWithinSchoolDayWindow(SchoolStage $stage, ?string $startsAt, ?string $endsAt): void
    {
        if ($startsAt === null || $endsAt === null) {
            return;
        }

        $stageStart = $this->normalizeTime((string) ($stage->school_day_start_time ?? ''));
        $stageEnd = $this->normalizeTime((string) ($stage->school_day_end_time ?? ''));

        if ($stageStart === null || $stageEnd === null) {
            return;
        }

        if ($startsAt < $stageStart || $endsAt > $stageEnd) {
            throw ValidationException::withMessages([
                'starts_at' => 'لا يمكن حفظ البيانات لأن الوقت خارج مواعيد اليوم الدراسي المعتمدة.',
            ]);
        }
    }

    /**
     * @param array{day_of_week:?int, day_of_month:?int, session_date:?string} $slot
     */
    private function validateTeacherAndClassroomTimeConflicts(
        int $schoolId,
        int $termId,
        string $scope,
        array $slot,
        int $classroomId,
        int $teacherUserId,
        ?string $startsAt,
        ?string $endsAt,
        ?int $ignoreScheduleId = null
    ): void {
        if ($startsAt === null || $endsAt === null) {
            return;
        }

        $classroomConflict = $this->baseScopeConflictQuery($schoolId, $termId, $scope, $slot, $ignoreScheduleId)
            ->where('school_classroom_id', $classroomId)
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($classroomConflict) {
            throw ValidationException::withMessages([
                'school_classroom_id' => 'لا يمكن الحفظ لأن الفصل مشغول خلال هذه الفترة.',
            ]);
        }

        $teacherConflict = $this->baseScopeConflictQuery($schoolId, $termId, $scope, $slot, $ignoreScheduleId)
            ->where('teacher_user_id', $teacherUserId)
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($teacherConflict) {
            throw ValidationException::withMessages([
                'teacher_user_id' => 'لا يمكن الحفظ لأن المعلم مرتبط بجدول آخر في نفس الوقت.',
            ]);
        }
    }

    /**
     * @param array{day_of_week:?int, day_of_month:?int, session_date:?string} $slot
     */
    private function baseScopeConflictQuery(
        int $schoolId,
        int $termId,
        string $scope,
        array $slot,
        ?int $ignoreScheduleId = null
    ): Builder {
        $query = SchoolClassSchedule::query()
            ->active()
            ->where('school_id', $schoolId)
            ->where('school_term_id', $termId)
            ->where('schedule_scope', $scope);

        if ($scope === SchoolClassSchedule::SCOPE_WEEKLY) {
            $query->where('day_of_week', (int) $slot['day_of_week']);
        }

        if ($scope === SchoolClassSchedule::SCOPE_MONTHLY) {
            $query->where('day_of_month', (int) $slot['day_of_month']);
        }

        if ($scope === SchoolClassSchedule::SCOPE_TERM) {
            $query->whereDate('session_date', (string) $slot['session_date']);
        }

        if ($ignoreScheduleId !== null) {
            $query->whereKeyNot($ignoreScheduleId);
        }

        return $query;
    }

    private function normalizeTime(string $value): ?string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        return substr($normalized, 0, 5);
    }

    private function normalizeGradeName(string $value): string
    {
        return trim(mb_strtolower($value));
    }
}
