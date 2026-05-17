<?php

namespace Tests\Feature;

use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolClassroom;
use App\Models\SchoolStage;
use App\Models\SchoolStageGrade;
use App\Models\SchoolStudent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentStructureTreeViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_receives_only_his_school_tree_data(): void
    {
        [$manager, $school] = $this->createManagerWithSchool('SCH-847001');
        [, $otherSchool] = $this->createManagerWithSchool('SCH-847002');

        $this->createStructureWithStudent($school, 'Primary A', 'Grade 1', 'Class A', 'Student A');
        $this->createStructureWithStudent($otherSchool, 'Primary B', 'Grade 2', 'Class B', 'Student B');

        $this->actingAs($manager)
            ->get(route('school.student_structure.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('School/StudentStructure')
                ->has('stages', 1)
                ->where('stages.0.name', 'Primary A')
                ->where('stages.0.classrooms.0.name', 'Class A')
                ->where('stages.0.classrooms.0.students.0.full_name', 'Student A')
            );
    }

    public function test_student_structure_returns_empty_stage_array_for_school_without_structure(): void
    {
        [$manager] = $this->createManagerWithSchool('SCH-847003');

        $this->actingAs($manager)
            ->get(route('school.student_structure.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('School/StudentStructure')
                ->has('stages', 0)
            );
    }

    public function test_student_structure_returns_empty_child_arrays_for_stage_without_children(): void
    {
        [$manager, $school] = $this->createManagerWithSchool('SCH-847004');

        SchoolStage::query()->create([
            'school_id' => $school->id,
            'name' => 'Stage Without Children',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('school.student_structure.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('School/StudentStructure')
                ->has('stages', 1)
                ->where('stages.0.name', 'Stage Without Children')
                ->has('stages.0.stage_terms', 0)
                ->has('stages.0.grades', 0)
                ->has('stages.0.classrooms', 0)
            );
    }

    /**
     * @return array{0: User, 1: School}
     */
    private function createManagerWithSchool(string $schoolCode): array
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::query()->create([
            'name' => 'Region ' . $schoolCode,
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create([
            'role' => 'school_manager',
            'is_active' => true,
        ]);
        $manager->assignRole('school_manager');

        $school = School::query()->create([
            'directorate_id' => $region->id,
            'name' => 'School ' . $schoolCode,
            'school_id' => $schoolCode,
            'phone' => '05' . substr(preg_replace('/\D+/', '', $schoolCode), -8),
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        return [$manager->fresh(), $school];
    }

    private function createStructureWithStudent(
        School $school,
        string $stageName,
        string $gradeName,
        string $classroomName,
        string $studentName
    ): void {
        $stage = SchoolStage::query()->create([
            'school_id' => $school->id,
            'name' => $stageName,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        SchoolStageGrade::query()->create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => $gradeName,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::query()->create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'grade_name' => $gradeName,
            'name' => $classroomName,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        SchoolStudent::query()->create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => $studentName,
            'student_code' => $studentName === 'Student A' ? 'ST-A' : 'ST-B',
            'is_active' => true,
        ]);
    }
}
