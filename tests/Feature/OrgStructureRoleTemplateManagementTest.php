<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\OrgStructureRoleTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrgStructureRoleTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_update_and_disable_org_structure_role_template(): void
    {
        Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->post(route('roles.org_structure.store'), [
                'name' => 'Student Affairs Officer',
                'code' => 'STUDENT_AFFAIRS',
                'is_active' => true,
            ])
            ->assertRedirect();

        $template = OrgStructureRoleTemplate::query()
            ->where('name', 'Student Affairs Officer')
            ->firstOrFail();

        $this->assertDatabaseHas('org_structure_role_templates', [
            'id' => $template->id,
            'code' => 'STUDENT_AFFAIRS',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.org_structure_roles.index'))
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Student Affairs Officer',
                'code' => 'STUDENT_AFFAIRS',
                'is_active' => true,
            ]);

        $this->actingAs($admin)
            ->put(route('roles.org_structure.update', $template->id), [
                'name' => 'Student Affairs Specialist',
                'code' => 'STUDENT_AFFAIRS',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('org_structure_role_templates', [
            'id' => $template->id,
            'name' => 'Student Affairs Specialist',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('roles.org_structure.disable', $template->id))
            ->assertRedirect();

        $this->assertDatabaseHas('org_structure_role_templates', [
            'id' => $template->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'org_structure_role_template.created',
            'entity_type' => 'org_structure_role_template',
            'entity_id' => $template->id,
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'org_structure_role_template.updated',
            'entity_type' => 'org_structure_role_template',
            'entity_id' => $template->id,
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'org_structure_role_template.disabled',
            'entity_type' => 'org_structure_role_template',
            'entity_id' => $template->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_non_super_admin_cannot_manage_org_structure_role_templates(): void
    {
        Role::query()->firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $this->actingAs($manager)
            ->post(route('roles.org_structure.store'), [
                'name' => 'Blocked Template',
            ])
            ->assertForbidden();
    }

    public function test_departments_store_rejects_legacy_role_name_payload_and_requires_templates(): void
    {
        Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');

        $this->from(route('departments.index'))
            ->actingAs($admin)
            ->post(route('departments.store'), [
                'name' => 'Legacy Payload Department',
                'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
                'role_names' => ['Legacy Role Name'],
            ])
            ->assertRedirect(route('departments.index', absolute: false))
            ->assertSessionHasErrors('org_structure_roles');
    }

    public function test_super_admin_can_create_org_structure_template_without_code_via_json_and_code_is_generated(): void
    {
        Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');

        $response = $this->actingAs($admin)->postJson(route('admin.org_structure_roles.store'), [
            'name' => 'Student Affairs Officer',
            'is_active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Student Affairs Officer')
            ->assertJsonPath('data.is_active', true);

        $code = (string) data_get($response->json(), 'data.code', '');
        $templateId = (int) data_get($response->json(), 'data.id', 0);
        $this->assertNotSame('', $code);
        $this->assertGreaterThan(0, $templateId);

        $this->assertDatabaseHas('org_structure_role_templates', [
            'name' => 'Student Affairs Officer',
            'code' => $code,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'org_structure_role_template.created',
            'entity_type' => 'org_structure_role_template',
            'entity_id' => $templateId,
            'user_id' => $admin->id,
        ]);
    }

    public function test_generated_org_structure_template_code_is_unique_when_names_normalize_to_same_value(): void
    {
        Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');

        $firstResponse = $this->actingAs($admin)->postJson(route('admin.org_structure_roles.store'), [
            'name' => 'Student Affairs',
        ]);
        $firstResponse->assertCreated();

        $secondResponse = $this->actingAs($admin)->postJson(route('admin.org_structure_roles.store'), [
            'name' => 'Student-Affairs',
        ]);
        $secondResponse->assertCreated();

        $firstCode = (string) data_get($firstResponse->json(), 'data.code', '');
        $secondCode = (string) data_get($secondResponse->json(), 'data.code', '');

        $this->assertNotSame('', $firstCode);
        $this->assertNotSame('', $secondCode);
        $this->assertNotSame($firstCode, $secondCode);
    }

    public function test_update_generates_code_when_legacy_template_has_no_code_and_json_mode_is_used(): void
    {
        Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');

        $template = OrgStructureRoleTemplate::query()->create([
            'name' => 'Legacy Template',
            'code' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->putJson(route('admin.org_structure_roles.update', $template), [
            'name' => 'Legacy Template Updated',
            'code' => null,
            'is_active' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Legacy Template Updated');

        $template->refresh();
        $this->assertNotNull($template->code);
        $this->assertNotSame('', (string) $template->code);
    }

    public function test_non_json_create_without_code_remains_backward_compatible_and_redirects(): void
    {
        Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');

        $this->from(route('roles.index'))
            ->actingAs($admin)
            ->post(route('roles.org_structure.store'), [
                'name' => 'Admissions Coordinator',
                'is_active' => true,
            ])
            ->assertRedirect(route('roles.index', absolute: false));

        $template = OrgStructureRoleTemplate::query()
            ->where('name', 'Admissions Coordinator')
            ->firstOrFail();

        $this->assertNotNull($template->code);
        $this->assertNotSame('', (string) $template->code);
    }

    public function test_super_admin_cannot_create_template_with_duplicate_code(): void
    {
        Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');

        OrgStructureRoleTemplate::query()->create([
            'name' => 'Existing Template',
            'code' => 'EXISTING_TEMPLATE',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.org_structure_roles.store'), [
            'name' => 'Duplicate Code Template',
            'code' => 'EXISTING_TEMPLATE',
            'is_active' => true,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }
}
