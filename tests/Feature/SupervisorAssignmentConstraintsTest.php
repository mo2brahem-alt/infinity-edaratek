<?php

namespace Tests\Feature;

use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupervisorAssignmentConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_cannot_have_more_than_one_supervisor_and_supervisor_can_manage_multiple_schools(): void
    {
        foreach (['super_admin', 'supervisor'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');

        $supervisorOne = User::factory()->create(['role' => 'supervisor']);
        $supervisorOne->assignRole('supervisor');

        $supervisorTwo = User::factory()->create(['role' => 'supervisor']);
        $supervisorTwo->assignRole('supervisor');

        $region = EducationalDirectorate::create([
            'name' => 'North',
            'governorate' => 'Riyadh',
        ]);

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A',
            'school_id' => 'SCH-500001',
            'phone' => '0500005001',
            'status' => School::STATUS_SUSPENDED,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'School B',
            'school_id' => 'SCH-500002',
            'phone' => '0500005002',
            'status' => School::STATUS_SUSPENDED,
        ]);

        $createFirst = $this
            ->from(route('admin.supervisor_assignments.index'))
            ->actingAs($admin)
            ->post(route('admin.supervisor_assignments.store'), [
                'supervisor_id' => $supervisorOne->id,
                'school_id' => $schoolA->id,
                'is_active' => true,
            ]);
        $createFirst->assertRedirect(route('admin.supervisor_assignments.index', absolute: false));

        $this->assertDatabaseHas('school_supervisor_assignments', [
            'school_id' => $schoolA->id,
            'supervisor_id' => $supervisorOne->id,
        ]);
        $this->assertDatabaseCount('school_supervisor_assignments', 1);

        $repeatSameSupervisor = $this
            ->from(route('admin.supervisor_assignments.index'))
            ->actingAs($admin)
            ->post(route('admin.supervisor_assignments.store'), [
                'supervisor_id' => $supervisorOne->id,
                'school_id' => $schoolA->id,
                'is_active' => true,
            ]);
        $repeatSameSupervisor->assertRedirect(route('admin.supervisor_assignments.index', absolute: false));

        $this->assertDatabaseCount('school_supervisor_assignments', 1);

        $assignDifferentSupervisorToSameSchool = $this
            ->from(route('admin.supervisor_assignments.index'))
            ->actingAs($admin)
            ->post(route('admin.supervisor_assignments.store'), [
                'supervisor_id' => $supervisorTwo->id,
                'school_id' => $schoolA->id,
                'is_active' => true,
            ]);
        $assignDifferentSupervisorToSameSchool
            ->assertRedirect(route('admin.supervisor_assignments.index', absolute: false))
            ->assertSessionHasErrors('school_id');

        $this->assertDatabaseCount('school_supervisor_assignments', 1);

        $assignSecondSchoolToSameSupervisor = $this
            ->from(route('admin.supervisor_assignments.index'))
            ->actingAs($admin)
            ->post(route('admin.supervisor_assignments.store'), [
                'supervisor_id' => $supervisorOne->id,
                'school_id' => $schoolB->id,
                'is_active' => true,
            ]);
        $assignSecondSchoolToSameSupervisor->assertRedirect(route('admin.supervisor_assignments.index', absolute: false));

        $this->assertDatabaseHas('school_supervisor_assignments', [
            'school_id' => $schoolB->id,
            'supervisor_id' => $supervisorOne->id,
        ]);
        $this->assertDatabaseCount('school_supervisor_assignments', 2);

        $schoolA->refresh();
        $schoolB->refresh();
        $this->assertSame($supervisorOne->id, $schoolA->supervisor_id);
        $this->assertSame($supervisorOne->id, $schoolB->supervisor_id);
    }
}
