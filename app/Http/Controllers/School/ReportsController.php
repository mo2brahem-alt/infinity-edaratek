<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolClassroom;
use App\Models\SchoolLeaveType;
use App\Models\SchoolStage;
use App\Models\SchoolStageGrade;
use App\Models\SchoolStudent;
use App\Models\SchoolStudentAttendance;
use App\Models\SchoolStudentLeaveRequest;
use App\Models\User;
use App\Services\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    private const ENTITIES = ['students', 'stages', 'grades', 'classrooms', 'teachers', 'attendance', 'leaves'];
    private const EXPORTS = ['csv', 'json', 'excel'];
    private const MAX_EXPORT_ROWS = 10000;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request): Response
    {
        $schoolId = $this->resolveSchoolId($request);
        $user = $request->user();
        $permissions = $this->permissions($user);
        $allowedEntities = $this->allowedEntities($permissions);
        if (count($allowedEntities) === 0) {
            abort(403, 'You do not have permission to access school reports.');
        }

        $filters = $this->validatedFilters($request, $schoolId, false);
        $selectedEntity = in_array($filters['entity'], $allowedEntities, true) ? $filters['entity'] : $allowedEntities[0];
        $dataset = $this->dataset($selectedEntity, $schoolId, $filters, true);

        return Inertia::render('School/Reports', [
            'school' => School::query()->whereKey($schoolId)->first(['id', 'name', 'school_id']),
            'summary' => $this->summary($schoolId, $filters, $allowedEntities),
            'selectedEntity' => $selectedEntity,
            'entityOptions' => collect($allowedEntities)->map(fn ($e) => ['value' => $e, 'label' => $this->entityLabel($e)])->values()->all(),
            'table' => $dataset,
            'filters' => $filters,
            'filterOptions' => [
                'stages' => SchoolStage::query()->where('school_id', $schoolId)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])->map(fn ($i) => ['id' => (int) $i->id, 'name' => (string) $i->name])->values()->all(),
                'grades' => SchoolStageGrade::query()->where('school_id', $schoolId)->when($filters['stage_id'], fn ($q, $v) => $q->where('school_stage_id', (int) $v))->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'school_stage_id'])->map(fn ($i) => ['id' => (int) $i->id, 'name' => (string) $i->name, 'school_stage_id' => (int) $i->school_stage_id])->values()->all(),
                'classrooms' => SchoolClassroom::query()->where('school_id', $schoolId)->when($filters['stage_id'], fn ($q, $v) => $q->where('school_stage_id', (int) $v))->when($filters['grade_name'], fn ($q, $v) => $q->where('grade_name', (string) $v))->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'grade_name', 'school_stage_id'])->map(fn ($i) => ['id' => (int) $i->id, 'name' => (string) $i->name, 'grade_name' => (string) $i->grade_name, 'school_stage_id' => (int) $i->school_stage_id])->values()->all(),
                'students' => SchoolStudent::query()->where('school_id', $schoolId)->when($filters['classroom_id'], fn ($q, $v) => $q->where('school_classroom_id', (int) $v))->orderBy('full_name')->limit(1000)->get(['id', 'full_name', 'student_code'])->map(fn ($i) => ['id' => (int) $i->id, 'full_name' => (string) $i->full_name, 'student_code' => (string) ($i->student_code ?? '')])->values()->all(),
                'teachers' => $this->teacherQuery($schoolId)->orderBy('name')->limit(1000)->get(['id', 'name', 'email'])->map(fn ($i) => ['id' => (int) $i->id, 'name' => (string) $i->name, 'email' => (string) ($i->email ?? '')])->values()->all(),
                'leaveTypes' => SchoolLeaveType::query()->where('school_id', $schoolId)->orderBy('name')->get(['id', 'name'])->map(fn ($i) => ['id' => (int) $i->id, 'name' => (string) $i->name])->values()->all(),
                'attendanceStatuses' => [
                    ['value' => SchoolStudentAttendance::STATUS_PRESENT, 'label' => 'حضور'],
                    ['value' => SchoolStudentAttendance::STATUS_ABSENT, 'label' => 'غياب'],
                    ['value' => SchoolStudentAttendance::STATUS_EXCUSED, 'label' => 'إذن'],
                    ['value' => SchoolStudentAttendance::STATUS_LEAVE, 'label' => 'إجازة'],
                ],
                'leaveStatuses' => [
                    ['value' => SchoolStudentLeaveRequest::STATUS_PENDING, 'label' => 'قيد المراجعة'],
                    ['value' => SchoolStudentLeaveRequest::STATUS_APPROVED, 'label' => 'مقبول'],
                    ['value' => SchoolStudentLeaveRequest::STATUS_REJECTED, 'label' => 'مرفوض'],
                    ['value' => SchoolStudentLeaveRequest::STATUS_CANCELLED, 'label' => 'ملغي'],
                ],
                'activeStates' => [
                    ['value' => 'all', 'label' => 'الكل'],
                    ['value' => 'active', 'label' => 'نشط فقط'],
                    ['value' => 'inactive', 'label' => 'غير نشط فقط'],
                ],
                'exportFormats' => [
                    ['value' => 'csv', 'label' => 'CSV'],
                    ['value' => 'json', 'label' => 'JSON'],
                    ['value' => 'excel', 'label' => 'Excel'],
                ],
                'perPageOptions' => [10, 25, 50, 100],
            ],
            'isManager' => $user?->hasSystemRole('school_manager') ?? false,
            'permissions' => $permissions,
        ]);
    }

    public function export(Request $request): JsonResponse|StreamedResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $user = $request->user();
        $permissions = $this->permissions($user);
        $allowedEntities = $this->allowedEntities($permissions);
        if (count($allowedEntities) === 0 || !($permissions['can_export_school_reports'] ?? false)) {
            abort(403, 'You do not have permission to export school reports.');
        }

        $filters = $this->validatedFilters($request, $schoolId, true);
        $entities = $filters['entity'] === 'all'
            ? $allowedEntities
            : [in_array($filters['entity'], $allowedEntities, true) ? $filters['entity'] : $allowedEntities[0]];

        $datasets = [];
        $totalRows = 0;
        foreach ($entities as $entity) {
            $dataset = $this->dataset($entity, $schoolId, $filters, false);
            $datasets[] = $dataset;
            $totalRows += (int) ($dataset['total'] ?? 0);
        }

        $this->auditLogger->log(
            'school_reports.exported',
            'school_reports',
            null,
            [
                'school_id' => $schoolId,
                'entity' => $filters['entity'],
                'format' => $filters['format'],
                'rows_count' => $totalRows,
                'filters' => $filters,
            ],
            $request,
            (int) ($user?->id ?? 0) > 0 ? (int) $user->id : null
        );

        if ($filters['format'] === 'json') {
            return response()->json([
                'generated_at' => now()->toIso8601String(),
                'school_id' => $schoolId,
                'entity' => $filters['entity'],
                'datasets' => $datasets,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $delimiter = $filters['format'] === 'excel' ? "\t" : ',';
        $contentType = $filters['format'] === 'excel' ? 'application/vnd.ms-excel; charset=UTF-8' : 'text/csv; charset=UTF-8';
        $extension = $filters['format'] === 'excel' ? 'xls' : 'csv';

        return response()->streamDownload(function () use ($datasets, $delimiter): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }
            foreach ($datasets as $index => $dataset) {
                if ($index > 0) {
                    fputcsv($stream, [], $delimiter);
                }
                fputcsv($stream, ['report_entity', (string) ($dataset['title'] ?? $dataset['entity'])], $delimiter);
                fputcsv($stream, collect($dataset['columns'] ?? [])->pluck('label')->all(), $delimiter);
                foreach (($dataset['rows'] ?? []) as $row) {
                    $line = [];
                    foreach (($dataset['columns'] ?? []) as $column) {
                        $key = (string) ($column['key'] ?? '');
                        $line[] = is_scalar($row[$key] ?? '') ? (string) ($row[$key] ?? '') : '';
                    }
                    fputcsv($stream, $line, $delimiter);
                }
                fputcsv($stream, ['total_rows', (string) ((int) ($dataset['total'] ?? 0))], $delimiter);
            }
            fclose($stream);
        }, sprintf('school-reports-%s.%s', now()->format('Ymd_His'), $extension), ['Content-Type' => $contentType]);
    }

    private function resolveSchoolId(Request $request): int
    {
        $schoolId = (int) $request->attributes->get('school_context_id', (int) ($request->user()?->school_id ?? 0));
        if ($schoolId <= 0) {
            abort(403, 'School context is required.');
        }
        return $schoolId;
    }

    private function dataset(string $entity, int $schoolId, array $filters, bool $paginate): array
    {
        $config = match ($entity) {
            'stages' => ['title' => 'تقارير المراحل التعليمية', 'columns' => [['key' => 'name', 'label' => 'اسم المرحلة'], ['key' => 'code', 'label' => 'الكود'], ['key' => 'sort_order', 'label' => 'الترتيب'], ['key' => 'grades_count', 'label' => 'عدد الصفوف'], ['key' => 'classrooms_count', 'label' => 'عدد الفصول'], ['key' => 'active_label', 'label' => 'الحالة']], 'query' => $this->stagesQuery($schoolId, $filters), 'map' => fn ($m) => ['name' => (string) $m->name, 'code' => (string) ($m->code ?? ''), 'sort_order' => (int) ($m->sort_order ?? 0), 'grades_count' => (int) ($m->grades_count ?? 0), 'classrooms_count' => (int) ($m->classrooms_count ?? 0), 'active_label' => (bool) $m->is_active ? 'نشط' : 'غير نشط']],
            'grades' => ['title' => 'تقارير الصفوف الدراسية', 'columns' => [['key' => 'name', 'label' => 'اسم الصف'], ['key' => 'stage_name', 'label' => 'المرحلة'], ['key' => 'sort_order', 'label' => 'الترتيب'], ['key' => 'classrooms_count', 'label' => 'عدد الفصول'], ['key' => 'active_label', 'label' => 'الحالة']], 'query' => $this->gradesQuery($schoolId, $filters), 'map' => fn ($m) => ['name' => (string) $m->name, 'stage_name' => (string) ($m->stage?->name ?? '-'), 'sort_order' => (int) ($m->sort_order ?? 0), 'classrooms_count' => (int) ($m->classrooms_count ?? 0), 'active_label' => (bool) $m->is_active ? 'نشط' : 'غير نشط']],
            'classrooms' => ['title' => 'تقارير الفصول التعليمية', 'columns' => [['key' => 'name', 'label' => 'اسم الفصل'], ['key' => 'code', 'label' => 'الكود'], ['key' => 'stage_name', 'label' => 'المرحلة'], ['key' => 'grade_name', 'label' => 'الصف'], ['key' => 'students_count', 'label' => 'عدد الطلاب'], ['key' => 'sort_order', 'label' => 'الترتيب'], ['key' => 'active_label', 'label' => 'الحالة']], 'query' => $this->classroomsQuery($schoolId, $filters), 'map' => fn ($m) => ['name' => (string) $m->name, 'code' => (string) ($m->code ?? ''), 'stage_name' => (string) ($m->stage?->name ?? '-'), 'grade_name' => (string) ($m->grade_name ?? '-'), 'students_count' => (int) ($m->students_count ?? 0), 'sort_order' => (int) ($m->sort_order ?? 0), 'active_label' => (bool) $m->is_active ? 'نشط' : 'غير نشط']],
            'teachers' => ['title' => 'تقارير المعلمين', 'columns' => [['key' => 'name', 'label' => 'اسم المعلم'], ['key' => 'email', 'label' => 'البريد'], ['key' => 'mobile', 'label' => 'الجوال'], ['key' => 'department_name', 'label' => 'الإدارة'], ['key' => 'role_name', 'label' => 'الدور'], ['key' => 'schedules_count', 'label' => 'عدد الجداول'], ['key' => 'course_assignments_count', 'label' => 'عدد الإسنادات'], ['key' => 'active_label', 'label' => 'الحالة']], 'query' => $this->teachersQuery($schoolId, $filters), 'map' => fn ($m) => ['name' => (string) $m->name, 'email' => (string) ($m->email ?? ''), 'mobile' => (string) ($m->mobile ?? ''), 'department_name' => (string) ($m->department?->name ?? '-'), 'role_name' => (string) ($m->departmentRole?->name ?? '-'), 'schedules_count' => (int) ($m->schedules_count ?? 0), 'course_assignments_count' => (int) ($m->course_assignments_count ?? 0), 'active_label' => (bool) $m->is_active ? 'نشط' : 'غير نشط']],
            'attendance' => ['title' => 'تقارير الحضور والانصراف', 'columns' => [['key' => 'attendance_date', 'label' => 'التاريخ'], ['key' => 'student_name', 'label' => 'الطالب'], ['key' => 'student_code', 'label' => 'الكود'], ['key' => 'stage_name', 'label' => 'المرحلة'], ['key' => 'grade_name', 'label' => 'الصف'], ['key' => 'classroom_name', 'label' => 'الفصل'], ['key' => 'status_label', 'label' => 'الحالة'], ['key' => 'leave_type', 'label' => 'نوع الإجازة'], ['key' => 'check_in_time', 'label' => 'حضور'], ['key' => 'check_out_time', 'label' => 'انصراف'], ['key' => 'notes', 'label' => 'ملاحظات']], 'query' => $this->attendanceQuery($schoolId, $filters), 'map' => fn ($m) => ['attendance_date' => $m->attendance_date?->toDateString() ?? '-', 'student_name' => (string) ($m->student?->full_name ?? '-'), 'student_code' => (string) ($m->student?->student_code ?? ''), 'stage_name' => (string) ($m->classroom?->stage?->name ?? '-'), 'grade_name' => (string) ($m->classroom?->grade_name ?? '-'), 'classroom_name' => (string) ($m->classroom?->name ?? '-'), 'status_label' => $this->attendanceLabel((string) $m->status), 'leave_type' => (string) ($m->leaveRequest?->leaveType?->name ?? '-'), 'check_in_time' => $this->timeLabel($m->check_in_time), 'check_out_time' => $this->timeLabel($m->check_out_time), 'notes' => (string) ($m->notes ?? '')]],
            'leaves' => ['title' => 'تقارير إجازات الطلاب', 'columns' => [['key' => 'student_name', 'label' => 'الطالب'], ['key' => 'student_code', 'label' => 'الكود'], ['key' => 'stage_name', 'label' => 'المرحلة'], ['key' => 'grade_name', 'label' => 'الصف'], ['key' => 'classroom_name', 'label' => 'الفصل'], ['key' => 'leave_type', 'label' => 'نوع الإجازة'], ['key' => 'source_label', 'label' => 'المصدر'], ['key' => 'status_label', 'label' => 'الحالة'], ['key' => 'start_date', 'label' => 'من'], ['key' => 'end_date', 'label' => 'إلى'], ['key' => 'reason', 'label' => 'السبب']], 'query' => $this->leavesQuery($schoolId, $filters), 'map' => fn ($m) => ['student_name' => (string) ($m->student?->full_name ?? '-'), 'student_code' => (string) ($m->student?->student_code ?? ''), 'stage_name' => (string) ($m->student?->classroom?->stage?->name ?? '-'), 'grade_name' => (string) ($m->student?->classroom?->grade_name ?? '-'), 'classroom_name' => (string) ($m->student?->classroom?->name ?? '-'), 'leave_type' => (string) ($m->leaveType?->name ?? '-'), 'source_label' => $this->leaveSourceLabel((string) $m->source), 'status_label' => $this->leaveStatusLabel((string) $m->status), 'start_date' => $m->start_date?->toDateString() ?? '-', 'end_date' => $m->end_date?->toDateString() ?? '-', 'reason' => (string) ($m->reason ?? '')]],
            default => ['title' => 'تقارير الطلاب', 'columns' => [['key' => 'full_name', 'label' => 'اسم الطالب'], ['key' => 'student_code', 'label' => 'الكود'], ['key' => 'national_id', 'label' => 'الرقم الوطني'], ['key' => 'stage_name', 'label' => 'المرحلة'], ['key' => 'grade_name', 'label' => 'الصف'], ['key' => 'classroom_name', 'label' => 'الفصل'], ['key' => 'active_label', 'label' => 'الحالة']], 'query' => $this->studentsQuery($schoolId, $filters), 'map' => fn ($m) => ['full_name' => (string) $m->full_name, 'student_code' => (string) ($m->student_code ?? ''), 'national_id' => (string) ($m->national_id ?? ''), 'stage_name' => (string) ($m->classroom?->stage?->name ?? '-'), 'grade_name' => (string) ($m->classroom?->grade_name ?? '-'), 'classroom_name' => (string) ($m->classroom?->name ?? '-'), 'active_label' => (bool) $m->is_active ? 'نشط' : 'غير نشط']],
        };

        if ($paginate) {
            $paginator = $config['query']->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();
            return [
                'entity' => $entity,
                'title' => $config['title'],
                'columns' => $config['columns'],
                'rows' => collect($paginator->items())->map($config['map'])->values()->all(),
                'meta' => $this->paginatorMeta($paginator),
            ];
        }

        $query = $config['query'];
        return [
            'entity' => $entity,
            'title' => $config['title'],
            'columns' => $config['columns'],
            'rows' => (clone $query)->limit(self::MAX_EXPORT_ROWS)->get()->map($config['map'])->values()->all(),
            'total' => (int) (clone $query)->count(),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function summary(int $schoolId, array $filters, array $allowedEntities): array
    {
        $summary = [
            'students_count' => in_array('students', $allowedEntities, true) ? (int) SchoolStudent::query()->where('school_id', $schoolId)->count() : 0,
            'stages_count' => in_array('stages', $allowedEntities, true) ? (int) SchoolStage::query()->where('school_id', $schoolId)->count() : 0,
            'grades_count' => in_array('grades', $allowedEntities, true) ? (int) SchoolStageGrade::query()->where('school_id', $schoolId)->count() : 0,
            'classrooms_count' => in_array('classrooms', $allowedEntities, true) ? (int) SchoolClassroom::query()->where('school_id', $schoolId)->count() : 0,
            'teachers_count' => in_array('teachers', $allowedEntities, true) ? (int) $this->teacherQuery($schoolId)->count() : 0,
            'attendance_records_count' => 0,
            'leave_requests_count' => 0,
        ];

        if (in_array('attendance', $allowedEntities, true)) {
            $query = SchoolStudentAttendance::query()->where('school_id', $schoolId);
            if ($filters['date_from']) $query->whereDate('attendance_date', '>=', (string) $filters['date_from']);
            if ($filters['date_to']) $query->whereDate('attendance_date', '<=', (string) $filters['date_to']);
            $summary['attendance_records_count'] = (int) $query->count();
        }
        if (in_array('leaves', $allowedEntities, true)) {
            $query = SchoolStudentLeaveRequest::query()->where('school_id', $schoolId);
            if ($filters['date_from']) $query->whereDate('end_date', '>=', (string) $filters['date_from']);
            if ($filters['date_to']) $query->whereDate('start_date', '<=', (string) $filters['date_to']);
            $summary['leave_requests_count'] = (int) $query->count();
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request, int $schoolId, bool $forExport): array
    {
        $rules = [
            'entity' => ['nullable', Rule::in(array_merge(self::ENTITIES, ['all']))],
            'search' => ['nullable', 'string', 'max:255'],
            'stage_id' => ['nullable', Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId))],
            'grade_name' => ['nullable', 'string', 'max:100'],
            'classroom_id' => ['nullable', Rule::exists('school_classrooms', 'id')->where(fn ($query) => $query->where('school_id', $schoolId))],
            'student_id' => ['nullable', Rule::exists('school_students', 'id')->where(fn ($query) => $query->where('school_id', $schoolId))],
            'teacher_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('school_id', $schoolId))],
            'leave_type_id' => ['nullable', Rule::exists('school_leave_types', 'id')->where(fn ($query) => $query->where('school_id', $schoolId))],
            'attendance_status' => ['nullable', Rule::in(SchoolStudentAttendance::allowedStatuses())],
            'leave_status' => ['nullable', Rule::in(SchoolStudentLeaveRequest::allowedStatuses())],
            'active_state' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];

        if ($forExport) {
            $rules['format'] = ['required', Rule::in(self::EXPORTS)];
        }

        $validated = $request->validate($rules);

        $from = $this->normalizeDate($validated['date_from'] ?? null);
        $to = $this->normalizeDate($validated['date_to'] ?? null);
        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'entity' => trim((string) ($validated['entity'] ?? 'students')),
            'format' => trim((string) ($validated['format'] ?? 'csv')),
            'search' => $this->nullIfEmpty($validated['search'] ?? null),
            'stage_id' => isset($validated['stage_id']) ? (int) $validated['stage_id'] : null,
            'grade_name' => $this->nullIfEmpty($validated['grade_name'] ?? null),
            'classroom_id' => isset($validated['classroom_id']) ? (int) $validated['classroom_id'] : null,
            'student_id' => isset($validated['student_id']) ? (int) $validated['student_id'] : null,
            'teacher_id' => isset($validated['teacher_id']) ? (int) $validated['teacher_id'] : null,
            'leave_type_id' => isset($validated['leave_type_id']) ? (int) $validated['leave_type_id'] : null,
            'attendance_status' => $this->nullIfEmpty($validated['attendance_status'] ?? null),
            'leave_status' => $this->nullIfEmpty($validated['leave_status'] ?? null),
            'active_state' => (string) ($validated['active_state'] ?? 'all'),
            'date_from' => $from,
            'date_to' => $to,
            'per_page' => (int) ($validated['per_page'] ?? 25),
        ];
    }

    private function studentsQuery(int $schoolId, array $filters): Builder
    {
        return SchoolStudent::query()
            ->where('school_students.school_id', $schoolId)
            ->with(['classroom:id,school_stage_id,grade_name,name', 'classroom.stage:id,name'])
            ->when($filters['stage_id'], fn ($query, $stageId) => $query->whereHas('classroom', fn ($classroom) => $classroom->where('school_stage_id', (int) $stageId)))
            ->when($filters['grade_name'], fn ($query, $gradeName) => $query->whereHas('classroom', fn ($classroom) => $classroom->where('grade_name', (string) $gradeName)))
            ->when($filters['classroom_id'], fn ($query, $classroomId) => $query->where('school_classroom_id', (int) $classroomId))
            ->when($filters['student_id'], fn ($query, $studentId) => $query->whereKey((int) $studentId))
            ->when($filters['active_state'] !== 'all', fn ($query) => $query->where('is_active', $filters['active_state'] === 'active'))
            ->when($filters['search'], function ($query, $search): void {
                $value = '%' . trim((string) $search) . '%';
                $query->where(function ($inner) use ($value): void {
                    $inner
                        ->where('full_name', 'like', $value)
                        ->orWhere('student_code', 'like', $value)
                        ->orWhere('national_id', 'like', $value);
                });
            })
            ->orderBy('full_name')
            ->orderBy('id');
    }

    private function stagesQuery(int $schoolId, array $filters): Builder
    {
        return SchoolStage::query()
            ->where('school_stages.school_id', $schoolId)
            ->withCount([
                'grades as grades_count' => fn ($query) => $query->where('school_id', $schoolId),
                'classrooms as classrooms_count' => fn ($query) => $query->where('school_id', $schoolId),
            ])
            ->when($filters['stage_id'], fn ($query, $stageId) => $query->whereKey((int) $stageId))
            ->when($filters['active_state'] !== 'all', fn ($query) => $query->where('is_active', $filters['active_state'] === 'active'))
            ->when($filters['search'], function ($query, $search): void {
                $value = '%' . trim((string) $search) . '%';
                $query->where(function ($inner) use ($value): void {
                    $inner->where('name', 'like', $value)->orWhere('code', 'like', $value);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    private function gradesQuery(int $schoolId, array $filters): Builder
    {
        return SchoolStageGrade::query()
            ->where('school_stage_grades.school_id', $schoolId)
            ->with(['stage:id,name'])
            ->addSelect([
                'classrooms_count' => SchoolClassroom::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('school_classrooms.school_id', 'school_stage_grades.school_id')
                    ->whereColumn('school_classrooms.school_stage_id', 'school_stage_grades.school_stage_id')
                    ->whereColumn('school_classrooms.grade_name', 'school_stage_grades.name'),
            ])
            ->when($filters['stage_id'], fn ($query, $stageId) => $query->where('school_stage_id', (int) $stageId))
            ->when($filters['grade_name'], fn ($query, $gradeName) => $query->where('name', (string) $gradeName))
            ->when($filters['active_state'] !== 'all', fn ($query) => $query->where('is_active', $filters['active_state'] === 'active'))
            ->when($filters['search'], fn ($query, $search) => $query->where('name', 'like', '%' . trim((string) $search) . '%'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    private function classroomsQuery(int $schoolId, array $filters): Builder
    {
        return SchoolClassroom::query()
            ->where('school_classrooms.school_id', $schoolId)
            ->with(['stage:id,name'])
            ->withCount(['students as students_count' => fn ($query) => $query->where('school_id', $schoolId)])
            ->when($filters['stage_id'], fn ($query, $stageId) => $query->where('school_stage_id', (int) $stageId))
            ->when($filters['grade_name'], fn ($query, $gradeName) => $query->where('grade_name', (string) $gradeName))
            ->when($filters['classroom_id'], fn ($query, $classroomId) => $query->whereKey((int) $classroomId))
            ->when($filters['active_state'] !== 'all', fn ($query) => $query->where('is_active', $filters['active_state'] === 'active'))
            ->when($filters['search'], function ($query, $search): void {
                $value = '%' . trim((string) $search) . '%';
                $query->where(function ($inner) use ($value): void {
                    $inner
                        ->where('name', 'like', $value)
                        ->orWhere('code', 'like', $value)
                        ->orWhere('grade_name', 'like', $value);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    private function teachersQuery(int $schoolId, array $filters): Builder
    {
        return $this->teacherQuery($schoolId)
            ->with(['department:id,name', 'departmentRole:id,name'])
            ->withCount([
                'classSchedulesAsTeacher as schedules_count' => fn ($query) => $query->where('school_id', $schoolId),
                'courseTeachingAssignments as course_assignments_count' => fn ($query) => $query->where('school_id', $schoolId)->where('is_active', true),
            ])
            ->when($filters['teacher_id'], fn ($query, $teacherId) => $query->whereKey((int) $teacherId))
            ->when($filters['active_state'] !== 'all', fn ($query) => $query->where('is_active', $filters['active_state'] === 'active'))
            ->when($filters['search'], function ($query, $search): void {
                $value = '%' . trim((string) $search) . '%';
                $query->where(function ($inner) use ($value): void {
                    $inner
                        ->where('name', 'like', $value)
                        ->orWhere('email', 'like', $value)
                        ->orWhere('phone', 'like', $value)
                        ->orWhere('mobile', 'like', $value);
                });
            })
            ->orderBy('name')
            ->orderBy('id');
    }

    private function attendanceQuery(int $schoolId, array $filters): Builder
    {
        return SchoolStudentAttendance::query()
            ->where('school_student_attendances.school_id', $schoolId)
            ->with([
                'student:id,full_name,student_code,school_classroom_id',
                'classroom:id,name,grade_name,school_stage_id',
                'classroom.stage:id,name',
                'leaveRequest:id,school_leave_type_id',
                'leaveRequest.leaveType:id,name',
            ])
            ->when($filters['stage_id'], fn ($query, $stageId) => $query->whereHas('classroom', fn ($classroom) => $classroom->where('school_stage_id', (int) $stageId)))
            ->when($filters['grade_name'], fn ($query, $gradeName) => $query->whereHas('classroom', fn ($classroom) => $classroom->where('grade_name', (string) $gradeName)))
            ->when($filters['classroom_id'], fn ($query, $classroomId) => $query->where('school_classroom_id', (int) $classroomId))
            ->when($filters['student_id'], fn ($query, $studentId) => $query->where('school_student_id', (int) $studentId))
            ->when($filters['attendance_status'], fn ($query, $status) => $query->where('status', (string) $status))
            ->when($filters['date_from'], fn ($query, $from) => $query->whereDate('attendance_date', '>=', (string) $from))
            ->when($filters['date_to'], fn ($query, $to) => $query->whereDate('attendance_date', '<=', (string) $to))
            ->when($filters['search'], function ($query, $search): void {
                $value = '%' . trim((string) $search) . '%';
                $query->where(function ($inner) use ($value): void {
                    $inner
                        ->where('notes', 'like', $value)
                        ->orWhere('permission_reason', 'like', $value)
                        ->orWhereHas('student', function ($studentQuery) use ($value): void {
                            $studentQuery->where('full_name', 'like', $value)->orWhere('student_code', 'like', $value);
                        });
                });
            })
            ->orderByDesc('attendance_date')
            ->orderByDesc('id');
    }

    private function leavesQuery(int $schoolId, array $filters): Builder
    {
        $query = SchoolStudentLeaveRequest::query()
            ->where('school_student_leave_requests.school_id', $schoolId)
            ->with([
                'student:id,full_name,student_code,school_classroom_id',
                'student.classroom:id,name,grade_name,school_stage_id',
                'student.classroom.stage:id,name',
                'leaveType:id,name',
            ])
            ->when($filters['stage_id'], fn ($builder, $stageId) => $builder->whereHas('student.classroom', fn ($classroom) => $classroom->where('school_stage_id', (int) $stageId)))
            ->when($filters['grade_name'], fn ($builder, $gradeName) => $builder->whereHas('student.classroom', fn ($classroom) => $classroom->where('grade_name', (string) $gradeName)))
            ->when($filters['classroom_id'], fn ($builder, $classroomId) => $builder->whereHas('student', fn ($student) => $student->where('school_classroom_id', (int) $classroomId)))
            ->when($filters['student_id'], fn ($builder, $studentId) => $builder->where('school_student_id', (int) $studentId))
            ->when($filters['leave_status'], fn ($builder, $status) => $builder->where('status', (string) $status))
            ->when($filters['leave_type_id'], fn ($builder, $leaveTypeId) => $builder->where('school_leave_type_id', (int) $leaveTypeId))
            ->when($filters['search'], function ($builder, $search): void {
                $value = '%' . trim((string) $search) . '%';
                $builder->where(function ($inner) use ($value): void {
                    $inner
                        ->where('reason', 'like', $value)
                        ->orWhere('rejection_reason', 'like', $value)
                        ->orWhere('cancellation_reason', 'like', $value)
                        ->orWhereHas('student', function ($studentQuery) use ($value): void {
                            $studentQuery->where('full_name', 'like', $value)->orWhere('student_code', 'like', $value);
                        });
                });
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];
        if ($dateFrom !== null && $dateTo !== null) {
            $query->whereDate('start_date', '<=', (string) $dateTo)->whereDate('end_date', '>=', (string) $dateFrom);
        } elseif ($dateFrom !== null) {
            $query->whereDate('end_date', '>=', (string) $dateFrom);
        } elseif ($dateTo !== null) {
            $query->whereDate('start_date', '<=', (string) $dateTo);
        }

        return $query;
    }

    private function teacherQuery(int $schoolId): Builder
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
                        $departmentQuery->where(function ($scope): void {
                            $scope
                                ->where('staff_type', User::SCHOOL_STAFF_EDUCATIONAL)
                                ->orWhereRaw('LOWER(name) LIKE ?', ['%teacher%'])
                                ->orWhere('name', 'like', '%معلم%');
                        });
                    })
                    ->orWhereHas('departmentRole', function ($departmentRoleQuery): void {
                        $departmentRoleQuery
                            ->where('is_active', true)
                            ->where(function ($scope): void {
                                $scope
                                    ->whereRaw('LOWER(name) LIKE ?', ['%teacher%'])
                                    ->orWhere('name', 'like', '%معلم%');
                            });
                    });
            });
    }

    /**
     * @return array{current_page:int,last_page:int,per_page:int,total:int}
     */
    private function paginatorMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => (int) $paginator->currentPage(),
            'last_page' => (int) $paginator->lastPage(),
            'per_page' => (int) $paginator->perPage(),
            'total' => (int) $paginator->total(),
        ];
    }

    private function permissions(?User $user): array
    {
        return [
            'can_manage_school_reports' => $user?->canManageSchoolReports() ?? false,
            'can_export_school_reports' => $user?->canExportSchoolReports() ?? false,
            'can_manage_student_structure' => $user?->canManageStudentStructure() ?? false,
            'can_manage_student_attendance' => $user?->canManageStudentAttendance() ?? false,
            'can_manage_academic_planning' => $user?->canManageAcademicPlanning() ?? false,
            'can_manage_student_leaves' => $user?->canManageStudentLeaves() ?? false,
            'can_manage_leave_types' => $user?->canManageLeaveTypes() ?? false,
            'can_manage_school_calendar' => $user?->canManageSchoolCalendar() ?? false,
            'can_manage_school_holidays' => $user?->canManageSchoolHolidays() ?? false,
        ];
    }

    private function allowedEntities(array $permissions): array
    {
        if (($permissions['can_manage_school_reports'] ?? false) === true) {
            return self::ENTITIES;
        }

        $allowed = [];
        if (($permissions['can_manage_student_structure'] ?? false) === true) {
            $allowed = array_merge($allowed, ['students', 'stages', 'grades', 'classrooms']);
        }
        if (($permissions['can_manage_academic_planning'] ?? false) === true) {
            $allowed[] = 'teachers';
        }
        if (($permissions['can_manage_student_attendance'] ?? false) === true) {
            $allowed[] = 'attendance';
        }
        if (($permissions['can_manage_student_leaves'] ?? false) || ($permissions['can_manage_leave_types'] ?? false) || ($permissions['can_manage_school_calendar'] ?? false) || ($permissions['can_manage_school_holidays'] ?? false)) {
            $allowed[] = 'leaves';
        }
        return array_values(array_unique($allowed));
    }

    private function entityLabel(string $entity): string
    {
        return match ($entity) {
            'students' => 'الطلاب',
            'stages' => 'المراحل التعليمية',
            'grades' => 'الصفوف الدراسية',
            'classrooms' => 'الفصول التعليمية',
            'teachers' => 'المعلمون',
            'attendance' => 'الحضور والانصراف',
            'leaves' => 'إجازات الطلاب',
            default => $entity,
        };
    }

    private function attendanceLabel(string $status): string
    {
        return match ($status) {
            SchoolStudentAttendance::STATUS_PRESENT => 'حضور',
            SchoolStudentAttendance::STATUS_ABSENT => 'غياب',
            SchoolStudentAttendance::STATUS_EXCUSED => 'إذن',
            SchoolStudentAttendance::STATUS_LEAVE => 'إجازة',
            default => $status,
        };
    }

    private function leaveStatusLabel(string $status): string
    {
        return match ($status) {
            SchoolStudentLeaveRequest::STATUS_PENDING => 'قيد المراجعة',
            SchoolStudentLeaveRequest::STATUS_APPROVED => 'مقبول',
            SchoolStudentLeaveRequest::STATUS_REJECTED => 'مرفوض',
            SchoolStudentLeaveRequest::STATUS_CANCELLED => 'ملغي',
            default => $status,
        };
    }

    private function leaveSourceLabel(string $source): string
    {
        return match ($source) {
            SchoolStudentLeaveRequest::SOURCE_PRE_APPROVED => 'مسبق',
            SchoolStudentLeaveRequest::SOURCE_RETROACTIVE => 'استرجاعي',
            default => $source,
        };
    }

    private function timeLabel(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') return '-';
        try { return Carbon::createFromFormat('H:i:s', $raw)->format('H:i'); } catch (\Throwable) { return $raw; }
    }

    private function normalizeDate(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') return null;
        try { return Carbon::parse($raw)->toDateString(); } catch (\Throwable) { return null; }
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        return $raw === '' ? null : $raw;
    }
}
