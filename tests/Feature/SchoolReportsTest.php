<?php

namespace Tests\Feature;

use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolClassroom;
use App\Models\SchoolLeaveType;
use App\Models\SchoolStage;
use App\Models\SchoolStudent;
use App\Models\SchoolStudentAttendance;
use App\Models\SchoolStudentLeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_school_reports_with_tenant_scoped_data_and_export_json(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Reports Region',
            'governorate' => 'Riyadh',
        ]);

        $managerA = User::factory()->create(['role' => 'school_manager']);
        $managerA->assignRole('school_manager');
        $managerB = User::factory()->create(['role' => 'school_manager']);
        $managerB->assignRole('school_manager');

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'Reports School A',
            'school_id' => 'SCH-REPORT-1',
            'phone' => '0501000001',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerA->id,
        ]);
        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'Reports School B',
            'school_id' => 'SCH-REPORT-2',
            'phone' => '0501000002',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerB->id,
        ]);

        $managerA->update(['school_id' => $schoolA->id]);
        $managerB->update(['school_id' => $schoolB->id]);

        $stageA = SchoolStage::create([
            'school_id' => $schoolA->id,
            'name' => 'Primary',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $stageB = SchoolStage::create([
            'school_id' => $schoolB->id,
            'name' => 'Secondary',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomA = SchoolClassroom::create([
            'school_id' => $schoolA->id,
            'school_stage_id' => $stageA->id,
            'grade_name' => 'الصف الأول',
            'name' => 'A1',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $classroomB = SchoolClassroom::create([
            'school_id' => $schoolB->id,
            'school_stage_id' => $stageB->id,
            'grade_name' => 'الصف الثاني',
            'name' => 'B1',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $studentA = SchoolStudent::create([
            'school_id' => $schoolA->id,
            'school_classroom_id' => $classroomA->id,
            'full_name' => 'Scoped Student A',
            'student_code' => 'ST-A-01',
            'is_active' => true,
        ]);
        $studentB = SchoolStudent::create([
            'school_id' => $schoolB->id,
            'school_classroom_id' => $classroomB->id,
            'full_name' => 'Scoped Student B',
            'student_code' => 'ST-B-01',
            'is_active' => true,
        ]);

        SchoolStudentAttendance::create([
            'school_id' => $schoolA->id,
            'school_student_id' => $studentA->id,
            'school_classroom_id' => $classroomA->id,
            'attendance_date' => '2026-03-01',
            'status' => SchoolStudentAttendance::STATUS_PRESENT,
            'recorded_by' => $managerA->id,
            'updated_by' => $managerA->id,
        ]);
        SchoolStudentAttendance::create([
            'school_id' => $schoolB->id,
            'school_student_id' => $studentB->id,
            'school_classroom_id' => $classroomB->id,
            'attendance_date' => '2026-03-01',
            'status' => SchoolStudentAttendance::STATUS_ABSENT,
            'recorded_by' => $managerB->id,
            'updated_by' => $managerB->id,
        ]);

        $leaveTypeA = SchoolLeaveType::create([
            'school_id' => $schoolA->id,
            'code' => 'L-A',
            'name' => 'Annual Leave A',
            'category' => 'STUDENT',
            'requires_attachment' => false,
            'is_active' => true,
            'created_by' => $managerA->id,
            'updated_by' => $managerA->id,
        ]);
        SchoolLeaveType::create([
            'school_id' => $schoolB->id,
            'code' => 'L-B',
            'name' => 'Annual Leave B',
            'category' => 'STUDENT',
            'requires_attachment' => false,
            'is_active' => true,
            'created_by' => $managerB->id,
            'updated_by' => $managerB->id,
        ]);

        SchoolStudentLeaveRequest::create([
            'school_id' => $schoolA->id,
            'school_student_id' => $studentA->id,
            'school_leave_type_id' => $leaveTypeA->id,
            'source' => SchoolStudentLeaveRequest::SOURCE_PRE_APPROVED,
            'status' => SchoolStudentLeaveRequest::STATUS_PENDING,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-02',
            'created_by' => $managerA->id,
            'updated_by' => $managerA->id,
        ]);

        $teacherA = User::factory()->create([
            'role' => 'staff',
            'school_id' => $schoolA->id,
            'school_staff_type' => User::SCHOOL_STAFF_EDUCATIONAL,
            'is_active' => true,
            'can_manage_student_structure' => true,
            'can_manage_student_attendance' => true,
        ]);
        $teacherA->assignRole('staff');

        $response = $this->actingAs($managerA)
            ->get(route('school.reports.index', ['entity' => 'students']));

        $response
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selectedEntity', 'students')
                ->where('summary.students_count', 1)
                ->where('summary.attendance_records_count', 1)
                ->where('summary.leave_requests_count', 1)
                ->where('summary.teachers_count', 1)
                ->where('table.rows.0.student_code', 'ST-A-01')
            );
        $response->assertDontSee('ST-B-01');

        $export = $this->actingAs($managerA)
            ->get(route('school.reports.export', [
                'entity' => 'students',
                'format' => 'json',
            ]));

        $export
            ->assertOk()
            ->assertJsonPath('entity', 'students')
            ->assertJsonPath('datasets.0.entity', 'students')
            ->assertJsonPath('datasets.0.rows.0.student_code', 'ST-A-01');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'school_reports.exported',
            'entity_type' => 'school_reports',
            'user_id' => $managerA->id,
        ]);
    }

    public function test_staff_without_any_school_module_permission_cannot_access_reports_page(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Reports Access Region',
            'governorate' => 'Makkah',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Reports Access School',
            'school_id' => 'SCH-REPORT-3',
            'phone' => '0501000003',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);
        $manager->update(['school_id' => $school->id]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'is_active' => true,
            'can_manage_student_structure' => false,
            'can_manage_student_attendance' => false,
            'can_manage_academic_planning' => false,
            'can_manage_student_leaves' => false,
            'can_manage_leave_types' => false,
            'can_manage_school_calendar' => false,
            'can_manage_school_holidays' => false,
        ]);
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('school.reports.index'))
            ->assertForbidden();
    }
}
