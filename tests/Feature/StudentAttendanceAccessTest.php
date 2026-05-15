<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolAttendanceAttachment;
use App\Models\SchoolAcademicYear;
use App\Models\SchoolClassroom;
use App\Models\SchoolHoliday;
use App\Models\SchoolLeaveType;
use App\Models\SchoolStage;
use App\Models\SchoolStudent;
use App\Models\SchoolStudentAttendance;
use App\Models\SchoolStudentLeaveRequest;
use App\Models\SchoolTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentAttendanceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_attendance_index_auto_initializes_present_records_for_active_students(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Daily Init Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Daily Init School',
            'school_id' => 'SCH-942001',
            'phone' => '0500094201',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

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

        $studentOne = SchoolStudent::create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Init Student One',
            'student_code' => 'ST-9421',
            'is_active' => true,
        ]);

        $studentTwo = SchoolStudent::create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Init Student Two',
            'student_code' => 'ST-9422',
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('school_student_attendances', 0);

        $this->actingAs($manager)
            ->get(route('school.student_attendance.index', [
                'attendance_date' => '2026-03-03',
                'stage_id' => $stage->id,
                'classroom_id' => $classroom->id,
            ]))
            ->assertOk();

        $this->assertDatabaseHas('school_student_attendances', [
            'school_id' => $school->id,
            'school_student_id' => $studentOne->id,
            'school_classroom_id' => $classroom->id,
            'attendance_date' => '2026-03-03',
            'status' => SchoolStudentAttendance::STATUS_PRESENT,
        ]);

        $this->assertDatabaseHas('school_student_attendances', [
            'school_id' => $school->id,
            'school_student_id' => $studentTwo->id,
            'school_classroom_id' => $classroom->id,
            'attendance_date' => '2026-03-03',
            'status' => SchoolStudentAttendance::STATUS_PRESENT,
        ]);
    }

    public function test_daily_attendance_auto_initialization_skips_non_school_days(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Daily Init Holiday Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Daily Init Holiday School',
            'school_id' => 'SCH-942010',
            'phone' => '0500094210',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

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

        $student = SchoolStudent::create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Holiday Init Student',
            'student_code' => 'ST-942H',
            'is_active' => true,
        ]);

        SchoolHoliday::query()->create([
            'school_id' => $school->id,
            'name' => 'National Holiday',
            'start_date' => '2026-03-05',
            'end_date' => '2026-03-05',
            'return_date' => '2026-03-06',
            'is_active' => true,
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->get(route('school.student_attendance.index', [
                'attendance_date' => '2026-03-05',
                'stage_id' => $stage->id,
                'classroom_id' => $classroom->id,
            ]))
            ->assertOk();

        $this->assertDatabaseMissing('school_student_attendances', [
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'attendance_date' => '2026-03-05',
        ]);
    }

    public function test_daily_attendance_auto_initialization_is_scoped_to_the_authenticated_managers_school(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Daily Init Isolation Region',
            'governorate' => 'Riyadh',
        ]);

        $managerA = User::factory()->create(['role' => 'school_manager']);
        $managerA->assignRole('school_manager');

        $managerB = User::factory()->create(['role' => 'school_manager']);
        $managerB->assignRole('school_manager');

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'Daily Init School A',
            'school_id' => 'SCH-942002',
            'phone' => '0500094202',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerA->id,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'Daily Init School B',
            'school_id' => 'SCH-942003',
            'phone' => '0500094203',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerB->id,
        ]);

        $managerA->update(['school_id' => $schoolA->id]);
        $managerB->update(['school_id' => $schoolB->id]);

        $stageA = SchoolStage::create([
            'school_id' => $schoolA->id,
            'name' => 'Stage A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomA = SchoolClassroom::create([
            'school_id' => $schoolA->id,
            'school_stage_id' => $stageA->id,
            'name' => 'Class A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $stageB = SchoolStage::create([
            'school_id' => $schoolB->id,
            'name' => 'Stage B',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomB = SchoolClassroom::create([
            'school_id' => $schoolB->id,
            'school_stage_id' => $stageB->id,
            'name' => 'Class B',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $studentA = SchoolStudent::create([
            'school_id' => $schoolA->id,
            'school_classroom_id' => $classroomA->id,
            'full_name' => 'Scoped Student A',
            'student_code' => 'ST-942A',
            'is_active' => true,
        ]);

        $studentB = SchoolStudent::create([
            'school_id' => $schoolB->id,
            'school_classroom_id' => $classroomB->id,
            'full_name' => 'Scoped Student B',
            'student_code' => 'ST-942B',
            'is_active' => true,
        ]);

        $this->actingAs($managerA)
            ->get(route('school.student_attendance.index', [
                'attendance_date' => '2026-03-04',
                'stage_id' => $stageA->id,
                'classroom_id' => $classroomA->id,
            ]))
            ->assertOk();

        $this->assertDatabaseHas('school_student_attendances', [
            'school_id' => $schoolA->id,
            'school_student_id' => $studentA->id,
            'attendance_date' => '2026-03-04',
            'status' => SchoolStudentAttendance::STATUS_PRESENT,
        ]);

        $this->assertDatabaseMissing('school_student_attendances', [
            'school_id' => $schoolB->id,
            'school_student_id' => $studentB->id,
            'attendance_date' => '2026-03-04',
        ]);
    }

    public function test_manager_can_access_student_attendance_and_save_records_for_his_school(): void
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
            'name' => 'Attendance School',
            'school_id' => 'SCH-940001',
            'phone' => '0500009401',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

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

        $student = SchoolStudent::create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Student One',
            'student_code' => 'ST-9401',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('school.student_attendance.index'))
            ->assertOk();

        $response = $this
            ->from(route('school.student_attendance.index'))
            ->actingAs($manager)
            ->post(route('school.student_attendance.records.upsert'), [
                'attendance_date' => '2026-02-20',
                'school_stage_id' => $stage->id,
                'school_classroom_id' => $classroom->id,
                'records' => [
                    [
                        'school_student_id' => $student->id,
                        'status' => SchoolStudentAttendance::STATUS_PRESENT,
                        'check_in_time' => '07:10',
                        'check_out_time' => '12:30',
                        'notes' => 'On time',
                    ],
                ],
            ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('school_student_attendances', [
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'attendance_date' => '2026-02-20',
            'status' => SchoolStudentAttendance::STATUS_PRESENT,
        ]);
    }

    public function test_manager_saves_complete_daily_attendance_list_with_exception_rows(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Exception Flow Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Exception Flow School',
            'school_id' => 'SCH-940101',
            'phone' => '0500009411',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

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

        $studentOne = SchoolStudent::create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Exception Student One',
            'student_code' => 'ST-940101',
            'is_active' => true,
        ]);

        $studentTwo = SchoolStudent::create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Exception Student Two',
            'student_code' => 'ST-940102',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('school.student_attendance.index', [
                'attendance_date' => '2026-03-08',
                'stage_id' => $stage->id,
                'classroom_id' => $classroom->id,
            ]))
            ->assertOk();

        $this
            ->from(route('school.student_attendance.index'))
            ->actingAs($manager)
            ->post(route('school.student_attendance.records.upsert'), [
                'attendance_date' => '2026-03-08',
                'school_stage_id' => $stage->id,
                'school_classroom_id' => $classroom->id,
                'records' => [
                    [
                        'school_student_id' => $studentOne->id,
                        'status' => SchoolStudentAttendance::STATUS_ABSENT,
                    ],
                    [
                        'school_student_id' => $studentTwo->id,
                        'status' => SchoolStudentAttendance::STATUS_PRESENT,
                    ],
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('school_student_attendances', [
            'school_id' => $school->id,
            'school_student_id' => $studentOne->id,
            'attendance_date' => '2026-03-08',
            'status' => SchoolStudentAttendance::STATUS_ABSENT,
        ]);

        $this->assertDatabaseHas('school_student_attendances', [
            'school_id' => $school->id,
            'school_student_id' => $studentTwo->id,
            'attendance_date' => '2026-03-08',
            'status' => SchoolStudentAttendance::STATUS_PRESENT,
        ]);
    }

    public function test_daily_attendance_save_requires_all_active_students_in_classroom(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Complete Daily Attendance Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Complete Daily Attendance School',
            'school_id' => 'SCH-940104',
            'phone' => '0500009414',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

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

        $studentOne = SchoolStudent::create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Complete Student One',
            'student_code' => 'ST-940104-1',
            'is_active' => true,
        ]);

        $studentTwo = SchoolStudent::create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Complete Student Two',
            'student_code' => 'ST-940104-2',
            'is_active' => true,
        ]);

        $this
            ->from(route('school.student_attendance.index'))
            ->actingAs($manager)
            ->post(route('school.student_attendance.records.upsert'), [
                'attendance_date' => '2026-03-11',
                'school_stage_id' => $stage->id,
                'school_classroom_id' => $classroom->id,
                'records' => [
                    [
                        'school_student_id' => $studentOne->id,
                        'status' => SchoolStudentAttendance::STATUS_ABSENT,
                    ],
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('records');

        $this->assertDatabaseMissing('school_student_attendances', [
            'school_id' => $school->id,
            'school_student_id' => $studentOne->id,
            'attendance_date' => '2026-03-11',
        ]);

        $this->assertDatabaseMissing('school_student_attendances', [
            'school_id' => $school->id,
            'school_student_id' => $studentTwo->id,
            'attendance_date' => '2026-03-11',
        ]);
    }

    public function test_leave_status_cannot_be_set_manually_without_approved_leave_request(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Leave Guard Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Leave Guard School',
            'school_id' => 'SCH-940102',
            'phone' => '0500009412',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

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

        $student = SchoolStudent::create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Leave Guard Student',
            'student_code' => 'ST-940103',
            'is_active' => true,
        ]);

        $this
            ->from(route('school.student_attendance.index'))
            ->actingAs($manager)
            ->post(route('school.student_attendance.records.upsert'), [
                'attendance_date' => '2026-03-09',
                'school_stage_id' => $stage->id,
                'school_classroom_id' => $classroom->id,
                'records' => [
                    [
                        'school_student_id' => $student->id,
                        'status' => SchoolStudentAttendance::STATUS_LEAVE,
                    ],
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('records.0.status');

        $this->assertDatabaseMissing('school_student_attendances', [
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'attendance_date' => '2026-03-09',
            'status' => SchoolStudentAttendance::STATUS_LEAVE,
        ]);
    }

    public function test_staff_with_attendance_permission_can_access_student_attendance(): void
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
            'school_id' => 'SCH-940002',
            'phone' => '0500009402',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $department = Department::create([
            'name' => 'الشؤون الإدارية',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'موظف حضور',
            'is_active' => true,
            'can_manage_student_attendance' => true,
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
            ->get(route('school.student_attendance.index'))
            ->assertOk();
    }

    public function test_staff_without_attendance_permission_is_forbidden(): void
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
            'school_id' => 'SCH-940003',
            'phone' => '0500009403',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $department = Department::create([
            'name' => 'الشؤون الإدارية',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'موظف إداري',
            'is_active' => true,
            'can_manage_student_attendance' => false,
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
            ->get(route('school.student_attendance.index'))
            ->assertForbidden();
    }

    public function test_attendance_write_is_scoped_to_user_school_only(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::create([
            'name' => 'الشؤون الإدارية',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'موظف حضور',
            'is_active' => true,
            'can_manage_student_attendance' => true,
        ]);

        $region = EducationalDirectorate::create([
            'name' => 'South',
            'governorate' => 'Jazan',
        ]);

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A',
            'school_id' => 'SCH-940004',
            'phone' => '0500009404',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'School B',
            'school_id' => 'SCH-940005',
            'phone' => '0500009405',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $stageA = SchoolStage::create([
            'school_id' => $schoolA->id,
            'name' => 'Stage A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomA = SchoolClassroom::create([
            'school_id' => $schoolA->id,
            'school_stage_id' => $stageA->id,
            'name' => 'Class A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $stageB = SchoolStage::create([
            'school_id' => $schoolB->id,
            'name' => 'Stage B',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomB = SchoolClassroom::create([
            'school_id' => $schoolB->id,
            'school_stage_id' => $stageB->id,
            'name' => 'Class B',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $studentB = SchoolStudent::create([
            'school_id' => $schoolB->id,
            'school_classroom_id' => $classroomB->id,
            'full_name' => 'Forbidden Student',
            'student_code' => 'ST-9405',
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

        $response = $this
            ->from(route('school.student_attendance.index'))
            ->actingAs($staff)
            ->post(route('school.student_attendance.records.upsert'), [
                'attendance_date' => '2026-02-20',
                'school_stage_id' => $stageA->id,
                'school_classroom_id' => $classroomA->id,
                'records' => [
                    [
                        'school_student_id' => $studentB->id,
                        'status' => SchoolStudentAttendance::STATUS_ABSENT,
                    ],
                ],
            ]);

        $response
            ->assertStatus(302)
            ->assertSessionHasErrors('records.0.school_student_id');

        $this->assertDatabaseMissing('school_student_attendances', [
            'school_id' => $schoolA->id,
            'school_student_id' => $studentB->id,
            'attendance_date' => '2026-02-20',
        ]);
    }

    public function test_attendance_report_separates_leave_days_from_unexcused_absence_days(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Attendance Reports Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Attendance Reports School',
            'school_id' => 'SCH-940006',
            'phone' => '0500009406',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $stage = SchoolStage::create([
            'school_id' => $school->id,
            'name' => 'Intermediate',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => '2A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $student = SchoolStudent::create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Attendance Student',
            'student_code' => 'ST-9406',
            'is_active' => true,
        ]);

        SchoolStudentAttendance::query()->create([
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'school_classroom_id' => $classroom->id,
            'attendance_date' => '2026-02-10',
            'status' => SchoolStudentAttendance::STATUS_PRESENT,
            'recorded_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        SchoolStudentAttendance::query()->create([
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'school_classroom_id' => $classroom->id,
            'attendance_date' => '2026-02-11',
            'status' => SchoolStudentAttendance::STATUS_EXCUSED,
            'permission_reason' => 'Approved excuse',
            'recorded_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        SchoolStudentAttendance::query()->create([
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'school_classroom_id' => $classroom->id,
            'attendance_date' => '2026-02-12',
            'status' => SchoolStudentAttendance::STATUS_LEAVE,
            'permission_reason' => 'Approved leave',
            'recorded_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        SchoolStudentAttendance::query()->create([
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'school_classroom_id' => $classroom->id,
            'attendance_date' => '2026-02-13',
            'status' => SchoolStudentAttendance::STATUS_ABSENT,
            'recorded_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->get(route('school.student_attendance.index', [
                'attendance_date' => '2026-02-20',
                'stage_id' => $stage->id,
                'classroom_id' => $classroom->id,
                'report_date_from' => '2026-02-01',
                'report_date_to' => '2026-02-28',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('attendanceReport.totals.recorded_days', 4)
                ->where('attendanceReport.totals.present_days', 1)
                ->where('attendanceReport.totals.excused_days', 1)
                ->where('attendanceReport.totals.leave_days', 1)
                ->where('attendanceReport.totals.absent_days', 1)
                ->where('attendanceReport.totals.unexcused_absence_days', 1)
                ->where('attendanceReport.range.from', '2026-02-01')
                ->where('attendanceReport.range.to', '2026-02-28')
                ->where('attendanceReport.per_student.0.school_student_id', $student->id)
                ->where('attendanceReport.per_student.0.leave_days', 1)
                ->where('attendanceReport.per_student.0.unexcused_absence_days', 1)
            );
    }

    public function test_attendance_report_can_filter_by_day_type_and_holiday_name(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Holiday Filter Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Holiday Filter School',
            'school_id' => 'SCH-941001',
            'phone' => '0500014101',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $stage = SchoolStage::create([
            'school_id' => $school->id,
            'name' => 'Primary',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => '1B',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $student = SchoolStudent::create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Holiday Student',
            'student_code' => 'ST-9411',
            'is_active' => true,
        ]);

        SchoolHoliday::query()->create([
            'school_id' => $school->id,
            'name' => 'اليوم الوطني',
            'start_date' => '2026-02-20',
            'end_date' => '2026-02-20',
            'return_date' => '2026-02-21',
            'is_active' => true,
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        SchoolStudentAttendance::query()->create([
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'school_classroom_id' => $classroom->id,
            'attendance_date' => '2026-02-20',
            'status' => SchoolStudentAttendance::STATUS_EXCUSED,
            'permission_reason' => 'Holiday',
            'recorded_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        SchoolStudentAttendance::query()->create([
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'school_classroom_id' => $classroom->id,
            'attendance_date' => '2026-02-21',
            'status' => SchoolStudentAttendance::STATUS_PRESENT,
            'recorded_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->get(route('school.student_attendance.index', [
                'attendance_date' => '2026-02-21',
                'stage_id' => $stage->id,
                'classroom_id' => $classroom->id,
                'report_date_from' => '2026-02-20',
                'report_date_to' => '2026-02-21',
                'report_day_type' => 'HOLIDAY',
                'report_holiday_name' => 'وطني',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('reportFilters.day_type', 'HOLIDAY')
                ->where('reportFilters.holiday_name', 'وطني')
                ->where('attendanceReport.totals.recorded_days', 1)
                ->where('attendanceReport.totals.excused_days', 1)
                ->where('attendanceReport.totals.present_days', 0)
                ->where('attendanceReport.day_type_summary.holiday_days', 1)
                ->where('attendanceReport.day_type_summary.school_days', 0)
            );
    }

    public function test_attendance_report_can_filter_by_leave_type(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Leave Type Filter Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Leave Type Filter School',
            'school_id' => 'SCH-941002',
            'phone' => '0500014102',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $stage = SchoolStage::create([
            'school_id' => $school->id,
            'name' => 'Intermediate',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => '2B',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $student = SchoolStudent::create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Leave Type Student',
            'student_code' => 'ST-9412',
            'is_active' => true,
        ]);

        $annualType = SchoolLeaveType::query()->create([
            'school_id' => $school->id,
            'code' => 'ANNUAL',
            'name' => 'Annual Leave',
            'category' => 'STUDENT',
            'requires_attachment' => false,
            'is_active' => true,
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $medicalType = SchoolLeaveType::query()->create([
            'school_id' => $school->id,
            'code' => 'MEDICAL',
            'name' => 'Medical Leave',
            'category' => 'STUDENT',
            'requires_attachment' => true,
            'is_active' => true,
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $annualLeaveRequest = SchoolStudentLeaveRequest::query()->create([
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'school_leave_type_id' => $annualType->id,
            'source' => 'PRE_APPROVED',
            'status' => 'APPROVED',
            'start_date' => '2026-02-10',
            'end_date' => '2026-02-10',
            'approved_by' => $manager->id,
            'approved_at' => now(),
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $medicalLeaveRequest = SchoolStudentLeaveRequest::query()->create([
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'school_leave_type_id' => $medicalType->id,
            'source' => 'PRE_APPROVED',
            'status' => 'APPROVED',
            'start_date' => '2026-02-11',
            'end_date' => '2026-02-11',
            'approved_by' => $manager->id,
            'approved_at' => now(),
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        SchoolStudentAttendance::query()->create([
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'school_classroom_id' => $classroom->id,
            'attendance_date' => '2026-02-10',
            'status' => SchoolStudentAttendance::STATUS_LEAVE,
            'school_student_leave_request_id' => $annualLeaveRequest->id,
            'recorded_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        SchoolStudentAttendance::query()->create([
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'school_classroom_id' => $classroom->id,
            'attendance_date' => '2026-02-11',
            'status' => SchoolStudentAttendance::STATUS_LEAVE,
            'school_student_leave_request_id' => $medicalLeaveRequest->id,
            'recorded_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        SchoolStudentAttendance::query()->create([
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'school_classroom_id' => $classroom->id,
            'attendance_date' => '2026-02-12',
            'status' => SchoolStudentAttendance::STATUS_PRESENT,
            'recorded_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->get(route('school.student_attendance.index', [
                'attendance_date' => '2026-02-12',
                'stage_id' => $stage->id,
                'classroom_id' => $classroom->id,
                'report_date_from' => '2026-02-10',
                'report_date_to' => '2026-02-12',
                'report_leave_type_id' => $annualType->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('reportFilters.leave_type_id', $annualType->id)
                ->where('attendanceReport.totals.recorded_days', 1)
                ->where('attendanceReport.totals.leave_days', 1)
                ->where('attendanceReport.totals.present_days', 0)
                ->where('attendanceReport.per_student.0.leave_days', 1)
                ->where('attendanceReport.per_student.0.recorded_days', 1)
            );
    }

    public function test_manager_can_upload_daily_attendance_attachments_and_download_them(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        Storage::fake('local');

        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Attendance Attachments Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Attendance Attachments School',
            'school_id' => 'SCH-941120',
            'phone' => '0500014120',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $stage = SchoolStage::create([
            'school_id' => $school->id,
            'name' => 'Primary',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => '1C',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $student = SchoolStudent::create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Attachment Student',
            'student_code' => 'ST-941120',
            'is_active' => true,
        ]);

        $this
            ->from(route('school.student_attendance.index'))
            ->actingAs($manager)
            ->post(route('school.student_attendance.records.upsert'), [
                'attendance_date' => '2026-03-10',
                'school_stage_id' => $stage->id,
                'school_classroom_id' => $classroom->id,
                'records' => [
                    [
                        'school_student_id' => $student->id,
                        'status' => SchoolStudentAttendance::STATUS_PRESENT,
                    ],
                ],
                'attachments' => [
                    UploadedFile::fake()->image('attendance-sheet.jpg'),
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $attachment = SchoolAttendanceAttachment::query()
            ->where('school_id', $school->id)
            ->where('school_classroom_id', $classroom->id)
            ->whereDate('attendance_date', '2026-03-10')
            ->where('file_name', 'attendance-sheet.jpg')
            ->first();

        $this->assertNotNull($attachment);
        $this->assertStringContainsString("schools/{$school->id}/student-attendance/{$classroom->id}/2026-03-10/attachments", (string) $attachment?->file_path);
        Storage::disk('local')->assertExists((string) $attachment?->file_path);

        $this->actingAs($manager)
            ->get(route('school.student_attendance.attachments.download', [
                'schoolAttendanceAttachment' => (int) $attachment?->id,
            ]))
            ->assertOk();
    }

    public function test_daily_attendance_attachment_download_is_tenant_scoped(): void
    {
        Storage::fake('local');

        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Attachment Isolation Region',
            'governorate' => 'Riyadh',
        ]);

        $managerA = User::factory()->create(['role' => 'school_manager']);
        $managerA->assignRole('school_manager');

        $managerB = User::factory()->create(['role' => 'school_manager']);
        $managerB->assignRole('school_manager');

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'Attachment Isolation School A',
            'school_id' => 'SCH-941121',
            'phone' => '0500014121',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerA->id,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'Attachment Isolation School B',
            'school_id' => 'SCH-941122',
            'phone' => '0500014122',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerB->id,
        ]);

        $managerA->update(['school_id' => $schoolA->id]);
        $managerB->update(['school_id' => $schoolB->id]);

        $stageA = SchoolStage::create([
            'school_id' => $schoolA->id,
            'name' => 'Primary A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomA = SchoolClassroom::create([
            'school_id' => $schoolA->id,
            'school_stage_id' => $stageA->id,
            'name' => '2A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $path = "schools/{$schoolA->id}/student-attendance/{$classroomA->id}/2026-03-11/attachments/proof.jpg";
        Storage::disk('local')->put($path, 'fake-content');

        $attachment = SchoolAttendanceAttachment::query()->create([
            'school_id' => $schoolA->id,
            'school_classroom_id' => $classroomA->id,
            'attendance_date' => '2026-03-11',
            'file_name' => 'proof.jpg',
            'file_path' => $path,
            'mime_type' => 'image/jpeg',
            'file_size' => 100,
            'uploaded_by' => $managerA->id,
            'uploaded_at' => now(),
        ]);

        $this->actingAs($managerB)
            ->get(route('school.student_attendance.attachments.download', [
                'schoolAttendanceAttachment' => (int) $attachment->id,
            ]))
            ->assertNotFound();
    }

    public function test_manager_can_upload_daily_attendance_attachment_without_resubmitting_records(): void
    {
        Storage::fake('local');

        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Attendance Upload Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Attendance Upload School',
            'school_id' => 'SCH-941124',
            'phone' => '0500014124',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $stage = SchoolStage::create([
            'school_id' => $school->id,
            'name' => 'Primary',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'grade_name' => 'الصف الأول',
            'name' => '1E',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->post(route('school.student_attendance.attachments.store'), [
                'attendance_date' => '2026-03-13',
                'school_stage_id' => $stage->id,
                'school_classroom_id' => $classroom->id,
                'classroom_grade_name' => 'الصف الأول',
                'attachments' => [
                    UploadedFile::fake()->create('attendance-proof.pdf', 120, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('school.student_attendance.index', [
                'attendance_date' => '2026-03-13',
                'classroom_id' => $classroom->id,
                'stage_id' => $stage->id,
                'classroom_grade_name' => 'الصف الأول',
            ]))
            ->assertSessionHasNoErrors();

        $attachment = SchoolAttendanceAttachment::query()
            ->where('school_id', $school->id)
            ->where('school_classroom_id', $classroom->id)
            ->whereDate('attendance_date', '2026-03-13')
            ->where('file_name', 'attendance-proof.pdf')
            ->first();

        $this->assertNotNull($attachment);
        Storage::disk('local')->assertExists((string) $attachment?->file_path);
    }

    public function test_manager_cannot_upload_daily_attendance_attachment_to_foreign_classroom(): void
    {
        Storage::fake('local');

        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Attendance Upload Isolation Region',
            'governorate' => 'Riyadh',
        ]);

        $managerA = User::factory()->create(['role' => 'school_manager']);
        $managerA->assignRole('school_manager');

        $managerB = User::factory()->create(['role' => 'school_manager']);
        $managerB->assignRole('school_manager');

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'Attendance Upload School A',
            'school_id' => 'SCH-941125A',
            'phone' => '0500014125',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerA->id,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'Attendance Upload School B',
            'school_id' => 'SCH-941125B',
            'phone' => '0500015125',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerB->id,
        ]);

        $managerA->update(['school_id' => $schoolA->id]);
        $managerB->update(['school_id' => $schoolB->id]);

        $stageA = SchoolStage::create([
            'school_id' => $schoolA->id,
            'name' => 'Primary A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomA = SchoolClassroom::create([
            'school_id' => $schoolA->id,
            'school_stage_id' => $stageA->id,
            'grade_name' => 'الصف الأول',
            'name' => '1F',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->from(route('school.student_attendance.index'))
            ->actingAs($managerB)
            ->post(route('school.student_attendance.attachments.store'), [
                'attendance_date' => '2026-03-14',
                'school_stage_id' => $stageA->id,
                'school_classroom_id' => $classroomA->id,
                'classroom_grade_name' => 'الصف الأول',
                'attachments' => [
                    UploadedFile::fake()->create('foreign-proof.pdf', 120, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('school.student_attendance.index', absolute: false))
            ->assertSessionHasErrors('school_classroom_id');

        $this->assertDatabaseCount('school_attendance_attachments', 0);
    }

    public function test_manager_can_delete_daily_attendance_attachment_and_preserve_selected_filters(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Attachment Delete Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Attachment Delete School',
            'school_id' => 'SCH-941123',
            'phone' => '0500014123',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $stage = SchoolStage::create([
            'school_id' => $school->id,
            'name' => 'Primary',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'grade_name' => 'الصف الأول',
            'name' => '1D',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $path = "schools/{$school->id}/student-attendance/{$classroom->id}/2026-03-12/attachments/delete-proof.jpg";
        Storage::disk('local')->put($path, 'fake-content');

        $attachment = SchoolAttendanceAttachment::query()->create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'attendance_date' => '2026-03-12',
            'file_name' => 'delete-proof.jpg',
            'file_path' => $path,
            'mime_type' => 'image/jpeg',
            'file_size' => 100,
            'uploaded_by' => $manager->id,
            'uploaded_at' => now(),
        ]);

        $this->actingAs($manager)
            ->delete(route('school.student_attendance.attachments.destroy', [
                'schoolAttendanceAttachment' => (int) $attachment->id,
            ]), [
                'attendance_date' => '2026-03-12',
                'stage_id' => $stage->id,
                'classroom_grade_name' => 'الصف الأول',
            ])
            ->assertRedirect(route('school.student_attendance.index', [
                'attendance_date' => '2026-03-12',
                'classroom_id' => $classroom->id,
                'stage_id' => $stage->id,
                'classroom_grade_name' => 'الصف الأول',
            ]));

        $this->assertDatabaseMissing('school_attendance_attachments', [
            'id' => $attachment->id,
            'school_id' => $school->id,
        ]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_manager_can_export_attendance_report_csv_for_his_school_classroom(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Export Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Export School',
            'school_id' => 'SCH-940007',
            'phone' => '0500009407',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $stage = SchoolStage::create([
            'school_id' => $school->id,
            'name' => 'Secondary',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => '3A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $student = SchoolStudent::create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Export Student',
            'student_code' => 'ST-9407',
            'is_active' => true,
        ]);

        SchoolStudentAttendance::query()->create([
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'school_classroom_id' => $classroom->id,
            'attendance_date' => '2026-02-15',
            'status' => SchoolStudentAttendance::STATUS_LEAVE,
            'recorded_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        SchoolStudentAttendance::query()->create([
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'school_classroom_id' => $classroom->id,
            'attendance_date' => '2026-02-16',
            'status' => SchoolStudentAttendance::STATUS_ABSENT,
            'recorded_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $response = $this->actingAs($manager)->get(
            route('school.student_attendance.report.export', [
                'attendance_date' => '2026-02-20',
                'school_stage_id' => $stage->id,
                'school_classroom_id' => $classroom->id,
                'report_date_from' => '2026-02-01',
                'report_date_to' => '2026-02-28',
            ])
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader(
            'content-disposition',
            'attachment; filename=attendance-report-' . $classroom->id . '-2026-02-01-to-2026-02-28.csv'
        );

        $csv = $response->streamedContent();
        $this->assertStringContainsString('student_name,student_code,leave_days,unexcused_absence_days,present_days,excused_days,recorded_days', $csv);
        $this->assertMatchesRegularExpression('/"?Export Student"?,ST-9407,1,1,0,0,2/', $csv);
    }

    public function test_export_report_rejects_classroom_from_other_school(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Export Isolation Region',
            'governorate' => 'Riyadh',
        ]);

        $managerA = User::factory()->create(['role' => 'school_manager']);
        $managerA->assignRole('school_manager');

        $managerB = User::factory()->create(['role' => 'school_manager']);
        $managerB->assignRole('school_manager');

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'Export School A',
            'school_id' => 'SCH-940008',
            'phone' => '0500009408',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerA->id,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'Export School B',
            'school_id' => 'SCH-940009',
            'phone' => '0500009409',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerB->id,
        ]);

        $managerA->update(['school_id' => $schoolA->id]);
        $managerB->update(['school_id' => $schoolB->id]);

        $stageB = SchoolStage::create([
            'school_id' => $schoolB->id,
            'name' => 'Forbidden Stage',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomB = SchoolClassroom::create([
            'school_id' => $schoolB->id,
            'school_stage_id' => $stageB->id,
            'name' => 'Forbidden Classroom',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($managerA)
            ->getJson(route('school.student_attendance.report.export', [
                'attendance_date' => '2026-02-20',
                'school_classroom_id' => $classroomB->id,
                'report_date_from' => '2026-02-01',
                'report_date_to' => '2026-02-28',
            ]));

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('school_classroom_id');
    }

    public function test_daily_attendance_auto_initialization_skips_dates_outside_academic_period_when_references_exist(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Academic Guard Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Academic Guard School',
            'school_id' => 'SCH-942020',
            'phone' => '0500094220',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $year = SchoolAcademicYear::create([
            'school_id' => $school->id,
            'name' => 'Year 2026-2027',
            'starts_on' => '2026-08-20',
            'ends_on' => '2027-06-20',
            'is_active' => true,
        ]);

        SchoolTerm::create([
            'school_id' => $school->id,
            'school_academic_year_id' => $year->id,
            'name' => 'Term 1',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-15',
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

        $student = SchoolStudent::create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Guarded Student',
            'student_code' => 'ST-942G',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('school.student_attendance.index', [
                'attendance_date' => '2026-08-15',
                'stage_id' => $stage->id,
                'classroom_id' => $classroom->id,
            ]))
            ->assertOk();

        $this->assertDatabaseMissing('school_student_attendances', [
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'attendance_date' => '2026-08-15',
        ]);
    }

    public function test_manager_cannot_save_attendance_outside_registered_academic_term(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Academic Guard Save Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Academic Guard Save School',
            'school_id' => 'SCH-942021',
            'phone' => '0500094221',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $year = SchoolAcademicYear::create([
            'school_id' => $school->id,
            'name' => 'Year 2026-2027',
            'starts_on' => '2026-08-20',
            'ends_on' => '2027-06-20',
            'is_active' => true,
        ]);

        SchoolTerm::create([
            'school_id' => $school->id,
            'school_academic_year_id' => $year->id,
            'name' => 'Term 1',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-15',
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

        $student = SchoolStudent::create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Blocked Attendance Student',
            'student_code' => 'ST-942B',
            'is_active' => true,
        ]);

        $response = $this->from(route('school.student_attendance.index'))
            ->actingAs($manager)
            ->post(route('school.student_attendance.records.upsert'), [
                'attendance_date' => '2026-08-15',
                'school_stage_id' => $stage->id,
                'school_classroom_id' => $classroom->id,
                'records' => [
                    [
                        'school_student_id' => $student->id,
                        'status' => SchoolStudentAttendance::STATUS_PRESENT,
                        'check_in_time' => '07:10',
                        'check_out_time' => '12:30',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('school.student_attendance.index', absolute: false))
            ->assertSessionHasErrors('attendance_date');

        $this->assertDatabaseMissing('school_student_attendances', [
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'attendance_date' => '2026-08-15',
        ]);
    }

    public function test_manager_can_save_attendance_inside_registered_academic_term(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Academic Allowed Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Academic Allowed School',
            'school_id' => 'SCH-942022',
            'phone' => '0500094222',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $year = SchoolAcademicYear::create([
            'school_id' => $school->id,
            'name' => 'Year 2026-2027',
            'starts_on' => '2026-08-20',
            'ends_on' => '2027-06-20',
            'is_active' => true,
        ]);

        SchoolTerm::create([
            'school_id' => $school->id,
            'school_academic_year_id' => $year->id,
            'name' => 'Term 1',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-15',
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

        $student = SchoolStudent::create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Allowed Attendance Student',
            'student_code' => 'ST-942C',
            'is_active' => true,
        ]);

        $response = $this->from(route('school.student_attendance.index'))
            ->actingAs($manager)
            ->post(route('school.student_attendance.records.upsert'), [
                'attendance_date' => '2026-09-03',
                'school_stage_id' => $stage->id,
                'school_classroom_id' => $classroom->id,
                'records' => [
                    [
                        'school_student_id' => $student->id,
                        'status' => SchoolStudentAttendance::STATUS_PRESENT,
                        'check_in_time' => '07:10',
                        'check_out_time' => '12:30',
                    ],
                ],
            ]);

        $response
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('school_student_attendances', [
            'school_id' => $school->id,
            'school_student_id' => $student->id,
            'attendance_date' => '2026-09-03',
            'status' => SchoolStudentAttendance::STATUS_PRESENT,
        ]);
    }
}

