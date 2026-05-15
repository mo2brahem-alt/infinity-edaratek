<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolSupervisionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActiveSchoolAssociationMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_access_dashboard_when_association_is_not_active(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Association Gate Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Pending Association School',
            'school_id' => 'SCH-AG-0001',
            'phone' => '0500011001',
            'status' => School::STATUS_SUSPENDED,
            'supervision_status' => School::SUPERVISION_STATUS_WAITING_MANAGER_APPROVAL,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk();
    }

    public function test_manager_can_access_requests_page_before_association_activation(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Pending Requests Region',
            'governorate' => 'Makkah',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Pending Requests School',
            'school_id' => 'SCH-AG-0002',
            'phone' => '0500011002',
            'status' => School::STATUS_SUSPENDED,
            'supervision_status' => School::SUPERVISION_STATUS_WAITING_MANAGER_APPROVAL,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $this->actingAs($manager)
            ->get(route('manager.requests.page'))
            ->assertOk();
    }

    public function test_staff_is_blocked_from_school_modules_when_association_is_not_active(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Staff Gate Region',
            'governorate' => 'Jeddah',
        ]);

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Staff Pending School',
            'school_id' => 'SCH-AG-0003',
            'phone' => '0500011003',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_WAITING_SUPERVISOR_CONFIRM,
        ]);

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
            ->get(route('staff.dashboard'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('school.student_structure.index'))
            ->assertForbidden();
    }

    public function test_manager_can_access_managed_routes_when_association_is_active(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Active Association Region',
            'governorate' => 'Dammam',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Active Association School',
            'school_id' => 'SCH-AG-0004',
            'phone' => '0500011004',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk();
    }

    public function test_manager_can_access_managed_routes_even_when_school_flags_are_waiting_with_manager_approval_request(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Mutual Approval Region',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $supervisor->assignRole('supervisor');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Mutual Approval School',
            'school_id' => 'SCH-AG-0005',
            'phone' => '0500011005',
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
            ->get(route('manager.dashboard'))
            ->assertOk();
    }
}
