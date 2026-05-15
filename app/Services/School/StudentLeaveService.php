<?php

namespace App\Services\School;

use App\Models\School;
use App\Models\SchoolDefaultLeaveTypeTemplate;
use App\Models\SchoolStudentAttendance;
use App\Models\SchoolLeaveType;
use App\Models\SchoolStudentLeaveAttachment;
use App\Models\SchoolStudentLeaveRequest;
use App\Services\Integrity\IntegrityImpactService;
use App\Services\Support\AuditLogger;
use App\Services\Support\StatusHistoryService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentLeaveService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly StatusHistoryService $statusHistoryService,
        private readonly IntegrityImpactService $integrityImpactService,
        private readonly SchoolDefaultTemplateScopeRegistry $scopeRegistry,
    ) {
    }

    public function ensureDefaultLeaveTypes(int $schoolId, ?int $actorId = null): void
    {
        $hasAny = SchoolLeaveType::query()
            ->where('school_id', $schoolId)
            ->exists();

        if ($hasAny) {
            SchoolLeaveType::query()
                ->where('school_id', $schoolId)
                ->whereNull('category')
                ->update([
                    'category' => SchoolLeaveType::CATEGORY_STUDENT,
                    'updated_by' => $actorId,
                ]);

            return;
        }

        $school = School::query()
            ->with('directorate:id,country_id,education_type_id')
            ->whereKey($schoolId)
            ->first();

        $countryId = $school?->directorate?->country_id ? (int) $school->directorate->country_id : null;
        $educationTypeId = $school?->directorate?->education_type_id ? (int) $school->directorate->education_type_id : null;
        $directorateId = $school?->directorate_id ? (int) $school->directorate_id : null;

        $platformTemplateQuery = SchoolDefaultLeaveTypeTemplate::query()->where('is_active', true);

        if (
            $countryId !== null
            && $educationTypeId !== null
            && $this->scopeRegistry->appliesToDirectorate($directorateId, $countryId, $educationTypeId)
        ) {
            $scopedTemplateExists = (clone $platformTemplateQuery)
                ->where('country_id', $countryId)
                ->where('education_type_id', $educationTypeId)
                ->exists();

            if ($scopedTemplateExists) {
                $platformTemplateQuery
                    ->where('country_id', $countryId)
                    ->where('education_type_id', $educationTypeId);
            } else {
                $platformTemplateQuery
                    ->whereNull('country_id')
                    ->whereNull('education_type_id');
            }
        } else {
            $platformTemplateQuery
                ->whereNull('country_id')
                ->whereNull('education_type_id');
        }

        $hasPlatformTemplates = $platformTemplateQuery->exists();

        if ($hasPlatformTemplates) {
            return;
        }

        $now = now();
        SchoolLeaveType::query()->insert([
            [
                'school_id' => $schoolId,
                'code' => 'ANNUAL',
                'name' => 'Annual Leave',
                'category' => SchoolLeaveType::CATEGORY_STUDENT,
                'requires_attachment' => false,
                'is_active' => true,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'school_id' => $schoolId,
                'code' => 'MEDICAL',
                'name' => 'Medical Leave',
                'category' => SchoolLeaveType::CATEGORY_STUDENT,
                'requires_attachment' => true,
                'is_active' => true,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createLeaveType(int $schoolId, int $actorId, array $payload, ?Request $request = null): SchoolLeaveType
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $code = $this->normalizeLeaveTypeCode($payload['code'] ?? null);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Leave type name is required.',
            ]);
        }

        $this->ensureLeaveTypeNameUnique($schoolId, $name);
        $this->ensureLeaveTypeCodeUnique($schoolId, $code);

        $leaveType = SchoolLeaveType::query()->create([
            'school_id' => $schoolId,
            'code' => $code,
            'name' => $name,
            'category' => SchoolLeaveType::CATEGORY_STUDENT,
            'requires_attachment' => (bool) ($payload['requires_attachment'] ?? false),
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        $this->auditLogger->log(
            'student_leave_type.created',
            'school_leave_type',
            (int) $leaveType->id,
            [
                'school_id' => $schoolId,
                'code' => $leaveType->code,
                'name' => $leaveType->name,
                'requires_attachment' => (bool) $leaveType->requires_attachment,
                'is_active' => (bool) $leaveType->is_active,
            ],
            $request,
            $actorId
        );

        return $leaveType->fresh();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateLeaveType(
        SchoolLeaveType $leaveType,
        int $schoolId,
        int $actorId,
        array $payload,
        ?Request $request = null
    ): SchoolLeaveType {
        $this->ensureLeaveTypeBelongsToSchool($leaveType, $schoolId);

        $impact = $this->integrityImpactService->checkUpdateImpact(
            'school_leave_type',
            (int) $leaveType->id,
            $payload,
            $schoolId
        );
        if (!($impact['allowed'] ?? false)) {
            throw ValidationException::withMessages([
                'impact' => (string) ($impact['message'] ?? 'لا يمكن تنفيذ عملية التعديل على نوع الإجازة.'),
            ]);
        }

        $name = trim((string) ($payload['name'] ?? $leaveType->name));
        $code = $this->normalizeLeaveTypeCode($payload['code'] ?? $leaveType->code);
        $requiresAttachment = array_key_exists('requires_attachment', $payload)
            ? (bool) $payload['requires_attachment']
            : (bool) $leaveType->requires_attachment;
        $isActive = array_key_exists('is_active', $payload)
            ? (bool) $payload['is_active']
            : (bool) $leaveType->is_active;

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Leave type name is required.',
            ]);
        }

        $this->ensureLeaveTypeNameUnique($schoolId, $name, (int) $leaveType->id);
        $this->ensureLeaveTypeCodeUnique($schoolId, $code, (int) $leaveType->id);

        $leaveType->update([
            'code' => $code,
            'name' => $name,
            'category' => SchoolLeaveType::CATEGORY_STUDENT,
            'requires_attachment' => $requiresAttachment,
            'is_active' => $isActive,
            'updated_by' => $actorId,
        ]);

        $this->auditLogger->log(
            'student_leave_type.updated',
            'school_leave_type',
            (int) $leaveType->id,
            [
                'school_id' => $schoolId,
                'code' => $leaveType->code,
                'name' => $leaveType->name,
                'requires_attachment' => (bool) $leaveType->requires_attachment,
                'is_active' => (bool) $leaveType->is_active,
                'impact' => $this->auditImpact($impact),
            ],
            $request,
            $actorId
        );

        return $leaveType->fresh();
    }

    public function disableLeaveType(
        SchoolLeaveType $leaveType,
        int $schoolId,
        int $actorId,
        bool $confirmedImpact = false,
        ?Request $request = null
    ): SchoolLeaveType {
        $this->ensureLeaveTypeBelongsToSchool($leaveType, $schoolId);

        $impact = $this->integrityImpactService->checkDeleteImpact(
            'school_leave_type',
            (int) $leaveType->id,
            $schoolId
        );
        if (($impact['requires_confirmation'] ?? false) && !$confirmedImpact) {
            throw ValidationException::withMessages([
                'confirm_impact' => (string) ($impact['message'] ?? 'تأكيد العملية مطلوب بسبب وجود بيانات مرتبطة.'),
            ]);
        }

        if (!(bool) $leaveType->is_active) {
            return $leaveType;
        }

        $leaveType->update([
            'is_active' => false,
            'updated_by' => $actorId,
        ]);

        $this->auditLogger->log(
            'student_leave_type.disabled',
            'school_leave_type',
            (int) $leaveType->id,
            [
                'school_id' => $schoolId,
                'code' => $leaveType->code,
                'name' => $leaveType->name,
                'impact' => $this->auditImpact($impact),
            ],
            $request,
            $actorId
        );

        return $leaveType->fresh();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(int $schoolId, int $actorId, array $payload, ?Request $request = null): SchoolStudentLeaveRequest
    {
        return DB::transaction(function () use ($schoolId, $actorId, $payload, $request): SchoolStudentLeaveRequest {
            $status = strtoupper((string) ($payload['status'] ?? SchoolStudentLeaveRequest::STATUS_PENDING));
            if ($status !== SchoolStudentLeaveRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Leave request must start as pending.',
                ]);
            }

            $leave = SchoolStudentLeaveRequest::query()->create([
                'school_id' => $schoolId,
                'school_student_id' => (int) $payload['school_student_id'],
                'school_leave_type_id' => (int) $payload['school_leave_type_id'],
                'source' => strtoupper((string) $payload['source']),
                'status' => SchoolStudentLeaveRequest::STATUS_PENDING,
                'start_date' => Carbon::parse((string) $payload['start_date'])->toDateString(),
                'end_date' => Carbon::parse((string) $payload['end_date'])->toDateString(),
                'reason' => $this->cleanText($payload['reason'] ?? null),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->statusHistoryService->record(
                'school_student_leave_request',
                (int) $leave->id,
                null,
                SchoolStudentLeaveRequest::STATUS_PENDING,
                $actorId,
                [
                    'source' => $leave->source,
                    'start_date' => $leave->start_date?->toDateString(),
                    'end_date' => $leave->end_date?->toDateString(),
                ]
            );

            $this->auditLogger->log(
                'student_leave.created',
                'school_student_leave_request',
                (int) $leave->id,
                [
                    'school_id' => $schoolId,
                    'school_student_id' => (int) $leave->school_student_id,
                    'source' => $leave->source,
                    'status' => $leave->status,
                    'start_date' => $leave->start_date?->toDateString(),
                    'end_date' => $leave->end_date?->toDateString(),
                ],
                $request,
                $actorId
            );

            return $leave->fresh([
                'leaveType:id,school_id,name,requires_attachment,is_active',
                'student:id,school_id,school_classroom_id,full_name,student_code',
                'attachments:id,school_id,school_student_leave_request_id,file_name,file_path,mime_type,file_size,uploaded_at',
            ]);
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(
        SchoolStudentLeaveRequest $leave,
        int $schoolId,
        int $actorId,
        array $payload,
        ?Request $request = null
    ): SchoolStudentLeaveRequest {
        $this->ensureLeaveBelongsToSchool($leave, $schoolId);
        $this->ensurePendingForMutation($leave, 'Only pending leave requests can be updated.');

        return DB::transaction(function () use ($leave, $actorId, $payload, $request): SchoolStudentLeaveRequest {
            $leave->update([
                'school_student_id' => (int) $payload['school_student_id'],
                'school_leave_type_id' => (int) $payload['school_leave_type_id'],
                'source' => strtoupper((string) $payload['source']),
                'start_date' => Carbon::parse((string) $payload['start_date'])->toDateString(),
                'end_date' => Carbon::parse((string) $payload['end_date'])->toDateString(),
                'reason' => $this->cleanText($payload['reason'] ?? null),
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->log(
                'student_leave.updated',
                'school_student_leave_request',
                (int) $leave->id,
                [
                    'school_id' => (int) $leave->school_id,
                    'school_student_id' => (int) $leave->school_student_id,
                    'source' => $leave->source,
                    'start_date' => $leave->start_date?->toDateString(),
                    'end_date' => $leave->end_date?->toDateString(),
                ],
                $request,
                $actorId
            );

            return $leave->fresh([
                'leaveType:id,school_id,name,requires_attachment,is_active',
                'student:id,school_id,school_classroom_id,full_name,student_code',
                'attachments:id,school_id,school_student_leave_request_id,file_name,file_path,mime_type,file_size,uploaded_at',
            ]);
        });
    }

    public function approve(
        SchoolStudentLeaveRequest $leave,
        int $schoolId,
        int $actorId,
        ?Request $request = null
    ): SchoolStudentLeaveRequest {
        $this->ensureLeaveBelongsToSchool($leave, $schoolId);
        $this->ensurePendingForMutation($leave, 'Only pending leave requests can be approved.');
        $this->ensureNoApprovedOverlap(
            $schoolId,
            (int) $leave->school_student_id,
            (string) $leave->start_date?->toDateString(),
            (string) $leave->end_date?->toDateString(),
            (int) $leave->id
        );
        $this->ensureAttachmentPolicyForApproval($leave);

        return DB::transaction(function () use ($leave, $actorId, $request): SchoolStudentLeaveRequest {
            $fromStatus = (string) $leave->status;

            $leave->update([
                'status' => SchoolStudentLeaveRequest::STATUS_APPROVED,
                'approved_by' => $actorId,
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'cancelled_by' => null,
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'updated_by' => $actorId,
            ]);

            $convertedCount = 0;
            if ($leave->source === SchoolStudentLeaveRequest::SOURCE_RETROACTIVE) {
                $convertedCount = $this->convertRetroactiveAbsencesToLeave($leave, $actorId, $request);
            }

            $this->statusHistoryService->record(
                'school_student_leave_request',
                (int) $leave->id,
                $fromStatus,
                SchoolStudentLeaveRequest::STATUS_APPROVED,
                $actorId,
                [
                    'source' => $leave->source,
                    'retroactive_attendance_converted' => $convertedCount,
                ]
            );

            $this->auditLogger->log(
                'student_leave.approved',
                'school_student_leave_request',
                (int) $leave->id,
                [
                    'school_id' => (int) $leave->school_id,
                    'school_student_id' => (int) $leave->school_student_id,
                    'source' => $leave->source,
                    'retroactive_attendance_converted' => $convertedCount,
                ],
                $request,
                $actorId
            );

            return $leave->fresh([
                'leaveType:id,school_id,name,requires_attachment,is_active',
                'student:id,school_id,school_classroom_id,full_name,student_code',
                'attachments:id,school_id,school_student_leave_request_id,file_name,file_path,mime_type,file_size,uploaded_at',
            ]);
        });
    }

    public function reject(
        SchoolStudentLeaveRequest $leave,
        int $schoolId,
        int $actorId,
        string $reason,
        ?Request $request = null
    ): SchoolStudentLeaveRequest {
        $this->ensureLeaveBelongsToSchool($leave, $schoolId);
        $this->ensurePendingForMutation($leave, 'Only pending leave requests can be rejected.');

        return DB::transaction(function () use ($leave, $actorId, $reason, $request): SchoolStudentLeaveRequest {
            $fromStatus = (string) $leave->status;

            $leave->update([
                'status' => SchoolStudentLeaveRequest::STATUS_REJECTED,
                'rejected_by' => $actorId,
                'rejected_at' => now(),
                'rejection_reason' => trim($reason),
                'updated_by' => $actorId,
            ]);

            $this->statusHistoryService->record(
                'school_student_leave_request',
                (int) $leave->id,
                $fromStatus,
                SchoolStudentLeaveRequest::STATUS_REJECTED,
                $actorId,
                [
                    'reason' => trim($reason),
                ]
            );

            $this->auditLogger->log(
                'student_leave.rejected',
                'school_student_leave_request',
                (int) $leave->id,
                [
                    'school_id' => (int) $leave->school_id,
                    'school_student_id' => (int) $leave->school_student_id,
                    'reason' => trim($reason),
                ],
                $request,
                $actorId
            );

            return $leave->fresh([
                'leaveType:id,school_id,name,requires_attachment,is_active',
                'student:id,school_id,school_classroom_id,full_name,student_code',
                'attachments:id,school_id,school_student_leave_request_id,file_name,file_path,mime_type,file_size,uploaded_at',
            ]);
        });
    }

    public function cancel(
        SchoolStudentLeaveRequest $leave,
        int $schoolId,
        int $actorId,
        ?string $reason = null,
        ?Request $request = null
    ): SchoolStudentLeaveRequest {
        $this->ensureLeaveBelongsToSchool($leave, $schoolId);

        if (!in_array($leave->status, [
            SchoolStudentLeaveRequest::STATUS_PENDING,
            SchoolStudentLeaveRequest::STATUS_APPROVED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only pending or approved leave requests can be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($leave, $actorId, $reason, $request): SchoolStudentLeaveRequest {
            $fromStatus = (string) $leave->status;

            $leave->update([
                'status' => SchoolStudentLeaveRequest::STATUS_CANCELLED,
                'cancelled_by' => $actorId,
                'cancelled_at' => now(),
                'cancellation_reason' => $this->cleanText($reason),
                'updated_by' => $actorId,
            ]);

            $revertedCount = 0;
            if ($fromStatus === SchoolStudentLeaveRequest::STATUS_APPROVED) {
                $revertedCount = $this->revertLeaveAttendancesToAbsence($leave, $actorId, $request);
            }

            $this->statusHistoryService->record(
                'school_student_leave_request',
                (int) $leave->id,
                $fromStatus,
                SchoolStudentLeaveRequest::STATUS_CANCELLED,
                $actorId,
                [
                    'reason' => $this->cleanText($reason),
                    'reverted_attendance_count' => $revertedCount,
                ]
            );

            $this->auditLogger->log(
                'student_leave.cancelled',
                'school_student_leave_request',
                (int) $leave->id,
                [
                    'school_id' => (int) $leave->school_id,
                    'school_student_id' => (int) $leave->school_student_id,
                    'reason' => $this->cleanText($reason),
                    'reverted_attendance_count' => $revertedCount,
                ],
                $request,
                $actorId
            );

            return $leave->fresh([
                'leaveType:id,school_id,name,requires_attachment,is_active',
                'student:id,school_id,school_classroom_id,full_name,student_code',
                'attachments:id,school_id,school_student_leave_request_id,file_name,file_path,mime_type,file_size,uploaded_at',
            ]);
        });
    }

    public function uploadAttachment(
        SchoolStudentLeaveRequest $leave,
        int $schoolId,
        int $actorId,
        UploadedFile $file,
        ?Request $request = null
    ): SchoolStudentLeaveAttachment {
        $this->ensureLeaveBelongsToSchool($leave, $schoolId);

        return DB::transaction(function () use ($leave, $schoolId, $actorId, $file, $request): SchoolStudentLeaveAttachment {
            $basePath = sprintf(
                'schools/%d/student-leaves/%d/attachments',
                $schoolId,
                (int) $leave->id
            );
            $path = $file->store($basePath, 'local');
            $originalFileName = trim(basename((string) $file->getClientOriginalName()));

            $attachment = SchoolStudentLeaveAttachment::query()->create([
                'school_id' => $schoolId,
                'school_student_leave_request_id' => (int) $leave->id,
                'file_name' => $originalFileName !== '' ? $originalFileName : $file->hashName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => $file->getSize(),
                'uploaded_by' => $actorId,
                'uploaded_at' => now(),
            ]);

            $this->auditLogger->log(
                'student_leave.attachment_uploaded',
                'school_student_leave_attachment',
                (int) $attachment->id,
                [
                    'school_id' => $schoolId,
                    'leave_request_id' => (int) $leave->id,
                    'file_name' => $attachment->file_name,
                    'mime_type' => $attachment->mime_type,
                ],
                $request,
                $actorId
            );

            return $attachment;
        });
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function applyLeaveStateToAttendanceRows(int $schoolId, string $attendanceDate, array $rows): array
    {
        if (!config('features.student_leaves.enabled', true) || !config('features.student_leaves.enforce_attendance_leave_state', true)) {
            return $rows;
        }

        $studentIds = collect($rows)
            ->map(fn ($row) => (int) ($row['school_student_id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (count($studentIds) === 0) {
            return $rows;
        }

        $activeLeaves = $this->activeApprovedLeavesForDate($schoolId, $attendanceDate, $studentIds);
        if ($activeLeaves->isEmpty()) {
            return $rows;
        }

        foreach ($rows as $index => $row) {
            $studentId = (int) ($row['school_student_id'] ?? 0);
            $leave = $activeLeaves->get($studentId);

            if (!$leave instanceof SchoolStudentLeaveRequest) {
                continue;
            }

            $status = strtoupper((string) ($row['status'] ?? SchoolStudentAttendance::STATUS_PRESENT));
            if ($status !== SchoolStudentAttendance::STATUS_ABSENT) {
                continue;
            }

            $rows[$index]['status'] = SchoolStudentAttendance::STATUS_LEAVE;
            $rows[$index]['check_in_time'] = null;
            $rows[$index]['check_out_time'] = null;
            $rows[$index]['permission_reason'] = $this->buildLeavePermissionReason($leave);
            $rows[$index]['__leave_request_id'] = (int) $leave->id;
        }

        return $rows;
    }

    /**
     * @param array<int> $studentIds
     * @return Collection<int, SchoolStudentLeaveRequest>
     */
    public function activeApprovedLeavesForDate(int $schoolId, string $date, array $studentIds): Collection
    {
        if (count($studentIds) === 0) {
            return collect();
        }

        return SchoolStudentLeaveRequest::query()
            ->where('school_id', $schoolId)
            ->whereIn('school_student_id', $studentIds)
            ->where('status', SchoolStudentLeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->with(['leaveType:id,school_id,name,requires_attachment,is_active'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->keyBy('school_student_id');
    }

    public function ensureNoApprovedOverlap(
        int $schoolId,
        int $schoolStudentId,
        string $startDate,
        string $endDate,
        ?int $ignoreLeaveRequestId = null
    ): void {
        $query = SchoolStudentLeaveRequest::query()
            ->where('school_id', $schoolId)
            ->where('school_student_id', $schoolStudentId)
            ->where('status', SchoolStudentLeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate);

        if ($ignoreLeaveRequestId !== null) {
            $query->whereKeyNot($ignoreLeaveRequestId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'There is already an approved leave overlapping this period for the selected student.',
            ]);
        }
    }

    private function ensureLeaveBelongsToSchool(SchoolStudentLeaveRequest $leave, int $schoolId): void
    {
        if ((int) $leave->school_id !== $schoolId) {
            abort(403, 'You are not allowed to access this leave request.');
        }
    }

    private function ensureLeaveTypeBelongsToSchool(SchoolLeaveType $leaveType, int $schoolId): void
    {
        if ((int) $leaveType->school_id !== $schoolId) {
            abort(403, 'You are not allowed to access this leave type.');
        }
    }

    private function ensureLeaveTypeNameUnique(int $schoolId, string $name, ?int $ignoreLeaveTypeId = null): void
    {
        $query = SchoolLeaveType::query()
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

        if ($ignoreLeaveTypeId !== null) {
            $query->whereKeyNot($ignoreLeaveTypeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Leave type name must be unique for this school.',
            ]);
        }
    }

    private function ensureLeaveTypeCodeUnique(int $schoolId, ?string $code, ?int $ignoreLeaveTypeId = null): void
    {
        if ($code === null || $code === '') {
            return;
        }

        $query = SchoolLeaveType::query()
            ->where('school_id', $schoolId)
            ->where('code', $code);

        if ($ignoreLeaveTypeId !== null) {
            $query->whereKeyNot($ignoreLeaveTypeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Leave type code must be unique for this school.',
            ]);
        }
    }

    private function ensurePendingForMutation(SchoolStudentLeaveRequest $leave, string $message): void
    {
        if ($leave->status !== SchoolStudentLeaveRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => $message,
            ]);
        }
    }

    private function ensureAttachmentPolicyForApproval(SchoolStudentLeaveRequest $leave): void
    {
        $leave->loadMissing('leaveType:id,school_id,name,requires_attachment,is_active');

        if (!$leave->leaveType || !(bool) $leave->leaveType->requires_attachment) {
            return;
        }

        $hasAttachment = $leave->attachments()->exists();
        if ($hasAttachment) {
            return;
        }

        $graceDays = max(0, (int) config('features.student_leaves.attachment_grace_days', 7));
        if ($leave->source === SchoolStudentLeaveRequest::SOURCE_RETROACTIVE && $graceDays > 0) {
            $threshold = now()->subDays($graceDays)->startOfDay();
            if ($leave->end_date && $leave->end_date->greaterThanOrEqualTo($threshold)) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'attachments' => 'Attachment is required for this leave type before approval.',
        ]);
    }

    private function convertRetroactiveAbsencesToLeave(
        SchoolStudentLeaveRequest $leave,
        int $actorId,
        ?Request $request = null
    ): int {
        $startDate = (string) $leave->start_date?->toDateString();
        $endDate = (string) $leave->end_date?->toDateString();
        if ($startDate === '' || $endDate === '') {
            return 0;
        }

        $attendances = SchoolStudentAttendance::query()
            ->where('school_id', (int) $leave->school_id)
            ->where('school_student_id', (int) $leave->school_student_id)
            ->whereDate('attendance_date', '>=', $startDate)
            ->whereDate('attendance_date', '<=', $endDate)
            ->where('status', SchoolStudentAttendance::STATUS_ABSENT)
            ->get();

        $converted = 0;
        foreach ($attendances as $attendance) {
            $fromStatus = (string) $attendance->status;

            $attendance->update([
                'status' => SchoolStudentAttendance::STATUS_LEAVE,
                'check_in_time' => null,
                'check_out_time' => null,
                'permission_reason' => $this->buildLeavePermissionReason($leave),
                'school_student_leave_request_id' => (int) $leave->id,
                'updated_by' => $actorId,
                'notes' => $this->appendSystemNote($attendance->notes, 'Converted by approved retroactive leave.'),
            ]);

            $this->statusHistoryService->record(
                'school_student_attendance',
                (int) $attendance->id,
                $fromStatus,
                SchoolStudentAttendance::STATUS_LEAVE,
                $actorId,
                [
                    'action' => 'retroactive_leave_approved',
                    'leave_request_id' => (int) $leave->id,
                ]
            );

            $this->auditLogger->log(
                'student_leave.retroactive_attendance_converted',
                'school_student_attendance',
                (int) $attendance->id,
                [
                    'school_id' => (int) $attendance->school_id,
                    'school_student_id' => (int) $attendance->school_student_id,
                    'leave_request_id' => (int) $leave->id,
                    'from_status' => $fromStatus,
                    'to_status' => SchoolStudentAttendance::STATUS_LEAVE,
                ],
                $request,
                $actorId
            );

            $converted++;
        }

        return $converted;
    }

    private function revertLeaveAttendancesToAbsence(
        SchoolStudentLeaveRequest $leave,
        int $actorId,
        ?Request $request = null
    ): int {
        $attendances = SchoolStudentAttendance::query()
            ->where('school_id', (int) $leave->school_id)
            ->where('school_student_id', (int) $leave->school_student_id)
            ->where('school_student_leave_request_id', (int) $leave->id)
            ->where('status', SchoolStudentAttendance::STATUS_LEAVE)
            ->get();

        $reverted = 0;
        foreach ($attendances as $attendance) {
            $attendance->update([
                'status' => SchoolStudentAttendance::STATUS_ABSENT,
                'check_in_time' => null,
                'check_out_time' => null,
                'permission_reason' => null,
                'updated_by' => $actorId,
                'notes' => $this->appendSystemNote($attendance->notes, 'Reverted after leave cancellation.'),
            ]);

            $this->statusHistoryService->record(
                'school_student_attendance',
                (int) $attendance->id,
                SchoolStudentAttendance::STATUS_LEAVE,
                SchoolStudentAttendance::STATUS_ABSENT,
                $actorId,
                [
                    'action' => 'leave_cancelled',
                    'leave_request_id' => (int) $leave->id,
                ]
            );

            $this->auditLogger->log(
                'student_leave.attendance_reverted_after_cancellation',
                'school_student_attendance',
                (int) $attendance->id,
                [
                    'school_id' => (int) $attendance->school_id,
                    'school_student_id' => (int) $attendance->school_student_id,
                    'leave_request_id' => (int) $leave->id,
                    'from_status' => SchoolStudentAttendance::STATUS_LEAVE,
                    'to_status' => SchoolStudentAttendance::STATUS_ABSENT,
                ],
                $request,
                $actorId
            );

            $reverted++;
        }

        return $reverted;
    }

    private function buildLeavePermissionReason(SchoolStudentLeaveRequest $leave): string
    {
        $typeName = trim((string) ($leave->leaveType?->name ?? ''));

        if ($typeName === '') {
            return 'Approved student leave';
        }

        return 'Approved leave: ' . $typeName;
    }

    private function appendSystemNote(?string $existing, string $note): string
    {
        $base = trim((string) ($existing ?? ''));
        if ($base === '') {
            return $note;
        }

        return $base . ' | ' . $note;
    }

    private function cleanText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }

    private function normalizeLeaveTypeCode(mixed $value): ?string
    {
        $code = strtoupper(trim((string) ($value ?? '')));

        return $code !== '' ? $code : null;
    }

    /**
     * @param array<string, mixed> $impact
     * @return array<string, mixed>
     */
    private function auditImpact(array $impact): array
    {
        return [
            'severity' => (string) ($impact['severity'] ?? ''),
            'message_code' => (string) ($impact['message_code'] ?? ''),
            'affected' => $impact['affected'] ?? [],
            'requires_confirmation' => (bool) ($impact['requires_confirmation'] ?? false),
        ];
    }
}
