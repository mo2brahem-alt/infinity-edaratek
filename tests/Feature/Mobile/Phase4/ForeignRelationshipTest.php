<?php

namespace Tests\Feature\Mobile\Phase4;

use App\Models\Subtask;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ticketing\TicketingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Support\MobileSchoolFixtures;
use Tests\TestCase;

class ForeignRelationshipTest extends TestCase
{
    use RefreshDatabase, MobileSchoolFixtures;

    public function test_manager_foreign_parent_and_assignee_cannot_create_or_reassign(): void
    {
        $s = $this->schools();
        $own = $this->task($s['A']);
        $foreign = $this->task($s['B']);
        $count = Subtask::count();
        $this->actingAs($s['A']['manager'])->postJson(route('manager.subtasks.store'), [
            'ticket_id' => $foreign->ticket_id, 'assigned_to' => $s['A']['staff']->id, 'title' => 'Denied'])->assertForbidden();
        $this->postJson(route('manager.subtasks.store'), ['ticket_id' => $own->ticket_id,
            'assigned_to' => $s['B']['staff']->id, 'title' => 'Denied'])->assertUnprocessable();
        $this->putJson(route('manager.subtasks.update', $own), ['assigned_to' => $s['B']['staff']->id])->assertUnprocessable();
        $this->putJson(route('manager.subtasks.update', $foreign), ['title' => 'Denied'])->assertForbidden();
        $this->postJson(route('manager.subtasks.approve', $foreign))->assertForbidden();
        $this->assertSame($count, Subtask::count());
        $this->assertSame($s['A']['staff']->id, $own->fresh()->assigned_to);
        $this->assertSame(Subtask::STATUS_OPEN, $foreign->fresh()->status);
    }

    public function test_authority_body_query_and_nested_ids_are_rejected_without_writes(): void
    {
        $s = $this->schools();
        $own = $this->task($s['A']);
        $this->actingAs($s['A']['staff']);
        foreach (['school_id', 'tenant_id', 'organization_id', 'created_by', 'assigned_by', 'owner_id', 'ticket_id', 'subtask_id', 'department_id', 'user_id', 'role', 'roles', 'permissions', 'approval_status', 'is_active'] as $field) {
            $this->postJson(route('staff.subtasks.reply', $own), ['message' => 'Denied', $field => $s['B']['school']->id])->assertUnprocessable();
        }
        $this->postJson(route('staff.subtasks.submit', $own).'?school_id='.$s['B']['school']->id)->assertUnprocessable();
        $this->assertDatabaseMissing('ticket_messages', ['subtask_id' => $own->id]);
        $this->assertSame(Subtask::STATUS_OPEN, $own->fresh()->status);
        $this->actingAs($s['A']['manager'])->putJson(route('manager.subtasks.update', $own), ['ticket_id' => 999, 'title' => 'Denied'])->assertUnprocessable();
        $this->postJson(route('manager.subtasks.approve', $own), ['school_id' => $s['B']['school']->id])->assertUnprocessable();
        $this->assertSame('Synthetic task', $own->fresh()->title);
    }

    public function test_ticket_methods_cannot_read_or_mutate_foreign_school(): void
    {
        $s = $this->schools();
        $own = $this->task($s['A'])->ticket;
        $foreign = $this->task($s['B'])->ticket;
        $this->actingAs($s['A']['manager'])->getJson(route('manager.tickets.index', ['per_page' => 1, 'school_id' => $s['B']['school']->id]))
            ->assertOk()->assertJsonPath('pagination.total', 1)->assertJsonPath('data.0.id', $own->id);
        $this->getJson(route('manager.tickets.show', $foreign))->assertForbidden();
        $this->postJson(route('manager.tickets.final_report', $foreign), ['message' => 'Denied'])->assertForbidden();
        $this->postJson(route('manager.tickets.close', $foreign))->assertForbidden();
        $this->actingAs($s['A']['supervisor'])->getJson(route('supervisor.tickets.show', $foreign))->assertForbidden();
        $this->putJson(route('supervisor.tickets.update', $foreign), ['title' => 'Denied'])->assertForbidden();
        $this->postJson(route('supervisor.tickets.close', $foreign))->assertForbidden();
        $this->assertSame(Ticket::STATUS_OPEN, $foreign->fresh()->status);
        $this->assertSame('Parent', $foreign->fresh()->title);
        $this->assertDatabaseMissing('ticket_messages', ['ticket_id' => $foreign->id]);
    }

    public function test_service_rejects_foreign_actor_even_without_controller(): void
    {
        $s = $this->schools();
        $foreign = $this->task($s['B']);
        try {
            app(TicketingService::class)->markSubtaskStatus($foreign, Subtask::STATUS_SUBMITTED, $s['A']['staff']->id);
            $this->fail('The service accepted a foreign actor.');
        } catch (AuthorizationException) {
            $this->assertSame(Subtask::STATUS_OPEN, $foreign->fresh()->status);
            $this->assertDatabaseMissing('status_history', ['entity_type' => 'subtask', 'entity_id' => $foreign->id]);
        }
    }

