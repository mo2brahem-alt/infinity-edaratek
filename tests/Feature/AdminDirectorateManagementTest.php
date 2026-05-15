<?php

namespace Tests\Feature;

use App\Models\EducationalDirectorate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDirectorateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_governorate_and_education_type_entry(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this
            ->from(route('admin.schools.index'))
            ->actingAs($admin)
            ->post(route('admin.directorates.store'), [
                'name' => 'خاص',
                'governorate' => 'الرياض',
            ]);

        $response->assertRedirect(route('admin.schools.index', absolute: false));

        $this->assertDatabaseHas('educational_directorates', [
            'name' => 'خاص',
            'governorate' => 'الرياض',
        ]);
    }

    public function test_super_admin_can_update_education_type_within_governorate(): void
    {
        $admin = $this->createSuperAdmin();

        $directorate = EducationalDirectorate::query()->create([
            'name' => 'أهلي',
            'governorate' => 'الرياض',
        ]);

        $response = $this
            ->from(route('admin.schools.index'))
            ->actingAs($admin)
            ->put(route('admin.directorates.update', $directorate->id), [
                'name' => 'خاص',
                'governorate' => 'الرياض',
            ]);

        $response->assertRedirect(route('admin.schools.index', absolute: false));

        $this->assertDatabaseHas('educational_directorates', [
            'id' => $directorate->id,
            'name' => 'خاص',
            'governorate' => 'الرياض',
        ]);
    }

    public function test_super_admin_cannot_create_school_from_admin_panel_anymore(): void
    {
        $admin = $this->createSuperAdmin();

        $directorate = EducationalDirectorate::query()->create([
            'name' => 'خاص',
            'governorate' => 'الرياض',
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.schools.store'), [
                'directorate_id' => $directorate->id,
                'name' => 'مدرسة ممنوعة',
                'phone' => '0500000001',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('schools', [
            'name' => 'مدرسة ممنوعة',
        ]);
    }

    private function createSuperAdmin(): User
    {
        Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $admin = User::factory()->create([
            'role' => 'super_admin',
        ]);
        $admin->assignRole('super_admin');

        return $admin;
    }
}
