<?php

namespace Tests\Feature;

use App\Models\EducationalDirectorate;
use App\Models\EducationStage;
use App\Models\Notification;
use App\Models\School;
use App\Models\SchoolSupervisorAssignment;
use App\Models\SchoolSupervisionRequest;
use App\Models\Subtask;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RealtimeNotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_school_creation_notifies_super_admin_and_responsible_supervisor_with_action_routes(): void
    {
        foreach (['super_admin', 'supervisor', 'school_manager'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $region = EducationalDirectorate::create([
            'name' => 'North Region',
            'governorate' => 'Riyadh',
        ]);

        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $superAdmin->assignRole('super_admin');

        $supervisor = User::factory()->create([
            'role' => 'supervisor',
            'is_active' => true,
        ]);
        $supervisor->assignRole('supervisor');

        $otherSupervisor = User::factory()->create([
            'role' => 'supervisor',
            'is_active' => true,
        ]);
        $otherSupervisor->assignRole('supervisor');

        SchoolSupervisorAssignment::create([
            'supervisor_id' => $supervisor->id,
            'directorate_id' => $region->id,
            'school_id' => null,
            'is_active' => true,
        ]);

        $manager = User::factory()->create([
            'role' => 'school_manager',
            'is_active' => true,
        ]);
        $manager->assignRole('school_manager');
        $educationStage = EducationStage::query()->create([
            'name' => 'ابتدائي',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $createResponse = $this->actingAs($manager)->post(route('manager.onboarding.schools.store'), [
            'region_id' => $region->id,
            'school_type' => School::TYPE_MIXED,
            'education_stage_ids' => [$educationStage->id],
            'name' => 'Realtime Notifications School',
            'phone' => '0500011111',
            'address' => 'Riyadh',
        ]);

        $createResponse->assertStatus(201);
        $schoolId = (int) $createResponse->json('school.id');
        $this->assertGreaterThan(0, $schoolId);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $superAdmin->id,
            'type' => 'MANAGER_SCHOOL_CREATED',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $supervisor->id,
            'type' => 'MANAGER_SCHOOL_CREATED',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $otherSupervisor->id,
            'type' => 'MANAGER_SCHOOL_CREATED',
        ]);

        $superAdminNotification = Notification::query()
            ->where('user_id', $superAdmin->id)
            ->where('type', 'MANAGER_SCHOOL_CREATED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('admin.schools.index', data_get($superAdminNotification->data, 'action_route_name'));
        $this->assertSame($schoolId, (int) data_get($superAdminNotification->data, 'school_id'));

        $supervisorNotification = Notification::query()
            ->where('user_id', $supervisor->id)
            ->where('type', 'MANAGER_SCHOOL_CREATED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('supervisor.onboarding.show', data_get($supervisorNotification->data, 'action_route_name'));
        $this->assertSame($schoolId, (int) data_get($supervisorNotification->data, 'school_id'));
    }

    public function test_supervision_and_ticket_workflow_emits_role_scoped_notifications_with_action_routes(): void
    {
        foreach (['super_admin', 'supervisor', 'school_manager', 'staff'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $superAdmin->assignRole('super_admin');

        $supervisor = User::factory()->create([
            'role' => 'supervisor',
            'is_active' => true,
        ]);
        $supervisor->assignRole('supervisor');

        $manager = User::factory()->create([
            'role' => 'school_manager',
            'is_active' => true,
        ]);
        $manager->assignRole('school_manager');

        $staff = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);
        $staff->assignRole('staff');

        $region = EducationalDirectorate::create([
            'name' => 'Central',
            'governorate' => 'Riyadh',
        ]);

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Action Routed School',
            'school_id' => 'SCH-900001',
            'phone' => '0500090001',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'supervisor_id' => $supervisor->id,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);
        $staff->update(['school_id' => $school->id]);

        $pendingRequest = SchoolSupervisionRequest::create([
            'school_id' => $school->id,
            'region_id' => $region->id,
            'supervisor_id' => $supervisor->id,
            'manager_id' => $manager->id,
            'status' => SchoolSupervisionRequest::STATUS_SUPERVISOR_REQUESTED,
            'requested_at' => now(),
        ]);

        $approveRequest = $this->actingAs($manager)->post(route('manager.requests.approve', $pendingRequest));
        $approveRequest->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $supervisor->id,
            'type' => 'SUPERVISION_REQUEST_MANAGER_APPROVED',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $superAdmin->id,
            'type' => 'SUPERVISION_REQUEST_MANAGER_APPROVED',
        ]);

        $supervisorApprovalNotification = Notification::query()
            ->where('user_id', $supervisor->id)
            ->where('type', 'SUPERVISION_REQUEST_MANAGER_APPROVED')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('supervisor.requests.page', data_get($supervisorApprovalNotification->data, 'action_route_name'));

        $confirmRequest = $this->actingAs($supervisor)->post(route('supervisor.requests.confirm', $pendingRequest->refresh()));
        $confirmRequest->assertOk();

        $createTicketResponse = $this->actingAs($supervisor)->post(route('supervisor.tickets.store'), [
            'title' => 'Weekly Follow-up',
            'description' => 'Check school indicators',
            'priority' => 'HIGH',
            'school_id' => $school->id,
            'assigned_to' => $manager->id,
        ]);
        $createTicketResponse->assertCreated();

        $ticket = Ticket::query()
            ->where('created_by', $supervisor->id)
            ->latest('id')
            ->firstOrFail();

        $createSubtaskResponse = $this->actingAs($manager)->post(route('manager.subtasks.store'), [
            'ticket_id' => $ticket->id,
            'title' => 'Collect evidence',
            'description' => 'Upload proof',
            'assigned_to' => $staff->id,
        ]);
        $createSubtaskResponse->assertCreated();

        $subtask = Subtask::query()
            ->where('ticket_id', $ticket->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $staff->id,
            'type' => 'SUBTASK_ASSIGNED',
        ]);

        $assignedNotification = Notification::query()
            ->where('user_id', $staff->id)
            ->where('type', 'SUBTASK_ASSIGNED')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('staff.subtasks.show', data_get($assignedNotification->data, 'action_route_name'));
        $this->assertSame($subtask->id, (int) data_get($assignedNotification->data, 'subtask_id'));
        $this->assertSame($subtask->id, (int) data_get($assignedNotification->data, 'action_route_params.subtask'));

        $submitResponse = $this->actingAs($staff)->post(route('staff.subtasks.submit', $subtask));
        $submitResponse->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'type' => 'SUBTASK_SUBMITTED',
        ]);

        $submittedNotification = Notification::query()
            ->where('user_id', $manager->id)
            ->where('type', 'SUBTASK_SUBMITTED')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('manager.tickets.show', data_get($submittedNotification->data, 'action_route_name'));
        $this->assertSame($ticket->id, (int) data_get($submittedNotification->data, 'ticket_id'));
        $this->assertSame($ticket->id, (int) data_get($submittedNotification->data, 'action_route_params.ticket'));

        $finalReportResponse = $this->actingAs($manager)->post(route('manager.tickets.final_report', $ticket), [
            'manager_final_report' => 'Everything completed.',
        ]);
        $finalReportResponse->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $supervisor->id,
            'type' => 'TICKET_FINAL_REPORT_SUBMITTED',
        ]);

        $closeResponse = $this->actingAs($supervisor)->post(route('supervisor.tickets.close', $ticket));
        $closeResponse->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'type' => 'TICKET_CLOSED',
        ]);
    }
}
