<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\EducationStage;
use App\Models\EducationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDefaultStageTemplateLinkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_persist_master_education_stage_link_on_default_stage_template(): void
    {
        $admin = $this->createSuperAdmin();
        $country = Country::query()->create(['name' => 'مصر']);
        $educationType = EducationType::query()->create(['name' => 'تعليم عام']);
        $educationStage = EducationStage::query()->create([
            'name' => 'المرحلة الابتدائية',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.school_defaults.stages.store'), [
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'education_stage_id' => $educationStage->id,
            'name' => 'اسم سيتم استبداله من الماستر',
            'code' => 'STG-PRI',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.education_stage_id', $educationStage->id)
            ->assertJsonPath('data.name', 'المرحلة الابتدائية');

        $this->assertDatabaseHas('school_default_stage_templates', [
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'education_stage_id' => $educationStage->id,
            'name' => 'المرحلة الابتدائية',
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
