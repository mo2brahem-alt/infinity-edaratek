<?php

namespace App\Services\Integrity;

use App\Models\SchoolClassroom;
use App\Models\SchoolClassSchedule;
use App\Models\SchoolHoliday;
use App\Models\SchoolLeaveType;
use App\Models\SchoolStage;
use App\Models\SchoolStudent;
use App\Models\SchoolStudentAttendance;
use App\Models\SchoolStudentLeaveRequest;
use Illuminate\Support\Carbon;

class IntegrityImpactService
{
    public const SEVERITY_INFO = 'INFO';
    public const SEVERITY_WARNING = 'WARNING';
    public const SEVERITY_BLOCK = 'BLOCK';

    /**
     * @return array{
     *     allowed: bool,
     *     severity: string,
     *     message_code: string,
     *     message: string,
     *     affected: array<int, array{entity: string, count: int}>,
     *     suggested_action: ?string,
     *     requires_confirmation: bool
     * }
     */
    public function checkDeleteImpact(string $entityType, int $entityId, int $schoolId): array
    {
        return match ($entityType) {
            'school_stage' => $this->checkStageDeleteImpact($entityId, $schoolId),
            'school_classroom' => $this->checkClassroomDeleteImpact($entityId, $schoolId),
            'school_student' => $this->checkStudentDeleteImpact($entityId, $schoolId),
            'school_leave_type' => $this->checkLeaveTypeDisableImpact($entityId, $schoolId),
            'school_holiday' => $this->checkHolidayDisableImpact($entityId, $schoolId),
            default => $this->info(
                'INTEGRITY_SAFE_TO_DELETE',
                'لا توجد تأثيرات معروفة تمنع تنفيذ عملية الحذف على هذا السجل.'
            ),
        };
    }

    /**
     * @param array<string, mixed> $patch
     * @return array{
     *     allowed: bool,
     *     severity: string,
     *     message_code: string,
     *     message: string,
     *     affected: array<int, array{entity: string, count: int}>,
     *     suggested_action: ?string,
     *     requires_confirmation: bool
     * }
     */
    public function checkUpdateImpact(string $entityType, int $entityId, array $patch, int $schoolId): array
    {
        return match ($entityType) {
            'school_leave_type' => $this->checkLeaveTypeUpdateImpact($entityId, $patch, $schoolId),
            'school_holiday' => $this->checkHolidayUpdateImpact($entityId, $patch, $schoolId),
            default => $this->info(
                'INTEGRITY_SAFE_TO_UPDATE',
                'لا توجد تأثيرات معروفة تمنع تنفيذ عملية التعديل على هذا السجل.'
            ),
        };
    }

