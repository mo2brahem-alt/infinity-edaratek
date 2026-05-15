<?php

namespace Tests\Feature;

use App\Models\EducationStage;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolDefaultStageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminEducationStageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manage_education_stages_from_school_defaults_feature(): void
    {
        $admin = $this->createSuperAdmin();

        $createResponse = $this->actingAs($admin)->postJson(route('admin.education_stages.store'), [
            'name' => 'ابتدائي',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'ابتدائي')
            ->assertJsonPath('data.sort_order', 20)
            ->assertJsonPath('data.is_active', true);

        $educationStageId = (int) $createResponse->json('data.id');

        $this->actingAs($admin)
            ->putJson(route('admin.education_stages.update', $educationStageId), [
                'name' => 'ابتدائي محدث',
                'sort_order' => 25,
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'ابتدائي محدث')
            ->assertJsonPath('data.sort_order', 25)
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($admin)
            ->deleteJson(route('admin.education_stages.delete', $educationStageId))
            ->assertOk()
            ->assertJsonPath('status', 'deleted');

        $this->assertDatabaseMissing('education_stages', [
            'id' => $educationStageId,
        ]);
    }

    public function test_school_manager_cannot_manage_global_education_stages(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $this->actingAs($manager)
            ->postJson(route('admin.education_stages.store'), [
                'name' => 'متوسط',
                'sort_order' => 30,
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_super_admin_cannot_delete_education_stage_used_by_school_or_template_stage(): void
    {
        $admin = $this->createSuperAdmin();
        $educationStage = EducationStage::query()->create([
            'name' => 'ثانوي',
            'sort_order' => 40,
            'is_active' => true,
        ]);
        $region = EducationalDirectorate::query()->create([
            'name' => 'تعليم ثانوي',
            'governorate' => 'الرياض',
        ]);

        $school = School::query()->create([
            'directorate_id' => $region->id,
            'name' => 'مدرسة مرتبطة',
            'school_id' => 'SCH-STAGE-1',
            'phone' => '0500990001',
            'status' => School::STATUS_SUSPENDED,
            'supervision_status' => School::SUPERVISION_STATUS_SUSPENDED,
        ]);
        $school->educationStages()->attach($educationStage->id);

        SchoolDefaultStageTemplate::query()->create([
            'name' => 'ثانوي',
            'code' => 'STG-SEC',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.education_stages.delete', $educationStage->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors('education_stage');

        $this->assertDatabaseHas('education_stages', [
            'id' => $educationStage->id,
            'name' => 'ثانوي',
        ]);
    }

    public function test_super_admin_cannot_delete_education_stage_used_by_linked_template_stage_even_when_template_name_differs(): void
    {
        $admin = $this->createSuperAdmin();
        $educationStage = EducationStage::query()->create([
            'name' => 'المرحلة الابتدائية',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        SchoolDefaultStageTemplate::query()->create([
            'name' => 'ابتدائي',
            'code' => 'STG-PRI-LINKED',
            'education_stage_id' => $educationStage->id,
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.education_stages.delete', $educationStage->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors('education_stage');
    }

    private function createSuperAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');

        return $admin;
    }
}
