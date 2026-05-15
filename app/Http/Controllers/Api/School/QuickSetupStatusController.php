<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use App\Models\SchoolAcademicYear;
use App\Models\SchoolCalendarSetting;
use App\Models\SchoolClassSchedule;
use App\Models\SchoolClassroom;
use App\Models\SchoolHoliday;
use App\Models\SchoolLeaveType;
use App\Models\SchoolStage;
use App\Models\SchoolSubject;
use App\Models\SchoolSubjectTeacherAssignment;
use App\Models\SchoolTerm;
use App\Models\SchoolTimetableVersion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuickSetupStatusController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $schoolId = $this->resolveSchoolId($request);

        $staffUsersCount = User::query()
            ->where('school_id', $schoolId)
            ->where(function ($query): void {
                $query->where('role', 'staff')
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'staff'));
            })
            ->count();

        $activeStaffUsersCount = User::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('role', 'staff')
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'staff'));
            })
            ->count();

        $stagesCount = SchoolStage::query()->where('school_id', $schoolId)->count();
        $activeStagesCount = SchoolStage::query()->where('school_id', $schoolId)->where('is_active', true)->count();

        $academicYearsCount = SchoolAcademicYear::query()->where('school_id', $schoolId)->count();
        $activeAcademicYearsCount = SchoolAcademicYear::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->count();

        $termsCount = SchoolTerm::query()->where('school_id', $schoolId)->count();
        $termsLinkedToAcademicYearCount = SchoolTerm::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('school_academic_year_id')
            ->count();

        $calendarSettings = SchoolCalendarSetting::query()
            ->where('school_id', $schoolId)
            ->first(['week_start_day', 'weekly_off_days']);

        $calendarHasWeekStart = $calendarSettings?->week_start_day !== null;
        $calendarWeeklyOffDaysCount = collect($calendarSettings?->weekly_off_days ?? [])->filter(
            fn ($day): bool => is_numeric($day)
        )->count();

        $holidaysCount = SchoolHoliday::query()->where('school_id', $schoolId)->count();
        $activeHolidaysCount = SchoolHoliday::query()->where('school_id', $schoolId)->where('is_active', true)->count();

        $leaveTypesCount = SchoolLeaveType::query()->where('school_id', $schoolId)->count();
        $activeLeaveTypesCount = SchoolLeaveType::query()->where('school_id', $schoolId)->where('is_active', true)->count();

        $classroomsCount = SchoolClassroom::query()->where('school_id', $schoolId)->count();
        $activeClassroomsCount = SchoolClassroom::query()->where('school_id', $schoolId)->where('is_active', true)->count();

        $subjectsCount = SchoolSubject::query()->where('school_id', $schoolId)->count();
        $activeSubjectsCount = SchoolSubject::query()->where('school_id', $schoolId)->where('is_active', true)->count();
        $subjectsWithTeacherAssignmentCount = SchoolSubjectTeacherAssignment::query()
            ->where('school_id', $schoolId)
            ->distinct('school_subject_id')
            ->count('school_subject_id');

        $timetableVersionsCount = SchoolTimetableVersion::query()->where('school_id', $schoolId)->count();
        $publishedTimetableVersionsCount = SchoolTimetableVersion::query()
            ->where('school_id', $schoolId)
            ->where('is_published', true)
            ->count();

        $schedulesCount = SchoolClassSchedule::query()->where('school_id', $schoolId)->count();
        $activeSchedulesCount = SchoolClassSchedule::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->count();

        $canManageUsers = (bool) $user?->hasSystemRole('school_manager');
        $canManageStudentStructure = (bool) $user?->canManageStudentStructure();
        $canManageAcademicPlanning = (bool) $user?->canManageAcademicPlanning();
        $canManageCalendar = (bool) $user?->canManageSchoolCalendar();
        $canManageHolidays = (bool) $user?->canManageSchoolHolidays();
        $canManageLeaveTypes = (bool) $user?->canManageLeaveTypes();

        $steps = collect([
            $this->buildStep(
                key: 'school_users',
                order: 1,
                label: 'مستخدمو المدرسة',
                editable: $canManageUsers,
                optional: false,
                status: $this->resolveStatus(
                    started: $staffUsersCount > 0,
                    completed: $activeStaffUsersCount > 0
                ),
                prerequisites: [],
                counts: [
                    'total' => $staffUsersCount,
                    'active' => $activeStaffUsersCount,
                ]
            ),
            $this->buildStep(
                key: 'stages',
                order: 2,
                label: 'المراحل الدراسية',
                editable: $canManageStudentStructure || $canManageAcademicPlanning,
                optional: false,
                status: $this->resolveStatus(
                    started: $stagesCount > 0,
                    completed: $activeStagesCount > 0,
                    needsAttention: $stagesCount > 0 && $activeStagesCount === 0
                ),
                prerequisites: [],
                counts: [
                    'total' => $stagesCount,
                    'active' => $activeStagesCount,
                ]
            ),
            $this->buildStep(
                key: 'academic_years',
                order: 3,
                label: 'العام الدراسي',
                editable: $canManageAcademicPlanning,
                optional: false,
                status: $this->resolveStatus(
                    started: $academicYearsCount > 0,
                    completed: $activeAcademicYearsCount > 0,
                    needsAttention: $academicYearsCount > 0 && $activeAcademicYearsCount === 0
                ),
                prerequisites: [],
                counts: [
                    'total' => $academicYearsCount,
                    'active' => $activeAcademicYearsCount,
                ]
            ),
            $this->buildStep(
                key: 'terms',
                order: 4,
                label: 'الفصل الدراسي',
                editable: $canManageAcademicPlanning,
                optional: false,
                status: $this->resolveStatus(
                    started: $termsCount > 0,
                    completed: $termsCount > 0 && $termsLinkedToAcademicYearCount === $termsCount,
                    needsAttention: $termsCount > 0 && $termsLinkedToAcademicYearCount < $termsCount
                ),
                prerequisites: [
                    [
                        'key' => 'academic_years',
                        'label' => 'وجود عام دراسي واحد على الأقل',
                        'required' => true,
                        'met' => $academicYearsCount > 0,
                    ],
                ],
                counts: [
                    'total' => $termsCount,
                    'linked_to_academic_year' => $termsLinkedToAcademicYearCount,
                ]
            ),
            $this->buildStep(
                key: 'calendar_settings',
                order: 5,
                label: 'إعدادات التقويم المدرسي',
                editable: $canManageCalendar,
                optional: false,
                status: $this->resolveStatus(
                    started: $calendarSettings !== null,
                    completed: $calendarHasWeekStart && $calendarWeeklyOffDaysCount > 0,
                    needsAttention: $calendarSettings !== null && (!$calendarHasWeekStart || $calendarWeeklyOffDaysCount === 0)
                ),
                prerequisites: [
                    [
                        'key' => 'terms',
                        'label' => 'وجود فصل دراسي واحد على الأقل',
                        'required' => true,
                        'met' => $termsCount > 0,
                    ],
                ],
                counts: [
                    'weekly_off_days_count' => $calendarWeeklyOffDaysCount,
                ]
            ),
            $this->buildStep(
                key: 'holidays',
                order: 6,
                label: 'العطل الرسمية',
                editable: $canManageHolidays,
                optional: true,
                status: $this->resolveStatus(
                    started: $holidaysCount > 0,
                    completed: $activeHolidaysCount > 0
                ),
                prerequisites: [
                    [
                        'key' => 'calendar_settings',
                        'label' => 'ضبط إعدادات التقويم المدرسي أولًا',
                        'required' => false,
                        'met' => $calendarHasWeekStart,
                    ],
                ],
                counts: [
                    'total' => $holidaysCount,
                    'active' => $activeHolidaysCount,
                ]
            ),
            $this->buildStep(
                key: 'leave_types',
                order: 7,
                label: 'أنواع الإجازات',
                editable: $canManageLeaveTypes,
                optional: true,
                status: $this->resolveStatus(
                    started: $leaveTypesCount > 0,
                    completed: $activeLeaveTypesCount > 0,
                    needsAttention: $leaveTypesCount > 0 && $activeLeaveTypesCount === 0
                ),
                prerequisites: [],
                counts: [
                    'total' => $leaveTypesCount,
                    'active' => $activeLeaveTypesCount,
                ]
            ),
            $this->buildStep(
                key: 'classrooms',
                order: 8,
                label: 'الفصول التعليمية',
                editable: $canManageStudentStructure || $canManageAcademicPlanning,
                optional: false,
                status: $this->resolveStatus(
                    started: $classroomsCount > 0,
                    completed: $activeClassroomsCount > 0,
                    needsAttention: $classroomsCount > 0 && $activeClassroomsCount === 0
                ),
                prerequisites: [
                    [
                        'key' => 'stages',
                        'label' => 'وجود مرحلة دراسية واحدة على الأقل',
                        'required' => true,
                        'met' => $stagesCount > 0,
                    ],
                ],
                counts: [
                    'total' => $classroomsCount,
                    'active' => $activeClassroomsCount,
                ]
            ),
            $this->buildStep(
                key: 'subjects',
                order: 9,
                label: 'المواد التعليمية',
                editable: $canManageAcademicPlanning,
                optional: false,
                status: $this->resolveStatus(
                    started: $subjectsCount > 0,
                    completed: $activeSubjectsCount > 0 && $subjectsWithTeacherAssignmentCount > 0,
                    needsAttention: $subjectsCount > 0 && $subjectsWithTeacherAssignmentCount === 0
                ),
                prerequisites: [
                    [
                        'key' => 'classrooms',
                        'label' => 'وجود فصل تعليمي واحد على الأقل',
                        'required' => true,
                        'met' => $classroomsCount > 0,
                    ],
                ],
                counts: [
                    'total' => $subjectsCount,
                    'active' => $activeSubjectsCount,
                    'with_teacher_assignments' => $subjectsWithTeacherAssignmentCount,
                ]
            ),
            $this->buildStep(
                key: 'timetable_copy',
                order: 10,
                label: 'إدارة نسخ الجدول',
                editable: $canManageAcademicPlanning,
                optional: true,
                status: $this->resolveStatus(
                    started: $timetableVersionsCount > 0,
                    completed: $publishedTimetableVersionsCount > 0,
                    needsAttention: $timetableVersionsCount > 0 && $publishedTimetableVersionsCount === 0
                ),
                prerequisites: [
                    [
                        'key' => 'terms',
                        'label' => 'وجود فصل دراسي واحد على الأقل',
                        'required' => true,
                        'met' => $termsCount > 0,
                    ],
                ],
                counts: [
                    'total' => $timetableVersionsCount,
                    'published' => $publishedTimetableVersionsCount,
                ]
            ),
            $this->buildStep(
                key: 'timetables',
                order: 11,
                label: 'الجداول الدراسية',
                editable: $canManageAcademicPlanning,
                optional: false,
                status: $this->resolveStatus(
                    started: $schedulesCount > 0,
                    completed: $activeSchedulesCount > 0,
                    needsAttention: $schedulesCount > 0 && $activeSchedulesCount === 0
                ),
                prerequisites: [
                    [
                        'key' => 'terms',
                        'label' => 'وجود فصل دراسي واحد على الأقل',
                        'required' => true,
                        'met' => $termsCount > 0,
                    ],
                    [
                        'key' => 'subjects',
                        'label' => 'وجود مادة تعليمية واحدة على الأقل',
                        'required' => true,
                        'met' => $subjectsCount > 0,
                    ],
                    [
                        'key' => 'classrooms',
                        'label' => 'وجود فصل تعليمي واحد على الأقل',
                        'required' => true,
                        'met' => $classroomsCount > 0,
                    ],
                ],
                counts: [
                    'total' => $schedulesCount,
                    'active' => $activeSchedulesCount,
                ]
            ),
        ])->values();

        $firstPending = $steps->first(
            fn (array $step): bool => $step['status'] !== 'completed' && $step['editable'] === true
        );

        return response()->json([
            'data' => [
                'school_id' => $schoolId,
                'generated_at' => now()->toISOString(),
                'steps' => $steps->all(),
                'first_incomplete_step_key' => $firstPending['key'] ?? null,
            ],
        ]);
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

    /**
     * @param array<int, array{key:string,label:string,required:bool,met:bool}> $prerequisites
     * @param array<string, int> $counts
     * @return array<string, mixed>
     */
    private function buildStep(
        string $key,
        int $order,
        string $label,
        bool $editable,
        bool $optional,
        string $status,
        array $prerequisites,
        array $counts
    ): array {
        $blocked = collect($prerequisites)->contains(
            fn (array $dependency): bool => ($dependency['required'] ?? false) === true && ($dependency['met'] ?? false) === false
        );

        return [
            'key' => $key,
            'order' => $order,
            'label' => $label,
            'status' => $status,
            'optional' => $optional,
            'editable' => $editable,
            'blocked' => $blocked,
            'prerequisites' => $prerequisites,
            'counts' => $counts,
        ];
    }

    private function resolveStatus(bool $started, bool $completed, bool $needsAttention = false): string
    {
        if ($completed) {
            return 'completed';
        }

        if ($needsAttention) {
            return 'needs_attention';
        }

        if ($started) {
            return 'needs_attention';
        }

        return 'not_started';
    }
}