    /**
     * @return array{
     *     allowed: bool,
     *     severity: string,
     *     message_code: string,
     *     message: string,
     *     affected: array<int, array{entity: string, count: int}>,
     *     suggested_action: ?string,
     *     requires_confirmation: bool
     * }
     */
    private function checkStageDeleteImpact(int $stageId, int $schoolId): array
    {
        $stage = SchoolStage::query()->whereKey($stageId)->first();
        if (!$stage) {
            abort(404);
        }

        if ((int) $stage->school_id !== $schoolId) {
            abort(403, 'لا يمكنك الوصول إلى هذه المرحلة الدراسية.');
        }

        $classroomIds = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->where('school_stage_id', $stageId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $classroomsCount = count($classroomIds);
        $studentsCount = $classroomsCount > 0
            ? SchoolStudent::query()
                ->where('school_id', $schoolId)
                ->whereIn('school_classroom_id', $classroomIds)
                ->count()
            : 0;
        $attendanceCount = $classroomsCount > 0
            ? SchoolStudentAttendance::query()
                ->where('school_id', $schoolId)
                ->whereIn('school_classroom_id', $classroomIds)
                ->count()
            : 0;

        $affected = $this->buildAffected([
            'school_classrooms' => $classroomsCount,
            'school_students' => $studentsCount,
            'school_student_attendances' => $attendanceCount,
        ]);

        if (count($affected) > 0) {
            return $this->block(
                'INTEGRITY_STAGE_DELETE_BLOCKED_HAS_DEPENDENCIES',
                "لا يمكن حذف المرحلة الدراسية لأنها مرتبطة ببيانات أخرى. عدد الفصول المرتبطة: {$classroomsCount}.",
                $affected,
                'DISABLE_INSTEAD'
            );
        }

        return $this->info(
            'INTEGRITY_SAFE_TO_DELETE',
            'يمكن حذف المرحلة الدراسية بدون التأثير على بيانات تشغيلية مرتبطة.'
        );
    }

    /**
     * @return array{
     *     allowed: bool,
     *     severity: string,
     *     message_code: string,
     *     message: string,
     *     affected: array<int, array{entity: string, count: int}>,
     *     suggested_action: ?string,
     *     requires_confirmation: bool
     * }
     */
    private function checkClassroomDeleteImpact(int $classroomId, int $schoolId): array
    {
        $classroom = SchoolClassroom::query()->whereKey($classroomId)->first();
        if (!$classroom) {
            abort(404);
        }

        if ((int) $classroom->school_id !== $schoolId) {
            abort(403, 'لا يمكنك الوصول إلى هذا الفصل.');
        }

        $studentsCount = SchoolStudent::query()
            ->where('school_id', $schoolId)
            ->where('school_classroom_id', $classroomId)
            ->count();

        $attendanceCount = SchoolStudentAttendance::query()
            ->where('school_id', $schoolId)
            ->where('school_classroom_id', $classroomId)
            ->count();

        $scheduleCount = SchoolClassSchedule::query()
            ->where('school_id', $schoolId)
            ->where('school_classroom_id', $classroomId)
            ->count();

        $affected = $this->buildAffected([
            'school_students' => $studentsCount,
            'school_student_attendances' => $attendanceCount,
            'school_class_schedules' => $scheduleCount,
        ]);

        if (count($affected) > 0) {
            return $this->block(
                'INTEGRITY_CLASSROOM_DELETE_BLOCKED_HAS_DEPENDENCIES',
                'لا يمكن حذف الفصل لأنه مرتبط بطلاب أو سجلات حضور أو جداول دراسية.',
                $affected,
                'DISABLE_INSTEAD'
            );
        }

        return $this->info(
            'INTEGRITY_SAFE_TO_DELETE',
            'يمكن حذف الفصل بدون التأثير على بيانات تشغيلية مرتبطة.'
        );
    }

    /**
     * @return array{
     *     allowed: bool,
     *     severity: string,
     *     message_code: string,
     *     message: string,
     *     affected: array<int, array{entity: string, count: int}>,
     *     suggested_action: ?string,
     *     requires_confirmation: bool
     * }
     */
    private function checkStudentDeleteImpact(int $studentId, int $schoolId): array
    {
        $student = SchoolStudent::query()->whereKey($studentId)->first();
        if (!$student) {
            abort(404);
        }

        if ((int) $student->school_id !== $schoolId) {
            abort(403, 'لا يمكنك الوصول إلى هذا الطالب.');
        }

        $attendanceCount = SchoolStudentAttendance::query()
            ->where('school_id', $schoolId)
            ->where('school_student_id', $studentId)
            ->count();

        $leaveRequestCount = SchoolStudentLeaveRequest::query()
            ->where('school_id', $schoolId)
            ->where('school_student_id', $studentId)
            ->count();

        $affected = $this->buildAffected([
            'school_student_attendances' => $attendanceCount,
            'school_student_leave_requests' => $leaveRequestCount,
        ]);

        if (count($affected) > 0) {
            return $this->block(
                'INTEGRITY_STUDENT_DELETE_BLOCKED_HAS_HISTORY',
                'لا يمكن حذف الطالب لوجود سجل حضور أو طلبات إجازة مرتبطة. استخدم التعطيل بدلًا من الحذف.',
                $affected,
                'DISABLE_INSTEAD'
            );
        }

        return $this->info(
            'INTEGRITY_SAFE_TO_DELETE',
            'يمكن حذف الطالب بدون التأثير على بيانات تشغيلية مرتبطة.'
        );
    }

    /**
     * @return array{
     *     allowed: bool,
     *     severity: string,
     *     message_code: string,
     *     message: string,
     *     affected: array<int, array{entity: string, count: int}>,
     *     suggested_action: ?string,
     *     requires_confirmation: bool
     * }
     */
    private function checkLeaveTypeDisableImpact(int $leaveTypeId, int $schoolId): array
    {
        $leaveType = SchoolLeaveType::query()->whereKey($leaveTypeId)->first();
        if (!$leaveType) {
            abort(404);
        }

        if ((int) $leaveType->school_id !== $schoolId) {
            abort(403, 'لا يمكنك الوصول إلى نوع الإجازة هذا.');
        }

        $requestsCount = SchoolStudentLeaveRequest::query()
            ->where('school_id', $schoolId)
            ->where('school_leave_type_id', $leaveTypeId)
            ->count();

        $pendingCount = SchoolStudentLeaveRequest::query()
            ->where('school_id', $schoolId)
            ->where('school_leave_type_id', $leaveTypeId)
            ->where('status', SchoolStudentLeaveRequest::STATUS_PENDING)
            ->count();

        $affected = $this->buildAffected([
            'school_student_leave_requests' => $requestsCount,
            'pending_school_student_leave_requests' => $pendingCount,
        ]);

        if ($pendingCount > 0) {
            return $this->warning(
                'INTEGRITY_LEAVE_TYPE_DISABLE_WARNING_PENDING_REQUESTS',
                'تعطيل نوع الإجازة سيؤثر على طلبات إجازة معلقة. أكّد العملية للمتابعة.',
                $affected,
                'CONFIRM_DISABLE'
            );
        }

        if ($requestsCount > 0) {
            return $this->info(
                'INTEGRITY_LEAVE_TYPE_DISABLE_HAS_HISTORY',
                'تم رصد طلبات إجازة تاريخية مرتبطة بهذا النوع. التعطيل مسموح ولن يحذف السجل التاريخي.',
                $affected
            );
        }

        return $this->info(
            'INTEGRITY_SAFE_TO_DISABLE',
            'يمكن تعطيل نوع الإجازة بدون تأثير على بيانات تشغيلية حالية.'
        );
    }

    /**
     * @param array<string, mixed> $patch
     * @return array{
     *     allowed: bool,
     *     severity: string,
     *     message_code: string,
     *     message: string,
     *     affected: array<int, array{entity: string, count: int}>,
     *     suggested_action: ?string,
     *     requires_confirmation: bool
     * }
     */
    private function checkLeaveTypeUpdateImpact(int $leaveTypeId, array $patch, int $schoolId): array
    {
        $leaveType = SchoolLeaveType::query()->whereKey($leaveTypeId)->first();
        if (!$leaveType) {
            abort(404);
        }

        if ((int) $leaveType->school_id !== $schoolId) {
            abort(403, 'لا يمكنك الوصول إلى نوع الإجازة هذا.');
        }

        $requestsCount = SchoolStudentLeaveRequest::query()
            ->where('school_id', $schoolId)
            ->where('school_leave_type_id', $leaveTypeId)
            ->count();

        if ($requestsCount <= 0) {
            return $this->info(
                'INTEGRITY_SAFE_TO_UPDATE',
                'يمكن تعديل نوع الإجازة بدون التأثير على سجل تاريخي.'
            );
        }

        $semanticChanges = [];
        if (array_key_exists('code', $patch) && (string) ($patch['code'] ?? '') !== (string) ($leaveType->code ?? '')) {
            $semanticChanges[] = 'code';
        }
        if (array_key_exists('requires_attachment', $patch)
            && (bool) ($patch['requires_attachment'] ?? false) !== (bool) $leaveType->requires_attachment) {
            $semanticChanges[] = 'requires_attachment';
        }
        if (array_key_exists('category', $patch) && (string) ($patch['category'] ?? '') !== (string) ($leaveType->category ?? '')) {
            $semanticChanges[] = 'category';
        }

        $affected = $this->buildAffected([
            'school_student_leave_requests' => $requestsCount,
        ]);

        if (count($semanticChanges) > 0) {
            return $this->block(
                'INTEGRITY_LEAVE_TYPE_UPDATE_BLOCKED_SEMANTIC_CHANGE',
                'لا يمكن تعديل الخصائص المرجعية لنوع إجازة مستخدم في طلبات سابقة. يمكنك تعديل الاسم فقط.',
                $affected,
                'UPDATE_NAME_ONLY'
            );
        }

        return $this->info(
            'INTEGRITY_LEAVE_TYPE_UPDATE_ALLOWED_HISTORY_PRESERVED',
            'تعديل الاسم مسموح ولن يكسر السجل التاريخي لطلبات الإجازات.',
            $affected
        );
    }

    /**
     * @return array{
     *     allowed: bool,
     *     severity: string,
     *     message_code: string,
     *     message: string,
     *     affected: array<int, array{entity: string, count: int}>,
     *     suggested_action: ?string,
     *     requires_confirmation: bool
     * }
     */
    private function checkHolidayDisableImpact(int $holidayId, int $schoolId): array
    {
        $holiday = SchoolHoliday::query()->whereKey($holidayId)->first();
        if (!$holiday) {
            abort(404);
        }

        if ((int) $holiday->school_id !== $schoolId) {
            abort(403, 'لا يمكنك الوصول إلى هذه العطلة.');
        }

        $attendanceCount = SchoolStudentAttendance::query()
            ->where('school_id', $schoolId)
            ->whereDate('attendance_date', '>=', (string) $holiday->start_date?->toDateString())
            ->whereDate('attendance_date', '<=', (string) $holiday->end_date?->toDateString())
            ->count();

        $affected = $this->buildAffected([
            'school_student_attendances' => $attendanceCount,
        ]);

        if ($attendanceCount > 0) {
            return $this->warning(
                'INTEGRITY_HOLIDAY_DISABLE_WARNING_ATTENDANCE_IMPACT',
                'تعطيل العطلة سيؤثر على احتسابات وتقارير حضور موجودة ضمن نفس الفترة. أكّد العملية للمتابعة.',
                $affected,
                'CONFIRM_DISABLE'
            );
        }

        return $this->info(
            'INTEGRITY_SAFE_TO_DISABLE',
            'يمكن تعطيل العطلة بدون تأثير على سجلات حضور مسجلة.'
        );
    }

    /**
     * @param array<string, mixed> $patch
     * @return array{
     *     allowed: bool,
     *     severity: string,
     *     message_code: string,
     *     message: string,
     *     affected: array<int, array{entity: string, count: int}>,
     *     suggested_action: ?string,
     *     requires_confirmation: bool
     * }
     */
    private function checkHolidayUpdateImpact(int $holidayId, array $patch, int $schoolId): array
    {
        $holiday = SchoolHoliday::query()->whereKey($holidayId)->first();
        if (!$holiday) {
            abort(404);
        }

        if ((int) $holiday->school_id !== $schoolId) {
            abort(403, 'لا يمكنك الوصول إلى هذه العطلة.');
        }

        $startDate = $this->resolveHolidayDate((string) ($patch['start_date'] ?? ''), (string) $holiday->start_date?->toDateString());
        $endDate = $this->resolveHolidayEndDate(
            $startDate,
            $patch,
            (string) $holiday->end_date?->toDateString()
        );

        $oldStart = (string) $holiday->start_date?->toDateString();
        $oldEnd = (string) $holiday->end_date?->toDateString();

        $dateRangeChanged = $startDate !== $oldStart || $endDate !== $oldEnd;

        if (!$dateRangeChanged) {
            return $this->info(
                'INTEGRITY_SAFE_TO_UPDATE',
                'لا يوجد تأثير زمني على سجلات الحضور، ويمكن تنفيذ التعديل مباشرة.'
            );
        }

        $rangeStart = min($oldStart, $startDate);
        $rangeEnd = max($oldEnd, $endDate);

        $attendanceCount = SchoolStudentAttendance::query()
            ->where('school_id', $schoolId)
            ->whereDate('attendance_date', '>=', $rangeStart)
            ->whereDate('attendance_date', '<=', $rangeEnd)
            ->count();

        $affected = $this->buildAffected([
            'school_student_attendances' => $attendanceCount,
        ]);

        if ($attendanceCount > 0) {
            return $this->warning(
                'INTEGRITY_HOLIDAY_UPDATE_WARNING_ATTENDANCE_IMPACT',
                "تعديل فترة العطلة سيؤثر على {$attendanceCount} سجل حضور ضمن الفترة من {$rangeStart} إلى {$rangeEnd}.",
                $affected,
                'CONFIRM_UPDATE'
            );
        }

        return $this->info(
            'INTEGRITY_HOLIDAY_UPDATE_NO_ATTENDANCE_IMPACT',
            'يمكن تعديل فترة العطلة، ولا توجد سجلات حضور متأثرة في الفترة المرتبطة.',
            $affected
        );
    }

    /**
     * @param array<string, int> $map
     * @return array<int, array{entity: string, count: int}>
     */
    private function buildAffected(array $map): array
    {
        $affected = [];
        foreach ($map as $entity => $count) {
            if ((int) $count <= 0) {
                continue;
            }

            $affected[] = [
                'entity' => (string) $entity,
                'count' => (int) $count,
            ];
        }

        return $affected;
    }

    /**
     * @param array<int, array{entity: string, count: int}> $affected
     * @return array{
     *     allowed: bool,
     *     severity: string,
     *     message_code: string,
     *     message: string,
     *     affected: array<int, array{entity: string, count: int}>,
     *     suggested_action: ?string,
     *     requires_confirmation: bool
     * }
     */
    private function info(string $messageCode, string $message, array $affected = []): array
    {
        return [
            'allowed' => true,
            'severity' => self::SEVERITY_INFO,
            'message_code' => $messageCode,
            'message' => $message,
            'affected' => $affected,
            'suggested_action' => null,
            'requires_confirmation' => false,
        ];
    }

    /**
     * @param array<int, array{entity: string, count: int}> $affected
     * @return array{
     *     allowed: bool,
     *     severity: string,
     *     message_code: string,
     *     message: string,
     *     affected: array<int, array{entity: string, count: int}>,
     *     suggested_action: ?string,
     *     requires_confirmation: bool
     * }
     */
    private function warning(string $messageCode, string $message, array $affected = [], ?string $suggestedAction = null): array
    {
        return [
            'allowed' => true,
            'severity' => self::SEVERITY_WARNING,
            'message_code' => $messageCode,
            'message' => $message,
            'affected' => $affected,
            'suggested_action' => $suggestedAction,
            'requires_confirmation' => true,
        ];
    }

    /**
     * @param array<int, array{entity: string, count: int}> $affected
     * @return array{
     *     allowed: bool,
     *     severity: string,
     *     message_code: string,
     *     message: string,
     *     affected: array<int, array{entity: string, count: int}>,
     *     suggested_action: ?string,
     *     requires_confirmation: bool
     * }
     */
    private function block(string $messageCode, string $message, array $affected = [], ?string $suggestedAction = null): array
    {
        return [
            'allowed' => false,
            'severity' => self::SEVERITY_BLOCK,
            'message_code' => $messageCode,
            'message' => $message,
            'affected' => $affected,
            'suggested_action' => $suggestedAction,
            'requires_confirmation' => false,
        ];
    }

    /**
     * @param array<string, mixed> $patch
     */
    private function resolveHolidayEndDate(string $startDate, array $patch, string $fallbackEndDate): string
    {
        $endDateInput = trim((string) ($patch['end_date'] ?? ''));
        if ($endDateInput !== '') {
            return Carbon::parse($endDateInput)->toDateString();
        }

        $daysCount = (int) ($patch['days_count'] ?? 0);
        if ($daysCount > 0) {
            return Carbon::parse($startDate)->addDays($daysCount - 1)->toDateString();
        }

        return Carbon::parse($fallbackEndDate)->toDateString();
    }

    private function resolveHolidayDate(string $input, string $fallback): string
    {
        if (trim($input) === '') {
            return Carbon::parse($fallback)->toDateString();
        }

        return Carbon::parse($input)->toDateString();
    }
}

