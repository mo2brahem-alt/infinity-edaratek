<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\EducationType;
use App\Models\SchoolDefaultStageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSchoolDefaultEducationTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manage_education_types_from_school_defaults_feature(): void
    {
        $admin = $this->createSuperAdmin();

        $createResponse = $this->actingAs($admin)->postJson(route('admin.education_types.store'), [
            'name' => 'وطني',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'وطني');

        $educationTypeId = (int) $createResponse->json('data.id');

        $this->actingAs($admin)
            ->putJson(route('admin.education_types.update', $educationTypeId), [
                'name' => 'وطني محدث',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'وطني محدث');

        $this->actingAs($admin)
            ->deleteJson(route('admin.education_types.delete', $educationTypeId))
            ->assertOk()
            ->assertJsonPath('status', 'deleted');

        $this->assertDatabaseMissing('education_types', [
            'id' => $educationTypeId,
        ]);
    }

    public function test_super_admin_cannot_delete_education_type_used_by_default_templates(): void
    {
        $admin = $this->createSuperAdmin();
        $country = Country::query()->create(['name' => 'السعودية']);
        $educationType = EducationType::query()->create(['name' => 'أهلي']);

        SchoolDefaultStageTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'مرحلة أهلية',
            'code' => 'STG-AHL',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.education_types.delete', $educationType->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors('education_type');

        $this->assertDatabaseHas('education_types', [
            'id' => $educationType->id,
            'name' => 'أهلي',
        ]);
    }

    private function createSuperAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');

        return $admin;
    }
}
