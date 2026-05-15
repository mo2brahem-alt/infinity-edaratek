<?php

namespace Tests\Feature;

use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\Subtask;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketWorkflowAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_and_subtask_workflow_with_role_restrictions(): void
    {
        foreach (['supervisor', 'school_manager', 'staff'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $supervisor->assignRole('supervisor');

        $otherSupervisor = User::factory()->create(['role' => 'supervisor']);
        $otherSupervisor->assignRole('supervisor');

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $otherManager = User::factory()->create(['role' => 'school_manager']);
        $otherManager->assignRole('school_manager');

        $staff = User::factory()->create(['role' => 'staff']);
        $staff->assignRole('staff');

        $otherStaff = User::factory()->create(['role' => 'staff']);
        $otherStaff->assignRole('staff');

        $directorate = EducationalDirectorate::create([
            'name' => 'Central',
            'governorate' => 'Riyadh',
        ]);

        $school = School::create([
            'directorate_id' => $directorate->id,
            'name' => 'Main School',
            'school_id' => 'SCH-200001',
            'phone' => '0500000010',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'supervisor_id' => $supervisor->id,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);
        $staff->update(['school_id' => $school->id]);

        $otherSchool = School::create([
            'directorate_id' => $directorate->id,
            'name' => 'Other School',
            'school_id' => 'SCH-200002',
            'phone' => '0500000011',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'supervisor_id' => $otherSupervisor->id,
            'manager_user_id' => $otherManager->id,
        ]);

        $otherManager->update(['school_id' => $otherSchool->id]);
        $otherStaff->update(['school_id' => $otherSchool->id]);

        $createInternalTaskResponse = $this->actingAs($manager)->post(route('manager.tickets.store'), [
            'title' => 'Internal task for school structure',
            'description' => 'Manager-created task',
            'priority' => 'MEDIUM',
            'assigned_to' => $staff->id,
        ]);

        $createInternalTaskResponse->assertCreated();

        $internalTicket = Ticket::query()
            ->where('created_by', $manager->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($manager->id, $internalTicket->assigned_to);
        $this->assertSame($school->id, $internalTicket->school_id);
        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $internalTicket->status);
        $this->assertDatabaseHas('subtasks', [
            'ticket_id' => $internalTicket->id,
            'assigned_to' => $staff->id,
            'school_id' => $school->id,
        ]);

        $showInternalTicket = $this->actingAs($manager)->get(route('manager.tickets.show', $internalTicket));
        $showInternalTicket
            ->assertOk()
            ->assertJsonPath('id', $internalTicket->id);

        $showInternalTicketByOtherManager = $this->actingAs($otherManager)->get(route('manager.tickets.show', $internalTicket));
        $showInternalTicketByOtherManager->assertForbidden();

        $createInvalidInternalTaskResponse = $this->actingAs($manager)->post(route('manager.tickets.store'), [
            'title' => 'Cross-school assignment',
            'description' => 'Should fail',
            'priority' => 'HIGH',
            'assigned_to' => $otherStaff->id,
        ]);

        $createInvalidInternalTaskResponse->assertStatus(422);

        $finalReportForInternalTask = $this->actingAs($manager)->post(route('manager.tickets.final_report', $internalTicket), [
            'manager_final_report' => 'not allowed for internal task',
        ]);
        $finalReportForInternalTask->assertStatus(422);

        $closeInternalTaskByOwner = $this->actingAs($manager)->post(route('manager.tickets.close', $internalTicket));
        $closeInternalTaskByOwner->assertOk();

        $internalTicket->refresh();
        $this->assertSame(Ticket::STATUS_CLOSED, $internalTicket->status);

        $closeInternalTaskByOtherManager = $this->actingAs($otherManager)->post(route('manager.tickets.close', $internalTicket));
        $closeInternalTaskByOtherManager->assertForbidden();

        $createTicketResponse = $this->actingAs($supervisor)->post(route('supervisor.tickets.store'), [
            'title' => 'Weekly Follow-up',
            'description' => 'Check weekly indicators',
            'priority' => 'HIGH',
            'school_id' => $school->id,
            'assigned_to' => $manager->id,
        ]);

        $createTicketResponse->assertCreated();

        $ticket = Ticket::query()
            ->where('created_by', $supervisor->id)
            ->latest('id')
            ->firstOrFail();

        $forbiddenTicketResponse = $this->actingAs($supervisor)->post(route('supervisor.tickets.store'), [
            'title' => 'Forbidden',
            'description' => 'Should fail',
            'priority' => 'LOW',
            'school_id' => $otherSchool->id,
            'assigned_to' => $manager->id,
        ]);

        $forbiddenTicketResponse->assertForbidden();

        $showManagerTicket = $this->actingAs($manager)->get(route('manager.tickets.show', $ticket));
        $showManagerTicket
            ->assertOk()
            ->assertJsonPath('id', $ticket->id);

        $createSubtaskResponse = $this->actingAs($manager)->post(route('manager.subtasks.store'), [
            'ticket_id' => $ticket->id,
            'title' => 'Collect evidence',
            'description' => 'Upload evidence file',
            'assigned_to' => $staff->id,
        ]);

        $createSubtaskResponse->assertCreated();

        $invalidSubtaskResponse = $this->actingAs($manager)->post(route('manager.subtasks.store'), [
            'ticket_id' => $ticket->id,
            'title' => 'Invalid assignment',
            'description' => 'Wrong school staff',
            'assigned_to' => $otherStaff->id,
        ]);

        $invalidSubtaskResponse->assertStatus(422);

        $subtask = Subtask::query()
            ->where('ticket_id', $ticket->id)
            ->latest('id')
            ->firstOrFail();

        $showOwnSubtask = $this->actingAs($staff)->get(route('staff.subtasks.show', $subtask));
        $showOwnSubtask
            ->assertOk()
            ->assertJsonPath('id', $subtask->id);

        $showSubtaskForbidden = $this->actingAs($otherStaff)->get(route('staff.subtasks.show', $subtask));
        $showSubtaskForbidden->assertForbidden();

        $submitOwnSubtask = $this->actingAs($staff)->post(route('staff.subtasks.submit', $subtask));
        $submitOwnSubtask->assertOk();

        $submitForbidden = $this->actingAs($otherStaff)->post(route('staff.subtasks.submit', $subtask));
        $submitForbidden->assertForbidden();

        $finalReport = $this->actingAs($manager)->post(route('manager.tickets.final_report', $ticket), [
            'manager_final_report' => 'Final report submitted',
        ]);
        $finalReport->assertOk();

        $closeTicket = $this->actingAs($supervisor)->post(route('supervisor.tickets.close', $ticket));
        $closeTicket->assertOk();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_CLOSED, $ticket->status);

        $secondaryManagerSchool = School::create([
            'directorate_id' => $directorate->id,
            'name' => 'Manager Secondary School',
            'school_id' => 'SCH-200003',
            'phone' => '0500000012',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'supervisor_id' => $supervisor->id,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $secondaryManagerSchool->id]);

        $this->actingAs($manager)
            ->get(route('manager.tickets.show', $internalTicket))
            ->assertForbidden();

        $this->actingAs($manager)
            ->getJson(route('manager.tickets.index'))
            ->assertOk()
            ->assertJsonMissing(['id' => $internalTicket->id]);
    }
}

