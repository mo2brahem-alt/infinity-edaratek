<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolAttendanceAttachment;
use App\Models\SchoolClassroom;
use App\Models\SchoolHoliday;
use App\Models\SchoolLeaveType;
use App\Models\SchoolStage;
use App\Models\SchoolStudent;
use App\Models\SchoolStudentAttendance;
use App\Models\SchoolStudentLeaveRequest;
use App\Services\Exports\SchoolExportDocumentService;
use App\Services\School\DailyAttendanceInitializerService;
use App\Services\School\SchoolCalendarService;
use App\Services\School\StudentLeaveService;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentAttendanceController extends Controller
{
    public function __construct(
        private readonly DailyAttendanceInitializerService $dailyAttendanceInitializerService,
        private readonly SchoolCalendarService $schoolCalendarService,
        private readonly StudentLeaveService $studentLeaveService,
        private readonly AuditLogger $auditLogger,
        private readonly SchoolExportDocumentService $exportDocuments,
    ) {
    }

    public function index(Request $request): Response
    {
        $schoolId = $this->resolveSchoolId($request);
        $user = $request->user();
        $attendanceDate = $this->normalizeAttendanceDate((string) $request->query('attendance_date', ''));
        $reportFilters = $this->resolveReportFilters($request, $schoolId);
        $dayState = $this->schoolCalendarService->resolveDayTypeForDate($schoolId, $attendanceDate);
        $reportRange = $this->resolveReportRange(
            (string) $request->query('report_date_from', ''),
            (string) $request->query('report_date_to', ''),
            $attendanceDate
        );

        $school = School::query()
            ->whereKey($schoolId)
            ->first(['id', 'name', 'school_id']);

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

        $selectedStage = $this->resolveSelectedStage($stages, (int) $request->query('stage_id', 0));
        $selectedClassroomGradeName = $this->normalizeGradeNameInput((string) $request->query('classroom_grade_name', ''));
        $selectedClassroom = $this->resolveSelectedClassroom(
            $stages,
            $selectedStage,
            (int) $request->query('classroom_id', 0),
            $selectedClassroomGradeName
        );

        if ($selectedClassroom && (!$selectedStage || (int) $selectedClassroom->school_stage_id !== (int) $selectedStage->id)) {
            $selectedStage = $stages->firstWhere('id', (int) $selectedClassroom->school_stage_id) ?? $selectedStage;
        }

        $dailyAttachments = $selectedClassroom
            ? $this->attendanceAttachmentsForDay($schoolId, (int) $selectedClassroom->id, $attendanceDate)
            : collect();

        $students = collect();
        if ($selectedClassroom) {
            $students = SchoolStudent::query()
                ->where('school_id', $schoolId)
                ->where('school_classroom_id', (int) $selectedClassroom->id)
                ->where('is_active', true)
                ->orderBy('full_name')
                ->get(['id', 'school_id', 'school_classroom_id', 'full_name', 'student_code', 'national_id', 'is_active']);
        }

        // Build the range report before daily auto-initialization so generated placeholder
        // rows for the selected day do not inflate report counters in the same request.
        $attendanceReport = $this->attendanceReport(
            $schoolId,
            $reportRange['from'],
            $reportRange['to'],
            $students,
            $reportFilters
        );

        if ($selectedClassroom && config('features.attendance.daily_initialization_enabled', true)) {
            $this->dailyAttendanceInitializerService->ensureDailyRecordsForClassroom(
                $schoolId,
                (int) $selectedClassroom->id,
                $attendanceDate,
                (int) ($user?->id ?? 0)
            );
        }

        $attendanceByStudent = $this->attendanceByStudent($schoolId, $attendanceDate, $students);
        $activeLeavesByStudent = $dayState['day_type'] === 'SCHOOL_DAY'
            ? $this->activeLeavesByStudent($schoolId, $attendanceDate, $students)
            : collect();

        $studentRows = $students
            ->map(function (SchoolStudent $student) use ($attendanceByStudent, $activeLeavesByStudent): array {
                $attendance = $attendanceByStudent->get((int) $student->id);
                $activeLeave = $activeLeavesByStudent->get((int) $student->id);

                return [
                    'id' => $student->id,
                    'full_name' => $student->full_name,
                    'student_code' => $student->student_code,
                    'national_id' => $student->national_id,
                    'attendance' => $attendance ? [
                        'status' => $attendance->status,
                        'check_in_time' => $this->formatStoredTime($attendance->check_in_time),
                        'check_out_time' => $this->formatStoredTime($attendance->check_out_time),
                        'permission_reason' => $attendance->permission_reason,
                        'notes' => $attendance->notes,
                        'school_student_leave_request_id' => $attendance->school_student_leave_request_id,
                    ] : null,
                    'leave_state' => $activeLeave instanceof SchoolStudentLeaveRequest ? [
                        'leave_request_id' => (int) $activeLeave->id,
                        'source' => $activeLeave->source,
                        'start_date' => $activeLeave->start_date?->toDateString(),
                        'end_date' => $activeLeave->end_date?->toDateString(),
                        'reason' => $activeLeave->reason,
                        'leave_type' => $activeLeave->leaveType ? [
                            'id' => (int) $activeLeave->leaveType->id,
                            'name' => $activeLeave->leaveType->name,
                            'requires_attachment' => (bool) $activeLeave->leaveType->requires_attachment,
                        ] : null,
                    ] : null,
                ];
            })
            ->values();

        $reportLeaveTypeOptions = SchoolLeaveType::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (SchoolLeaveType $leaveType): array => [
                'id' => (int) $leaveType->id,
                'name' => $leaveType->name,
            ])
            ->values()
            ->all();

        return Inertia::render('School/StudentAttendance', [
            'school' => $school,
            'stages' => $stages,
            'students' => $studentRows,
            'dailyAttachments' => $dailyAttachments
                ->map(fn (SchoolAttendanceAttachment $attachment): array => [
                    'id' => (int) $attachment->id,
                    'file_name' => $attachment->file_name,
                    'download_url' => $this->attendanceAttachmentDownloadUrl($attachment),
                    'mime_type' => $attachment->mime_type,
                    'file_size' => (int) ($attachment->file_size ?? 0),
                    'uploaded_at' => optional($attachment->uploaded_at)->toISOString(),
                    'uploaded_by' => (string) ($attachment->uploader?->name ?? ''),
                ])
                ->values()
                ->all(),
            'selectedDate' => $attendanceDate,
            'reportDateFrom' => $reportRange['from'],
            'reportDateTo' => $reportRange['to'],
            'attendanceReport' => $attendanceReport,
            'dayState' => $dayState,
            'selectedStageId' => $selectedStage?->id,
            'selectedClassroomId' => $selectedClassroom?->id,
            'selectedClassroomGradeName' => $selectedClassroom?->grade_name ?? $selectedClassroomGradeName,
            'reportFilters' => $reportFilters,
            'reportDayTypeOptions' => [
                ['value' => 'SCHOOL_DAY', 'label' => 'أيام الدراسة'],
                ['value' => 'HOLIDAY', 'label' => 'العطل الرسمية'],
                ['value' => 'WEEKLY_OFF', 'label' => 'الإجازة الأسبوعية'],
            ],
            'reportLeaveTypeOptions' => $reportLeaveTypeOptions,
            'statusOptions' => [
                ['value' => SchoolStudentAttendance::STATUS_PRESENT, 'label' => 'حضور'],
                ['value' => SchoolStudentAttendance::STATUS_ABSENT, 'label' => 'غياب'],
                ['value' => SchoolStudentAttendance::STATUS_EXCUSED, 'label' => 'إذن'],
                ['value' => SchoolStudentAttendance::STATUS_LEAVE, 'label' => 'إجازة'],
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
            ],
        ]);
    }

    public function upsertRecords(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);

        $validated = $request->validate([
            'attendance_date' => ['required', 'date'],
            'school_stage_id' => [
                'nullable',
                Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_classroom_id' => [
                'required',
                Rule::exists('school_classrooms', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'classroom_grade_name' => ['nullable', 'string', 'max:100'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.school_student_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('school_students', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('school_classroom_id', (int) $request->input('school_classroom_id'))
                    ->where('is_active', true)),
            ],
            'records.*.status' => ['required', Rule::in(SchoolStudentAttendance::allowedStatuses())],
            'records.*.check_in_time' => ['nullable', 'date_format:H:i'],
            'records.*.check_out_time' => ['nullable', 'date_format:H:i'],
            'records.*.permission_reason' => ['nullable', 'string', 'max:500'],
            'records.*.notes' => ['nullable', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => $this->attendanceAttachmentValidationRules(),
        ], [
            'records.required' => 'يرجى إدخال بيانات الطلاب للحفظ.',
            'records.*.school_student_id.distinct' => 'لا يمكن تكرار نفس الطالب في نفس عملية الحفظ.',
            'attachments.max' => 'الحد الأقصى لعدد مرفقات الحضور هو 10 ملفات.',
            'attachments.*.file' => 'الملف المرفق غير صالح.',
            'attachments.*.max' => 'حجم كل مرفق يجب ألا يتجاوز 10 ميجابايت.',
            'attachments.*.mimetypes' => 'صيغة المرفق غير مدعومة. استخدم PDF أو صورة.',
        ]);

        $stageId = (int) ($validated['school_stage_id'] ?? 0);
        $classroomId = (int) $validated['school_classroom_id'];
        $classroomGradeName = $this->normalizeGradeNameInput((string) ($validated['classroom_grade_name'] ?? ''));
        $attendanceDate = Carbon::parse($validated['attendance_date'])->toDateString();
        if ($this->schoolCalendarService->hasAcademicDateReferences($schoolId)) {
            $this->schoolCalendarService->ensureDateWithinOperationalAcademicTerm($schoolId, $attendanceDate);
        }
        $dayState = $this->schoolCalendarService->resolveDayTypeForDate($schoolId, $attendanceDate);

        $classroom = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->whereKey($classroomId)
            ->firstOrFail();

        if ($stageId > 0 && (int) $classroom->school_stage_id !== $stageId) {
            throw ValidationException::withMessages([
                'school_classroom_id' => 'الفصل المحدد لا يتبع المرحلة المختارة.',
            ]);
        }

        if ($classroomGradeName !== null && (string) $classroom->grade_name !== $classroomGradeName) {
            throw ValidationException::withMessages([
                'classroom_grade_name' => 'الصف المحدد لا يتوافق مع الفصل المختار.',
            ]);
        }

        $activeStudentIds = SchoolStudent::query()
            ->where('school_id', $schoolId)
            ->where('school_classroom_id', $classroomId)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values();

        $recordStudentIds = collect($validated['records'])
            ->pluck('school_student_id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values();

        if (
            $activeStudentIds->count() !== $recordStudentIds->count()
            || $activeStudentIds->diff($recordStudentIds)->isNotEmpty()
            || $recordStudentIds->diff($activeStudentIds)->isNotEmpty()
        ) {
            throw ValidationException::withMessages([
                'records' => __('messages.daily_attendance_full_records_required'),
            ]);
        }

        $records = $this->applyNonSchoolDayStateToAttendanceRows(
            $validated['records'],
            $dayState
        );

        $records = $this->studentLeaveService->applyLeaveStateToAttendanceRows(
            $schoolId,
            $attendanceDate,
            $records
        );

        $this->validateAttendanceRows($records);

        $userId = (int) $request->user()->id;
        $uploadedFiles = collect($request->file('attachments', []))
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->values();

        DB::transaction(function () use (
            $records,
            $attendanceDate,
            $schoolId,
            $classroomId,
            $userId,
            $uploadedFiles,
            $request
        ): void {
            $existingByStudent = SchoolStudentAttendance::query()
                ->where('school_id', $schoolId)
                ->whereDate('attendance_date', $attendanceDate)
                ->whereIn('school_student_id', collect($records)->pluck('school_student_id')->all())
                ->get()
                ->keyBy(fn (SchoolStudentAttendance $attendance): int => (int) $attendance->school_student_id);

            foreach ($records as $row) {
                $status = (string) $row['status'];
                $checkIn = in_array($status, [SchoolStudentAttendance::STATUS_ABSENT, SchoolStudentAttendance::STATUS_LEAVE], true)
                    ? null
                    : ($row['check_in_time'] ?? null);
                $checkOut = in_array($status, [SchoolStudentAttendance::STATUS_ABSENT, SchoolStudentAttendance::STATUS_LEAVE], true)
                    ? null
                    : ($row['check_out_time'] ?? null);
                $permissionReason = in_array($status, [SchoolStudentAttendance::STATUS_EXCUSED, SchoolStudentAttendance::STATUS_LEAVE], true)
                    ? $this->emptyToNull($row['permission_reason'] ?? null)
                    : null;
                $notes = $this->emptyToNull($row['notes'] ?? null);
                $leaveRequestId = $status === SchoolStudentAttendance::STATUS_LEAVE
                    ? (int) ($row['__leave_request_id'] ?? 0)
                    : 0;

                $studentId = (int) $row['school_student_id'];
                $existing = $existingByStudent->get($studentId);

                $payload = [
                    'school_id' => $schoolId,
                    'school_student_id' => $studentId,
                    'school_classroom_id' => $classroomId,
                    'attendance_date' => $attendanceDate,
                    'status' => $status,
                    'check_in_time' => $checkIn ?: null,
                    'check_out_time' => $checkOut ?: null,
                    'permission_reason' => $permissionReason,
                    'notes' => $notes,
                    'school_student_leave_request_id' => $leaveRequestId > 0 ? $leaveRequestId : null,
                    'updated_by' => $userId,
                ];

                if ($existing) {
                    $existing->update($payload);
                    continue;
                }

                SchoolStudentAttendance::query()->create($payload + ['recorded_by' => $userId]);
            }

            foreach ($uploadedFiles as $uploadedFile) {
                $this->storeAttendanceAttachment(
                    $schoolId,
                    $classroomId,
                    $attendanceDate,
                    $uploadedFile,
                    $userId,
                    $request
                );
            }
        });

        return redirect()
            ->route('school.student_attendance.index', $this->buildAttendanceIndexRouteParams(
                $request,
                $attendanceDate,
                $classroomId,
                $stageId,
                $classroomGradeName
            ))
            ->with('success', __('messages.daily_attendance_saved'));
    }

    public function storeAttachments(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);

        $validated = $request->validate([
            'attendance_date' => ['required', 'date'],
            'school_stage_id' => [
                'nullable',
                Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_classroom_id' => [
                'required',
                Rule::exists('school_classrooms', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'classroom_grade_name' => ['nullable', 'string', 'max:100'],
            'attachments' => ['required', 'array', 'min:1', 'max:10'],
            'attachments.*' => $this->attendanceAttachmentValidationRules(),
        ], [
            'attachments.required' => 'يرجى اختيار مرفق واحد على الأقل.',
            'attachments.array' => 'صيغة المرفقات غير صالحة.',
            'attachments.min' => 'يرجى اختيار مرفق واحد على الأقل.',
            'attachments.max' => 'الحد الأقصى لعدد مرفقات الحضور هو 10 ملفات.',
            'attachments.*.file' => 'الملف المرفق غير صالح.',
            'attachments.*.max' => 'حجم كل مرفق يجب ألا يتجاوز 10 ميجابايت.',
            'attachments.*.mimetypes' => 'صيغة المرفق غير مدعومة. استخدم PDF أو صورة.',
        ]);

        $stageId = (int) ($validated['school_stage_id'] ?? 0);
        $classroomId = (int) $validated['school_classroom_id'];
        $classroomGradeName = $this->normalizeGradeNameInput((string) ($validated['classroom_grade_name'] ?? ''));
        $attendanceDate = Carbon::parse($validated['attendance_date'])->toDateString();

        if ($this->schoolCalendarService->hasAcademicDateReferences($schoolId)) {
            $this->schoolCalendarService->ensureDateWithinOperationalAcademicTerm($schoolId, $attendanceDate);
        }

        $classroom = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->whereKey($classroomId)
            ->firstOrFail();

        if ($stageId > 0 && (int) $classroom->school_stage_id !== $stageId) {
            throw ValidationException::withMessages([
                'school_classroom_id' => 'الفصل المحدد لا يتبع المرحلة المختارة.',
            ]);
        }

        if ($classroomGradeName !== null && (string) $classroom->grade_name !== $classroomGradeName) {
            throw ValidationException::withMessages([
                'classroom_grade_name' => 'الصف المحدد لا يتوافق مع الفصل المختار.',
            ]);
        }

        $uploadedFiles = collect($request->file('attachments', []))
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->values();

        if ($uploadedFiles->isEmpty()) {
            throw ValidationException::withMessages([
                'attachments' => 'يرجى اختيار مرفق واحد على الأقل.',
            ]);
        }

        $userId = (int) $request->user()->id;

        DB::transaction(function () use (
            $schoolId,
            $classroomId,
            $attendanceDate,
            $uploadedFiles,
            $userId,
            $request
        ): void {
            foreach ($uploadedFiles as $uploadedFile) {
                $this->storeAttendanceAttachment(
                    $schoolId,
                    $classroomId,
                    $attendanceDate,
                    $uploadedFile,
                    $userId,
                    $request
                );
            }
        });

        return redirect()
            ->route('school.student_attendance.index', $this->buildAttendanceIndexRouteParams(
                $request,
                $attendanceDate,
                $classroomId,
                $stageId,
                $classroomGradeName
            ))
            ->with('success', 'تم رفع مرفقات الحضور بنجاح.');
    }

    public function downloadAttachment(Request $request, SchoolAttendanceAttachment $schoolAttendanceAttachment): StreamedResponse
    {
        $schoolId = $this->resolveSchoolId($request);

        if ((int) $schoolAttendanceAttachment->school_id !== $schoolId) {
            abort(404);
        }

        $path = trim((string) $schoolAttendanceAttachment->file_path);
        if ($path === '') {
            abort(404);
        }

        $downloadName = trim((string) $schoolAttendanceAttachment->file_name);
        if ($downloadName === '') {
            $downloadName = basename($path);
        }

        $headers = [
            'Content-Type' => $schoolAttendanceAttachment->mime_type ?: 'application/octet-stream',
        ];

        $localDisk = Storage::disk('local');
        if ($localDisk->exists($path)) {
            return $localDisk->download($path, $downloadName, $headers);
        }

        $legacyPublicDisk = Storage::disk('public');
        if ($legacyPublicDisk->exists($path)) {
            return $legacyPublicDisk->download($path, $downloadName, $headers);
        }

        abort(404, 'Attachment file is missing.');
    }

    public function destroyAttachment(Request $request, SchoolAttendanceAttachment $schoolAttendanceAttachment): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);

        if ((int) $schoolAttendanceAttachment->school_id !== $schoolId) {
            abort(404);
        }

        $attachmentId = (int) $schoolAttendanceAttachment->id;
        $classroomId = (int) $schoolAttendanceAttachment->school_classroom_id;
        $attendanceDateInput = (string) $request->input('attendance_date', (string) $request->query('attendance_date', ''));
        $attendanceDate = $schoolAttendanceAttachment->attendance_date?->toDateString()
            ?? $this->normalizeAttendanceDate($attendanceDateInput);
        $filePath = trim((string) $schoolAttendanceAttachment->file_path);
        $fileName = (string) ($schoolAttendanceAttachment->file_name ?? '');

        if ($filePath !== '') {
            Storage::disk('local')->delete($filePath);
            Storage::disk('public')->delete($filePath);
        }

        $schoolAttendanceAttachment->delete();

        $this->auditLogger->log(
            'student_attendance.attachment_deleted',
            'school_attendance_attachment',
            $attachmentId,
            [
                'school_id' => $schoolId,
                'school_classroom_id' => $classroomId,
                'attendance_date' => $attendanceDate,
                'file_name' => $fileName,
            ],
            $request,
            (int) ($request->user()?->id ?? 0)
        );

        $stageId = (int) $request->input('stage_id', (int) $request->query('stage_id', 0));
        $classroomGradeName = $this->normalizeGradeNameInput(
            (string) $request->input('classroom_grade_name', (string) $request->query('classroom_grade_name', ''))
        );

        return redirect()->route('school.student_attendance.index', $this->buildAttendanceIndexRouteParams(
            $request,
            $attendanceDate,
            $classroomId,
            $stageId,
            $classroomGradeName
        ));
    }

    public function exportReportCsv(Request $request): StreamedResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $validated = $request->validate([
            'attendance_date' => ['nullable', 'date'],
            'school_stage_id' => [
                'nullable',
                Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_classroom_id' => [
                'required',
                Rule::exists('school_classrooms', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'classroom_grade_name' => ['nullable', 'string', 'max:100'],
            'report_date_from' => ['nullable', 'date'],
            'report_date_to' => ['nullable', 'date'],
            'report_day_type' => ['nullable', Rule::in(['SCHOOL_DAY', 'HOLIDAY', 'WEEKLY_OFF'])],
            'report_holiday_name' => ['nullable', 'string', 'max:255'],
            'report_leave_type_id' => [
                'nullable',
                Rule::exists('school_leave_types', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
        ]);

        $stageId = (int) ($validated['school_stage_id'] ?? 0);
        $classroomId = (int) $validated['school_classroom_id'];
        $classroomGradeName = $this->normalizeGradeNameInput((string) ($validated['classroom_grade_name'] ?? ''));
        $attendanceDate = $this->normalizeAttendanceDate((string) ($validated['attendance_date'] ?? ''));
        $reportFilters = $this->resolveReportFilters($request, $schoolId);

        $classroom = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->whereKey($classroomId)
            ->firstOrFail();
        $school = $this->exportDocuments->schoolForExport($schoolId);

        if ($stageId > 0 && (int) $classroom->school_stage_id !== $stageId) {
            throw ValidationException::withMessages([
                'school_classroom_id' => 'Selected classroom does not belong to selected stage.',
            ]);
        }

        if ($classroomGradeName !== null && (string) $classroom->grade_name !== $classroomGradeName) {
            throw ValidationException::withMessages([
                'classroom_grade_name' => 'Selected grade does not match selected classroom.',
            ]);
        }

        $reportRange = $this->resolveReportRange(
            (string) ($validated['report_date_from'] ?? ''),
            (string) ($validated['report_date_to'] ?? ''),
            $attendanceDate
        );

        $students = SchoolStudent::query()
            ->where('school_id', $schoolId)
            ->where('school_classroom_id', $classroomId)
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'student_code']);

        ['totals' => $totals, 'per_student' => $perStudent] = $this->attendanceReport(
            $schoolId,
            $reportRange['from'],
            $reportRange['to'],
            $students,
            $reportFilters
        );

        $fileName = $this->exportDocuments->safeFileName(
            'attendance-report',
            $school,
            'csv',
            [$classroom->name, $reportRange['from'], $reportRange['to']]
        );

        return response()->streamDownload(function () use ($request, $school, $classroom, $students, $perStudent, $totals, $reportRange, $reportFilters): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }

            $this->exportDocuments->writeCsvPreamble($stream, $school, 'تقرير حضور الطلاب', $request->user(), [
                'الفصل' => (string) $classroom->name,
                'الفترة من' => $reportRange['from'],
                'الفترة إلى' => $reportRange['to'],
            ]);

            $this->exportDocuments->putCsvRow($stream, [
                'اسم الطالب',
                'كود الطالب',
                'أيام الإجازة',
                'غياب بدون عذر',
                'أيام الحضور',
                'أيام الإذن',
                'الأيام المسجلة',
            ]);

            $rowsByStudent = collect($perStudent)
                ->keyBy(fn (array $row): int => (int) ($row['school_student_id'] ?? 0));

            foreach ($students as $student) {
                $studentId = (int) $student->id;
                $row = $rowsByStudent->get($studentId, [
                    'leave_days' => 0,
                    'unexcused_absence_days' => 0,
                    'present_days' => 0,
                    'excused_days' => 0,
                    'recorded_days' => 0,
                ]);

                $this->exportDocuments->putCsvRow($stream, [
                    (string) $student->full_name,
                    (string) ($student->student_code ?? ''),
                    (int) ($row['leave_days'] ?? 0),
                    (int) ($row['unexcused_absence_days'] ?? 0),
                    (int) ($row['present_days'] ?? 0),
                    (int) ($row['excused_days'] ?? 0),
                    (int) ($row['recorded_days'] ?? 0),
                ]);
            }

            $this->exportDocuments->putCsvRow($stream, []);
            $this->exportDocuments->putCsvRow($stream, ['إجمالي أيام الإجازة', (int) $totals['leave_days']]);
            $this->exportDocuments->putCsvRow($stream, ['إجمالي الغياب بدون عذر', (int) $totals['unexcused_absence_days']]);
            $this->exportDocuments->putCsvRow($stream, ['إجمالي أيام الحضور', (int) $totals['present_days']]);
            $this->exportDocuments->putCsvRow($stream, ['إجمالي أيام الإذن', (int) $totals['excused_days']]);
            $this->exportDocuments->putCsvRow($stream, ['إجمالي الأيام المسجلة', (int) $totals['recorded_days']]);
            $this->exportDocuments->putCsvRow($stream, ['نوع اليوم', (string) ($reportFilters['day_type'] ?? '')]);
            $this->exportDocuments->putCsvRow($stream, ['اسم العطلة', (string) ($reportFilters['holiday_name'] ?? '')]);
            $this->exportDocuments->putCsvRow($stream, ['نوع الإجازة', (string) ($reportFilters['leave_type_id'] ?? '')]);
            $this->exportDocuments->writeCsvFooter($stream, $school, $request->user());

            fclose($stream);
        }, $fileName, $this->exportDocuments->csvHeaders());
    }

    private function resolveSchoolId(Request $request): int
    {
        $schoolId = (int) $request->attributes->get('school_context_id', (int) ($request->user()?->school_id ?? 0));
        if ($schoolId <= 0) {
            abort(403, 'School context is required.');
        }

        return $schoolId;
    }

    private function normalizeAttendanceDate(string $value): string
    {
        try {
            return $value !== '' ? Carbon::parse($value)->toDateString() : now()->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    private function normalizeGradeNameInput(string $value): ?string
    {
        $normalized = trim($value);
        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array{from:string,to:string}
     */
    private function resolveReportRange(string $from, string $to, string $attendanceDate): array
    {
        $anchor = Carbon::parse($attendanceDate);
        $defaultFrom = $anchor->copy()->startOfMonth()->toDateString();
        $defaultTo = $anchor->copy()->endOfMonth()->toDateString();

        $normalizedFrom = $defaultFrom;
        $normalizedTo = $defaultTo;

        try {
            if (trim($from) !== '') {
                $normalizedFrom = Carbon::parse($from)->toDateString();
            }
        } catch (\Throwable) {
            $normalizedFrom = $defaultFrom;
        }

        try {
            if (trim($to) !== '') {
                $normalizedTo = Carbon::parse($to)->toDateString();
            }
        } catch (\Throwable) {
            $normalizedTo = $defaultTo;
        }

        if ($normalizedFrom > $normalizedTo) {
            [$normalizedFrom, $normalizedTo] = [$normalizedTo, $normalizedFrom];
        }

        return ['from' => $normalizedFrom, 'to' => $normalizedTo];
    }

    private function resolveSelectedStage(Collection $stages, int $stageId): ?SchoolStage
    {
        $selected = $stages->firstWhere('id', $stageId);
        if ($selected instanceof SchoolStage) {
            return $selected;
        }

        $withClassrooms = $stages->first(fn ($stage) => count($stage->classrooms ?? []) > 0);
        return $withClassrooms instanceof SchoolStage ? $withClassrooms : $stages->first();
    }

    private function resolveSelectedClassroom(
        Collection $stages,
        ?SchoolStage $selectedStage,
        int $classroomId,
        ?string $gradeName = null
    ): ?SchoolClassroom {
        $classroomsForStage = $this->filterClassroomsByGrade(collect($selectedStage?->classrooms ?? []), $gradeName);
        $selected = $classroomsForStage->firstWhere('id', $classroomId);
        if ($selected instanceof SchoolClassroom) {
            return $selected;
        }

        if ($classroomId > 0) {
            $selectedAcrossStages = $this->filterClassroomsByGrade(
                $stages->flatMap(fn ($stage) => $stage->classrooms ?? []),
                $gradeName
            )->firstWhere('id', $classroomId);

            if ($selectedAcrossStages instanceof SchoolClassroom) {
                return $selectedAcrossStages;
            }
        }

        $firstInStage = $classroomsForStage->first();
        if ($firstInStage instanceof SchoolClassroom) {
            return $firstInStage;
        }

        $firstInAnyStage = $this->filterClassroomsByGrade(
            $stages->flatMap(fn ($stage) => $stage->classrooms ?? []),
            $gradeName
        )->first();

        return $firstInAnyStage instanceof SchoolClassroom ? $firstInAnyStage : null;
    }

    private function filterClassroomsByGrade(Collection $classrooms, ?string $gradeName): Collection
    {
        if ($gradeName === null) {
            return $classrooms->values();
        }

        return $classrooms
            ->filter(fn ($classroom) => (string) ($classroom->grade_name ?? '') === $gradeName)
            ->values();
    }

    private function attendanceByStudent(int $schoolId, string $attendanceDate, Collection $students): Collection
    {
        if ($students->isEmpty()) {
            return collect();
        }

        return SchoolStudentAttendance::query()
            ->where('school_id', $schoolId)
            ->whereDate('attendance_date', $attendanceDate)
            ->whereIn('school_student_id', $students->pluck('id')->all())
            ->get(['id', 'school_student_id', 'status', 'check_in_time', 'check_out_time', 'permission_reason', 'notes', 'school_student_leave_request_id'])
            ->keyBy('school_student_id');
    }

    private function activeLeavesByStudent(int $schoolId, string $attendanceDate, Collection $students): Collection
    {
        if (!config('features.student_leaves.enabled', true) || $students->isEmpty()) {
            return collect();
        }

        return $this->studentLeaveService->activeApprovedLeavesForDate(
            $schoolId,
            $attendanceDate,
            $students->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
        );
    }

    private function attendanceAttachmentsForDay(int $schoolId, int $classroomId, string $attendanceDate): Collection
    {
        return SchoolAttendanceAttachment::query()
            ->where('school_id', $schoolId)
            ->where('school_classroom_id', $classroomId)
            ->whereDate('attendance_date', $attendanceDate)
            ->with(['uploader:id,name'])
            ->orderByDesc('id')
            ->get(['id', 'school_id', 'school_classroom_id', 'attendance_date', 'file_name', 'file_path', 'mime_type', 'file_size', 'uploaded_by', 'uploaded_at']);
    }

    private function attendanceAttachmentDownloadUrl(SchoolAttendanceAttachment $attachment): string
    {
        return route('school.student_attendance.attachments.download', [
            'schoolAttendanceAttachment' => (int) $attachment->id,
        ], false);
    }

    private function attendanceReport(int $schoolId, string $dateFrom, string $dateTo, Collection $students, array $filters = []): array
    {
        ['totals' => $totals, 'per_student' => $perStudent, 'day_type_summary' => $dayTypeSummary] = $this->aggregateAttendanceCounters(
            $schoolId,
            $dateFrom,
            $dateTo,
            $students,
            $filters
        );

        return [
            'range' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'totals' => $totals,
            'per_student' => array_values($perStudent),
            'day_type_summary' => $dayTypeSummary,
        ];
    }

    /**
     * @return array{
     *     totals: array<string, int>,
     *     per_student: array<int, array<string, mixed>>,
     *     day_type_summary: array<string, int>
     * }
     */
    private function aggregateAttendanceCounters(
        int $schoolId,
        string $dateFrom,
        string $dateTo,
        Collection $students,
        array $filters = []
    ): array {
        $totals = [
            'recorded_days' => 0,
            'present_days' => 0,
            'excused_days' => 0,
            'leave_days' => 0,
            'absent_days' => 0,
            'unexcused_absence_days' => 0,
        ];

        if ($students->isEmpty()) {
            return [
                'totals' => $totals,
                'per_student' => [],
                'day_type_summary' => [
                    'school_days' => 0,
                    'holiday_days' => 0,
                    'weekly_off_days' => 0,
                ],
            ];
        }

        $studentIds = $students->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $dayTypeMap = $this->buildDayTypeMap($schoolId, $dateFrom, $dateTo);
        $normalizedDayTypeFilter = strtoupper((string) ($filters['day_type'] ?? ''));
        $dayTypeFilter = in_array($normalizedDayTypeFilter, ['SCHOOL_DAY', 'HOLIDAY', 'WEEKLY_OFF'], true)
            ? $normalizedDayTypeFilter
            : null;
        $holidayNameFilter = mb_strtolower(trim((string) ($filters['holiday_name'] ?? '')));
        $leaveTypeIdFilter = (int) ($filters['leave_type_id'] ?? 0);
        if ($leaveTypeIdFilter <= 0) {
            $leaveTypeIdFilter = null;
        }

        $perStudent = $students
            ->mapWithKeys(fn (SchoolStudent $student): array => [
                (int) $student->id => [
                    'school_student_id' => (int) $student->id,
                    'full_name' => $student->full_name,
                    'student_code' => $student->student_code,
                    'recorded_days' => 0,
                    'present_days' => 0,
                    'excused_days' => 0,
                    'leave_days' => 0,
                    'absent_days' => 0,
                    'unexcused_absence_days' => 0,
                ],
            ])
            ->all();

        $attendanceRows = SchoolStudentAttendance::query()
            ->selectRaw('school_student_id, status, attendance_date, school_student_leave_request_id, COUNT(*) as aggregate_count')
            ->where('school_id', $schoolId)
            ->whereIn('school_student_id', $studentIds)
            ->whereDate('attendance_date', '>=', $dateFrom)
            ->whereDate('attendance_date', '<=', $dateTo)
            ->groupBy('school_student_id', 'status', 'attendance_date', 'school_student_leave_request_id')
            ->get();

        $leaveRequestTypeMap = [];
        if ($leaveTypeIdFilter !== null) {
            $leaveRequestTypeMap = SchoolStudentLeaveRequest::query()
                ->where('school_id', $schoolId)
                ->whereIn('id', $attendanceRows
                    ->pluck('school_student_leave_request_id')
                    ->map(fn ($value) => (int) $value)
                    ->filter(fn (int $value): bool => $value > 0)
                    ->unique()
                    ->values()
                    ->all())
                ->pluck('school_leave_type_id', 'id')
                ->mapWithKeys(fn ($typeId, $requestId): array => [(int) $requestId => (int) $typeId])
                ->all();
        }

        foreach ($attendanceRows as $attendanceRow) {
            $studentId = (int) $attendanceRow->school_student_id;
            if (!isset($perStudent[$studentId])) {
                continue;
            }

            $attendanceDay = Carbon::parse((string) $attendanceRow->attendance_date)->toDateString();
            $dayType = $dayTypeMap[$attendanceDay]['day_type'] ?? 'SCHOOL_DAY';
            $holidayName = mb_strtolower((string) ($dayTypeMap[$attendanceDay]['holiday_name'] ?? ''));
            if ($dayTypeFilter === null && $dayType !== 'SCHOOL_DAY') {
                continue;
            }

            $status = strtoupper((string) $attendanceRow->status);
            $count = (int) ($attendanceRow->aggregate_count ?? 0);
            if ($count <= 0) {
                continue;
            }

            if ($dayTypeFilter !== null && $dayType !== $dayTypeFilter) {
                continue;
            }

            if ($holidayNameFilter !== '') {
                if ($dayType !== 'HOLIDAY') {
                    continue;
                }

                if (!str_contains($holidayName, $holidayNameFilter)) {
                    continue;
                }
            }

            if ($leaveTypeIdFilter !== null) {
                if ($status !== SchoolStudentAttendance::STATUS_LEAVE) {
                    continue;
                }

                $leaveRequestId = (int) ($attendanceRow->school_student_leave_request_id ?? 0);
                $leaveTypeId = $leaveRequestTypeMap[$leaveRequestId] ?? null;
                if ($leaveTypeId !== $leaveTypeIdFilter) {
                    continue;
                }
            }

            $totals['recorded_days'] += $count;
            $perStudent[$studentId]['recorded_days'] += $count;

            if ($status === SchoolStudentAttendance::STATUS_PRESENT) {
                $totals['present_days'] += $count;
                $perStudent[$studentId]['present_days'] += $count;
                continue;
            }

            if ($status === SchoolStudentAttendance::STATUS_EXCUSED) {
                $totals['excused_days'] += $count;
                $perStudent[$studentId]['excused_days'] += $count;
                continue;
            }

            if ($status === SchoolStudentAttendance::STATUS_LEAVE) {
                $totals['leave_days'] += $count;
                $perStudent[$studentId]['leave_days'] += $count;
                continue;
            }

            if ($status === SchoolStudentAttendance::STATUS_ABSENT) {
                $totals['absent_days'] += $count;
                $totals['unexcused_absence_days'] += $count;
                $perStudent[$studentId]['absent_days'] += $count;
                $perStudent[$studentId]['unexcused_absence_days'] += $count;
            }
        }

        return [
            'totals' => $totals,
            'per_student' => $perStudent,
            'day_type_summary' => $this->summarizeDayTypes($dayTypeMap, [
                'day_type' => $dayTypeFilter,
                'holiday_name' => $holidayNameFilter,
            ]),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array{day_type:string,holiday_name:?string} $dayState
     * @return array<int, array<string, mixed>>
     */
    private function applyNonSchoolDayStateToAttendanceRows(array $rows, array $dayState): array
    {
        $dayType = strtoupper((string) ($dayState['day_type'] ?? 'SCHOOL_DAY'));
        if ($dayType === 'SCHOOL_DAY') {
            return $rows;
        }

        $reason = $dayType === 'HOLIDAY'
            ? ('Holiday' . (!empty($dayState['holiday_name']) ? ': ' . $dayState['holiday_name'] : ''))
            : 'Weekly off day';

        foreach ($rows as $index => $row) {
            if (strtoupper((string) ($row['status'] ?? '')) !== SchoolStudentAttendance::STATUS_ABSENT) {
                continue;
            }

            $rows[$index]['status'] = SchoolStudentAttendance::STATUS_EXCUSED;
            $rows[$index]['permission_reason'] = $reason;
            $rows[$index]['check_in_time'] = null;
            $rows[$index]['check_out_time'] = null;
            $rows[$index]['__leave_request_id'] = null;
        }

        return $rows;
    }

    /**
     * @return array<string, array{day_type:string,holiday_name:?string}>
     */
    private function buildDayTypeMap(int $schoolId, string $dateFrom, string $dateTo): array
    {
        $map = [];
        $settings = $this->schoolCalendarService->getOrCreateSettings($schoolId);
        $weeklyOffDays = $this->schoolCalendarService->normalizeWeeklyOffDays($settings->weekly_off_days);

        $cursor = Carbon::parse($dateFrom)->startOfDay();
        $end = Carbon::parse($dateTo)->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $dateKey = $cursor->toDateString();
            $map[$dateKey] = [
                'day_type' => in_array($cursor->dayOfWeek, $weeklyOffDays, true) ? 'WEEKLY_OFF' : 'SCHOOL_DAY',
                'holiday_name' => null,
            ];

            $cursor->addDay();
        }

        $holidays = SchoolHoliday::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $dateTo)
            ->whereDate('end_date', '>=', $dateFrom)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get(['id', 'name', 'start_date', 'end_date']);

        foreach ($holidays as $holiday) {
            if (!$holiday->start_date || !$holiday->end_date) {
                continue;
            }

            $holidayStart = $holiday->start_date->copy()->startOfDay();
            $holidayEnd = $holiday->end_date->copy()->startOfDay();
            if ($holidayEnd->lessThan($holidayStart)) {
                continue;
            }

            $from = $holidayStart->greaterThan(Carbon::parse($dateFrom)) ? $holidayStart : Carbon::parse($dateFrom);
            $to = $holidayEnd->lessThan(Carbon::parse($dateTo)) ? $holidayEnd : Carbon::parse($dateTo);

            if ($to->lessThan($from)) {
                continue;
            }

            $holidayCursor = $from->copy();
            while ($holidayCursor->lessThanOrEqualTo($to)) {
                $dayKey = $holidayCursor->toDateString();
                $map[$dayKey] = [
                    'day_type' => 'HOLIDAY',
                    'holiday_name' => $holiday->name,
                ];
                $holidayCursor->addDay();
            }
        }

        return $map;
    }

    /**
     * @param array<string, array{day_type:string,holiday_name:?string}> $dayTypeMap
     * @return array<string, int>
     */
    private function summarizeDayTypes(array $dayTypeMap, array $filters = []): array
    {
        $summary = [
            'school_days' => 0,
            'holiday_days' => 0,
            'weekly_off_days' => 0,
        ];

        $dayTypeFilter = strtoupper((string) ($filters['day_type'] ?? ''));
        if (!in_array($dayTypeFilter, ['SCHOOL_DAY', 'HOLIDAY', 'WEEKLY_OFF'], true)) {
            $dayTypeFilter = null;
        }
        $holidayNameFilter = mb_strtolower(trim((string) ($filters['holiday_name'] ?? '')));

        foreach ($dayTypeMap as $state) {
            $dayType = strtoupper((string) ($state['day_type'] ?? 'SCHOOL_DAY'));
            $holidayName = mb_strtolower((string) ($state['holiday_name'] ?? ''));

            if ($dayTypeFilter !== null && $dayType !== $dayTypeFilter) {
                continue;
            }

            if ($holidayNameFilter !== '') {
                if ($dayType !== 'HOLIDAY') {
                    continue;
                }

                if (!str_contains($holidayName, $holidayNameFilter)) {
                    continue;
                }
            }

            if ($dayType === 'HOLIDAY') {
                $summary['holiday_days']++;
                continue;
            }

            if ($dayType === 'WEEKLY_OFF') {
                $summary['weekly_off_days']++;
                continue;
            }

            $summary['school_days']++;
        }

        return $summary;
    }

    /**
     * @return array{day_type:?string,holiday_name:string,leave_type_id:?int}
     */
    private function resolveReportFilters(Request $request, int $schoolId): array
    {
        $dayType = strtoupper(trim((string) $request->query('report_day_type', '')));
        if (!in_array($dayType, ['SCHOOL_DAY', 'HOLIDAY', 'WEEKLY_OFF'], true)) {
            $dayType = '';
        }

        $holidayName = trim((string) $request->query('report_holiday_name', ''));
        if (mb_strlen($holidayName) > 255) {
            $holidayName = mb_substr($holidayName, 0, 255);
        }

        $leaveTypeId = (int) $request->query('report_leave_type_id', 0);
        if ($leaveTypeId > 0) {
            $exists = SchoolLeaveType::query()
                ->where('school_id', $schoolId)
                ->whereKey($leaveTypeId)
                ->exists();

            if (!$exists) {
                $leaveTypeId = 0;
            }
        }

        return [
            'day_type' => $dayType !== '' ? $dayType : null,
            'holiday_name' => $holidayName,
            'leave_type_id' => $leaveTypeId > 0 ? $leaveTypeId : null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function attendanceAttachmentValidationRules(): array
    {
        $rules = ['file', 'max:10240'];

        if ($this->strictAttendanceAttachmentValidationEnabled()) {
            $mimeTypes = $this->allowedAttendanceAttachmentMimeTypes();
            if (count($mimeTypes) > 0) {
                $rules[] = 'mimetypes:' . implode(',', $mimeTypes);
            }
        }

        return $rules;
    }

    private function strictAttendanceAttachmentValidationEnabled(): bool
    {
        return (bool) config('features.uploads.strict_student_attendance_attachment_validation', true)
            || (bool) config('features.uploads.strict_validation_enabled', false);
    }

    /**
     * @return array<int, string>
     */
    private function allowedAttendanceAttachmentMimeTypes(): array
    {
        return collect(config('features.uploads.student_attendance_attachment_mime_types', []))
            ->map(fn ($mime) => trim((string) $mime))
            ->filter()
            ->values()
            ->all();
    }

    private function buildAttendanceIndexRouteParams(
        Request $request,
        string $attendanceDate,
        int $classroomId,
        int $stageId = 0,
        ?string $classroomGradeName = null
    ): array {
        $routeParams = [
            'attendance_date' => $attendanceDate,
            'classroom_id' => $classroomId,
        ];

        if ($stageId > 0) {
            $routeParams['stage_id'] = $stageId;
        }

        if ($classroomGradeName !== null) {
            $routeParams['classroom_grade_name'] = $classroomGradeName;
        }

        $reportDateFrom = trim((string) $request->input('report_date_from', (string) $request->query('report_date_from', '')));
        if ($reportDateFrom !== '') {
            $routeParams['report_date_from'] = $reportDateFrom;
        }

        $reportDateTo = trim((string) $request->input('report_date_to', (string) $request->query('report_date_to', '')));
        if ($reportDateTo !== '') {
            $routeParams['report_date_to'] = $reportDateTo;
        }

        $reportDayType = strtoupper(trim((string) $request->input('report_day_type', (string) $request->query('report_day_type', ''))));
        if (in_array($reportDayType, ['SCHOOL_DAY', 'HOLIDAY', 'WEEKLY_OFF'], true)) {
            $routeParams['report_day_type'] = $reportDayType;
        }

        $reportHolidayName = trim((string) $request->input('report_holiday_name', (string) $request->query('report_holiday_name', '')));
        if ($reportHolidayName !== '') {
            $routeParams['report_holiday_name'] = $reportHolidayName;
        }

        $reportLeaveTypeId = (int) $request->input('report_leave_type_id', (int) $request->query('report_leave_type_id', 0));
        if ($reportLeaveTypeId > 0) {
            $routeParams['report_leave_type_id'] = $reportLeaveTypeId;
        }

        return $routeParams;
    }

    private function storeAttendanceAttachment(
        int $schoolId,
        int $classroomId,
        string $attendanceDate,
        UploadedFile $file,
        int $userId,
        Request $request
    ): SchoolAttendanceAttachment {
        $basePath = sprintf(
            'schools/%d/student-attendance/%d/%s/attachments',
            $schoolId,
            $classroomId,
            $attendanceDate
        );
        $path = $file->store($basePath, 'local');
        $originalFileName = trim(basename((string) $file->getClientOriginalName()));

        $attachment = SchoolAttendanceAttachment::query()->create([
            'school_id' => $schoolId,
            'school_classroom_id' => $classroomId,
            'attendance_date' => $attendanceDate,
            'file_name' => $originalFileName !== '' ? $originalFileName : $file->hashName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => (int) ($file->getSize() ?? 0),
            'uploaded_by' => $userId,
            'uploaded_at' => now(),
        ]);

        $this->auditLogger->log(
            'student_attendance.attachment_uploaded',
            'school_attendance_attachment',
            (int) $attachment->id,
            [
                'school_id' => $schoolId,
                'school_classroom_id' => $classroomId,
                'attendance_date' => $attendanceDate,
                'file_name' => $attachment->file_name,
                'mime_type' => $attachment->mime_type,
                'file_size' => (int) $attachment->file_size,
            ],
            $request,
            $userId
        );

        return $attachment;
    }

    /**
     * @param array<int, array{
     *     school_student_id:int,
     *     status:string,
     *     check_in_time?:string|null,
     *     check_out_time?:string|null,
     *     permission_reason?:string|null,
     *     notes?:string|null,
     *     __leave_request_id?:int|null
     * }> $rows
     */
    private function validateAttendanceRows(array $rows): void
    {
        $errors = [];

        foreach ($rows as $index => $row) {
            $status = (string) ($row['status'] ?? '');
            $checkIn = (string) ($row['check_in_time'] ?? '');
            $checkOut = (string) ($row['check_out_time'] ?? '');
            $permissionReason = trim((string) ($row['permission_reason'] ?? ''));
            $leaveRequestId = (int) ($row['__leave_request_id'] ?? 0);

            if ($status === SchoolStudentAttendance::STATUS_EXCUSED && $permissionReason === '') {
                $errors["records.$index.permission_reason"] = 'سبب الإذن مطلوب عندما تكون الحالة "إذن".';
            }

            if ($status === SchoolStudentAttendance::STATUS_LEAVE && $leaveRequestId <= 0) {
                $errors["records.$index.status"] = 'لا يمكن تعيين حالة إجازة بدون إجازة معتمدة.';
            }

            if (!in_array($status, [SchoolStudentAttendance::STATUS_ABSENT, SchoolStudentAttendance::STATUS_LEAVE], true)
                && $checkIn !== ''
                && $checkOut !== ''
                && $checkOut < $checkIn) {
                $errors["records.$index.check_out_time"] = 'وقت الانصراف يجب أن يكون بعد أو مساويًا لوقت الحضور.';
            }
        }

        if (count($errors) > 0) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function emptyToNull(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        return $normalized !== '' ? $normalized : null;
    }

    private function formatStoredTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr($value, 0, 5);
    }
}
