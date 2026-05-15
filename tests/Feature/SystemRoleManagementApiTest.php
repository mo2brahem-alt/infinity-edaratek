<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemRoleManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_role_and_sync_permissions_via_system_api(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $superAdmin->assignRole('super_admin');

        $createPermission = $this->actingAs($superAdmin)->postJson(route('api.system.permissions.store'), [
            'name' => 'school.users.manage',
            'guard_name' => 'web',
        ]);

        $createPermission->assertCreated()->assertJsonPath('data.name', 'school.users.manage');

        Permission::query()->create(['name' => 'school.roles.assign', 'guard_name' => 'web']);

        $createRole = $this->actingAs($superAdmin)->postJson(route('api.system.roles.store'), [
            'name' => 'academic_planner',
            'display_name' => 'Academic Planner',
            'description' => 'Planner role for school scheduling workflows.',
            'guard_name' => 'web',
            'assignable_by_school_admin' => true,
            'is_system' => false,
            'permission_names' => [
                'school.users.manage',
                'school.roles.assign',
            ],
        ]);

        $createRole
            ->assertCreated()
            ->assertJsonPath('data.name', 'academic_planner')
            ->assertJsonPath('data.assignable_by_school_admin', true)
            ->assertJsonPath('data.is_system', false);

        $this->assertDatabaseHas('roles', [
            'name' => 'academic_planner',
            'guard_name' => 'web',
            'assignable_by_school_admin' => true,
            'is_system' => false,
        ]);

        $role = Role::query()->where('name', 'academic_planner')->firstOrFail();
        $this->assertTrue($role->hasPermissionTo('school.users.manage'));
        $this->assertTrue($role->hasPermissionTo('school.roles.assign'));

        $syncPermissions = $this->actingAs($superAdmin)->putJson(route('api.system.roles.permissions.sync', $role), [
            'permission_names' => ['school.roles.assign'],
        ]);

        $syncPermissions
            ->assertOk()
            ->assertJsonPath('data.permission_names.0', 'school.roles.assign');

        $role->refresh();
        $this->assertFalse($role->hasPermissionTo('school.users.manage'));
        $this->assertTrue($role->hasPermissionTo('school.roles.assign'));
    }

    public function test_school_manager_is_forbidden_from_system_roles_api(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        $role = Role::query()->create([
            'name' => 'existing_role',
            'guard_name' => 'web',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $forbiddenIndex = $this->actingAs($manager)->getJson(route('api.system.roles.index'));
        $forbiddenIndex->assertForbidden();

        $forbiddenStore = $this->actingAs($manager)->postJson(route('api.system.roles.store'), [
            'name' => 'forbidden_role',
            'guard_name' => 'web',
        ]);
        $forbiddenStore->assertForbidden();

        $forbiddenPermissionStore = $this->actingAs($manager)->postJson(route('api.system.permissions.store'), [
            'name' => 'system.permissions.manage',
            'guard_name' => 'web',
        ]);
        $forbiddenPermissionStore->assertForbidden();

        $forbiddenUpdate = $this->actingAs($manager)->putJson(route('api.system.roles.update', $role), [
            'display_name' => 'Manager Should Not Update',
        ]);
        $forbiddenUpdate->assertForbidden();

        $this->assertDatabaseMissing('roles', [
            'name' => 'forbidden_role',
            'guard_name' => 'web',
        ]);
    }

    public function test_super_admin_cannot_delete_system_role_from_system_api(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $superAdmin->assignRole('super_admin');

        $systemRole = Role::query()->create([
            'name' => 'system_only_role',
            'guard_name' => 'web',
            'is_system' => true,
            'assignable_by_school_admin' => false,
        ]);

        $response = $this->actingAs($superAdmin)->deleteJson(route('api.system.roles.destroy', $systemRole));

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseHas('roles', [
            'id' => $systemRole->id,
            'name' => 'system_only_role',
        ]);
    }
}
