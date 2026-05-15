<?php

namespace Tests\Feature;

use App\Models\EducationalDirectorate;
use App\Models\Media;
use App\Models\School;
use App\Models\Subtask;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HeavyEndpointsPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_media_index_supports_optional_pagination(): void
    {
        $admin = $this->createUserWithRole('super_admin');

        Media::query()->create([
            'file_name' => 'one.jpg',
            'file_path' => 'uploads/one.jpg',
            'file_type' => 'image',
            'mime_type' => 'image/jpeg',
            'file_size' => 1000,
        ]);
        Media::query()->create([
            'file_name' => 'two.jpg',
            'file_path' => 'uploads/two.jpg',
            'file_type' => 'image',
            'mime_type' => 'image/jpeg',
            'file_size' => 1000,
        ]);
        Media::query()->create([
            'file_name' => 'three.mp4',
            'file_path' => 'uploads/three.mp4',
            'file_type' => 'video',
            'mime_type' => 'video/mp4',
            'file_size' => 2000,
        ]);

        $defaultResponse = $this->actingAs($admin)->getJson(route('admin.media.index'));
        $defaultResponse->assertOk()->assertJsonCount(3);

        $paginated = $this->actingAs($admin)->getJson(route('admin.media.index', [
            'per_page' => 2,
            'page' => 1,
        ]));

        $paginated
            ->assertOk()
            ->assertJsonPath('pagination.per_page', 2)
            ->assertJsonPath('pagination.total', 3)
            ->assertJsonCount(2, 'data');

        $invalid = $this->actingAs($admin)->getJson(route('admin.media.index', [
            'per_page' => 0,
        ]));
        $invalid->assertStatus(422)->assertJsonValidationErrors('per_page');
    }

    public function test_manager_tickets_index_supports_optional_pagination_without_data_leakage(): void
    {
        $managerA = $this->createUserWithRole('school_manager');
        $managerB = $this->createUserWithRole('school_manager');

        $schoolA = $this->createSchool('SCH-980001', $managerA);
        $schoolB = $this->createSchool('SCH-980002', $managerB);

        $managerA->update(['school_id' => $schoolA->id]);
        $managerB->update(['school_id' => $schoolB->id]);

        Ticket::query()->create([
            'school_id' => $schoolA->id,
            'created_by' => $managerA->id,
            'assigned_to' => $managerA->id,
            'title' => 'A-1',
            'description' => 'Ticket A-1',
            'priority' => 'MEDIUM',
            'status' => Ticket::STATUS_OPEN,
        ]);
        Ticket::query()->create([
            'school_id' => $schoolA->id,
            'created_by' => $managerA->id,
            'assigned_to' => $managerA->id,
            'title' => 'A-2',
            'description' => 'Ticket A-2',
            'priority' => 'MEDIUM',
            'status' => Ticket::STATUS_OPEN,
        ]);
        Ticket::query()->create([
            'school_id' => $schoolB->id,
            'created_by' => $managerB->id,
            'assigned_to' => $managerB->id,
            'title' => 'B-1',
            'description' => 'Ticket B-1',
            'priority' => 'MEDIUM',
            'status' => Ticket::STATUS_OPEN,
        ]);

        $defaultResponse = $this->actingAs($managerA)->getJson(route('manager.tickets.index'));
        $defaultResponse
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonMissing(['title' => 'B-1']);

        $paginated = $this->actingAs($managerA)->getJson(route('manager.tickets.index', [
            'per_page' => 1,
            'page' => 1,
        ]));

        $paginated
            ->assertOk()
            ->assertJsonPath('pagination.per_page', 1)
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['title' => 'B-1']);

        $invalid = $this->actingAs($managerA)->getJson(route('manager.tickets.index', [
            'per_page' => 101,
        ]));
        $invalid->assertStatus(422)->assertJsonValidationErrors('per_page');
    }

    public function test_supervisor_tickets_index_supports_optional_pagination_without_data_leakage(): void
    {
        $supervisorA = $this->createUserWithRole('supervisor');
        $supervisorB = $this->createUserWithRole('supervisor');
        $managerA = $this->createUserWithRole('school_manager');
        $managerB = $this->createUserWithRole('school_manager');

        $schoolA = $this->createSchool('SCH-980003', $managerA, $supervisorA);
        $schoolB = $this->createSchool('SCH-980004', $managerB, $supervisorB);

        Ticket::query()->create([
            'school_id' => $schoolA->id,
            'created_by' => $supervisorA->id,
            'assigned_to' => $managerA->id,
            'title' => 'S-A-1',
            'description' => 'Supervisor A Ticket 1',
            'priority' => 'HIGH',
            'status' => Ticket::STATUS_OPEN,
        ]);
        Ticket::query()->create([
            'school_id' => $schoolA->id,
            'created_by' => $supervisorA->id,
            'assigned_to' => $managerA->id,
            'title' => 'S-A-2',
            'description' => 'Supervisor A Ticket 2',
            'priority' => 'HIGH',
            'status' => Ticket::STATUS_OPEN,
        ]);
        Ticket::query()->create([
            'school_id' => $schoolB->id,
            'created_by' => $supervisorB->id,
            'assigned_to' => $managerB->id,
            'title' => 'S-B-1',
            'description' => 'Supervisor B Ticket 1',
            'priority' => 'HIGH',
            'status' => Ticket::STATUS_OPEN,
        ]);

        $defaultResponse = $this->actingAs($supervisorA)->getJson(route('supervisor.tickets.index'));
        $defaultResponse
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonMissing(['title' => 'S-B-1']);

        $paginated = $this->actingAs($supervisorA)->getJson(route('supervisor.tickets.index', [
            'per_page' => 1,
            'page' => 1,
        ]));

        $paginated
            ->assertOk()
            ->assertJsonPath('pagination.per_page', 1)
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['title' => 'S-B-1']);

        $invalid = $this->actingAs($supervisorA)->getJson(route('supervisor.tickets.index', [
            'per_page' => -1,
        ]));
        $invalid->assertStatus(422)->assertJsonValidationErrors('per_page');
    }

    public function test_staff_subtasks_index_supports_optional_pagination_without_data_leakage(): void
    {
        $manager = $this->createUserWithRole('school_manager');
        $staffA = $this->createUserWithRole('staff');
        $staffB = $this->createUserWithRole('staff');

        $school = $this->createSchool('SCH-980005', $manager);
        $manager->update(['school_id' => $school->id]);
        $staffA->update(['school_id' => $school->id]);
        $staffB->update(['school_id' => $school->id]);

        $ticket = Ticket::query()->create([
            'school_id' => $school->id,
            'created_by' => $manager->id,
            'assigned_to' => $manager->id,
            'title' => 'Subtask Root',
            'description' => 'Subtask Root Description',
            'priority' => 'MEDIUM',
            'status' => Ticket::STATUS_OPEN,
        ]);

        Subtask::query()->create([
            'ticket_id' => $ticket->id,
            'school_id' => $school->id,
            'created_by' => $manager->id,
            'assigned_to' => $staffA->id,
            'title' => 'ST-A-1',
            'status' => Subtask::STATUS_OPEN,
        ]);
        Subtask::query()->create([
            'ticket_id' => $ticket->id,
            'school_id' => $school->id,
            'created_by' => $manager->id,
            'assigned_to' => $staffA->id,
            'title' => 'ST-A-2',
            'status' => Subtask::STATUS_OPEN,
        ]);
        Subtask::query()->create([
            'ticket_id' => $ticket->id,
            'school_id' => $school->id,
            'created_by' => $manager->id,
            'assigned_to' => $staffB->id,
            'title' => 'ST-B-1',
            'status' => Subtask::STATUS_OPEN,
        ]);

        $defaultResponse = $this->actingAs($staffA)->getJson(route('staff.subtasks.index'));
        $defaultResponse
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonMissing(['title' => 'ST-B-1']);

        $paginated = $this->actingAs($staffA)->getJson(route('staff.subtasks.index', [
            'per_page' => 1,
            'page' => 1,
        ]));

        $paginated
            ->assertOk()
            ->assertJsonPath('pagination.per_page', 1)
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['title' => 'ST-B-1']);

        $invalid = $this->actingAs($staffA)->getJson(route('staff.subtasks.index', [
            'per_page' => 999,
        ]));
        $invalid->assertStatus(422)->assertJsonValidationErrors('per_page');
    }

    private function createUserWithRole(string $roleName): User
    {
        Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create([
            'role' => $roleName,
            'is_active' => true,
        ]);
        $user->assignRole($roleName);

        return $user;
    }

    private function createSchool(string $schoolCode, User $manager, ?User $supervisor = null): School
    {
        $digits = preg_replace('/\D+/', '', $schoolCode) ?: '0';
        $schoolPhone = '05' . str_pad(substr($digits, -8), 8, '0', STR_PAD_LEFT);

        $region = EducationalDirectorate::query()->create([
            'name' => 'Region ' . $schoolCode,
            'governorate' => 'Riyadh',
        ]);

        return School::query()->create([
            'directorate_id' => $region->id,
            'name' => 'School ' . $schoolCode,
            'school_id' => $schoolCode,
            'phone' => $schoolPhone,
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
            'supervisor_id' => $supervisor?->id,
        ]);
    }
}

