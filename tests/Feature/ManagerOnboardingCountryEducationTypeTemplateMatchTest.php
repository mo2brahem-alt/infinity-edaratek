<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\EducationStage;
use App\Models\EducationType;
use App\Models\Governorate;
use App\Models\School;
use App\Models\SchoolDefaultClassroomTemplate;
use App\Models\SchoolDefaultStageGradeTemplate;
use App\Models\SchoolDefaultStageTemplate;
use App\Models\User;
use App\Services\School\SchoolDefaultTemplateScopeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManagerOnboardingCountryEducationTypeTemplateMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_school_creation_uses_country_governorate_and_education_type_to_import_only_selected_stage_templates(): void
    {
        $admin = $this->createSuperAdmin();
        $manager = $this->createManager();
        $country = Country::query()->create(['name' => 'السعودية']);
        $governorate = Governorate::query()->create([
            'country_id' => $country->id,
            'name' => 'الرياض',
        ]);
        $educationType = EducationType::query()->create(['name' => 'وطني']);
        $primaryStage = EducationStage::query()->create([
            'name' => 'ابتدائي',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $middleStage = EducationStage::query()->create([
            'name' => 'متوسط',
            'sort_order' => 20,
            'is_active' => true,
        ]);
        $templateKey = sprintf('country:%d:education-type:%d', $country->id, $educationType->id);

        app(SchoolDefaultTemplateScopeRegistry::class)->upsert(
            'قالب التعليم الوطني',
            (int) $country->id,
            (int) $educationType->id
        );

        SchoolDefaultStageTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'ابتدائي',
            'code' => 'STG-PRM',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        SchoolDefaultStageTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'متوسط',
            'code' => 'STG-MID',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $response = $this->actingAs($manager)->postJson(route('manager.onboarding.schools.store'), [
            'country_id' => $country->id,
            'governorate_id' => $governorate->id,
            'education_type_id' => $educationType->id,
            'template_key' => $templateKey,
            'school_type' => School::TYPE_BOYS,
            'education_stage_ids' => [$primaryStage->id],
            'name' => 'مدرسة الاختبار الوطنية',
            'phone' => '0501234567',
            'address' => 'عنوان تجريبي',
        ]);

        $response->assertCreated();

        $school = School::query()
            ->with(['directorate', 'educationStages'])
            ->findOrFail((int) $response->json('school.id'));

        $this->assertNotNull($school->directorate);
        $this->assertSame($country->id, (int) $school->directorate->country_id);
        $this->assertSame($governorate->id, (int) $school->directorate->governorate_id);
        $this->assertSame($educationType->id, (int) $school->directorate->education_type_id);
        $this->assertSame(School::TYPE_BOYS, $school->school_type);
        $this->assertSame([$primaryStage->id], $school->educationStages->pluck('id')->map(fn ($id) => (int) $id)->all());

        $this->assertDatabaseHas('schools', [
            'id' => $school->id,
            'school_type' => School::TYPE_BOYS,
            'default_data_imported_by' => $manager->id,
            'default_template_key' => $templateKey,
            'default_template_name' => 'قالب التعليم الوطني',
        ]);
        $this->assertDatabaseHas('education_stage_school', [
            'school_id' => $school->id,
            'education_stage_id' => $primaryStage->id,
        ]);
        $this->assertDatabaseMissing('education_stage_school', [
            'school_id' => $school->id,
            'education_stage_id' => $middleStage->id,
        ]);
        $this->assertDatabaseHas('school_stages', [
            'school_id' => $school->id,
            'name' => 'ابتدائي',
        ]);
        $this->assertDatabaseMissing('school_stages', [
            'school_id' => $school->id,
            'name' => 'متوسط',
        ]);
    }

    public function test_manager_can_load_only_templates_matching_selected_country_and_education_type(): void
    {
        $manager = $this->createManager();
        $country = Country::query()->create(['name' => 'السعودية']);
        $otherCountry = Country::query()->create(['name' => 'مصر']);
        $educationType = EducationType::query()->create(['name' => 'وطني']);
        $otherEducationType = EducationType::query()->create(['name' => 'دولي']);

        app(SchoolDefaultTemplateScopeRegistry::class)->upsert(
            'قالب وطني',
            (int) $country->id,
            (int) $educationType->id
        );
        app(SchoolDefaultTemplateScopeRegistry::class)->upsert(
            'قالب دولي',
            (int) $country->id,
            (int) $otherEducationType->id
        );

        SchoolDefaultStageTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'مرحلة وطني',
            'code' => 'STG-NAT-1',
            'is_active' => true,
        ]);

        SchoolDefaultStageTemplate::query()->create([
            'country_id' => $otherCountry->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'مرحلة دولة أخرى',
            'code' => 'STG-OTH-1',
            'is_active' => true,
        ]);

        SchoolDefaultStageTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $otherEducationType->id,
            'directorate_id' => null,
            'name' => 'مرحلة دولي',
            'code' => 'STG-INT-1',
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)->getJson(route('manager.onboarding.templates', [
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'templates')
            ->assertJsonPath('templates.0.key', sprintf('country:%d:education-type:%d', $country->id, $educationType->id))
            ->assertJsonPath('templates.0.template_name', 'قالب وطني')
            ->assertJsonPath('templates.0.country_id', $country->id)
            ->assertJsonPath('templates.0.education_type_id', $educationType->id);
    }

    public function test_manager_regions_payload_returns_only_active_education_stages_for_school_creation(): void
    {
        $manager = $this->createManager();

        $activeStage = EducationStage::query()->create([
            'name' => 'روضة',
            'sort_order' => 5,
            'is_active' => true,
        ]);
        EducationStage::query()->create([
            'name' => 'ثانوي قديم',
            'sort_order' => 50,
            'is_active' => false,
        ]);

        $response = $this->actingAs($manager)->getJson(route('manager.onboarding.regions'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'educationStages')
            ->assertJsonFragment([
                'id' => $activeStage->id,
                'name' => 'روضة',
            ]);

        $stageIds = collect($response->json('educationStages'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame([$activeStage->id], $stageIds);
    }

    public function test_manager_school_creation_imports_selected_stage_templates_even_when_legacy_template_names_differ_from_master_stage_names(): void
    {
        $admin = $this->createSuperAdmin();
        $manager = $this->createManager();
        $country = Country::query()->create(['name' => 'مصر']);
        $governorate = Governorate::query()->create([
            'country_id' => $country->id,
            'name' => 'القاهرة',
        ]);
        $educationType = EducationType::query()->create(['name' => 'تعليم عام']);
        $kindergartenStage = EducationStage::query()->create([
            'name' => 'مرحلة رياض الأطفال',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $primaryStage = EducationStage::query()->create([
            'name' => 'المرحلة الابتدائية',
            'sort_order' => 20,
            'is_active' => true,
        ]);
        $middleStage = EducationStage::query()->create([
            'name' => 'المرحلة المتوسطة',
            'sort_order' => 30,
            'is_active' => true,
        ]);
        $templateKey = sprintf('country:%d:education-type:%d', $country->id, $educationType->id);

        app(SchoolDefaultTemplateScopeRegistry::class)->upsert(
            'قالب دولة مصر',
            (int) $country->id,
            (int) $educationType->id
        );

        $kindergartenTemplate = SchoolDefaultStageTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'روضة',
            'code' => 'STG-KG',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $primaryTemplate = SchoolDefaultStageTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'ابتدائي',
            'code' => 'STG-PRI',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $middleTemplate = SchoolDefaultStageTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'متوسط',
            'code' => 'STG-MID',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        foreach ([
            [$kindergartenTemplate, 'المستوى الأول', 'KG-A'],
            [$primaryTemplate, 'الصف الأول', 'PRI-A'],
            [$middleTemplate, 'الصف المتوسط الأول', 'MID-A'],
        ] as [$stageTemplate, $gradeName, $classroomCode]) {
            $grade = SchoolDefaultStageGradeTemplate::query()->create([
                'school_default_stage_template_id' => $stageTemplate->id,
                'name' => $gradeName,
                'sort_order' => 10,
                'is_active' => true,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            SchoolDefaultClassroomTemplate::query()->create([
                'school_default_stage_template_id' => $stageTemplate->id,
                'school_default_stage_grade_template_id' => $grade->id,
                'name' => 'فصل أ',
                'code' => $classroomCode,
                'sort_order' => 10,
                'is_active' => true,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        }

        $response = $this->actingAs($manager)->postJson(route('manager.onboarding.schools.store'), [
            'country_id' => $country->id,
            'governorate_id' => $governorate->id,
            'education_type_id' => $educationType->id,
            'template_key' => $templateKey,
            'school_type' => School::TYPE_MIXED,
            'education_stage_ids' => [$kindergartenStage->id, $primaryStage->id],
            'name' => 'مدرسة الفردوس',
            'phone' => '0501234568',
            'address' => 'عنوان تجريبي',
        ]);

        $response->assertCreated();

        $schoolId = (int) $response->json('school.id');

        $this->assertDatabaseHas('school_stages', [
            'school_id' => $schoolId,
            'name' => 'روضة',
        ]);
        $this->assertDatabaseHas('school_stages', [
            'school_id' => $schoolId,
            'name' => 'ابتدائي',
        ]);
        $this->assertDatabaseMissing('school_stages', [
            'school_id' => $schoolId,
            'name' => 'متوسط',
        ]);

        $this->assertDatabaseHas('school_stage_grades', [
            'school_id' => $schoolId,
            'name' => 'المستوى الأول',
        ]);
        $this->assertDatabaseHas('school_stage_grades', [
            'school_id' => $schoolId,
            'name' => 'الصف الأول',
        ]);
        $this->assertDatabaseMissing('school_stage_grades', [
            'school_id' => $schoolId,
            'name' => 'الصف المتوسط الأول',
        ]);

        $this->assertDatabaseHas('school_classrooms', [
            'school_id' => $schoolId,
            'code' => 'KG-A',
        ]);
        $this->assertDatabaseHas('school_classrooms', [
            'school_id' => $schoolId,
            'code' => 'PRI-A',
        ]);
        $this->assertDatabaseMissing('school_classrooms', [
            'school_id' => $schoolId,
            'code' => 'MID-A',
        ]);
    }

    private function createSuperAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function createManager(): User
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        return $manager;
    }
}
