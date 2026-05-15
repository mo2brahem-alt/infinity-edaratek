<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolClassSchedule;
use App\Models\SchoolClassroom;
use App\Models\SchoolStage;
use App\Models\SchoolSubject;
use App\Models\SchoolSubjectTeacherAssignment;
use App\Models\SchoolTeacherAvailability;
use App\Models\SchoolTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherAvailabilitySchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_can_sync_teacher_weekly_availability_for_his_school(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::create([
            'name' => 'تنظيم الجداول',
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
            'school_id' => 'SCH-982001',
            'phone' => '0500009821',
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

        $this->from(route('school.academic_planning.index'))
            ->actingAs($planner)
            ->post(route('school.academic_planning.teachers.availability.sync', $teacher->id), [
                'slots' => [
                    ['day_of_week' => 1, 'session_index' => 2],
                    ['day_of_week' => 1, 'session_index' => 3],
                ],
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $this->assertDatabaseHas('school_teacher_availabilities', [
            'school_id' => $school->id,
            'teacher_user_id' => $teacher->id,
            'day_of_week' => 1,
            'session_index' => 2,
        ]);
    }

    public function test_teacher_availability_sync_is_scoped_to_staff_school(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::create([
            'name' => 'تنظيم',
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
            'school_id' => 'SCH-982002',
            'phone' => '0500009822',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'School B',
            'school_id' => 'SCH-982003',
            'phone' => '0500009823',
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

        $foreignTeacher = User::factory()->create([
            'role' => 'staff',
            'school_id' => $schoolB->id,
            'department_id' => $department->id,
            'school_staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
            'is_active' => true,
        ]);
        $foreignTeacher->assignRole('staff');

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($planner)
            ->post(route('school.academic_planning.teachers.availability.sync', $foreignTeacher->id), [
                'slots' => [
                    ['day_of_week' => 2, 'session_index' => 4],
                ],
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('teacher_user_id');

        $this->assertDatabaseMissing('school_teacher_availabilities', [
            'teacher_user_id' => $foreignTeacher->id,
        ]);
    }

    public function test_weekly_schedule_rejects_teacher_conflict_across_classrooms(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::create([
            'name' => 'التنظيم الأكاديمي',
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
            'school_id' => 'SCH-982004',
            'phone' => '0500009824',
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

        $classroomA = SchoolClassroom::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => '1A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomB = SchoolClassroom::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => '1B',
            'sort_order' => 2,
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

        SchoolClassSchedule::create([
            'school_id' => $school->id,
            'school_term_id' => $term->id,
            'school_stage_id' => $stage->id,
            'school_classroom_id' => $classroomA->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 1,
            'session_index' => 2,
            'is_active' => true,
        ]);

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($planner)
            ->post(route('school.academic_planning.schedules.store'), [
                'school_term_id' => $term->id,
                'school_stage_id' => $stage->id,
                'school_classroom_id' => $classroomB->id,
                'school_subject_id' => $subject->id,
                'teacher_user_id' => $teacher->id,
                'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
                'day_of_week' => 1,
                'session_index' => 2,
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('teacher_user_id');
    }

    public function test_weekly_schedule_respects_configured_teacher_availability(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::create([
            'name' => 'التنسيق',
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
            'school_id' => 'SCH-982005',
            'phone' => '0500009825',
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
            'name' => 'Science',
            'code' => 'SCI',
            'is_active' => true,
        ]);

        SchoolSubjectTeacherAssignment::create([
            'school_id' => $school->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
        ]);

        SchoolTeacherAvailability::create([
            'school_id' => $school->id,
            'teacher_user_id' => $teacher->id,
            'day_of_week' => 1,
            'session_index' => 2,
            'is_available' => true,
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
                'day_of_week' => 3,
                'session_index' => 2,
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('teacher_user_id');

        $this->assertDatabaseMissing('school_class_schedules', [
            'school_id' => $school->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
            'day_of_week' => 3,
            'session_index' => 2,
        ]);
    }
}

