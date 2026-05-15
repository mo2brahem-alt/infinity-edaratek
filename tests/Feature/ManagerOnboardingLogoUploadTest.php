<?php

namespace Tests\Feature;

use App\Models\EducationStage;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManagerOnboardingLogoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_school_with_logo_during_onboarding(): void
    {
        Storage::fake('public');

        $manager = $this->createManager();
        $region = $this->createRegion();
        $educationStage = $this->createEducationStage();

        $response = $this->actingAs($manager)->post(route('manager.onboarding.schools.store'), [
            'region_id' => $region->id,
            'school_type' => School::TYPE_MIXED,
            'education_stage_ids' => [$educationStage->id],
            'name' => 'مدرسة الشعار الأولى',
            'phone' => '0500007711',
            'logo' => $this->fakePngUpload('school-logo.png'),
        ]);

        $response->assertCreated();

        $school = School::query()->where('manager_user_id', $manager->id)->firstOrFail();

        $this->assertNotNull($school->logo_path);
        $this->assertStringStartsWith('schools/logos/', $school->logo_path);
        Storage::disk('public')->assertExists($school->logo_path);

        $response->assertJsonPath('school.logo_path', $school->logo_path);
        $response->assertJsonPath('school.logo_url', '/media-files/' . $school->logo_path);
    }

    public function test_manager_can_update_logo_for_his_current_school_only(): void
    {
        Storage::fake('public');

        $manager = $this->createManager();
        $region = $this->createRegion();

        Storage::disk('public')->put('schools/logos/old-logo.png', 'old-logo');

        $school = School::query()->create([
            'directorate_id' => $region->id,
            'name' => 'مدرسة قابلة للتعديل',
            'school_id' => 'SCH-770001',
            'phone' => '0500007712',
            'status' => School::STATUS_SUSPENDED,
            'supervision_status' => School::SUPERVISION_STATUS_SUSPENDED,
            'manager_user_id' => $manager->id,
            'logo_path' => 'schools/logos/old-logo.png',
        ]);

        $manager->update(['school_id' => $school->id]);

        $response = $this->actingAs($manager)->post(route('manager.onboarding.schools.update', $school), [
            '_method' => 'PUT',
            'name' => 'مدرسة قابلة للتعديل',
            'phone' => '0500007712',
            'logo' => $this->fakePngUpload('updated-logo.png'),
        ]);

        $response->assertOk();

        $school->refresh();

        $this->assertNotSame('schools/logos/old-logo.png', $school->logo_path);
        Storage::disk('public')->assertMissing('schools/logos/old-logo.png');
        Storage::disk('public')->assertExists($school->logo_path);
    }

    public function test_manager_cannot_update_logo_for_school_owned_by_another_manager(): void
    {
        Storage::fake('public');

        $manager = $this->createManager();
        $otherManager = $this->createManager('other-manager@example.com', '0500007714');
        $region = $this->createRegion();

        $otherSchool = School::query()->create([
            'directorate_id' => $region->id,
            'name' => 'مدرسة مدير آخر',
            'school_id' => 'SCH-770002',
            'phone' => '0500007713',
            'status' => School::STATUS_SUSPENDED,
            'supervision_status' => School::SUPERVISION_STATUS_SUSPENDED,
            'manager_user_id' => $otherManager->id,
        ]);

        $response = $this->actingAs($manager)->post(
            route('manager.onboarding.schools.update', $otherSchool),
            [
                '_method' => 'PUT',
                'name' => 'محاولة تعديل غير مصرح بها',
                'phone' => '0500007715',
                'logo' => $this->fakePngUpload('forbidden-logo.png'),
            ],
            [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('school');
    }

    private function createManager(
        string $email = 'manager-logo@example.com',
        string $mobile = '0500007710'
    ): User {
        Role::query()->firstOrCreate([
            'name' => 'school_manager',
            'guard_name' => 'web',
        ]);

        $manager = User::factory()->create([
            'role' => 'school_manager',
            'email' => $email,
            'mobile' => $mobile,
        ]);

        $manager->assignRole('school_manager');

        return $manager;
    }

    private function createRegion(): EducationalDirectorate
    {
        return EducationalDirectorate::query()->create([
            'name' => 'تعليم خاص',
            'governorate' => 'الرياض',
        ]);
    }

    private function createEducationStage(): EducationStage
    {
        return EducationStage::query()->create([
            'name' => 'ابتدائي',
            'sort_order' => 10,
            'is_active' => true,
        ]);
    }

    private function fakePngUpload(string $name): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9l9H0AAAAASUVORK5CYII=',
            true
        );

        $path = tempnam(sys_get_temp_dir(), 'school-logo-');
        file_put_contents($path, $png);

        return new UploadedFile(
            $path,
            $name,
            'image/png',
            null,
            true
        );
    }
}
