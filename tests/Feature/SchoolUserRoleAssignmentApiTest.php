<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\OrgStructureRoleTemplate;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolPermissionGroup;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SchoolPermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolUserRoleAssignmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_list_assignable_roles_and_create_user_for_his_school(): void
    {
        $this->seedSystemRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-910001');
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $assignableResponse = $this->actingAs($manager)->getJson(route('api.school.roles.assignable'));
        $assignableResponse
            ->assertOk()
            ->assertJsonFragment(['name' => 'staff'])
            ->assertJsonFragment(['name' => 'teacher'])
            ->assertJsonMissing(['name' => 'super_admin']);

        $storeResponse = $this->actingAs($manager)->postJson(route('api.school.users.store'), [
            'name' => 'School Staff One',
            'email' => 'school.staff.one@example.com',
            'mobile' => '0500009101',
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role_names' => ['teacher'],
            'can_manage_student_structure' => true,
            'can_manage_student_attendance' => false,
            'can_manage_academic_planning' => true,
            'can_manage_student_leaves' => true,
            'can_manage_leave_types' => true,
            'can_manage_school_calendar' => false,
            'can_manage_school_holidays' => true,
        ]);

        $storeResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'School Staff One')
            ->assertJsonPath('data.school_id', $manager->school_id)
            ->assertJsonPath('data.can_manage_student_structure', true)
            ->assertJsonPath('data.can_manage_student_attendance', false)
            ->assertJsonPath('data.can_manage_academic_planning', true)
            ->assertJsonPath('data.can_manage_student_leaves', true)
            ->assertJsonPath('data.can_manage_leave_types', true)
            ->assertJsonPath('data.can_manage_school_calendar', false)
            ->assertJsonPath('data.can_manage_school_holidays', true);

        $createdUser = User::query()->where('email', 'school.staff.one@example.com')->firstOrFail();
        $this->assertSame((int) $manager->school_id, (int) $createdUser->school_id);
        $this->assertTrue($createdUser->hasRole('staff'));
        $this->assertTrue($createdUser->hasRole('teacher'));
        $this->assertSame('staff', $createdUser->role);
        $this->assertTrue((bool) $createdUser->can_manage_student_structure);
        $this->assertFalse((bool) $createdUser->can_manage_student_attendance);
        $this->assertTrue((bool) $createdUser->can_manage_academic_planning);
        $this->assertTrue((bool) $createdUser->can_manage_student_leaves);
        $this->assertTrue((bool) $createdUser->can_manage_leave_types);
        $this->assertFalse((bool) $createdUser->can_manage_school_calendar);
        $this->assertTrue((bool) $createdUser->can_manage_school_holidays);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'school_user.created',
            'entity_type' => 'user',
            'entity_id' => $createdUser->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_manager_can_create_user_with_school_owned_department(): void
    {
        $this->seedSystemRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-910025');

        $department = Department::create([
            'name' => 'شؤون الطلاب',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => $manager->school_id,
        ]);

        $departmentRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'مسؤول شؤون الطلاب',
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)->postJson(route('api.school.users.store'), [
            'name' => 'School Owned Department User',
            'email' => 'school.owned.department.user@example.com',
            'mobile' => '0500009122',
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role_names' => ['staff'],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.department_id', $department->id)
            ->assertJsonPath('data.department_role_id', $departmentRole->id)
            ->assertJsonPath('data.school_id', $manager->school_id);
    }

    public function test_manager_assignable_roles_list_keeps_staff_visible_for_legacy_staff_flags(): void
    {
        $this->seedSystemRoles();

        Role::query()
            ->where('name', 'staff')
            ->where('guard_name', 'web')
            ->update([
                'assignable_by_school_admin' => false,
                'is_system' => true,
            ]);

        $manager = $this->createSchoolManagerWithSchool('SCH-910019');

        $assignableResponse = $this->actingAs($manager)->getJson(route('api.school.roles.assignable'));
        $assignableResponse
            ->assertOk()
            ->assertJsonFragment(['name' => 'staff']);
    }

    public function test_manager_assignable_roles_list_restores_missing_staff_role(): void
    {
        $this->seedSystemRoles();

        Role::query()
            ->where('name', 'staff')
            ->where('guard_name', 'web')
            ->delete();

        $manager = $this->createSchoolManagerWithSchool('SCH-910020');

        $assignableResponse = $this->actingAs($manager)->getJson(route('api.school.roles.assignable'));
        $assignableResponse
            ->assertOk()
            ->assertJsonFragment(['name' => 'staff']);

        $this->assertDatabaseHas('roles', [
            'name' => 'staff',
            'guard_name' => 'web',
        ]);
    }

    public function test_manager_cannot_assign_system_or_non_assignable_role_when_creating_user(): void
    {
        $this->seedSystemRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-910002');
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $response = $this->actingAs($manager)->postJson(route('api.school.users.store'), [
            'name' => 'Forbidden Role User',
            'email' => 'forbidden.role.user@example.com',
            'mobile' => '0500009102',
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role_names' => ['super_admin'],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('role_names.0');

        $this->assertDatabaseMissing('users', [
            'email' => 'forbidden.role.user@example.com',
        ]);
    }

    public function test_manager_cannot_sync_roles_for_user_outside_his_school(): void
    {
        $this->seedSystemRoles();

        $managerA = $this->createSchoolManagerWithSchool('SCH-910003');
        $managerB = $this->createSchoolManagerWithSchool('SCH-910004');
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $schoolBUser = User::factory()->create([
            'name' => 'School B Staff',
            'email' => 'school.b.staff@example.com',
            'mobile' => '0500009103',
            'role' => 'staff',
            'school_id' => $managerB->school_id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $schoolBUser->syncRoles(['staff']);

        $response = $this->actingAs($managerA)->putJson(route('api.school.users.roles.sync', $schoolBUser), [
            'role_names' => ['teacher'],
        ]);

        $response->assertForbidden();

        $schoolBUser->refresh();
        $this->assertTrue($schoolBUser->hasRole('staff'));
        $this->assertFalse($schoolBUser->hasRole('teacher'));
    }

    public function test_manager_can_sync_assignable_roles_for_user_in_his_school(): void
    {
        $this->seedSystemRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-910005');
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $staffUser = User::factory()->create([
            'name' => 'Local Staff',
            'email' => 'local.staff@example.com',
            'mobile' => '0500009104',
            'role' => 'staff',
            'school_id' => $manager->school_id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $staffUser->syncRoles(['staff']);

        $response = $this->actingAs($manager)->putJson(route('api.school.users.roles.sync', $staffUser), [
            'role_names' => ['teacher'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $staffUser->id);

        $staffUser->refresh();
        $this->assertTrue($staffUser->hasRole('staff'));
        $this->assertTrue($staffUser->hasRole('teacher'));
        $this->assertSame('staff', $staffUser->role);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'school_user.roles_synced',
            'entity_type' => 'user',
            'entity_id' => $staffUser->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_manager_can_update_school_user_profile_and_roles_in_his_school(): void
    {
        $this->seedSystemRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-910008');
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $staffUser = User::factory()->create([
            'name' => 'Profile Before',
            'email' => 'profile.before@example.com',
            'mobile' => '0500009107',
            'role' => 'staff',
            'school_id' => $manager->school_id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $staffUser->syncRoles(['staff']);

        $response = $this->actingAs($manager)->putJson(route('api.school.users.update', $staffUser), [
            'name' => 'Profile After',
            'email' => 'profile.after@example.com',
            'mobile' => '0500009108',
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'role_names' => ['teacher'],
            'can_manage_student_structure' => false,
            'can_manage_student_attendance' => true,
            'can_manage_academic_planning' => true,
            'can_manage_student_leaves' => false,
            'can_manage_leave_types' => true,
            'can_manage_school_calendar' => true,
            'can_manage_school_holidays' => false,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Profile After')
            ->assertJsonPath('data.email', 'profile.after@example.com')
            ->assertJsonPath('data.can_manage_student_structure', false)
            ->assertJsonPath('data.can_manage_student_attendance', true)
            ->assertJsonPath('data.can_manage_academic_planning', true)
            ->assertJsonPath('data.can_manage_student_leaves', false)
            ->assertJsonPath('data.can_manage_leave_types', true)
            ->assertJsonPath('data.can_manage_school_calendar', true)
            ->assertJsonPath('data.can_manage_school_holidays', false);

        $staffUser->refresh();
        $this->assertSame('Profile After', $staffUser->name);
        $this->assertSame('profile.after@example.com', $staffUser->email);
        $this->assertSame('0500009108', $staffUser->mobile);
        $this->assertTrue($staffUser->hasRole('staff'));
        $this->assertTrue($staffUser->hasRole('teacher'));
        $this->assertFalse((bool) $staffUser->can_manage_student_structure);
        $this->assertTrue((bool) $staffUser->can_manage_student_attendance);
        $this->assertTrue((bool) $staffUser->can_manage_academic_planning);
        $this->assertFalse((bool) $staffUser->can_manage_student_leaves);
        $this->assertTrue((bool) $staffUser->can_manage_leave_types);
        $this->assertTrue((bool) $staffUser->can_manage_school_calendar);
        $this->assertFalse((bool) $staffUser->can_manage_school_holidays);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'school_user.updated',
            'entity_type' => 'user',
            'entity_id' => $staffUser->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_manager_can_list_active_org_structure_role_templates_for_school_context(): void
    {
        $this->seedSystemRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-910015');

        $department = Department::create([
            'name' => 'Administrative Affairs',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $activeTemplate = OrgStructureRoleTemplate::query()->create([
            'name' => 'Registrar',
            'code' => 'REGISTRAR',
            'is_active' => true,
        ]);

        $inactiveTemplate = OrgStructureRoleTemplate::query()->create([
            'name' => 'Legacy Role',
            'code' => 'LEGACY_ROLE',
            'is_active' => false,
        ]);

        DepartmentRole::create([
            'department_id' => $department->id,
            'org_structure_role_template_id' => $activeTemplate->id,
            'name' => 'Registrar',
            'is_active' => true,
        ]);

        DepartmentRole::create([
            'department_id' => $department->id,
            'org_structure_role_template_id' => $inactiveTemplate->id,
            'name' => 'Legacy Role',
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)->getJson(route('api.school.org_structure_roles.index', [
            'department_id' => $department->id,
        ]));

        $response
            ->assertOk()
            ->assertJsonFragment(['name' => 'Registrar'])
            ->assertJsonMissing(['name' => 'Legacy Role']);
    }

    public function test_manager_can_create_user_when_staff_role_flags_are_legacy_but_role_name_is_staff(): void
    {
        $this->seedSystemRoles();

        Role::query()
            ->where('name', 'staff')
            ->where('guard_name', 'web')
            ->update([
                'assignable_by_school_admin' => false,
                'is_system' => true,
            ]);

        $manager = $this->createSchoolManagerWithSchool('SCH-910018');
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $storeResponse = $this->actingAs($manager)->postJson(route('api.school.users.store'), [
            'name' => 'Legacy Staff Flags User',
            'email' => 'legacy.staff.flags.user@example.com',
            'mobile' => '0500009120',
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role_names' => ['staff'],
        ]);

        $storeResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'Legacy Staff Flags User')
            ->assertJsonPath('data.school_id', $manager->school_id);

        $createdUser = User::query()->where('email', 'legacy.staff.flags.user@example.com')->firstOrFail();
        $this->assertTrue($createdUser->hasRole('staff'));
    }

    public function test_manager_can_create_user_when_staff_role_is_missing_from_roles_table(): void
    {
        $this->seedSystemRoles();

        Role::query()
            ->where('name', 'staff')
            ->where('guard_name', 'web')
            ->delete();

        $manager = $this->createSchoolManagerWithSchool('SCH-910021');
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $storeResponse = $this->actingAs($manager)->postJson(route('api.school.users.store'), [
            'name' => 'Missing Staff Role User',
            'email' => 'missing.staff.role.user@example.com',
            'mobile' => '0500009121',
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role_names' => ['teacher'],
        ]);

        $storeResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'Missing Staff Role User')
            ->assertJsonPath('data.school_id', $manager->school_id);

        $createdUser = User::query()->where('email', 'missing.staff.role.user@example.com')->firstOrFail();
        $this->assertTrue($createdUser->hasRole('staff'));
        $this->assertTrue($createdUser->hasRole('teacher'));
    }

    public function test_manager_cannot_assign_department_role_linked_to_disabled_org_structure_template(): void
    {
        $this->seedSystemRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-910016');

        $department = Department::create([
            'name' => 'Administrative Affairs',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $inactiveTemplate = OrgStructureRoleTemplate::query()->create([
            'name' => 'Disabled Role Template',
            'code' => 'DISABLED_TEMPLATE',
            'is_active' => false,
        ]);

        $departmentRole = DepartmentRole::create([
            'department_id' => $department->id,
            'org_structure_role_template_id' => $inactiveTemplate->id,
            'name' => 'Disabled Role Template',
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)->postJson(route('api.school.users.store'), [
            'name' => 'Blocked Staff',
            'email' => 'blocked.staff@example.com',
            'mobile' => '0500009117',
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role_names' => ['staff'],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('department_role_id');
    }

    public function test_manager_can_update_user_and_keep_current_department_role_even_if_template_was_disabled_later(): void
    {
        $this->seedSystemRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-910017');

        $department = Department::create([
            'name' => 'Administrative Affairs',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $inactiveTemplate = OrgStructureRoleTemplate::query()->create([
            'name' => 'Previously Active Role Template',
            'code' => 'PREV_ACTIVE_TEMPLATE',
            'is_active' => false,
        ]);

        $departmentRole = DepartmentRole::create([
            'department_id' => $department->id,
            'org_structure_role_template_id' => $inactiveTemplate->id,
            'name' => 'Previously Active Role Template',
            'is_active' => true,
        ]);

        $staffUser = User::factory()->create([
            'name' => 'Legacy Linked Staff',
            'email' => 'legacy.linked.staff@example.com',
            'mobile' => '0500009118',
            'role' => 'staff',
            'school_id' => $manager->school_id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $staffUser->syncRoles(['staff']);

        $response = $this->actingAs($manager)->putJson(route('api.school.users.update', $staffUser), [
            'name' => 'Legacy Linked Staff Updated',
            'email' => 'legacy.linked.staff.updated@example.com',
            'mobile' => '0500009119',
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'role_names' => ['staff'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Legacy Linked Staff Updated')
            ->assertJsonPath('data.department_role_id', $departmentRole->id);

        $staffUser->refresh();
        $this->assertSame('Legacy Linked Staff Updated', $staffUser->name);
        $this->assertSame('legacy.linked.staff.updated@example.com', $staffUser->email);
        $this->assertSame('0500009119', $staffUser->mobile);
        $this->assertSame((int) $departmentRole->id, (int) $staffUser->department_role_id);
    }

    public function test_manager_update_without_permission_fields_keeps_existing_structure_permissions(): void
    {
        $this->seedSystemRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-910011');
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $staffUser = User::factory()->create([
            'name' => 'Legacy Permissions User',
            'email' => 'legacy.permissions.user@example.com',
            'mobile' => '0500009111',
            'role' => 'staff',
            'school_id' => $manager->school_id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'can_manage_student_structure' => true,
            'can_manage_student_attendance' => false,
            'can_manage_academic_planning' => true,
            'can_manage_student_leaves' => false,
            'can_manage_leave_types' => true,
            'can_manage_school_calendar' => false,
            'can_manage_school_holidays' => true,
            'is_active' => true,
        ]);
        $staffUser->syncRoles(['staff']);

        $response = $this->actingAs($manager)->putJson(route('api.school.users.update', $staffUser), [
            'name' => 'Legacy Permissions User Updated',
            'email' => 'legacy.permissions.user.updated@example.com',
            'mobile' => '0500009112',
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'role_names' => ['teacher'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.can_manage_student_structure', true)
            ->assertJsonPath('data.can_manage_student_attendance', false)
            ->assertJsonPath('data.can_manage_academic_planning', true)
            ->assertJsonPath('data.can_manage_student_leaves', false)
            ->assertJsonPath('data.can_manage_leave_types', true)
            ->assertJsonPath('data.can_manage_school_calendar', false)
            ->assertJsonPath('data.can_manage_school_holidays', true);

        $staffUser->refresh();
        $this->assertTrue((bool) $staffUser->can_manage_student_structure);
        $this->assertFalse((bool) $staffUser->can_manage_student_attendance);
        $this->assertTrue((bool) $staffUser->can_manage_academic_planning);
        $this->assertFalse((bool) $staffUser->can_manage_student_leaves);
        $this->assertTrue((bool) $staffUser->can_manage_leave_types);
        $this->assertFalse((bool) $staffUser->can_manage_school_calendar);
        $this->assertTrue((bool) $staffUser->can_manage_school_holidays);
    }

    public function test_manager_only_sees_his_school_users_in_school_users_index(): void
    {
        $this->seedSystemRoles();

        $managerA = $this->createSchoolManagerWithSchool('SCH-910006');
        $managerB = $this->createSchoolManagerWithSchool('SCH-910007');
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $schoolAUser = User::factory()->create([
            'name' => 'School A Staff',
            'email' => 'school.a.staff@example.com',
            'mobile' => '0500009105',
            'role' => 'staff',
            'school_id' => $managerA->school_id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $schoolAUser->syncRoles(['staff']);

        $schoolBUser = User::factory()->create([
            'name' => 'School B Staff',
            'email' => 'school.b.staff.two@example.com',
            'mobile' => '0500009106',
            'role' => 'staff',
            'school_id' => $managerB->school_id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $schoolBUser->syncRoles(['staff']);

        $response = $this->actingAs($managerA)->getJson(route('api.school.users.index'));

        $response
            ->assertOk()
            ->assertJsonFragment(['email' => 'school.a.staff@example.com'])
            ->assertJsonMissing(['email' => 'school.b.staff.two@example.com']);
    }

    public function test_manager_can_paginate_school_users_index_without_cross_tenant_leakage(): void
    {
        $this->seedSystemRoles();

        $managerA = $this->createSchoolManagerWithSchool('SCH-910012');
        $managerB = $this->createSchoolManagerWithSchool('SCH-910013');
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $schoolAOne = User::factory()->create([
            'name' => 'School A One',
            'email' => 'school.a.one@example.com',
            'mobile' => '0500009113',
            'role' => 'staff',
            'school_id' => $managerA->school_id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $schoolAOne->syncRoles(['staff']);

        $schoolATwo = User::factory()->create([
            'name' => 'School A Two',
            'email' => 'school.a.two@example.com',
            'mobile' => '0500009114',
            'role' => 'staff',
            'school_id' => $managerA->school_id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $schoolATwo->syncRoles(['staff']);

        $schoolAThree = User::factory()->create([
            'name' => 'School A Three',
            'email' => 'school.a.three@example.com',
            'mobile' => '0500009115',
            'role' => 'staff',
            'school_id' => $managerA->school_id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $schoolAThree->syncRoles(['staff']);

        $schoolBUser = User::factory()->create([
            'name' => 'School B Staff',
            'email' => 'school.b.paginated@example.com',
            'mobile' => '0500009116',
            'role' => 'staff',
            'school_id' => $managerB->school_id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $schoolBUser->syncRoles(['staff']);

        $pageOne = $this->actingAs($managerA)->getJson(route('api.school.users.index', [
            'per_page' => 2,
            'page' => 1,
        ]));

        $pageOne
            ->assertOk()
            ->assertJsonPath('pagination.per_page', 2)
            ->assertJsonPath('pagination.total', 3)
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['email' => 'school.b.paginated@example.com']);

        $pageTwo = $this->actingAs($managerA)->getJson(route('api.school.users.index', [
            'per_page' => 2,
            'page' => 2,
        ]));

        $pageTwo
            ->assertOk()
            ->assertJsonPath('pagination.per_page', 2)
            ->assertJsonPath('pagination.total', 3)
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['email' => 'school.b.paginated@example.com']);
    }

    public function test_manager_school_users_index_rejects_invalid_per_page_value(): void
    {
        $this->seedSystemRoles();
        $manager = $this->createSchoolManagerWithSchool('SCH-910014');

        $response = $this->actingAs($manager)->getJson(route('api.school.users.index', [
            'per_page' => 0,
        ]));

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_manager_cannot_update_school_user_profile_outside_his_school(): void
    {
        $this->seedSystemRoles();

        $managerA = $this->createSchoolManagerWithSchool('SCH-910009');
        $managerB = $this->createSchoolManagerWithSchool('SCH-910010');
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $schoolBUser = User::factory()->create([
            'name' => 'School B Profile',
            'email' => 'school.b.profile@example.com',
            'mobile' => '0500009109',
            'role' => 'staff',
            'school_id' => $managerB->school_id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $schoolBUser->syncRoles(['staff']);

        $response = $this->actingAs($managerA)->putJson(route('api.school.users.update', $schoolBUser), [
            'name' => 'Hacked Name',
            'email' => 'hacked.name@example.com',
            'mobile' => '0500009110',
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'role_names' => ['teacher'],
        ]);

        $response->assertForbidden();

        $schoolBUser->refresh();
        $this->assertSame('School B Profile', $schoolBUser->name);
        $this->assertSame('school.b.profile@example.com', $schoolBUser->email);
        $this->assertSame('0500009109', $schoolBUser->mobile);
        $this->assertFalse($schoolBUser->hasRole('teacher'));
    }

    public function test_manager_can_create_and_list_school_permission_groups_inside_his_school_only(): void
    {
        $this->seedSystemRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-910021');

        $response = $this->actingAs($manager)->postJson(route('api.school.permission_groups.store'), [
            'name' => 'شؤون الطلاب',
            'group_type' => SchoolPermissionCatalog::TYPE_ADMINISTRATIVE,
            'permission_names' => [
                'school.student_structure.manage',
                'school.student_attendance.manage',
                'school.student_leaves.manage',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'شؤون الطلاب')
            ->assertJsonPath('data.group_type', SchoolPermissionCatalog::TYPE_ADMINISTRATIVE)
            ->assertJsonPath('data.users_count', 0);

        $groupId = (int) $response->json('data.id');

        $this->assertDatabaseHas('school_permission_groups', [
            'id' => $groupId,
            'school_id' => $manager->school_id,
            'name' => 'شؤون الطلاب',
            'group_type' => SchoolPermissionCatalog::TYPE_ADMINISTRATIVE,
        ]);

        $indexResponse = $this->actingAs($manager)->getJson(route('api.school.permission_groups.index'));
        $indexResponse
            ->assertOk()
            ->assertJsonFragment(['id' => $groupId, 'name' => 'شؤون الطلاب']);
    }

    public function test_manager_cannot_assign_school_permission_group_from_another_school(): void
    {
        $this->seedSystemRoles();

        $managerA = $this->createSchoolManagerWithSchool('SCH-910022');
        $managerB = $this->createSchoolManagerWithSchool('SCH-910023');
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $foreignGroup = SchoolPermissionGroup::query()->create([
            'school_id' => $managerB->school_id,
            'name' => 'مجموعة مدرسة أخرى',
            'group_type' => SchoolPermissionCatalog::TYPE_ADMINISTRATIVE,
            'permission_names' => ['school.student_structure.manage'],
        ]);

        $response = $this->actingAs($managerA)->postJson(route('api.school.users.store'), [
            'name' => 'Scoped Staff',
            'email' => 'scoped.staff@example.com',
            'mobile' => '0500009121',
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role_names' => ['staff'],
            'school_permission_group_ids' => [$foreignGroup->id],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('school_permission_group_ids');
    }

    public function test_manager_can_assign_school_permission_groups_to_user_and_effective_permissions_follow_group_membership(): void
    {
        $this->seedSystemRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-910024');
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $attendanceGroup = SchoolPermissionGroup::query()->create([
            'school_id' => $manager->school_id,
            'name' => 'شؤون الحضور',
            'group_type' => SchoolPermissionCatalog::TYPE_ADMINISTRATIVE,
            'permission_names' => [
                'school.student_attendance.manage',
                'school.student_structure.manage',
            ],
        ]);

        $staffUser = User::factory()->create([
            'name' => 'Grouped Staff',
            'email' => 'grouped.staff@example.com',
            'mobile' => '0500009122',
            'role' => 'staff',
            'school_id' => $manager->school_id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'can_manage_student_structure' => false,
            'can_manage_student_attendance' => false,
            'is_active' => true,
        ]);
        $staffUser->syncRoles(['staff']);

        $response = $this->actingAs($manager)->putJson(route('api.school.users.update', $staffUser), [
            'name' => 'Grouped Staff Updated',
            'email' => 'grouped.staff.updated@example.com',
            'mobile' => '0500009123',
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'role_names' => ['staff'],
            'school_permission_group_ids' => [$attendanceGroup->id],
            'can_manage_student_structure' => false,
            'can_manage_student_attendance' => false,
            'can_manage_academic_planning' => false,
            'can_manage_student_leaves' => false,
            'can_manage_leave_types' => false,
            'can_manage_school_calendar' => false,
            'can_manage_school_holidays' => false,
        ]);

        $response
            ->assertOk()
            ->assertJsonFragment(['name' => 'شؤون الحضور'])
            ->assertJsonPath('data.school_permission_group_ids.0', $attendanceGroup->id);

        $staffUser->refresh();

        $this->assertFalse((bool) $staffUser->can_manage_student_attendance);
        $this->assertTrue($staffUser->canManageStudentAttendance());
        $this->assertTrue($staffUser->canManageStudentStructure());
        $this->assertDatabaseHas('school_permission_group_user', [
            'school_permission_group_id' => $attendanceGroup->id,
            'user_id' => $staffUser->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'school_user.delegation_synced',
            'entity_type' => 'user',
            'entity_id' => $staffUser->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_manager_cannot_assign_non_manager_assignable_school_permission_even_if_request_is_forged(): void
    {
        $this->seedSystemRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-910026');
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $staffUser = User::factory()->create([
            'name' => 'Protected Delegation User',
            'email' => 'protected.delegation.user@example.com',
            'mobile' => '0500009124',
            'role' => 'staff',
            'school_id' => $manager->school_id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $staffUser->syncRoles(['staff']);

        $response = $this->actingAs($manager)->putJson(route('api.school.users.update', $staffUser), [
            'name' => 'Protected Delegation User',
            'email' => 'protected.delegation.user@example.com',
            'mobile' => '0500009124',
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'role_names' => ['staff'],
            'permission_names' => ['school.delegations.manage'],
            'school_permission_group_ids' => [],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('permission_names');

        $staffUser->refresh();
        $this->assertSame([], $staffUser->directSchoolPermissionNames());
    }

    public function test_manager_can_assign_reports_view_permission_without_export_permission(): void
    {
        $this->seedSystemRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-910027');
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $staffUser = User::factory()->create([
            'name' => 'Reports Staff',
            'email' => 'reports.staff@example.com',
            'mobile' => '0500009125',
            'role' => 'staff',
            'school_id' => $manager->school_id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $staffUser->syncRoles(['staff']);

        $response = $this->actingAs($manager)->putJson(route('api.school.users.update', $staffUser), [
            'name' => 'Reports Staff',
            'email' => 'reports.staff@example.com',
            'mobile' => '0500009125',
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'role_names' => ['staff'],
            'permission_names' => ['school.reports.view'],
            'school_permission_group_ids' => [],
        ]);

        $response
            ->assertOk()
            ->assertJsonFragment(['permission_names' => ['school.reports.view']]);

        $staffUser->refresh();
        $this->assertTrue($staffUser->canManageSchoolReports());
        $this->assertFalse($staffUser->canExportSchoolReports());
        $this->assertFalse($staffUser->canManageAcademicPlanning());

        $this->actingAs($staffUser)
            ->get(route('school.reports.index'))
            ->assertOk();

        $this->actingAs($staffUser)
            ->get(route('school.reports.export', [
                'entity' => 'students',
                'format' => 'json',
            ]))
            ->assertForbidden();
    }

    public function test_manager_can_assign_direct_exam_permission_without_granting_academic_planning(): void
    {
        $this->seedSystemRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-910028');
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $staffUser = User::factory()->create([
            'name' => 'Exam Staff',
            'email' => 'exam.staff@example.com',
            'mobile' => '0500009126',
            'role' => 'staff',
            'school_id' => $manager->school_id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $staffUser->syncRoles(['staff']);

        $response = $this->actingAs($manager)->putJson(route('api.school.users.update', $staffUser), [
            'name' => 'Exam Staff',
            'email' => 'exam.staff@example.com',
            'mobile' => '0500009126',
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'role_names' => ['staff'],
            'permission_names' => ['school.exams.manage'],
            'school_permission_group_ids' => [],
        ]);

        $response
            ->assertOk()
            ->assertJsonFragment(['permission_names' => ['school.exams.manage']]);

        $staffUser->refresh();
        $this->assertTrue($staffUser->canManageSchoolExams());
        $this->assertFalse($staffUser->canManageAcademicPlanning());

        $this->actingAs($staffUser)
            ->get(route('school.exams.index'))
            ->assertOk();
    }

    private function seedSystemRoles(): void
    {
        $roles = [
            ['name' => 'super_admin', 'is_system' => true, 'assignable_by_school_admin' => false],
            ['name' => 'school_manager', 'is_system' => true, 'assignable_by_school_admin' => false],
            ['name' => 'staff', 'is_system' => false, 'assignable_by_school_admin' => true],
            ['name' => 'teacher', 'is_system' => false, 'assignable_by_school_admin' => true],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['name' => $role['name'], 'guard_name' => 'web'],
                [
                    'is_system' => $role['is_system'],
                    'assignable_by_school_admin' => $role['assignable_by_school_admin'],
                ]
            );
        }
    }

    /**
     * @return array{0: Department, 1: DepartmentRole}
     */
    private function createGlobalDepartmentAndRole(): array
    {
        $department = Department::create([
            'name' => 'Administrative Affairs',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'Registrar',
            'is_active' => true,
        ]);

        return [$department, $departmentRole];
    }

    private function createSchoolManagerWithSchool(string $schoolCode): User
    {
        $digits = preg_replace('/\D+/', '', $schoolCode) ?: '0';
        $schoolPhone = '05' . str_pad(substr($digits, -8), 8, '0', STR_PAD_LEFT);

        $region = EducationalDirectorate::create([
            'name' => 'Region ' . $schoolCode,
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create([
            'role' => 'school_manager',
            'is_active' => true,
        ]);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'School ' . $schoolCode,
            'school_id' => $schoolCode,
            'phone' => $schoolPhone,
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $plan = Plan::query()->create([
            'name' => 'Manager Plan ' . $schoolCode,
            'role_type' => Plan::ROLE_SCHOOL_MANAGER,
            'price' => 1000,
            'monthly_price' => 1000,
            'yearly_price' => 11000,
            'included_users_count' => 50,
            'extra_user_monthly_price' => 60,
            'billing_cycle' => Plan::BILLING_MONTHLY,
            'is_active' => true,
        ]);

        Subscription::query()->create([
            'user_id' => $manager->id,
            'plan_id' => $plan->id,
            'school_id' => $school->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Plan::BILLING_YEARLY,
            'base_price' => 11000,
            'included_users_count' => 50,
            'extra_user_monthly_price' => 60,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
        ]);

        return $manager->fresh();
    }
}

