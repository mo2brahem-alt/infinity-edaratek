<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\OrgStructureRoleTemplate;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DepartmentRolesStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_define_department_type_and_roles(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');
        $studentAffairsTemplate = $this->createOrgStructureRoleTemplate('موظف شؤون الطلاب');
        $registryTemplate = $this->createOrgStructureRoleTemplate('مسؤول سجلات');

        $response = $this
            ->from(route('departments.index'))
            ->actingAs($admin)
            ->post(route('departments.store'), [
                'name' => 'الشؤون الإدارية',
                'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
                'org_structure_roles' => [
                    $this->orgStructureRolePayload($studentAffairsTemplate->id),
                    $this->orgStructureRolePayload($registryTemplate->id),
                ],
            ]);

        $response->assertRedirect(route('departments.index', absolute: false));

        $department = Department::query()->where('name', 'الشؤون الإدارية')->firstOrFail();
        $this->assertNull($department->school_id);
        $this->assertSame(Department::STAFF_TYPE_ADMINISTRATIVE, $department->staff_type);

        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'name' => 'موظف شؤون الطلاب',
            'org_structure_role_template_id' => $studentAffairsTemplate->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'name' => 'مسؤول سجلات',
            'org_structure_role_template_id' => $registryTemplate->id,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_student_structure_permission_input_is_ignored_when_manager_assigns_permissions(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');
        $studentAffairsTemplate = $this->createOrgStructureRoleTemplate('موظف شؤون الطلاب');
        $registryTemplate = $this->createOrgStructureRoleTemplate('موظف سجلات');

        $response = $this
            ->from(route('departments.index'))
            ->actingAs($admin)
            ->post(route('departments.store'), [
                'name' => 'القبول والتسجيل',
                'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
                'org_structure_roles' => [
                    $this->orgStructureRolePayload($studentAffairsTemplate->id, [
                        'can_manage_student_structure' => true,
                    ]),
                    $this->orgStructureRolePayload($registryTemplate->id, [
                        'can_manage_student_structure' => false,
                    ]),
                ],
            ]);

        $response->assertRedirect(route('departments.index', absolute: false));

        $department = Department::query()->where('name', 'القبول والتسجيل')->firstOrFail();

        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'name' => 'موظف شؤون الطلاب',
            'can_manage_student_structure' => false,
        ]);

        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'name' => 'موظف سجلات',
            'can_manage_student_structure' => false,
        ]);
    }

    public function test_student_structure_permission_is_not_enabled_for_educational_department_roles(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');
        $educationalTemplate = $this->createOrgStructureRoleTemplate('أخصائي تعليم');

        $response = $this
            ->from(route('departments.index'))
            ->actingAs($admin)
            ->post(route('departments.store'), [
                'name' => 'الشؤون التعليمية',
                'staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
                'org_structure_roles' => [
                    $this->orgStructureRolePayload($educationalTemplate->id, [
                        'can_manage_student_structure' => true,
                    ]),
                ],
            ]);

        $response->assertRedirect(route('departments.index', absolute: false));

        $department = Department::query()->where('name', 'الشؤون التعليمية')->firstOrFail();

        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'name' => 'أخصائي تعليم',
            'can_manage_student_structure' => false,
        ]);
    }

    public function test_super_admin_student_attendance_permission_input_is_ignored_when_manager_assigns_permissions(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');
        $attendanceTemplate = $this->createOrgStructureRoleTemplate('موظف حضور');
        $registryTemplate = $this->createOrgStructureRoleTemplate('موظف سجلات');

        $response = $this
            ->from(route('departments.index'))
            ->actingAs($admin)
            ->post(route('departments.store'), [
                'name' => 'إدارة المتابعة',
                'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
                'org_structure_roles' => [
                    $this->orgStructureRolePayload($attendanceTemplate->id, [
                        'can_manage_student_attendance' => true,
                        'can_manage_student_structure' => false,
                    ]),
                    $this->orgStructureRolePayload($registryTemplate->id, [
                        'can_manage_student_attendance' => false,
                        'can_manage_student_structure' => false,
                    ]),
                ],
            ]);

        $response->assertRedirect(route('departments.index', absolute: false));

        $department = Department::query()->where('name', 'إدارة المتابعة')->firstOrFail();

        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'name' => 'موظف حضور',
            'can_manage_student_attendance' => false,
        ]);

        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'name' => 'موظف سجلات',
            'can_manage_student_attendance' => false,
        ]);
    }

    public function test_student_attendance_permission_is_not_enabled_for_educational_department_roles(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');
        $counselorTemplate = $this->createOrgStructureRoleTemplate('مرشد طلابي');

        $response = $this
            ->from(route('departments.index'))
            ->actingAs($admin)
            ->post(route('departments.store'), [
                'name' => 'الإرشاد الطلابي',
                'staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
                'org_structure_roles' => [
                    $this->orgStructureRolePayload($counselorTemplate->id, [
                        'can_manage_student_attendance' => true,
                        'can_manage_student_structure' => false,
                    ]),
                ],
            ]);

        $response->assertRedirect(route('departments.index', absolute: false));

        $department = Department::query()->where('name', 'الإرشاد الطلابي')->firstOrFail();

        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'name' => 'مرشد طلابي',
            'can_manage_student_attendance' => false,
        ]);
    }

    public function test_super_admin_academic_planning_permission_input_is_ignored_when_manager_assigns_permissions(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');
        $scheduleTemplate = $this->createOrgStructureRoleTemplate('منسق الجداول');
        $adminStaffTemplate = $this->createOrgStructureRoleTemplate('موظف إداري');

        $response = $this
            ->from(route('departments.index'))
            ->actingAs($admin)
            ->post(route('departments.store'), [
                'name' => 'تنظيم الجداول',
                'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
                'org_structure_roles' => [
                    $this->orgStructureRolePayload($scheduleTemplate->id, [
                        'can_manage_academic_planning' => true,
                        'can_manage_student_structure' => false,
                        'can_manage_student_attendance' => false,
                    ]),
                    $this->orgStructureRolePayload($adminStaffTemplate->id, [
                        'can_manage_academic_planning' => false,
                        'can_manage_student_structure' => false,
                        'can_manage_student_attendance' => false,
                    ]),
                ],
            ]);

        $response->assertRedirect(route('departments.index', absolute: false));

        $department = Department::query()->where('name', 'تنظيم الجداول')->firstOrFail();

        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'name' => 'منسق الجداول',
            'can_manage_academic_planning' => false,
        ]);

        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'name' => 'موظف إداري',
            'can_manage_academic_planning' => false,
        ]);
    }

    public function test_academic_planning_permission_is_not_enabled_for_educational_department_roles(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');
        $educationalCoordinatorTemplate = $this->createOrgStructureRoleTemplate('منسق تعليمي');

        $response = $this
            ->from(route('departments.index'))
            ->actingAs($admin)
            ->post(route('departments.store'), [
                'name' => 'التنسيق التعليمي',
                'staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
                'org_structure_roles' => [
                    $this->orgStructureRolePayload($educationalCoordinatorTemplate->id, [
                        'can_manage_academic_planning' => true,
                        'can_manage_student_structure' => false,
                        'can_manage_student_attendance' => false,
                    ]),
                ],
            ]);

        $response->assertRedirect(route('departments.index', absolute: false));

        $department = Department::query()->where('name', 'التنسيق التعليمي')->firstOrFail();

        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'name' => 'منسق تعليمي',
            'can_manage_academic_planning' => false,
        ]);
    }

    public function test_manager_can_create_staff_user_only_from_super_admin_defined_structure_roles(): void
    {
        foreach (['school_manager', 'staff'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $department = Department::create([
            'name' => 'الشؤون الإدارية',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'موظف شؤون الطلاب',
            'is_active' => true,
        ]);

        $region = EducationalDirectorate::create([
            'name' => 'Central',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Main School',
            'school_id' => 'SCH-710001',
            'phone' => '0500007101',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);
        Subscription::query()->create([
            'user_id' => $manager->id,
            'school_id' => $school->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => 'MONTHLY',
            'base_price' => 0,
            'included_users_count' => 10,
            'extra_user_monthly_price' => 60,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $createResponse = $this
            ->from(route('manager.structure.index'))
            ->actingAs($manager)
            ->post(route('manager.structure.users.store'), [
                'name' => 'Staff One',
                'email' => 'staff.one@example.com',
                'mobile' => '0500007102',
                'department_id' => $department->id,
                'department_role_id' => $departmentRole->id,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $createResponse->assertRedirect(route('manager.structure.index', absolute: false));

        $staff = User::query()->where('email', 'staff.one@example.com')->firstOrFail();
        $this->assertSame($school->id, $staff->school_id);
        $this->assertSame($department->id, $staff->department_id);
        $this->assertSame($departmentRole->id, $staff->department_role_id);
        $this->assertSame(Department::STAFF_TYPE_ADMINISTRATIVE, $staff->school_staff_type);
        $this->assertTrue($staff->hasRole('staff'));
    }

    public function test_manager_cannot_assign_role_that_belongs_to_another_department(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $departmentA = Department::create([
            'name' => 'الشؤون الإدارية',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentB = Department::create([
            'name' => 'الشؤون التعليمية',
            'staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
            'school_id' => null,
        ]);

        $roleForDepartmentB = DepartmentRole::create([
            'department_id' => $departmentB->id,
            'name' => 'أخصائي تعليم',
            'is_active' => true,
        ]);

        $region = EducationalDirectorate::create([
            'name' => 'East',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Main School 2',
            'school_id' => 'SCH-710002',
            'phone' => '0500007103',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $response = $this
            ->from(route('manager.structure.index'))
            ->actingAs($manager)
            ->post(route('manager.structure.users.store'), [
                'name' => 'Staff Invalid',
                'email' => 'staff.invalid@example.com',
                'mobile' => '0500007104',
                'department_id' => $departmentA->id,
                'department_role_id' => $roleForDepartmentB->id,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response
            ->assertRedirect(route('manager.structure.index', absolute: false))
            ->assertSessionHasErrors('department_role_id');
    }

    public function test_manager_can_create_school_departments_directly_inside_his_school(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'South',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Main School 3',
            'school_id' => 'SCH-710003',
            'phone' => '0500007105',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $response = $this
            ->from(route('manager.structure.index'))
            ->actingAs($manager)
            ->post(route('manager.structure.departments.store'), [
                'name' => 'شؤون الطلاب',
                'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
                'org_structure_roles' => [
                    $this->schoolDepartmentRolePayload('منسق شؤون الطلاب'),
                ],
            ]);

        $response->assertRedirect(route('manager.structure.index', absolute: false));

        $department = Department::query()->where('name', 'شؤون الطلاب')->firstOrFail();

        $this->assertSame((int) $school->id, (int) $department->school_id);
        $this->assertSame(Department::STAFF_TYPE_ADMINISTRATIVE, $department->staff_type);
        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'name' => 'منسق شؤون الطلاب',
            'is_active' => true,
            'org_structure_role_template_id' => null,
        ]);
    }

    public function test_manager_can_update_school_department_and_manage_internal_roles(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Central 2',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Main School 3B',
            'school_id' => 'SCH-710003B',
            'phone' => '0500007115',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $department = Department::create([
            'name' => 'شؤون الطلاب',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => $school->id,
        ]);

        $existingRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'مسؤول الصفوف',
            'is_active' => true,
        ]);

        $response = $this
            ->from(route('manager.structure.index'))
            ->actingAs($manager)
            ->put(route('manager.structure.departments.update', $department), [
                'name' => 'شؤون الطلاب والمتابعة',
                'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
                'org_structure_roles' => [
                    $this->schoolDepartmentRolePayload('مسؤول شؤون الطلاب', ['id' => $existingRole->id]),
                    $this->schoolDepartmentRolePayload('مسؤول الحضور'),
                ],
            ]);

        $response->assertRedirect(route('manager.structure.index', absolute: false));

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'شؤون الطلاب والمتابعة',
            'school_id' => $school->id,
        ]);

        $this->assertDatabaseHas('department_roles', [
            'id' => $existingRole->id,
            'department_id' => $department->id,
            'name' => 'مسؤول شؤون الطلاب',
        ]);

        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'name' => 'مسؤول الحضور',
            'is_active' => true,
        ]);
    }

    public function test_manager_cannot_update_department_from_another_school(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'North',
            'governorate' => 'Riyadh',
        ]);

        $managerA = User::factory()->create(['role' => 'school_manager']);
        $managerA->assignRole('school_manager');
        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A',
            'school_id' => 'SCH-710004',
            'phone' => '0500007106',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerA->id,
        ]);
        $managerA->update(['school_id' => $schoolA->id]);

        $managerB = User::factory()->create(['role' => 'school_manager']);
        $managerB->assignRole('school_manager');
        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'School B',
            'school_id' => 'SCH-710005',
            'phone' => '0500007107',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $managerB->id,
        ]);
        $managerB->update(['school_id' => $schoolB->id]);

        $department = Department::create([
            'name' => 'شؤون الاختبارات',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => $schoolB->id,
        ]);
        DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'منسق اختبارات',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($managerA)
            ->put(route('manager.structure.departments.update', $department), [
                'name' => 'اسم غير مسموح',
                'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
                'org_structure_roles' => [
                    $this->schoolDepartmentRolePayload('منسق اختبارات'),
                ],
            ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'شؤون الاختبارات',
            'school_id' => $schoolB->id,
        ]);
    }

    public function test_manager_cannot_delete_school_department_when_users_are_still_assigned(): void
    {
        foreach (['school_manager', 'staff'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $region = EducationalDirectorate::create([
            'name' => 'West',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');
        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Main School 4',
            'school_id' => 'SCH-710006',
            'phone' => '0500007108',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);
        $manager->update(['school_id' => $school->id]);

        $department = Department::create([
            'name' => 'شؤون الحضور',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => $school->id,
        ]);
        $departmentRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'منسق حضور',
            'is_active' => true,
        ]);

        $staffUser = User::factory()->create([
            'name' => 'Department Staff',
            'email' => 'department.staff@example.com',
            'mobile' => '0500007109',
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $staffUser->syncRoles(['staff']);

        $response = $this
            ->from(route('manager.structure.index'))
            ->actingAs($manager)
            ->delete(route('manager.structure.departments.destroy', $department));

        $response
            ->assertRedirect(route('manager.structure.index', absolute: false))
            ->assertSessionHasErrors('department');

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'school_id' => $school->id,
        ]);
    }

    public function test_manager_cannot_remove_department_role_when_users_are_still_assigned(): void
    {
        foreach (['school_manager', 'staff'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $region = EducationalDirectorate::create([
            'name' => 'West 2',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');
        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Main School 5',
            'school_id' => 'SCH-710007',
            'phone' => '0500007110',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);
        $manager->update(['school_id' => $school->id]);

        $department = Department::create([
            'name' => 'شؤون الطلاب',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => $school->id,
        ]);

        $assignedRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'مسؤول شؤون الطلاب',
            'is_active' => true,
        ]);

        $otherRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'مسؤول الحضور',
            'is_active' => true,
        ]);

        $staffUser = User::factory()->create([
            'name' => 'Assigned Staff',
            'email' => 'assigned.staff.department.role@example.com',
            'mobile' => '0500007111',
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'department_role_id' => $assignedRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $staffUser->syncRoles(['staff']);

        $response = $this
            ->from(route('manager.structure.index'))
            ->actingAs($manager)
            ->put(route('manager.structure.departments.update', $department), [
                'name' => 'شؤون الطلاب',
                'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
                'org_structure_roles' => [
                    $this->schoolDepartmentRolePayload('مسؤول الحضور', ['id' => $otherRole->id]),
                ],
            ]);

        $response
            ->assertRedirect(route('manager.structure.index', absolute: false))
            ->assertSessionHasErrors('org_structure_roles');

        $this->assertDatabaseHas('department_roles', [
            'id' => $assignedRole->id,
            'department_id' => $department->id,
            'name' => 'مسؤول شؤون الطلاب',
        ]);
    }

    private function createOrgStructureRoleTemplate(string $name): OrgStructureRoleTemplate
    {
        return OrgStructureRoleTemplate::query()->create([
            'name' => $name,
            'is_active' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function orgStructureRolePayload(int $templateId, array $overrides = []): array
    {
        return array_merge([
            'org_structure_role_template_id' => $templateId,
            'can_manage_student_structure' => false,
            'can_manage_student_attendance' => false,
            'can_manage_academic_planning' => false,
            'can_manage_student_leaves' => false,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function schoolDepartmentRolePayload(string $name, array $overrides = []): array
    {
        return array_merge([
            'name' => $name,
            'can_manage_student_structure' => false,
            'can_manage_student_attendance' => false,
            'can_manage_academic_planning' => false,
            'can_manage_student_leaves' => false,
        ], $overrides);
    }
}

