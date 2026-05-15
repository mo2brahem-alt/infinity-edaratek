<?php

namespace Tests\Feature;

use App\Models\EducationStage;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArabicValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_validation_errors_are_returned_in_arabic(): void
    {
        $response = $this->from(route('register'))->post(route('register'), [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['name', 'email', 'password']);

        $errors = session('errors')->getBag('default');

        $this->assertSame('الاسم مطلوب.', $errors->first('name'));
        $this->assertSame('البريد الإلكتروني يجب أن يكون بريدًا إلكترونيًا صالحًا.', $errors->first('email'));
    }

    public function test_login_failure_message_is_returned_in_arabic(): void
    {
        $user = User::factory()->create();

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');

        $errors = session('errors')->getBag('default');

        $this->assertSame('بيانات الدخول غير صحيحة.', $errors->first('email'));
    }

    public function test_manager_onboarding_json_validation_errors_are_returned_in_arabic(): void
    {
        $manager = $this->createManager();

        $response = $this->actingAs($manager)->postJson(route('manager.onboarding.schools.store'), [
            'name' => '',
            'phone' => '123',
            'email' => 'bad-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'الدولة مطلوبة.');
        $response->assertJsonPath('errors.country_id.0', 'الدولة مطلوبة.');
        $response->assertJsonPath('errors.governorate_id.0', 'المحافظة مطلوبة.');
        $response->assertJsonPath('errors.education_type_id.0', 'نوع التعليم مطلوب.');
        $response->assertJsonPath('errors.school_type.0', 'نوع المدرسة مطلوب.');
        $response->assertJsonPath('errors.education_stage_ids.0', 'اختر مرحلة تعليمية واحدة على الأقل.');
        $response->assertJsonPath('errors.email.0', 'صيغة البريد الإلكتروني غير صحيحة.');
    }

    public function test_manager_onboarding_duplicate_school_name_error_is_returned_in_arabic(): void
    {
        $manager = $this->createManager();
        $region = $this->createRegion();
        $educationStage = $this->createEducationStage();

        School::query()->create([
            'directorate_id' => $region->id,
            'name' => 'مدرسة الأنوار',
            'school_id' => 'SCH-001',
            'phone' => '0500000001',
            'email' => 'existing-school@gmail.com',
            'status' => School::STATUS_SUSPENDED,
        ]);

        $response = $this->actingAs($manager)->postJson(route('manager.onboarding.schools.store'), [
            'region_id' => $region->id,
            'school_type' => School::TYPE_BOYS,
            'education_stage_ids' => [$educationStage->id],
            'name' => 'مدرسة الأنوار',
            'phone' => '0500000002',
            'email' => 'new-school@gmail.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.name.0', 'يوجد بالفعل اسم مدرسة مطابق للاسم المدخل.');
    }

    public function test_manager_onboarding_duplicate_school_phone_error_is_returned_in_arabic(): void
    {
        $manager = $this->createManager();
        $region = $this->createRegion();
        $educationStage = $this->createEducationStage();

        School::query()->create([
            'directorate_id' => $region->id,
            'name' => 'مدرسة الأنوار',
            'school_id' => 'SCH-001',
            'phone' => '0500000001',
            'email' => 'existing-school@gmail.com',
            'status' => School::STATUS_SUSPENDED,
        ]);

        $response = $this->actingAs($manager)->postJson(route('manager.onboarding.schools.store'), [
            'region_id' => $region->id,
            'school_type' => School::TYPE_GIRLS,
            'education_stage_ids' => [$educationStage->id],
            'name' => 'مدرسة جديدة',
            'phone' => '0500000001',
            'email' => 'new-school@gmail.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.phone.0', 'رقم الجوال مستخدم لمدرسة أخرى بالفعل.');
    }

    public function test_manager_onboarding_duplicate_school_email_error_is_returned_in_arabic(): void
    {
        $manager = $this->createManager();
        $region = $this->createRegion();
        $educationStage = $this->createEducationStage();

        School::query()->create([
            'directorate_id' => $region->id,
            'name' => 'مدرسة الأنوار',
            'school_id' => 'SCH-001',
            'phone' => '0500000001',
            'email' => 'existing-school@gmail.com',
            'status' => School::STATUS_SUSPENDED,
        ]);

        $response = $this->actingAs($manager)->postJson(route('manager.onboarding.schools.store'), [
            'region_id' => $region->id,
            'school_type' => School::TYPE_MIXED,
            'education_stage_ids' => [$educationStage->id],
            'name' => 'مدرسة جديدة',
            'phone' => '0500000002',
            'email' => 'existing-school@gmail.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.email.0', 'البريد الإلكتروني مستخدم لمدرسة أخرى بالفعل.');
    }

    private function createManager(): User
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $manager = User::factory()->create([
            'role' => 'school_manager',
            'is_active' => true,
        ]);
        $manager->assignRole('school_manager');

        return $manager;
    }

    private function createRegion(): EducationalDirectorate
    {
        return EducationalDirectorate::query()->create([
            'name' => 'خاص',
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
}
