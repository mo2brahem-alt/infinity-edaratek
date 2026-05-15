<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolLeaveType;
use App\Models\SchoolClassroom;
use App\Models\SchoolStage;
use App\Models\SchoolStudent;
use App\Services\School\StudentLeaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class StudentLeaveController extends Controller
{
    public function __construct(
        private readonly StudentLeaveService $studentLeaveService,
    ) {
    }

    public function index(Request $request): Response
    {
        $schoolId = $this->resolveSchoolId($request);
        $user = $request->user();
        $actorId = (int) ($user?->id ?? 0);

        $this->studentLeaveService->ensureDefaultLeaveTypes($schoolId, $actorId > 0 ? $actorId : null);

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

        $students = SchoolStudent::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'school_id', 'school_classroom_id', 'full_name', 'student_code', 'national_id', 'is_active']);

        $leaveTypes = SchoolLeaveType::query()
            ->where('school_id', $schoolId)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'school_id', 'code', 'name', 'category', 'requires_attachment', 'is_active']);

        return Inertia::render('School/StudentLeaves', [
            'school' => $school,
            'stages' => $stages,
            'students' => $students,
            'leaveTypes' => $leaveTypes,
            'selectedStageId' => $selectedStage?->id,
            'selectedClassroomId' => $selectedClassroom?->id,
            'selectedClassroomGradeName' => $selectedClassroom?->grade_name ?? $selectedClassroomGradeName,
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

    private function resolveSchoolId(Request $request): int
    {
        $schoolId = (int) $request->attributes->get('school_context_id', (int) ($request->user()?->school_id ?? 0));

        if ($schoolId <= 0) {
            abort(403, 'School context is required.');
        }

        return $schoolId;
    }

    private function resolveSelectedStage(Collection $stages, int $stageId): ?SchoolStage
    {
        $selected = $stages->firstWhere('id', $stageId);
        if ($selected instanceof SchoolStage) {
            return $selected;
        }

        $withClassrooms = $stages->first(function ($stage) {
            return count($stage->classrooms ?? []) > 0;
        });

        return $withClassrooms instanceof SchoolStage ? $withClassrooms : $stages->first();
    }

    private function resolveSelectedClassroom(
        Collection $stages,
        ?SchoolStage $selectedStage,
        int $classroomId,
        ?string $gradeName = null
    ): ?SchoolClassroom
    {
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

    private function normalizeGradeNameInput(string $value): ?string
    {
        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