    public function test_elevated_roles_preserve_only_their_existing_policy_powers(): void
    {
        $s = $this->schools();
        $task = $this->task($s['A']);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');
        $this->assertTrue(Gate::forUser($admin)->allows('view', $task));
        $this->assertFalse(Gate::forUser($admin)->allows('submit', $task));
        $this->assertFalse(Gate::forUser($s['A']['supervisor'])->allows('approve', $task));
        $this->assertTrue(Gate::forUser($s['A']['supervisor'])->allows('view', $task->ticket));
        $this->assertFalse(Gate::forUser($s['B']['supervisor'])->allows('view', $task->ticket));
        $this->assertTrue(Gate::forUser($s['A']['manager'])->allows('approve', $task));
    }

    public function test_inconsistent_nested_subtask_is_hidden_from_ticket_readers(): void
    {
        $s = $this->schools();
        $own = $this->task($s['A']);
        $bad = $this->task($s['B'], attributes: ['ticket_id' => $own->ticket_id]);
        $this->actingAs($s['A']['manager'])->getJson(route('manager.tickets.show', $own->ticket_id))
            ->assertOk()->assertJsonCount(1, 'subtasks')->assertJsonPath('subtasks.0.id', $own->id);
        $this->getJson(route('manager.tickets.index'))->assertOk()->assertJsonCount(1, '0.subtasks');
        $this->actingAs($s['A']['supervisor'])->getJson(route('supervisor.tickets.show', $own->ticket_id))
            ->assertOk()->assertJsonCount(1, 'subtasks')->assertJsonPath('subtasks.0.id', $own->id);
        $this->actingAs($s['A']['manager'])->putJson(route('manager.subtasks.update', $bad), ['title' => 'Denied'])->assertForbidden();
    }

    public function test_supervision_on_another_school_does_not_grant_manager_assignment_powers(): void
    {
        $s = $this->schools();
        $actor = $s['A']['manager'];
        $actor->assignRole('supervisor');
        $s['B']['school']->update(['supervisor_id' => $actor->id]);
        $foreign = $this->task($s['B']);
        $foreign->ticket->update(['created_by' => $actor->id]);
        $this->assertTrue(Gate::forUser($actor)->allows('view', $foreign->ticket->fresh()));
        $count = Subtask::count();
        $this->actingAs($actor)->postJson(route('manager.subtasks.store'), ['ticket_id' => $foreign->ticket_id,
            'assigned_to' => $s['B']['staff']->id, 'title' => 'Denied'])->assertForbidden();
        $this->assertSame($count, Subtask::count());
        $this->getJson(route('manager.tickets.show', $foreign->ticket_id))->assertForbidden();
        $this->postJson(route('manager.tickets.close', $foreign->ticket_id))->assertForbidden();
        $this->assertSame(Ticket::STATUS_OPEN, $foreign->ticket->fresh()->status);
    }

    public function test_manager_updates_and_approves_allowed_task_and_rejects_authority_and_foreign_creation(): void
    {
        $s = $this->schools();
        $own = $this->task($s['A']);
        $this->actingAs($s['A']['manager'])->putJson(route('manager.subtasks.update', $own), ['title' => 'Updated', 'status' => Subtask::STATUS_IN_PROGRESS])
            ->assertOk()->assertJsonPath('title', 'Updated')->assertJsonPath('status', Subtask::STATUS_IN_PROGRESS);
        $this->postJson(route('manager.subtasks.approve', $own))->assertOk()->assertJsonPath('status', Subtask::STATUS_APPROVED);
        $count = Ticket::count();
        $this->postJson(route('manager.tickets.store'), ['title' => 'Denied', 'assigned_to' => $s['B']['staff']->id])->assertUnprocessable();
        $this->postJson(route('manager.tickets.store'), ['title' => 'Denied', 'assigned_to' => $s['A']['staff']->id,
            'school_id' => $s['B']['school']->id])->assertUnprocessable();
        $this->assertSame($count, Ticket::count());
        $this->actingAs($s['A']['supervisor'])->postJson(route('supervisor.tickets.store'), ['title' => 'Denied', 'description' => 'Denied',
            'school_id' => $s['A']['school']->id, 'assigned_to' => $s['B']['manager']->id])->assertUnprocessable();
        $this->putJson(route('supervisor.tickets.update', $own->ticket_id), ['school_id' => $s['B']['school']->id, 'title' => 'Denied'])->assertUnprocessable();
        $this->assertSame('Parent', $own->ticket->fresh()->title);
    }
}
