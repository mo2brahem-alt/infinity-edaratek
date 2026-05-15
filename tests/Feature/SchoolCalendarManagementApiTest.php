<?php

namespace Tests\Feature;

use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolAcademicYear;
use App\Models\SchoolClassroom;
use App\Models\SchoolHoliday;
use App\Models\SchoolLeaveType;
use App\Models\SchoolStage;
use App\Models\SchoolStudent;
use App\Models\SchoolStudentAttendance;
use App\Models\SchoolStudentLeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolCalendarManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_with_granted_leave_calendar_permissions_can_manage_related_endpoints(): void
    {
        $this->seedBaseRoles();
        $manager = $this->createSchoolManagerWithSchool('SCH-970001');
        $this->createAcademicYear((int) $manager->school_id, '2026-2027', '2026-08-15', '2027-06-30');

        $staff = User::factory()->create([
            'role' => 'staff',
            'school_id' => (int) $manager->school_id,
            'is_active' => true,
            'can_manage_student_leaves' => false,
            'can_manage_leave_types' => true,
            'can_manage_school_calendar' => true,
            'can_manage_school_holidays' => true,
        ]);
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->postJson(route('api.school.leave_types.store'), [
                'name' => 'Exam Leave',
                'code' => 'EXAM',
                'requires_attachment' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Exam Leave')
            ->assertJsonPath('data.code', 'EXAM');

        $this->actingAs($staff)
            ->putJson(route('api.school.calendar_settings.update'), [
                'week_start_day' => 0,
                'weekly_off_days' => [5, 6],
            ])
            ->assertOk()
            ->assertJsonPath('data.week_start_day', 0)
            ->assertJsonPath('data.weekly_off_days.0', 5)
            ->assertJsonPath('data.weekly_off_days.1', 6);

        $this->actingAs($staff)
            ->postJson(route('api.school.holidays.store'), [
                'name' => 'National Day',
                'start_date' => '2026-09-23',
                'days_count' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'National Day')
            ->assertJsonPath('data.start_date', '2026-09-23');

        $this->assertDatabaseHas('school_leave_types', [
            'school_id' => (int) $manager->school_id,
            'name' => 'Exam Leave',
            'code' => 'EXAM',
        ]);

        $this->assertDatabaseHas('school_holidays', [
            'school_id' => (int) $manager->school_id,
            'name' => 'National Day',
            'start_date' => '2026-09-23',
            'end_date' => '2026-09-23',
        ]);
    }

    public function test_holiday_end_and_return_dates_skip_weekly_off_days_when_days_count_is_used(): void
    {
        $this->seedBaseRoles();
        $manager = $this->createSchoolManagerWithSchool('SCH-970013');
        $this->createAcademicYear((int) $manager->school_id, '2026-2027', '2026-08-15', '2027-06-30');

        $this->actingAs($manager)
            ->putJson(route('api.school.calendar_settings.update'), [
                'week_start_day' => 0,
                'weekly_off_days' => [5, 6],
            ])
            ->assertOk();

        $response = $this->actingAs($manager)
            ->postJson(route('api.school.holidays.store'), [
                'name' => 'Weekend Aware Holiday',
                'start_date' => '2026-09-24',
                'days_count' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('data.start_date', '2026-09-24')
            ->assertJsonPath('data.end_date', '2026-09-27')
            ->assertJsonPath('data.return_date', '2026-09-28');

        $holidayId = (int) $response->json('data.id');

        $this->assertTrue(
            SchoolHoliday::query()
                ->whereKey($holidayId)
                ->where('school_id', (int) $manager->school_id)
                ->whereDate('start_date', '2026-09-24')
                ->whereDate('end_date', '2026-09-27')
                ->whereDate('return_date', '2026-09-28')
                ->exists()
        );
    }

    public function test_staff_without_permissions_is_forbidden_from_leave_type_calendar_and_holiday_management(): void
    {
        $this->seedBaseRoles();
        $manager = $this->createSchoolManagerWithSchool('SCH-970002');
        $this->createAcademicYear((int) $manager->school_id, '2026-2027', '2026-08-15', '2027-06-30');

        $staff = User::factory()->create([
            'role' => 'staff',
            'school_id' => (int) $manager->school_id,
            'is_active' => true,
            'can_manage_student_leaves' => false,
            'can_manage_leave_types' => false,
            'can_manage_school_calendar' => false,
            'can_manage_school_holidays' => false,
        ]);
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->postJson(route('api.school.leave_types.store'), [
                'name' => 'Forbidden Type',
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->putJson(route('api.school.calendar_settings.update'), [
                'week_start_day' => 0,
                'weekly_off_days' => [5],
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->postJson(route('api.school.holidays.store'), [
                'name' => 'Forbidden Holiday',
                'start_date' => '2026-09-23',
                'days_count' => 1,
            ])
            ->assertForbidden();
    }

    public function test_holiday_actions_are_tenant_scoped(): void
    {
        $this->seedBaseRoles();
        $managerA = $this->createSchoolManagerWithSchool('SCH-970003');
        $managerB = $this->createSchoolManagerWithSchool('SCH-970004');
        $this->createAcademicYear((int) $managerB->school_id, '2026-2027', '2026-08-15', '2027-06-30');

        $holidayResponse = $this->actingAs($managerB)
            ->postJson(route('api.school.holidays.store'), [
                'name' => 'School B Holiday',
                'start_date' => '2026-12-01',
                'days_count' => 2,
            ])
            ->assertCreated();

        $holidayId = (int) $holidayResponse->json('data.id');

        $this->actingAs($managerA)
            ->patchJson(route('api.school.holidays.update', $holidayId), [
                'name' => 'Hijacked Holiday',
                'start_date' => '2026-12-01',
                'days_count' => 2,
            ])
            ->assertForbidden();

        $this->actingAs($managerA)
            ->getJson(route('api.school.holidays.index'))
            ->assertOk()
            ->assertJsonMissing(['id' => $holidayId]);
    }

    public function test_holiday_creation_fails_when_period_is_outside_configured_academic_year(): void
    {
        $this->seedBaseRoles();
        $manager = $this->createSchoolManagerWithSchool('SCH-970014');
        $this->createAcademicYear((int) $manager->school_id, '2026-2027', '2026-08-15', '2027-06-30');

        $this->actingAs($manager)
            ->postJson(route('api.school.holidays.store'), [
                'name' => 'Outside Academic Year Holiday',
                'start_date' => '2027-07-05',
                'days_count' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('start_date');

        $this->assertDatabaseMissing('school_holidays', [
            'school_id' => (int) $manager->school_id,
            'name' => 'Outside Academic Year Holiday',
        ]);
    }

    public function test_leave_type_name_must_be_unique_per_school_case_insensitive(): void
    {
        $this->seedBaseRoles();
        $manager = $this->createSchoolManagerWithSchool('SCH-970005');

        $this->actingAs($manager)
            ->postJson(route('api.school.leave_types.store'), [
                'name' => 'Special Leave',
            ])
            ->assertCreated();

        $this->actingAs($manager)
            ->postJson(route('api.school.leave_types.store'), [
                'name' => 'special leave',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_leave_type_actions_are_tenant_scoped(): void
    {
        $this->seedBaseRoles();
        $managerA = $this->createSchoolManagerWithSchool('SCH-970006');
        $managerB = $this->createSchoolManagerWithSchool('SCH-970007');

        $leaveTypeResponse = $this->actingAs($managerB)
            ->postJson(route('api.school.leave_types.store'), [
                'name' => 'School B Type',
                'code' => 'SCHOOL_B',
                'requires_attachment' => false,
            ])
            ->assertCreated();

        $leaveTypeId = (int) $leaveTypeResponse->json('data.id');

        $this->actingAs($managerA)
            ->patchJson(route('api.school.leave_types.update', $leaveTypeId), [
                'name' => 'Hijacked Type',
                'code' => 'HIJACKED',
                'requires_attachment' => true,
            ])
            ->assertForbidden();

        $this->actingAs($managerA)
            ->postJson(route('api.school.leave_types.disable', $leaveTypeId), [])
            ->assertForbidden();

        $this->actingAs($managerA)
            ->getJson(route('api.school.leave_types.index'))
            ->assertOk()
            ->assertJsonMissing(['id' => $leaveTypeId]);
    }

    public function test_leave_type_semantic_update_is_blocked_when_requests_exist(): void
    {
        $this->seedBaseRoles();
        $manager = $this->createSchoolManagerWithSchool('SCH-970012');
        [, , $student] = $this->createStudentStructure((int) $manager->school_id, 'ST-970012');

        $leaveType = SchoolLeaveType::query()->create([
            'school_id' => (int) $manager->school_id,
            'name' => 'Medical Leave',
            'code' => 'MEDICAL_970012',
            'requires_attachment' => false,
            'is_active' => true,
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        SchoolStudentLeaveRequest::query()->create([
            'school_id' => (int) $manager->school_id,
            'school_student_id' => $student->id,
            'school_leave_type_id' => $leaveType->id,
            'source' => SchoolStudentLeaveRequest::SOURCE_PRE_APPROVED,
            'status' => SchoolStudentLeaveRequest::STATUS_PENDING,
            'start_date' => '2026-03-05',
            'end_date' => '2026-03-05',
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->patchJson(route('api.school.leave_types.update', $leaveType->id), [
                'name' => 'Medical Leave',
                'code' => 'MEDICAL_970012_EDITED',
                'requires_attachment' => true,
                'is_active' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('impact');

        $this->assertDatabaseHas('school_leave_types', [
            'id' => $leaveType->id,
            'code' => 'MEDICAL_970012',
            'requires_attachment' => false,
        ]);
    }

    public function test_leave_type_disable_requires_confirmation_when_pending_requests_exist(): void
    {
        $this->seedBaseRoles();
        $manager = $this->createSchoolManagerWithSchool('SCH-970008');
        [$stage, $classroom, $student] = $this->createStudentStructure((int) $manager->school_id, 'ST-970008');

        $leaveType = SchoolLeaveType::query()->create([
            'school_id' => (int) $manager->school_id,
            'name' => 'Exam Leave',
            'code' => 'EXAM_970008',
            'requires_attachment' => false,
            'is_active' => true,
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        SchoolStudentLeaveRequest::query()->create([
            'school_id' => (int) $manager->school_id,
            'school_student_id' => $student->id,
            'school_leave_type_id' => $leaveType->id,
            'source' => SchoolStudentLeaveRequest::SOURCE_PRE_APPROVED,
            'status' => SchoolStudentLeaveRequest::STATUS_PENDING,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-01',
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->getJson(route('api.school.leave_types.delete_impact', $leaveType->id))
            ->assertOk()
            ->assertJsonPath('data.message_code', 'INTEGRITY_LEAVE_TYPE_DISABLE_WARNING_PENDING_REQUESTS')
            ->assertJsonPath('data.requires_confirmation', true);

        $this->actingAs($manager)
            ->postJson(route('api.school.leave_types.disable', $leaveType->id), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('confirm_impact');

        $this->assertDatabaseHas('school_leave_types', [
            'id' => $leaveType->id,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->postJson(route('api.school.leave_types.disable', $leaveType->id), [
                'confirm_impact' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('school_leave_types', [
            'id' => $leaveType->id,
            'is_active' => false,
        ]);
    }

    public function test_holiday_update_and_disable_require_confirmation_when_attendance_exists(): void
    {
        $this->seedBaseRoles();
        $manager = $this->createSchoolManagerWithSchool('SCH-970009');
        [, $classroom, $student] = $this->createStudentStructure((int) $manager->school_id, 'ST-970009');
        $this->createAcademicYear((int) $manager->school_id, '2025-2026', '2025-08-15', '2026-06-30');

        $holiday = SchoolHoliday::query()->create([
            'school_id' => (int) $manager->school_id,
            'name' => 'Term Break',
            'start_date' => '2026-03-10',
            'end_date' => '2026-03-11',
            'return_date' => '2026-03-12',
            'is_active' => true,
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        SchoolStudentAttendance::query()->create([
            'school_id' => (int) $manager->school_id,
            'school_student_id' => $student->id,
            'school_classroom_id' => $classroom->id,
            'attendance_date' => '2026-03-10',
            'status' => SchoolStudentAttendance::STATUS_ABSENT,
            'recorded_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $impactUrl = route('api.school.holidays.update_impact', $holiday->id) . '?' . http_build_query([
            'name' => 'Term Break Updated',
            'start_date' => '2026-03-09',
            'end_date' => '2026-03-11',
            'return_date' => '2026-03-12',
        ]);

        $this->actingAs($manager)
            ->getJson($impactUrl)
            ->assertOk()
            ->assertJsonPath('data.message_code', 'INTEGRITY_HOLIDAY_UPDATE_WARNING_ATTENDANCE_IMPACT')
            ->assertJsonPath('data.requires_confirmation', true);

        $this->actingAs($manager)
            ->patchJson(route('api.school.holidays.update', $holiday->id), [
                'name' => 'Term Break Updated',
                'start_date' => '2026-03-09',
                'end_date' => '2026-03-11',
                'return_date' => '2026-03-12',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message_code', 'INTEGRITY_HOLIDAY_UPDATE_WARNING_ATTENDANCE_IMPACT');

        $this->actingAs($manager)
            ->patchJson(route('api.school.holidays.update', $holiday->id), [
                'name' => 'Term Break Updated',
                'start_date' => '2026-03-09',
                'end_date' => '2026-03-11',
                'return_date' => '2026-03-12',
                'confirm_impact' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Term Break Updated');

        $this->actingAs($manager)
            ->postJson(route('api.school.holidays.disable', $holiday->id), [])
            ->assertStatus(409)
            ->assertJsonPath('message_code', 'INTEGRITY_HOLIDAY_DISABLE_WARNING_ATTENDANCE_IMPACT');

        $this->actingAs($manager)
            ->postJson(route('api.school.holidays.disable', $holiday->id), [
                'confirm_impact' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_impact_preview_endpoints_are_tenant_scoped(): void
    {
        $this->seedBaseRoles();
        $managerA = $this->createSchoolManagerWithSchool('SCH-970010');
        $managerB = $this->createSchoolManagerWithSchool('SCH-970011');

        $leaveTypeB = SchoolLeaveType::query()->create([
            'school_id' => (int) $managerB->school_id,
            'name' => 'School B Type',
            'code' => 'SCB_970011',
            'requires_attachment' => false,
            'is_active' => true,
            'created_by' => $managerB->id,
            'updated_by' => $managerB->id,
        ]);

        $holidayB = SchoolHoliday::query()->create([
            'school_id' => (int) $managerB->school_id,
            'name' => 'School B Holiday',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-01',
            'return_date' => '2026-04-02',
            'is_active' => true,
            'created_by' => $managerB->id,
            'updated_by' => $managerB->id,
        ]);

        $this->actingAs($managerA)
            ->getJson(route('api.school.leave_types.delete_impact', $leaveTypeB->id))
            ->assertForbidden();

        $this->actingAs($managerA)
            ->getJson(route('api.school.holidays.delete_impact', $holidayB->id))
            ->assertForbidden();

        $this->actingAs($managerA)
            ->getJson(route('api.school.holidays.update_impact', $holidayB->id))
            ->assertForbidden();
    }

    private function seedBaseRoles(): void
    {
        Role::query()->firstOrCreate([
            'name' => 'school_manager',
            'guard_name' => 'web',
        ]);

        Role::query()->firstOrCreate([
            'name' => 'staff',
            'guard_name' => 'web',
        ]);
    }

    private function createSchoolManagerWithSchool(string $schoolCode): User
    {
        $digits = preg_replace('/\D+/', '', $schoolCode) ?: '0';
        $schoolPhone = '05' . str_pad(substr($digits, -8), 8, '0', STR_PAD_LEFT);

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
            'phone' => $schoolPhone,
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        return $manager->fresh();
    }

    private function createAcademicYear(
        int $schoolId,
        string $name,
        string $startsOn,
        string $endsOn,
        bool $isActive = true
    ): SchoolAcademicYear {
        return SchoolAcademicYear::query()->create([
            'school_id' => $schoolId,
            'name' => $name,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'is_active' => $isActive,
        ]);
    }

    /**
     * @return array{0: SchoolStage, 1: SchoolClassroom, 2: SchoolStudent}
     */
    private function createStudentStructure(int $schoolId, string $studentCode): array
    {
        $stage = SchoolStage::query()->create([
            'school_id' => $schoolId,
            'name' => 'Primary ' . $studentCode,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::query()->create([
            'school_id' => $schoolId,
            'school_stage_id' => $stage->id,
            'name' => 'Class ' . $studentCode,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $student = SchoolStudent::query()->create([
            'school_id' => $schoolId,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Student ' . $studentCode,
            'student_code' => $studentCode,
            'is_active' => true,
        ]);

        return [$stage, $classroom, $student];
    }
}

