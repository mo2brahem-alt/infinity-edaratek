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
use App\Models\SchoolTimetableVersion;
use App\Models\SchoolTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TimetableVersionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_can_create_update_and_publish_timetable_versions_for_his_school(): void
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
            'name' => 'Central',
            'governorate' => 'Riyadh',
        ]);

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A',
            'school_id' => 'SCH-984001',
            'phone' => '0500009841',
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

        $term = SchoolTerm::create([
            'school_id' => $school->id,
            'name' => 'Term 1',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-01',
            'is_active' => true,
        ]);

        $this->from(route('school.academic_planning.index'))
            ->actingAs($planner)
            ->post(route('school.academic_planning.versions.store'), [
                'school_term_id' => $term->id,
                'name' => 'Draft A',
            ])
            ->assertRedirect(route('school.academic_planning.index', [
                'term_id' => $term->id,
            ], absolute: false));

        $versionA = SchoolTimetableVersion::query()
            ->where('school_id', $school->id)
            ->where('school_term_id', $term->id)
            ->where('name', 'Draft A')
            ->firstOrFail();

        $this->actingAs($planner)
            ->put(route('school.academic_planning.versions.update', $versionA->id), [
                'name' => 'Draft A1',
            ])
            ->assertRedirect();

        $this->from(route('school.academic_planning.index'))
            ->actingAs($planner)
            ->post(route('school.academic_planning.versions.store'), [
                'school_term_id' => $term->id,
                'name' => 'Draft B',
            ])
            ->assertRedirect(route('school.academic_planning.index', [
                'term_id' => $term->id,
            ], absolute: false));

        $versionB = SchoolTimetableVersion::query()
            ->where('school_id', $school->id)
            ->where('school_term_id', $term->id)
            ->where('name', 'Draft B')
            ->firstOrFail();

        $this->actingAs($planner)
            ->post(route('school.academic_planning.versions.publish', $versionA->id))
            ->assertRedirect();

        $this->actingAs($planner)
            ->post(route('school.academic_planning.versions.publish', $versionB->id))
            ->assertRedirect();

        $this->assertDatabaseHas('school_timetable_versions', [
            'id' => $versionA->id,
            'school_id' => $school->id,
            'is_published' => false,
        ]);

        $this->assertDatabaseHas('school_timetable_versions', [
            'id' => $versionB->id,
            'school_id' => $school->id,
            'is_published' => true,
        ]);
    }

    public function test_staff_cannot_update_or_publish_timetable_version_of_another_school(): void
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
            'school_id' => 'SCH-984002',
            'phone' => '0500009842',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'School B',
            'school_id' => 'SCH-984003',
            'phone' => '0500009843',
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

        $foreignTerm = SchoolTerm::create([
            'school_id' => $schoolB->id,
            'name' => 'Term B',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-01',
            'is_active' => true,
        ]);

        $foreignVersion = SchoolTimetableVersion::create([
            'school_id' => $schoolB->id,
            'school_term_id' => $foreignTerm->id,
            'name' => 'Foreign Version',
            'is_published' => false,
        ]);

        $this->actingAs($planner)
            ->put(route('school.academic_planning.versions.update', $foreignVersion->id), [
                'name' => 'Updated Name',
            ])
            ->assertForbidden();

        $this->actingAs($planner)
            ->post(route('school.academic_planning.versions.publish', $foreignVersion->id))
            ->assertForbidden();
    }

    public function test_schedule_accepts_valid_timetable_version_for_same_school_and_term(): void
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
            'name' => 'North',
            'governorate' => 'Tabuk',
        ]);

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A',
            'school_id' => 'SCH-984004',
            'phone' => '0500009844',
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

        $version = SchoolTimetableVersion::create([
            'school_id' => $school->id,
            'school_term_id' => $term->id,
            'name' => 'Draft 1',
            'is_published' => false,
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

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($planner)
            ->post(route('school.academic_planning.schedules.store'), [
                'school_term_id' => $term->id,
                'school_timetable_version_id' => $version->id,
                'school_stage_id' => $stage->id,
                'school_classroom_id' => $classroom->id,
                'school_subject_id' => $subject->id,
                'teacher_user_id' => $teacher->id,
                'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
                'day_of_week' => 1,
                'session_index' => 2,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('school_class_schedules', [
            'school_id' => $school->id,
            'school_term_id' => $term->id,
            'school_timetable_version_id' => $version->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 1,
            'session_index' => 2,
        ]);
    }

    public function test_schedule_rejects_timetable_version_from_other_school(): void
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

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A',
            'school_id' => 'SCH-984005',
            'phone' => '0500009845',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'School B',
            'school_id' => 'SCH-984006',
            'phone' => '0500009846',
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

        $teacher = User::factory()->create([
            'role' => 'staff',
            'school_id' => $schoolA->id,
            'department_id' => $department->id,
            'school_staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
            'is_active' => true,
        ]);
        $teacher->assignRole('staff');

        $termA = SchoolTerm::create([
            'school_id' => $schoolA->id,
            'name' => 'Term A',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-01',
            'is_active' => true,
        ]);

        $termB = SchoolTerm::create([
            'school_id' => $schoolB->id,
            'name' => 'Term B',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-01',
            'is_active' => true,
        ]);

        $foreignVersion = SchoolTimetableVersion::create([
            'school_id' => $schoolB->id,
            'school_term_id' => $termB->id,
            'name' => 'Foreign Draft',
            'is_published' => false,
        ]);

        $stage = SchoolStage::create([
            'school_id' => $schoolA->id,
            'name' => 'Primary',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::create([
            'school_id' => $schoolA->id,
            'school_stage_id' => $stage->id,
            'name' => '1A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subject = SchoolSubject::create([
            'school_id' => $schoolA->id,
            'name' => 'Science',
            'code' => 'SCI',
            'is_active' => true,
        ]);

        SchoolSubjectTeacherAssignment::create([
            'school_id' => $schoolA->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
        ]);

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($planner)
            ->post(route('school.academic_planning.schedules.store'), [
                'school_term_id' => $termA->id,
                'school_timetable_version_id' => $foreignVersion->id,
                'school_stage_id' => $stage->id,
                'school_classroom_id' => $classroom->id,
                'school_subject_id' => $subject->id,
                'teacher_user_id' => $teacher->id,
                'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
                'day_of_week' => 2,
                'session_index' => 3,
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('school_timetable_version_id');

        $this->assertDatabaseMissing('school_class_schedules', [
            'school_id' => $schoolA->id,
            'school_timetable_version_id' => $foreignVersion->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
        ]);
    }
}

