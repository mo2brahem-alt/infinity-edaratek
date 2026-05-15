<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolClassSchedule;
use App\Models\SchoolClassroom;
use App\Models\SchoolCourseOffering;
use App\Models\SchoolStage;
use App\Models\SchoolSubject;
use App\Models\SchoolSubjectTeacherAssignment;
use App\Models\SchoolTeachingAssignment;
use App\Models\SchoolTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourseOfferingAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_can_create_course_offering_and_assign_teacher(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::create([
            'name' => 'التنظيم',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $plannerRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'منسق أكاديمي',
            'is_active' => true,
            'can_manage_academic_planning' => true,
        ]);

        $region = EducationalDirectorate::create([
            'name' => 'Central',
            'governorate' => 'Riyadh',
        ]);

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A',
            'school_id' => 'SCH-983001',
            'phone' => '0500009831',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $planner = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'department_role_id' => $plannerRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
        ]);
        $planner->assignRole('staff');

        $teacher = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'school_staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
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
            'code' => 'MATH',
            'is_active' => true,
        ]);

        SchoolSubjectTeacherAssignment::create([
            'school_id' => $school->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
        ]);

        $this->from(route('school.academic_planning.index'))
            ->actingAs($planner)
            ->post(route('school.academic_planning.offerings.store'), [
                'school_term_id' => $term->id,
                'school_stage_id' => $stage->id,
                'school_classroom_id' => $classroom->id,
                'school_subject_id' => $subject->id,
                'is_active' => true,
                'study_plan_units' => [
                    [
                        'name' => 'الوحدة الأساسية',
                        'start_date' => '2026-09-02',
                        'end_date' => '2026-09-20',
                        'lessons' => [],
                    ],
                ],
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $offering = SchoolCourseOffering::query()
            ->where('school_id', $school->id)
            ->where('school_term_id', $term->id)
            ->where('school_classroom_id', $classroom->id)
            ->where('school_subject_id', $subject->id)
            ->firstOrFail();

        $this->from(route('school.academic_planning.index'))
            ->actingAs($planner)
            ->post(route('school.academic_planning.offerings.assignment.sync', $offering->id), [
                'teacher_user_id' => $teacher->id,
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $this->assertDatabaseHas('school_teaching_assignments', [
            'school_id' => $school->id,
            'school_course_offering_id' => $offering->id,
            'teacher_user_id' => $teacher->id,
        ]);
    }

    public function test_course_offering_update_is_scoped_to_user_school_only(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::create([
            'name' => 'التنظيم',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $plannerRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'منسق أكاديمي',
            'is_active' => true,
            'can_manage_academic_planning' => true,
        ]);

        $region = EducationalDirectorate::create([
            'name' => 'West',
            'governorate' => 'Makkah',
        ]);

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A',
            'school_id' => 'SCH-983002',
            'phone' => '0500009832',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'School B',
            'school_id' => 'SCH-983003',
            'phone' => '0500009833',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $planner = User::factory()->create([
            'role' => 'staff',
            'school_id' => $schoolA->id,
            'department_id' => $department->id,
            'department_role_id' => $plannerRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
        ]);
        $planner->assignRole('staff');

        $termB = SchoolTerm::create([
            'school_id' => $schoolB->id,
            'name' => 'Term B',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-01',
            'is_active' => true,
        ]);

        $stageB = SchoolStage::create([
            'school_id' => $schoolB->id,
            'name' => 'Middle',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomB = SchoolClassroom::create([
            'school_id' => $schoolB->id,
            'school_stage_id' => $stageB->id,
            'name' => '2B',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subjectB = SchoolSubject::create([
            'school_id' => $schoolB->id,
            'name' => 'Science',
            'code' => 'SCI',
            'is_active' => true,
        ]);

        $foreignOffering = SchoolCourseOffering::create([
            'school_id' => $schoolB->id,
            'school_term_id' => $termB->id,
            'school_stage_id' => $stageB->id,
            'school_classroom_id' => $classroomB->id,
            'school_subject_id' => $subjectB->id,
            'is_active' => true,
        ]);

        $this->actingAs($planner)
            ->put(route('school.academic_planning.offerings.update', $foreignOffering->id), [
                'school_term_id' => $termB->id,
                'school_stage_id' => $stageB->id,
                'school_classroom_id' => $classroomB->id,
                'school_subject_id' => $subjectB->id,
                'is_active' => false,
            ])
            ->assertForbidden();
    }

    public function test_schedule_requires_course_offering_and_assignment_when_feature_flag_enabled(): void
    {
        config(['features.course_offerings.enforce_for_scheduling' => true]);

        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::create([
            'name' => 'التنظيم',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $plannerRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'منسق أكاديمي',
            'is_active' => true,
            'can_manage_academic_planning' => true,
        ]);

        $region = EducationalDirectorate::create([
            'name' => 'North',
            'governorate' => 'Tabuk',
        ]);

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A',
            'school_id' => 'SCH-983004',
            'phone' => '0500009834',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $planner = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'department_role_id' => $plannerRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
        ]);
        $planner->assignRole('staff');

        $teacher = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'school_staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
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
            'name' => 'Physics',
            'code' => 'PHYS',
            'is_active' => true,
        ]);

        SchoolSubjectTeacherAssignment::create([
            'school_id' => $school->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
        ]);

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($planner)
            ->post(route('school.academic_planning.schedules.store'), [
                'school_term_id' => $term->id,
                'school_stage_id' => $stage->id,
                'school_classroom_id' => $classroom->id,
                'school_subject_id' => $subject->id,
                'teacher_user_id' => $teacher->id,
                'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
                'day_of_week' => 1,
                'session_index' => 2,
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('school_subject_id');
    }

    public function test_schedule_passes_with_course_offering_and_assignment_when_feature_flag_enabled(): void
    {
        config(['features.course_offerings.enforce_for_scheduling' => true]);

        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::create([
            'name' => 'التنظيم',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $plannerRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'منسق أكاديمي',
            'is_active' => true,
            'can_manage_academic_planning' => true,
        ]);

        $region = EducationalDirectorate::create([
            'name' => 'East',
            'governorate' => 'Riyadh',
        ]);

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A',
            'school_id' => 'SCH-983005',
            'phone' => '0500009835',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $planner = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'department_role_id' => $plannerRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
        ]);
        $planner->assignRole('staff');

        $teacher = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'school_staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
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
            'name' => 'Chemistry',
            'code' => 'CHEM',
            'is_active' => true,
        ]);

        SchoolSubjectTeacherAssignment::create([
            'school_id' => $school->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
        ]);

        $offering = SchoolCourseOffering::create([
            'school_id' => $school->id,
            'school_term_id' => $term->id,
            'school_stage_id' => $stage->id,
            'school_classroom_id' => $classroom->id,
            'school_subject_id' => $subject->id,
            'is_active' => true,
        ]);

        SchoolTeachingAssignment::create([
            'school_id' => $school->id,
            'school_course_offering_id' => $offering->id,
            'teacher_user_id' => $teacher->id,
            'is_active' => true,
        ]);

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($planner)
            ->post(route('school.academic_planning.schedules.store'), [
                'school_term_id' => $term->id,
                'school_stage_id' => $stage->id,
                'school_classroom_id' => $classroom->id,
                'school_subject_id' => $subject->id,
                'teacher_user_id' => $teacher->id,
                'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
                'day_of_week' => 2,
                'session_index' => 3,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('school_class_schedules', [
            'school_id' => $school->id,
            'school_term_id' => $term->id,
            'school_classroom_id' => $classroom->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 2,
            'session_index' => 3,
        ]);
    }
}

