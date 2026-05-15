<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolClassroom;
use App\Models\SchoolStage;
use App\Models\SchoolStudent;
use App\Models\SchoolStudentAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentStructureAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_access_student_structure_and_create_stage_for_his_school(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Central',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Manager School',
            'school_id' => 'SCH-830001',
            'phone' => '0500008301',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $this->actingAs($manager)
            ->get(route('school.student_structure.index'))
            ->assertOk();

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->post(route('school.student_structure.stages.store'), [
                'name' => 'Primary Stage',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false));

        $this->assertDatabaseHas('school_stages', [
            'school_id' => $school->id,
            'name' => 'Primary Stage',
        ]);
    }

    public function test_staff_with_configured_permission_can_access_student_structure(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'West',
            'governorate' => 'Makkah',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A',
            'school_id' => 'SCH-830002',
            'phone' => '0500008302',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $department = Department::create([
            'name' => 'Administrative Affairs',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'Student Affairs Officer',
            'is_active' => true,
            'can_manage_student_structure' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
        ]);
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('school.student_structure.index'))
            ->assertOk();
    }

    public function test_staff_without_permission_is_forbidden_from_student_structure(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'North',
            'governorate' => 'Tabuk',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'School B',
            'school_id' => 'SCH-830003',
            'phone' => '0500008303',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $department = Department::create([
            'name' => 'Administrative Affairs',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'Administrative Employee',
            'is_active' => true,
            'can_manage_student_structure' => false,
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
        ]);
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('school.student_structure.index'))
            ->assertForbidden();
    }

    public function test_student_structure_update_is_scoped_to_staff_school_only(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::create([
            'name' => 'Administrative Affairs',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'Student Affairs Officer',
            'is_active' => true,
            'can_manage_student_structure' => true,
        ]);

        $region = EducationalDirectorate::create([
            'name' => 'South',
            'governorate' => 'Jazan',
        ]);

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A',
            'school_id' => 'SCH-830004',
            'phone' => '0500008304',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'School B',
            'school_id' => 'SCH-830005',
            'phone' => '0500008305',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $forbiddenStage = SchoolStage::create([
            'school_id' => $schoolB->id,
            'name' => 'Out Of Scope Stage',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'school_id' => $schoolA->id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
        ]);
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->put(route('school.student_structure.stages.update', $forbiddenStage->id), [
                'name' => 'Hijack Attempt',
                'sort_order' => 2,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('school_stages', [
            'id' => $forbiddenStage->id,
            'name' => 'Out Of Scope Stage',
        ]);
    }

    public function test_manager_cannot_delete_stage_or_classroom_or_student_when_dependencies_exist(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Central-Delete-Guard',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Delete Guard School',
            'school_id' => 'SCH-830006',
            'phone' => '0500008306',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $stage = SchoolStage::query()->create([
            'school_id' => $school->id,
            'name' => 'Stage Guard',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::query()->create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => 'Class Guard',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $student = SchoolStudent::query()->create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Student Guard',
            'student_code' => 'ST-830006',
            'is_active' => true,
        ]);

        SchoolStudentAttendance::query()->create([
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'school_classroom_id' => $classroom->id,
            'attendance_date' => '2026-03-01',
            'status' => SchoolStudentAttendance::STATUS_ABSENT,
            'recorded_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->delete(route('school.student_structure.students.destroy', $student->id))
            ->assertRedirect(route('school.student_structure.index', absolute: false))
            ->assertSessionHasErrors('student');

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->delete(route('school.student_structure.classrooms.destroy', $classroom->id))
            ->assertRedirect(route('school.student_structure.index', absolute: false))
            ->assertSessionHasErrors('classroom');

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->delete(route('school.student_structure.stages.destroy', $stage->id))
            ->assertRedirect(route('school.student_structure.index', absolute: false))
            ->assertSessionHasErrors('stage');

        $this->assertDatabaseHas('school_students', ['id' => $student->id]);
        $this->assertDatabaseHas('school_classrooms', ['id' => $classroom->id]);
        $this->assertDatabaseHas('school_stages', ['id' => $stage->id]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student_structure.student.delete_blocked',
            'entity_type' => 'school_student',
            'entity_id' => $student->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_student_structure_delete_is_tenant_scoped(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::create([
            'name' => 'Administrative Affairs',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'Student Affairs Officer',
            'is_active' => true,
            'can_manage_student_structure' => true,
        ]);

        $region = EducationalDirectorate::create([
            'name' => 'South-Delete-Scope',
            'governorate' => 'Jazan',
        ]);

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A Scope',
            'school_id' => 'SCH-830007',
            'phone' => '0500008307',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'School B Scope',
            'school_id' => 'SCH-830008',
            'phone' => '0500008308',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $stageB = SchoolStage::create([
            'school_id' => $schoolB->id,
            'name' => 'Scope Stage B',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomB = SchoolClassroom::create([
            'school_id' => $schoolB->id,
            'school_stage_id' => $stageB->id,
            'name' => 'Scope Classroom B',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $forbiddenStudent = SchoolStudent::create([
            'school_id' => $schoolB->id,
            'school_classroom_id' => $classroomB->id,
            'full_name' => 'Forbidden Student',
            'student_code' => 'ST-830008',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'school_id' => $schoolA->id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
        ]);
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->delete(route('school.student_structure.students.destroy', $forbiddenStudent->id))
            ->assertForbidden();

        $this->assertDatabaseHas('school_students', [
            'id' => $forbiddenStudent->id,
            'school_id' => $schoolB->id,
        ]);
    }
}

