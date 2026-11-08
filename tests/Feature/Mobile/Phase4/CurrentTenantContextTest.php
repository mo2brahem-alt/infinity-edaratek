<?php

namespace Tests\Feature\Mobile\Phase4;

use App\Models\School;
use App\Models\SchoolPermissionGroup;
use App\Models\User;
use App\Services\School\SchoolContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MobileSchoolFixtures;
use Tests\TestCase;

class CurrentTenantContextTest extends TestCase
{
    use RefreshDatabase, MobileSchoolFixtures;

    public function test_unlinked_identity_is_null_and_school_operation_requires_context_without_provisioning(): void
    {
        $s = $this->schools();
        $user = $s['A']['staff'];
        $token = $this->nativeToken($user);
        $user->update(['school_id' => null]);
        $before = School::count();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.school', null);
        $this->getJson('/api/v1/staff/tasks')->assertStatus(409)->assertJsonPath('code', 'SCHOOL_CONTEXT_REQUIRED');
        $this->assertSame($before, School::count());
        $this->assertNull($user->fresh()->school_id);
    }

    public function test_suspension_after_issuance_denies_tasks_and_manager_quick_setup(): void
    {
        $s = $this->schools();
        $token = $this->nativeToken($s['A']['staff']);
        $s['A']['school']->update(['status' => School::STATUS_SUSPENDED]);
        $this->withToken($token)->getJson('/api/v1/staff/tasks')->assertForbidden()->assertJsonPath('code', 'SCHOOL_SUSPENDED');
        $this->actingAs($s['A']['manager'])->getJson(route('api.school.quick_setup.status'))->assertForbidden();
    }

    public function test_role_and_assignment_removal_apply_to_existing_token(): void
    {
        $s = $this->schools();
        $task = $this->task($s['A']);
        $staff = $s['A']['staff'];
        $this->withToken($this->nativeToken($staff))->getJson('/api/v1/staff/tasks/'.$task->id)->assertOk();
        $task->update(['assigned_to' => $s['B']['staff']->id]);
        $this->getJson('/api/v1/staff/tasks')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/staff/tasks/'.$task->id)->assertNotFound();
        $staff->syncRoles(['teacher']);
        $staff->update(['role' => 'teacher']);
        $this->getJson('/api/v1/staff/tasks')->assertForbidden();
    }

    public function test_headers_and_raw_manager_school_id_cannot_impersonate_membership(): void
    {
        $s = $this->schools();
        $own = $this->task($s['A']);
        $this->task($s['B'], $s['A']['staff']);
        $this->withToken($this->nativeToken($s['A']['staff']))
            ->withHeaders(['X-School-Id' => (string) $s['B']['school']->id, 'X-Tenant-Id' => (string) $s['B']['school']->id])
            ->getJson('/api/v1/staff/tasks')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $own->id);
        $s['A']['manager']->update(['school_id' => $s['B']['school']->id]);
        $this->actingAs($s['A']['manager'])->getJson(route('manager.tickets.index'))->assertForbidden();
        $this->getJson(route('api.school.quick_setup.status'))->assertForbidden();
        $this->assertNull(app(SchoolContextResolver::class)->identitySchool($s['A']['manager']));
    }

    public function test_delegated_groups_are_fresh_and_scoped_to_current_school_even_when_loaded(): void
    {
        $s = $this->schools();
        $staff = $s['A']['staff'];
        $permission = 'school.academic_planning.manage';
        $group = SchoolPermissionGroup::create(['school_id' => $s['A']['school']->id, 'name' => 'Planning',
            'group_type' => 'custom', 'permission_names' => [$permission], 'created_by' => $s['A']['manager']->id]);
        $staff->schoolPermissionGroups()->attach($group);
        $staff->load('schoolPermissionGroups');
        $token = $this->nativeToken($staff);
        $this->assertContains($permission, $staff->groupedSchoolPermissionNames());
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonFragment(['capabilities' => [$permission]]);
        $this->actingAs($staff)->getJson(route('school.academic_planning.index'))->assertOk();
        $group->update(['permission_names' => []]);
        $this->assertNotContains($permission, $staff->groupedSchoolPermissionNames());
        $this->getJson(route('school.academic_planning.index'))->assertForbidden();
        $group->update(['permission_names' => [$permission]]);
        $staff->update(['school_id' => $s['B']['school']->id]);
        $this->assertNotContains($permission, $staff->groupedSchoolPermissionNames());
        $this->getJson(route('school.academic_planning.index'))->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.capabilities', []);
    }

    public function test_supervisor_current_relationship_removal_hides_list_count_and_denies_actions(): void
    {
        $s = $this->schools();
        $ticket = $this->task($s['A'])->ticket;
        $this->actingAs($s['A']['supervisor'])->getJson(route('supervisor.tickets.index', ['per_page' => 1]))
            ->assertOk()->assertJsonPath('pagination.total', 1);
        $s['A']['school']->update(['supervisor_id' => $s['B']['supervisor']->id]);
        $this->getJson(route('supervisor.tickets.index', ['per_page' => 1]))->assertOk()->assertJsonPath('pagination.total', 0);
        $this->getJson(route('supervisor.tickets.show', $ticket))->assertForbidden();
        $this->putJson(route('supervisor.tickets.update', $ticket), ['title' => 'Denied'])->assertForbidden();
        $this->postJson(route('supervisor.tickets.close', $ticket))->assertForbidden();
        $this->assertSame('Parent', $ticket->fresh()->title);
        $this->postJson(route('supervisor.tickets.store'), ['school_id' => $s['A']['school']->id,
            'assigned_to' => $s['A']['manager']->id, 'title' => 'Denied', 'description' => 'Denied'])->assertForbidden();
    }
}
