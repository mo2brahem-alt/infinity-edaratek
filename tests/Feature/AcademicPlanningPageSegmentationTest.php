<?php

namespace Tests\Feature;

use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolClassSchedule;
use App\Models\SchoolClassroom;
use App\Models\SchoolStage;
use App\Models\SchoolSubject;
use App\Models\SchoolSubjectTeacherAssignment;
use App\Models\SchoolTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicPlanningPageSegmentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_exposes_selected_page_from_query(): void
    {
        [$manager] = $this->createManagerAndSchool();

        $this->actingAs($manager)
            ->get(route('school.academic_planning.index', ['page' => 'subjects']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('School/AcademicPlanning')
                ->where('selectedPage', 'subjects')
            );
    }

    public function test_index_falls_back_to_stages_for_invalid_page_query(): void
    {
        [$manager] = $this->createManagerAndSchool();

        $this->actingAs($manager)
            ->get(route('school.academic_planning.index', ['page' => 'not-valid']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('School/AcademicPlanning')
                ->where('selectedPage', 'stages')
            );
    }

    public function test_store_timetable_version_redirect_keeps_requested_page(): void
    {
        [$manager, $school] = $this->createManagerAndSchool();

        $term = SchoolTerm::create([
            'school_id' => $school->id,
            'name' => 'Term 1',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-01',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->post(route('school.academic_planning.versions.store', ['page' => 'schedules']), [
                'school_term_id' => $term->id,
                'name' => 'Version A',
            ])
            ->assertRedirect(route('school.academic_planning.index', [
                'term_id' => $term->id,
                'page' => 'schedules',
            ], absolute: false));

        $this->assertDatabaseHas('school_timetable_versions', [
            'school_id' => $school->id,
            'school_term_id' => $term->id,
            'name' => 'Version A',
        ]);
    }

    public function test_store_schedule_redirect_keeps_requested_page(): void
    {
        [$manager, $school] = $this->createManagerAndSchool();

        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $teacher = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'school_staff_type' => User::SCHOOL_STAFF_EDUCATIONAL,
            'is_active' => true,
        ]);
        $teacher->assignRole('staff');

        $term = SchoolTerm::create([
            'school_id' => $school->id,
            'name' => 'Term 1',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-01',
            'is_active' => true,
        ]);

        $stage = SchoolStage::create([
            'school_id' => $school->id,
            'name' => 'Primary',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => '1A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subject = SchoolSubject::create([
            'school_id' => $school->id,
            'name' => 'Math',
            'code' => 'MATH-01',
            'is_active' => true,
        ]);

        SchoolSubjectTeacherAssignment::create([
            'school_id' => $school->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
        ]);

        $response = $this->actingAs($manager)
            ->post(route('school.academic_planning.schedules.store', ['page' => 'schedules']), [
                'school_term_id' => $term->id,
                'school_stage_id' => $stage->id,
                'school_classroom_id' => $classroom->id,
                'school_subject_id' => $subject->id,
                'teacher_user_id' => $teacher->id,
                'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
                'day_of_week' => 1,
                'session_index' => 1,
            ]);

        $response->assertRedirect(route('school.academic_planning.index', [
            'term_id' => $term->id,
            'scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'classroom_id' => $classroom->id,
            'page' => 'schedules',
        ], absolute: false));

        $this->assertDatabaseHas('school_class_schedules', [
            'school_id' => $school->id,
            'school_term_id' => $term->id,
            'school_stage_id' => $stage->id,
            'school_classroom_id' => $classroom->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 1,
            'session_index' => 1,
        ]);
    }

    /**
     * @return array{0: User, 1: School}
     */
    private function createManagerAndSchool(): array
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Planning Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create([
            'role' => 'school_manager',
        ]);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Planning School',
            'school_id' => 'SCH-990001',
            'phone' => '0500099001',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        return [$manager, $school];
    }
}
