<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolStage;
use App\Models\SchoolSupervisionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuickSetupStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_fetch_quick_setup_status_for_his_school(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Quick Setup Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Quick Setup School',
            'school_id' => 'SCH-QS-1001',
            'phone' => '0500100101',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        SchoolStage::create([
            'school_id' => $school->id,
            'name' => 'Primary',
            'code' => 'STG-01',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)->getJson(route('api.school.quick_setup.status'));
        $response->assertOk();

        $this->assertSame($school->id, (int) $response->json('data.school_id'));

        $steps = collect($response->json('data.steps', []));
        $stagesStep = $steps->firstWhere('key', 'stages');

        $this->assertIsArray($stagesStep);
        $this->assertSame('completed', $stagesStep['status']);
        $this->assertSame(1, (int) ($stagesStep['counts']['total'] ?? 0));
    }

    public function test_status_endpoint_is_tenant_scoped(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Tenant Scope Region',
            'governorate' => 'Makkah',
        ]);

        $managerA = User::factory()->create(['role' => 'school_manager']);
        $managerA->assignRole('school_manager');

        $managerB = User::factory()->create(['role' => 'school_manager']);
        $managerB->assignRole('school_manager');

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'Tenant School A',
            'school_id' => 'SCH-QS-1002',
            'phone' => '0500100102',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerA->id,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'Tenant School B',
            'school_id' => 'SCH-QS-1003',
            'phone' => '0500100103',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerB->id,
        ]);

        $managerA->update(['school_id' => $schoolA->id]);
        $managerB->update(['school_id' => $schoolB->id]);

        SchoolStage::create([
            'school_id' => $schoolB->id,
            'name' => 'Out Of Scope Stage',
            'code' => 'STG-B',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($managerA)->getJson(route('api.school.quick_setup.status'));
        $response->assertOk();

        $steps = collect($response->json('data.steps', []));
        $stagesStep = $steps->firstWhere('key', 'stages');

        $this->assertIsArray($stagesStep);
        $this->assertSame(0, (int) ($stagesStep['counts']['total'] ?? -1));
    }

    public function test_staff_with_academic_planning_permission_can_access_status_and_users_step_is_read_only(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Planner Staff Region',
            'governorate' => 'Jeddah',
        ]);

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Planner Staff School',
            'school_id' => 'SCH-QS-1004',
            'phone' => '0500100104',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $department = Department::create([
            'name' => 'Administrative Affairs',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'Planning Officer',
            'is_active' => true,
            'can_manage_academic_planning' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
        ]);
        $staff->assignRole('staff');

        $response = $this->actingAs($staff)->getJson(route('api.school.quick_setup.status'));
        $response->assertOk();

        $steps = collect($response->json('data.steps', []));
        $usersStep = $steps->firstWhere('key', 'school_users');
        $stagesStep = $steps->firstWhere('key', 'stages');

        $this->assertIsArray($usersStep);
        $this->assertFalse((bool) ($usersStep['editable'] ?? true));

        $this->assertIsArray($stagesStep);
        $this->assertTrue((bool) ($stagesStep['editable'] ?? false));
    }

    public function test_staff_without_academic_planning_permission_is_forbidden(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'No Planner Staff Region',
            'governorate' => 'Tabuk',
        ]);

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'No Planner Staff School',
            'school_id' => 'SCH-QS-1005',
            'phone' => '0500100105',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $department = Department::create([
            'name' => 'Administrative Affairs',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'Regular Employee',
            'is_active' => true,
            'can_manage_academic_planning' => false,
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
            ->getJson(route('api.school.quick_setup.status'))
            ->assertForbidden();
    }

    public function test_manager_can_fetch_status_when_school_association_is_not_active(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Pending Association Region',
            'governorate' => 'Dammam',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Pending Association School',
            'school_id' => 'SCH-QS-1006',
            'phone' => '0500100106',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_WAITING_SUPERVISOR_CONFIRM,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $this->actingAs($manager)
            ->getJson(route('api.school.quick_setup.status'))
            ->assertOk();
    }

    public function test_manager_can_fetch_status_when_school_flags_are_waiting_with_manager_approval_request(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Mutual Approval Quick Setup Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $supervisor->assignRole('supervisor');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Mutual Approval Quick Setup School',
            'school_id' => 'SCH-QS-1007',
            'phone' => '0500100107',
            'status' => School::STATUS_SUSPENDED,
            'supervision_status' => School::SUPERVISION_STATUS_WAITING_SUPERVISOR_CONFIRM,
            'manager_user_id' => $manager->id,
            'supervisor_id' => $supervisor->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        SchoolSupervisionRequest::create([
            'school_id' => $school->id,
            'region_id' => $region->id,
            'supervisor_id' => $supervisor->id,
            'manager_id' => $manager->id,
            'status' => SchoolSupervisionRequest::STATUS_MANAGER_APPROVED,
            'requested_at' => now()->subHour(),
            'manager_action_at' => now(),
        ]);

        $this->actingAs($manager)
            ->getJson(route('api.school.quick_setup.status'))
            ->assertOk();
    }
}
