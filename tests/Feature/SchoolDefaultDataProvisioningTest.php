<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\Country;
use App\Models\EducationStage;
use App\Models\EducationType;
use App\Models\EducationalDirectorate;
use App\Models\Governorate;
use App\Models\School;
use App\Models\SchoolDefaultLeaveTypeTemplate;
use App\Models\SchoolDefaultStageTemplate;
use App\Models\SchoolDefaultSubjectTemplate;
use App\Models\SchoolDefaultHolidayTemplate;
use App\Models\SchoolStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolDefaultDataProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_global_templates_and_manager_can_reimport_without_duplicate_records(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)
            ->post(route('admin.school_defaults.stages.store'), [
                'name' => 'المرحلة الابتدائية',
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.school_defaults.index', absolute: false));

        $stageTemplate = SchoolDefaultStageTemplate::query()->where('name', 'المرحلة الابتدائية')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.school_defaults.stage_grades.store'), [
                'school_default_stage_template_id' => $stageTemplate->id,
                'name' => 'الصف الأول',
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.school_defaults.index', absolute: false));

        $gradeTemplateId = (int) $stageTemplate->fresh()->grades()->value('id');

        $this->actingAs($admin)
            ->post(route('admin.school_defaults.classrooms.store'), [
                'school_default_stage_template_id' => $stageTemplate->id,
                'school_default_stage_grade_template_id' => $gradeTemplateId,
                'name' => 'فصل أ',
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.school_defaults.index', absolute: false));

        $this->actingAs($admin)
            ->post(route('admin.school_defaults.academic_years.store'), [
                'name' => '2026 / 2027',
                'starts_on' => '2026-09-01',
                'ends_on' => '2027-06-30',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.school_defaults.index', absolute: false));

        $this->actingAs($admin)
            ->post(route('admin.school_defaults.holidays.store'), [
                'name' => 'إجازة منتصف العام',
                'start_date' => '2027-01-15',
                'end_date' => '2027-01-22',
                'return_date' => '2027-01-23',
                'notes' => 'إجازة عامة',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.school_defaults.index', absolute: false));

        $this->actingAs($admin)
            ->post(route('admin.school_defaults.leave_types.store'), [
                'name' => 'إجازة مرضية',
                'requires_attachment' => true,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.school_defaults.index', absolute: false));

        $this->actingAs($admin)
            ->post(route('admin.school_defaults.subjects.store'), [
                'name' => 'الرياضيات',
                'branches' => ['الفرع الرئيسي'],
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.school_defaults.index', absolute: false));

        [$manager, $school] = $this->createManagerWithSchool('1001');

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->post(route('school.default_data.import'))
            ->assertRedirect(route('school.student_structure.index', absolute: false));

        $this->assertDatabaseHas('school_stages', [
            'school_id' => $school->id,
            'name' => 'المرحلة الابتدائية',
        ]);
        $this->assertDatabaseHas('school_stage_grades', [
            'school_id' => $school->id,
            'name' => 'الصف الأول',
        ]);
        $this->assertDatabaseHas('school_classrooms', [
            'school_id' => $school->id,
            'name' => 'فصل أ',
        ]);
        $this->assertDatabaseHas('school_academic_years', [
            'school_id' => $school->id,
            'name' => '2026 / 2027',
        ]);
        $this->assertDatabaseHas('school_holidays', [
            'school_id' => $school->id,
            'name' => 'إجازة منتصف العام',
        ]);
        $this->assertDatabaseHas('school_leave_types', [
            'school_id' => $school->id,
            'name' => 'إجازة مرضية',
        ]);
        $this->assertDatabaseHas('school_subjects', [
            'school_id' => $school->id,
            'name' => 'الرياضيات',
        ]);
        $this->assertDatabaseHas('schools', [
            'id' => $school->id,
            'default_data_imported_by' => $manager->id,
        ]);

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->post(route('school.default_data.import'))
            ->assertRedirect(route('school.student_structure.index', absolute: false))
            ->assertSessionHas('success');

        $this->assertSame(1, SchoolStage::query()->where('school_id', $school->id)->count());
        $this->assertSame(1, \App\Models\SchoolStageGrade::query()->where('school_id', $school->id)->count());
        $this->assertSame(1, \App\Models\SchoolClassroom::query()->where('school_id', $school->id)->count());
        $this->assertSame(1, \App\Models\SchoolAcademicYear::query()->where('school_id', $school->id)->count());
        $this->assertSame(1, \App\Models\SchoolHoliday::query()->where('school_id', $school->id)->count());
        $this->assertSame(1, \App\Models\SchoolLeaveType::query()->where('school_id', $school->id)->count());
        $this->assertSame(1, \App\Models\SchoolSubject::query()->where('school_id', $school->id)->count());
    }

    public function test_manager_can_import_new_matching_templates_later_without_overwriting_existing_school_data(): void
    {
        $admin = $this->createSuperAdmin();

        SchoolDefaultStageTemplate::query()->create([
            'name' => 'المرحلة الابتدائية',
            'code' => 'STG-001',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        [$manager, $school] = $this->createManagerWithSchool('1006');

        $this->actingAs($manager)
            ->post(route('school.default_data.import'))
            ->assertRedirect();

        $initialImportedAt = $school->fresh()->default_data_imported_at?->toISOString();

        $this->actingAs($admin)
            ->post(route('admin.school_defaults.subjects.store'), [
                'name' => 'العلوم',
                'branches' => ['الفرع الرئيسي'],
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.school_defaults.index', absolute: false));

        $this->from(route('school.academic_planning.index'))
            ->actingAs($manager)
            ->post(route('school.default_data.import'))
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHas('success');

        $this->assertSame(1, SchoolStage::query()->where('school_id', $school->id)->count());
        $this->assertDatabaseHas('school_subjects', [
            'school_id' => $school->id,
            'name' => 'العلوم',
        ]);
        $this->assertSame($initialImportedAt, $school->fresh()->default_data_imported_at?->toISOString());
        $this->assertDatabaseHas('schools', [
            'id' => $school->id,
            'default_data_imported_by' => $manager->id,
        ]);
    }

    public function test_scoped_school_import_falls_back_to_global_templates_when_no_scoped_templates_exist_for_that_section(): void
    {
        $admin = $this->createSuperAdmin();

        $country = Country::query()->create(['name' => 'المملكة العربية السعودية']);
        $governorate = Governorate::query()->create([
            'country_id' => $country->id,
            'name' => 'الرياض',
        ]);
        $educationType = EducationType::query()->create(['name' => 'تعليم عام']);
        $directorate = EducationalDirectorate::query()->create([
            'country_id' => $country->id,
            'governorate_id' => $governorate->id,
            'education_type_id' => $educationType->id,
            'governorate' => $governorate->name,
            'name' => $educationType->name,
        ]);

        SchoolDefaultStageTemplate::query()->create([
            'name' => 'المرحلة الابتدائية العامة',
            'code' => 'STG-GLB',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        SchoolDefaultSubjectTemplate::query()->create([
            'name' => 'اللغة العربية',
            'code' => 'ARB',
            'branches' => ['الفرع الرئيسي'],
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        SchoolDefaultHolidayTemplate::query()->create([
            'name' => 'إجازة عامة',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-03',
            'return_date' => '2026-01-04',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::query()->create([
            'directorate_id' => $directorate->id,
            'name' => 'مدرسة الفallback',
            'school_id' => 'SCH-3001',
            'phone' => '0500300001',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);
        $manager->update(['school_id' => $school->id]);

        $this->actingAs($manager)
            ->post(route('school.default_data.import'))
            ->assertRedirect();

        $this->assertDatabaseHas('school_stages', [
            'school_id' => $school->id,
            'name' => 'المرحلة الابتدائية العامة',
        ]);
        $this->assertDatabaseHas('school_subjects', [
            'school_id' => $school->id,
            'name' => 'اللغة العربية',
        ]);
        $this->assertDatabaseHas('school_holidays', [
            'school_id' => $school->id,
            'name' => 'إجازة عامة',
        ]);
    }

    public function test_scoped_school_does_not_seed_legacy_leave_types_when_global_templates_exist(): void
    {
        $this->createRoles();

        $country = Country::query()->create(['name' => 'المملكة العربية السعودية']);
        $governorate = Governorate::query()->create([
            'country_id' => $country->id,
            'name' => 'الرياض',
        ]);
        $educationType = EducationType::query()->create(['name' => 'تعليم عام']);
        $directorate = EducationalDirectorate::query()->create([
            'country_id' => $country->id,
            'governorate_id' => $governorate->id,
            'education_type_id' => $educationType->id,
            'governorate' => $governorate->name,
            'name' => $educationType->name,
        ]);

        SchoolDefaultLeaveTypeTemplate::query()->create([
            'name' => 'إجازة عارضة',
            'code' => 'LEAVE-GLB',
            'requires_attachment' => false,
            'is_active' => true,
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::query()->create([
            'directorate_id' => $directorate->id,
            'name' => 'مدرسة الإجازات',
            'school_id' => 'SCH-3002',
            'phone' => '0500300002',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);
        $manager->update(['school_id' => $school->id]);

        $this->actingAs($manager)
            ->get(route('school.academic_planning.index'))
            ->assertOk();

        $this->assertDatabaseCount('school_leave_types', 0);
    }

    public function test_updating_platform_templates_does_not_change_existing_school_copy_and_new_school_gets_latest_version(): void
    {
        $admin = $this->createSuperAdmin();

        $template = SchoolDefaultStageTemplate::query()->create([
            'name' => 'المرحلة الابتدائية',
            'code' => 'STG-001',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        [$managerA, $schoolA] = $this->createManagerWithSchool('1002');

        $this->actingAs($managerA)->post(route('school.default_data.import'))->assertRedirect();

        $schoolAStage = SchoolStage::query()->where('school_id', $schoolA->id)->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.school_defaults.stages.update', $template), [
                'name' => 'المرحلة الابتدائية المحدثة',
                'code' => 'STG-001',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.school_defaults.index', absolute: false));

        $this->from(route('school.student_structure.index'))
            ->actingAs($managerA)
            ->put(route('school.student_structure.stages.update', $schoolAStage), [
                'name' => 'مرحلة المدرسة أ',
                'code' => $schoolAStage->code,
                'sort_order' => $schoolAStage->sort_order,
                'is_active' => true,
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false));

        [$managerB, $schoolB] = $this->createManagerWithSchool('1003');

        $this->actingAs($managerB)->post(route('school.default_data.import'))->assertRedirect();

        $this->assertDatabaseHas('school_default_stage_templates', [
            'id' => $template->id,
            'name' => 'المرحلة الابتدائية المحدثة',
        ]);
        $this->assertDatabaseHas('school_stages', [
            'school_id' => $schoolA->id,
            'name' => 'مرحلة المدرسة أ',
        ]);
        $this->assertDatabaseMissing('school_stages', [
            'school_id' => $schoolA->id,
            'name' => 'المرحلة الابتدائية المحدثة',
        ]);
        $this->assertDatabaseHas('school_stages', [
            'school_id' => $schoolB->id,
            'name' => 'المرحلة الابتدائية المحدثة',
        ]);
    }

    public function test_staff_needs_full_cross_module_permissions_to_import_school_default_data(): void
    {
        $this->createRoles();
        SchoolDefaultStageTemplate::query()->create(['name' => 'المرحلة المتوسطة', 'code' => 'STG-010', 'is_active' => true]);
        [$manager, $school] = $this->createManagerWithSchool('1004');

        $partialStaff = $this->createStaffForSchool($school, [
            'can_manage_student_structure' => true,
            'can_manage_academic_planning' => true,
        ]);

        $this->actingAs($partialStaff)
            ->post(route('school.default_data.import'))
            ->assertForbidden();

        $fullStaff = $this->createStaffForSchool($school, [
            'can_manage_student_structure' => true,
            'can_manage_academic_planning' => true,
            'can_manage_leave_types' => true,
            'can_manage_school_calendar' => true,
            'can_manage_school_holidays' => true,
        ]);

        $this->actingAs($fullStaff)
            ->post(route('school.default_data.import'))
            ->assertRedirect();

        $this->assertDatabaseHas('schools', [
            'id' => $school->id,
            'default_data_imported_by' => $fullStaff->id,
        ]);
    }

    public function test_platform_leave_type_templates_stop_legacy_defaults_until_school_import_runs(): void
    {
        $this->createRoles();
        SchoolDefaultLeaveTypeTemplate::query()->create([
            'name' => 'إجازة اضطرارية',
            'code' => 'LEAVE-900',
            'requires_attachment' => false,
            'is_active' => true,
        ]);

        [$manager, $school] = $this->createManagerWithSchool('1005');

        $this->actingAs($manager)
            ->get(route('school.academic_planning.index'))
            ->assertOk();

        $this->assertDatabaseCount('school_leave_types', 0);

        $this->from(route('school.academic_planning.index'))
            ->actingAs($manager)
            ->post(route('school.default_data.import'))
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $this->assertDatabaseCount('school_leave_types', 1);
        $this->assertDatabaseHas('school_leave_types', [
            'school_id' => $school->id,
            'name' => 'إجازة اضطرارية',
        ]);
    }

    public function test_school_import_uses_only_templates_matching_school_country_and_education_type(): void
    {
        $admin = $this->createSuperAdmin();

        $country = Country::query()->create(['name' => 'السعودية']);
        $governorate = Governorate::query()->create([
            'country_id' => $country->id,
            'name' => 'الرياض',
        ]);
        $generalEducation = EducationType::query()->create(['name' => 'تعليم عام']);
        $internationalEducation = EducationType::query()->create(['name' => 'تعليم دولي']);

        $generalDirectorate = EducationalDirectorate::query()->create([
            'country_id' => $country->id,
            'governorate_id' => $governorate->id,
            'education_type_id' => $generalEducation->id,
            'governorate' => $governorate->name,
            'name' => $generalEducation->name,
        ]);

        $internationalDirectorate = EducationalDirectorate::query()->create([
            'country_id' => $country->id,
            'governorate_id' => $governorate->id,
            'education_type_id' => $internationalEducation->id,
            'governorate' => $governorate->name,
            'name' => $internationalEducation->name,
        ]);

        SchoolDefaultStageTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $generalEducation->id,
            'name' => 'مرحلة التعليم العام',
            'code' => 'STG-GEN',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        SchoolDefaultStageTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $internationalEducation->id,
            'name' => 'مرحلة التعليم الدولي',
            'code' => 'STG-INT',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $generalManager = User::factory()->create(['role' => 'school_manager']);
        $generalManager->assignRole('school_manager');
        $generalSchool = School::query()->create([
            'directorate_id' => $generalDirectorate->id,
            'name' => 'مدرسة التعليم العام',
            'school_id' => 'SCH-2001',
            'phone' => '0500200001',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $generalManager->id,
        ]);
        $generalManager->update(['school_id' => $generalSchool->id]);

        $internationalManager = User::factory()->create(['role' => 'school_manager']);
        $internationalManager->assignRole('school_manager');
        $internationalSchool = School::query()->create([
            'directorate_id' => $internationalDirectorate->id,
            'name' => 'مدرسة التعليم الدولي',
            'school_id' => 'SCH-2002',
            'phone' => '0500200002',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $internationalManager->id,
        ]);
        $internationalManager->update(['school_id' => $internationalSchool->id]);

        $this->actingAs($generalManager)
            ->post(route('school.default_data.import'))
            ->assertRedirect();

        $this->actingAs($internationalManager)
            ->post(route('school.default_data.import'))
            ->assertRedirect();

        $this->assertDatabaseHas('school_stages', [
            'school_id' => $generalSchool->id,
            'name' => 'مرحلة التعليم العام',
        ]);
        $this->assertDatabaseMissing('school_stages', [
            'school_id' => $generalSchool->id,
            'name' => 'مرحلة التعليم الدولي',
        ]);
        $this->assertDatabaseHas('school_stages', [
            'school_id' => $internationalSchool->id,
            'name' => 'مرحلة التعليم الدولي',
        ]);
        $this->assertDatabaseMissing('school_stages', [
            'school_id' => $internationalSchool->id,
            'name' => 'مرحلة التعليم العام',
        ]);
    }

    public function test_manager_onboarding_school_creation_auto_imports_matching_scoped_template(): void
    {
        $admin = $this->createSuperAdmin();

        $country = Country::query()->create(['name' => 'مصر']);
        $governorate = Governorate::query()->create([
            'country_id' => $country->id,
            'name' => 'القاهرة',
        ]);
        $educationType = EducationType::query()->create(['name' => 'تعليم خاص']);
        $directorate = EducationalDirectorate::query()->create([
            'country_id' => $country->id,
            'governorate_id' => $governorate->id,
            'education_type_id' => $educationType->id,
            'governorate' => $governorate->name,
            'name' => $educationType->name,
        ]);

        SchoolDefaultStageTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'name' => 'مرحلة التهيئة الخاصة',
            'code' => 'STG-PRV',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');
        $educationStage = EducationStage::query()->create([
            'name' => 'مرحلة التهيئة الخاصة',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)->postJson(route('manager.onboarding.schools.store'), [
            'region_id' => $directorate->id,
            'school_type' => School::TYPE_MIXED,
            'education_stage_ids' => [$educationStage->id],
            'name' => 'مدرسة النطاق الخاص',
            'phone' => '0500123456',
            'address' => 'عنوان تجريبي',
            'notes' => 'مدرسة مخصصة للاختبار',
        ]);

        $response->assertCreated();

        $schoolId = (int) $response->json('school.id');

        $this->assertDatabaseHas('schools', [
            'id' => $schoolId,
            'directorate_id' => $directorate->id,
            'default_data_imported_by' => $manager->id,
        ]);
        $this->assertDatabaseHas('school_stages', [
            'school_id' => $schoolId,
            'name' => 'مرحلة التهيئة الخاصة',
        ]);
    }

    private function createSuperAdmin(): User
    {
        $this->createRoles();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function createRoles(): void
    {
        foreach (['super_admin', 'school_manager', 'staff'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
    }

    /**
     * @return array{0: User, 1: School}
     */
    private function createManagerWithSchool(string $suffix): array
    {
        $this->createRoles();

        $region = EducationalDirectorate::create([
            'name' => 'منطقة الاختبار ' . $suffix,
            'governorate' => 'الرياض',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'مدرسة الاختبار ' . $suffix,
            'school_id' => 'SCH-' . $suffix,
            'phone' => '0500' . str_pad($suffix, 6, '0', STR_PAD_LEFT),
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        return [$manager, $school];
    }

    private function createStaffForSchool(School $school, array $permissionOverrides): User
    {
        $department = Department::create([
            'name' => 'الإدارة الإدارية ' . $school->id . '-' . substr(md5((string) microtime(true)), 0, 6),
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::create(array_merge([
            'department_id' => $department->id,
            'name' => 'دور صلاحيات المدرسة ' . $school->id . '-' . substr(md5((string) microtime(true)), 0, 6),
            'is_active' => true,
            'can_manage_student_structure' => false,
            'can_manage_academic_planning' => false,
            'can_manage_leave_types' => false,
            'can_manage_school_calendar' => false,
            'can_manage_school_holidays' => false,
        ], $permissionOverrides));

        $staff = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
        ]);
        $staff->assignRole('staff');

        return $staff;
    }
}
