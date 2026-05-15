<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\Leaves\ApproveStudentLeaveRequest;
use App\Http\Requests\School\Leaves\CancelStudentLeaveRequest;
use App\Http\Requests\School\Leaves\DisableSchoolLeaveTypeRequest;
use App\Http\Requests\School\Leaves\ListStudentLeavesRequest;
use App\Http\Requests\School\Leaves\RejectStudentLeaveRequest;
use App\Http\Requests\School\Leaves\StoreSchoolLeaveTypeRequest;
use App\Http\Requests\School\Leaves\StoreStudentLeaveRequest;
use App\Http\Requests\School\Leaves\UpdateSchoolLeaveTypeRequest;
use App\Http\Requests\School\Leaves\UpdateStudentLeaveRequest;
use App\Http\Requests\School\Leaves\UploadStudentLeaveAttachmentRequest;
use App\Models\SchoolLeaveType;
use App\Models\SchoolStudentLeaveAttachment;
use App\Models\SchoolStudentLeaveRequest;
use App\Services\Integrity\IntegrityImpactService;
use App\Services\School\StudentLeaveService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentLeaveManagementController extends Controller
{
    public function __construct(
        private readonly StudentLeaveService $studentLeaveService,
        private readonly IntegrityImpactService $integrityImpactService,
    ) {
    }

    public function leaveTypes(Request $request): JsonResponse
    {
        if (!($request->user()?->can('manage-student-leaves') || $request->user()?->can('manage-leave-types'))) {
            abort(403, 'You do not have permission to view leave types.');
        }

        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) ($request->user()?->id ?? 0);
        $this->studentLeaveService->ensureDefaultLeaveTypes($schoolId, $actorId > 0 ? $actorId : null);

        $types = SchoolLeaveType::query()
            ->where('school_id', $schoolId)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get([
                'id',
                'school_id',
                'code',
                'name',
                'category',
                'requires_attachment',
                'is_active',
            ]);

        return response()->json([
            'data' => $types->map(fn (SchoolLeaveType $type) => [
                'id' => (int) $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'category' => $type->category,
                'requires_attachment' => (bool) $type->requires_attachment,
                'is_active' => (bool) $type->is_active,
            ])->values()->all(),
        ]);
    }

    public function storeLeaveType(StoreSchoolLeaveTypeRequest $request): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) $request->user()->id;

        $leaveType = $this->studentLeaveService->createLeaveType(
            $schoolId,
            $actorId,
            $request->validated(),
            $request
        );

        return response()->json([
            'data' => [
                'id' => (int) $leaveType->id,
                'code' => $leaveType->code,
                'name' => $leaveType->name,
                'category' => $leaveType->category,
                'requires_attachment' => (bool) $leaveType->requires_attachment,
                'is_active' => (bool) $leaveType->is_active,
            ],
        ], 201);
    }

    public function updateLeaveType(
        UpdateSchoolLeaveTypeRequest $request,
        SchoolLeaveType $schoolLeaveType
    ): JsonResponse {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) $request->user()->id;

        $leaveType = $this->studentLeaveService->updateLeaveType(
            $schoolLeaveType,
            $schoolId,
            $actorId,
            $request->validated(),
            $request
        );

        return response()->json([
            'data' => [
                'id' => (int) $leaveType->id,
                'code' => $leaveType->code,
                'name' => $leaveType->name,
                'category' => $leaveType->category,
                'requires_attachment' => (bool) $leaveType->requires_attachment,
                'is_active' => (bool) $leaveType->is_active,
            ],
        ]);
    }

    public function disableLeaveType(
        DisableSchoolLeaveTypeRequest $request,
        SchoolLeaveType $schoolLeaveType
    ): JsonResponse {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) $request->user()->id;

        $leaveType = $this->studentLeaveService->disableLeaveType(
            $schoolLeaveType,
            $schoolId,
            $actorId,
            $request->boolean('confirm_impact'),
            $request
        );

        return response()->json([
            'data' => [
                'id' => (int) $leaveType->id,
                'code' => $leaveType->code,
                'name' => $leaveType->name,
                'category' => $leaveType->category,
                'requires_attachment' => (bool) $leaveType->requires_attachment,
                'is_active' => (bool) $leaveType->is_active,
            ],
        ]);
    }

    public function leaveTypeDeleteImpact(Request $request, SchoolLeaveType $schoolLeaveType): JsonResponse
    {
        if (!$request->user()?->can('manage-leave-types')) {
            abort(403, 'You do not have permission to view leave type impact.');
        }

        $schoolId = $this->resolveSchoolId($request);

        $impact = $this->integrityImpactService->checkDeleteImpact(
            'school_leave_type',
            (int) $schoolLeaveType->id,
            $schoolId
        );

        return response()->json([
            'data' => $impact,
        ]);
    }

    public function index(ListStudentLeavesRequest $request): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $validated = $request->validated();

        $query = $this->baseLeavesQuery($schoolId);

        if (!empty($validated['school_student_id'])) {
            $query->where('school_student_id', (int) $validated['school_student_id']);
        }

        if (!empty($validated['school_leave_type_id'])) {
            $query->where('school_leave_type_id', (int) $validated['school_leave_type_id']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', (string) $validated['status']);
        }

        if (!empty($validated['source'])) {
            $query->where('source', (string) $validated['source']);
        }

        if (!empty($validated['school_stage_id'])) {
            $stageId = (int) $validated['school_stage_id'];
            $query->whereHas('student.classroom', fn ($classroomQuery) => $classroomQuery->where('school_stage_id', $stageId));
        }

        if (!empty($validated['school_classroom_id'])) {
            $classroomId = (int) $validated['school_classroom_id'];
            $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('school_classroom_id', $classroomId));
        }

        if (!empty($validated['classroom_grade_name'])) {
            $gradeName = trim((string) $validated['classroom_grade_name']);
            $query->whereHas('student.classroom', fn ($classroomQuery) => $classroomQuery->where('grade_name', $gradeName));
        }

        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;
        if ($dateFrom && $dateTo) {
            $query
                ->whereDate('start_date', '<=', (string) $dateTo)
                ->whereDate('end_date', '>=', (string) $dateFrom);
        } elseif ($dateFrom) {
            $query->whereDate('end_date', '>=', (string) $dateFrom);
        } elseif ($dateTo) {
            $query->whereDate('start_date', '<=', (string) $dateTo);
        }

        $perPage = (int) ($validated['per_page'] ?? 0);
        if ($perPage > 0) {
            $paginator = $query->paginate($perPage)->appends($request->query());

            return response()->json([
                'data' => collect($paginator->items())
                    ->map(fn (SchoolStudentLeaveRequest $leave) => $this->serializeLeave($leave))
                    ->values()
                    ->all(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        }

        $leaves = $query->get();

        return response()->json([
            'data' => $leaves->map(fn (SchoolStudentLeaveRequest $leave) => $this->serializeLeave($leave))->values()->all(),
        ]);
    }

    public function store(StoreStudentLeaveRequest $request): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $validated = $request->validated();
        $actorId = (int) $request->user()->id;

        $leave = $this->studentLeaveService->create(
            $schoolId,
            $actorId,
            $validated,
            $request
        );

        return response()->json([
            'data' => $this->serializeLeave($leave),
        ], 201);
    }

    public function update(UpdateStudentLeaveRequest $request, SchoolStudentLeaveRequest $schoolStudentLeaveRequest): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $validated = $request->validated();
        $actorId = (int) $request->user()->id;

        $leave = $this->studentLeaveService->update(
            $schoolStudentLeaveRequest,
            $schoolId,
            $actorId,
            $validated,
            $request
        );

        return response()->json([
            'data' => $this->serializeLeave($leave),
        ]);
    }

    public function approve(ApproveStudentLeaveRequest $request, SchoolStudentLeaveRequest $schoolStudentLeaveRequest): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) $request->user()->id;

        $leave = $this->studentLeaveService->approve(
            $schoolStudentLeaveRequest,
            $schoolId,
            $actorId,
            $request
        );

        return response()->json([
            'data' => $this->serializeLeave($leave),
        ]);
    }

    public function reject(RejectStudentLeaveRequest $request, SchoolStudentLeaveRequest $schoolStudentLeaveRequest): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) $request->user()->id;
        $validated = $request->validated();

        $leave = $this->studentLeaveService->reject(
            $schoolStudentLeaveRequest,
            $schoolId,
            $actorId,
            (string) $validated['reason'],
            $request
        );

        return response()->json([
            'data' => $this->serializeLeave($leave),
        ]);
    }

    public function cancel(CancelStudentLeaveRequest $request, SchoolStudentLeaveRequest $schoolStudentLeaveRequest): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) $request->user()->id;
        $validated = $request->validated();

        $leave = $this->studentLeaveService->cancel(
            $schoolStudentLeaveRequest,
            $schoolId,
            $actorId,
            $validated['reason'] ?? null,
            $request
        );

        return response()->json([
            'data' => $this->serializeLeave($leave),
        ]);
    }

    public function storeAttachment(
        UploadStudentLeaveAttachmentRequest $request,
        SchoolStudentLeaveRequest $schoolStudentLeaveRequest
    ): JsonResponse {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) $request->user()->id;

        $attachment = $this->studentLeaveService->uploadAttachment(
            $schoolStudentLeaveRequest,
            $schoolId,
            $actorId,
            $request->file('file'),
            $request
        );

        return response()->json([
            'data' => [
                'id' => (int) $attachment->id,
                'file_name' => $attachment->file_name,
                'file_path' => $this->attachmentDownloadUrl($schoolStudentLeaveRequest, $attachment),
                'download_url' => $this->attachmentDownloadUrl($schoolStudentLeaveRequest, $attachment),
                'mime_type' => $attachment->mime_type,
                'file_size' => $attachment->file_size,
                'uploaded_at' => optional($attachment->uploaded_at)->toISOString(),
            ],
        ], 201);
    }

    public function downloadAttachment(
        Request $request,
        SchoolStudentLeaveRequest $schoolStudentLeaveRequest,
        SchoolStudentLeaveAttachment $schoolStudentLeaveAttachment
    ): StreamedResponse {
        $schoolId = $this->resolveSchoolId($request);

        if ((int) $schoolStudentLeaveRequest->school_id !== $schoolId) {
            abort(403, 'You are not allowed to access this leave request.');
        }

        if (
            (int) $schoolStudentLeaveAttachment->school_id !== $schoolId
            || (int) $schoolStudentLeaveAttachment->school_student_leave_request_id !== (int) $schoolStudentLeaveRequest->id
        ) {
            abort(404);
        }

        $path = trim((string) $schoolStudentLeaveAttachment->file_path);
        if ($path === '') {
            abort(404);
        }

        $downloadName = trim((string) $schoolStudentLeaveAttachment->file_name);
        if ($downloadName === '') {
            $downloadName = basename($path);
        }

        $headers = [
            'Content-Type' => $schoolStudentLeaveAttachment->mime_type ?: 'application/octet-stream',
        ];

        $localDisk = Storage::disk('local');
        if ($localDisk->exists($path)) {
            return $localDisk->download($path, $downloadName, $headers);
        }

        // Backward compatibility: attachments uploaded before hardening were stored on the public disk.
        $legacyPublicDisk = Storage::disk('public');
        if ($legacyPublicDisk->exists($path)) {
            return $legacyPublicDisk->download($path, $downloadName, $headers);
        }

        abort(404, 'Attachment file is missing.');
    }

    private function resolveSchoolId(Request $request): int
    {
        $schoolId = (int) $request->attributes->get('school_context_id', (int) ($request->user()?->school_id ?? 0));
        if ($schoolId <= 0) {
            throw ValidationException::withMessages([
                'school' => 'School context is required.',
            ]);
        }

        return $schoolId;
    }

    private function baseLeavesQuery(int $schoolId): Builder
    {
        return SchoolStudentLeaveRequest::query()
            ->where('school_id', $schoolId)
            ->with([
                'student:id,school_id,school_classroom_id,full_name,student_code',
                'student.classroom:id,school_id,school_stage_id,grade_name,name',
                'student.classroom.stage:id,school_id,name',
                'leaveType:id,school_id,name,requires_attachment,is_active',
                'creator:id,name',
                'updater:id,name',
                'approver:id,name',
                'rejector:id,name',
                'canceller:id,name',
                'attachments' => fn ($query) => $query->orderByDesc('id'),
            ])
            ->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLeave(SchoolStudentLeaveRequest $leave): array
    {
        return [
            'id' => (int) $leave->id,
            'school_id' => (int) $leave->school_id,
            'school_student_id' => (int) $leave->school_student_id,
            'school_leave_type_id' => (int) $leave->school_leave_type_id,
            'source' => $leave->source,
            'status' => $leave->status,
            'start_date' => $leave->start_date?->toDateString(),
            'end_date' => $leave->end_date?->toDateString(),
            'reason' => $leave->reason,
            'approved_by' => $leave->approved_by,
            'approved_at' => optional($leave->approved_at)->toISOString(),
            'rejected_by' => $leave->rejected_by,
            'rejected_at' => optional($leave->rejected_at)->toISOString(),
            'rejection_reason' => $leave->rejection_reason,
            'cancelled_by' => $leave->cancelled_by,
            'cancelled_at' => optional($leave->cancelled_at)->toISOString(),
            'cancellation_reason' => $leave->cancellation_reason,
            'created_by' => $leave->created_by,
            'updated_by' => $leave->updated_by,
            'created_at' => optional($leave->created_at)->toISOString(),
            'updated_at' => optional($leave->updated_at)->toISOString(),
            'student' => $leave->student ? [
                'id' => (int) $leave->student->id,
                'full_name' => $leave->student->full_name,
                'student_code' => $leave->student->student_code,
                'classroom' => $leave->student->classroom ? [
                    'id' => (int) $leave->student->classroom->id,
                    'grade_name' => $leave->student->classroom->grade_name,
                    'name' => $leave->student->classroom->name,
                    'stage' => $leave->student->classroom->stage ? [
                        'id' => (int) $leave->student->classroom->stage->id,
                        'name' => $leave->student->classroom->stage->name,
                    ] : null,
                ] : null,
            ] : null,
            'leave_type' => $leave->leaveType ? [
                'id' => (int) $leave->leaveType->id,
                'name' => $leave->leaveType->name,
                'requires_attachment' => (bool) $leave->leaveType->requires_attachment,
                'is_active' => (bool) $leave->leaveType->is_active,
            ] : null,
            'attachments' => $leave->attachments->map(fn (SchoolStudentLeaveAttachment $attachment) => [
                'id' => (int) $attachment->id,
                'file_name' => $attachment->file_name,
                'file_path' => $this->attachmentDownloadUrl($leave, $attachment),
                'download_url' => $this->attachmentDownloadUrl($leave, $attachment),
                'mime_type' => $attachment->mime_type,
                'file_size' => $attachment->file_size,
                'uploaded_by' => $attachment->uploaded_by,
                'uploaded_at' => optional($attachment->uploaded_at)->toISOString(),
                'created_at' => optional($attachment->created_at)->toISOString(),
            ])->values()->all(),
            'attachments_count' => (int) $leave->attachments->count(),
        ];
    }

    private function attachmentDownloadUrl(
        SchoolStudentLeaveRequest $leave,
        SchoolStudentLeaveAttachment $attachment
    ): string {
        return route('api.school.leaves.attachments.download', [
            'schoolStudentLeaveRequest' => (int) $leave->id,
            'schoolStudentLeaveAttachment' => (int) $attachment->id,
        ], false);
    }
}
