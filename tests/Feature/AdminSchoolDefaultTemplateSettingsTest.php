<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\EducationalDirectorate;
use App\Models\EducationType;
use App\Models\School;
use App\Models\SchoolAcademicYear;
use App\Models\SchoolDefaultLeaveTypeTemplate;
use App\Models\SchoolDefaultStageGradeTemplate;
use App\Models\SchoolDefaultStageTemplate;
use App\Models\SchoolDefaultSubjectTemplate;
use App\Models\SchoolStage;
use App\Models\User;
use App\Services\School\SchoolDefaultDataProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSchoolDefaultTemplateSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_school_defaults_index_renders_the_standalone_page_when_not_embedded(): void
    {
        $admin = $this->createSuperAdmin();

        Country::query()->create(['name' => 'السعودية']);

        $this->actingAs($admin)
            ->get(route('admin.school_defaults.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/SchoolDefaults/Index')
                ->where('embedded', false)
            );
    }

    public function test_school_defaults_index_renders_embedded_editor_mode_for_selected_country_and_education_type(): void
    {
        $admin = $this->createSuperAdmin();

        $country = Country::query()->create(['name' => 'السعودية']);
        $educationType = EducationType::query()->create(['name' => 'تعليم أهلي']);

        SchoolDefaultStageTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'المرحلة الابتدائية',
            'code' => 'STG-PRI',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.school_defaults.index', [
                'embedded' => 1,
                'editor' => 1,
                'country_id' => $country->id,
                'education_type_id' => $educationType->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/SchoolDefaults/Index')
                ->where('embedded', true)
                ->where('editor', true)
                ->where('filters.country_id', $country->id)
                ->where('filters.education_type_id', $educationType->id)
                ->where('stageTemplates.0.name', 'المرحلة الابتدائية')
            );
    }

    public function test_settings_default_templates_tab_query_redirects_to_the_standalone_page(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.index', ['tab' => 'default_templates']))
            ->assertRedirect(route('admin.school_defaults.index', absolute: false));
    }

    public function test_super_admin_can_store_template_scope_configuration_and_json_payload_lists_it(): void
    {
        $admin = $this->createSuperAdmin();

        $country = Country::query()->create(['name' => 'السعودية']);
        $educationType = EducationType::query()->create(['name' => 'تعليم أهلي']);

        $this->fakeCountryApis();
        Carbon::setTestNow('2026-04-09 10:00:00');

        $storeResponse = $this->actingAs($admin)
            ->postJson(route('admin.school_defaults.scopes.store'), [
                'template_name' => 'قالب السعودية الأهلي',
                'country_id' => $country->id,
                'education_type_id' => $educationType->id,
            ]);

        $storeResponse
            ->assertOk()
            ->assertJsonPath('data.scope.template_name', 'قالب السعودية الأهلي')
            ->assertJsonPath('data.scope.country_id', $country->id)
            ->assertJsonPath('data.scope.education_type_id', $educationType->id)
            ->assertJsonPath('data.scope.directorate_id', null)
            ->assertJsonPath('data.country_reference.supported_data.0', 'public_holidays')
            ->assertJsonPath('data.country_reference.supported_data.1', 'academic_year_start')
            ->assertJsonPath('data.country_reference.unavailable_data.0', 'school_breaks')
            ->assertJsonPath('data.holiday_sync.status', 'synced')
            ->assertJsonPath('data.academic_year_sync.status', 'created')
            ->assertJsonPath('data.template_bootstrap.has_generated_defaults', true)
            ->assertJsonPath('data.template_bootstrap.stages.created', 4)
            ->assertJsonPath('data.template_bootstrap.stage_grades.created', 15)
            ->assertJsonPath('data.template_bootstrap.classrooms.created', 15)
            ->assertJsonPath('data.template_bootstrap.leave_types.created', 4)
            ->assertJsonPath('data.template_bootstrap.subjects.created', 6)
            ->assertJsonPath('data.academic_year_sync.template.name', 'العام الدراسي 2025-2026')
            ->assertJsonPath('data.academic_year_sync.template.starts_on', '2025-08-24')
            ->assertJsonPath('data.academic_year_sync.template.ends_on', '2026-06-25');

        $this->assertDatabaseHas('settings', [
            'key' => 'school_default_template_scopes',
        ]);

        $this->assertDatabaseCount('school_default_stage_templates', 4);
        $this->assertDatabaseCount('school_default_stage_grade_templates', 15);
        $this->assertDatabaseCount('school_default_classroom_templates', 15);
        $this->assertDatabaseCount('school_default_leave_type_templates', 4);
        $this->assertDatabaseCount('school_default_subject_templates', 6);

        $this->assertDatabaseHas('school_default_holiday_templates', [
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'اليوم الوطني',
        ]);

        $this->assertDatabaseHas('school_default_academic_year_templates', [
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'العام الدراسي 2025-2026',
            'starts_on' => '2025-08-24',
            'ends_on' => '2026-06-25',
        ]);

        $payloadResponse = $this->actingAs($admin)
            ->getJson(route('admin.school_defaults.index', [
                'country_id' => $country->id,
                'education_type_id' => $educationType->id,
            ]));

        $payloadResponse
            ->assertOk()
            ->assertJsonPath('scopeConfig.template_name', 'قالب السعودية الأهلي')
            ->assertJsonPath('scopeConfig.country_id', $country->id)
            ->assertJsonPath('scopeConfig.education_type_id', $educationType->id)
            ->assertJsonPath('stageTemplates.0.name', 'روضة')
            ->assertJsonPath('academicYearTemplates.0.name', 'العام الدراسي 2025-2026')
            ->assertJsonPath('academicYearTemplates.0.starts_on', '2025-08-24')
            ->assertJsonPath('academicYearTemplates.0.ends_on', '2026-06-25');

        Carbon::setTestNow();
    }

    public function test_country_reference_endpoint_returns_supported_public_holidays_payload(): void
    {
        $admin = $this->createSuperAdmin();
        $country = Country::query()->create(['name' => 'السعودية']);

        $this->fakeCountryApis();

        $this->actingAs($admin)
            ->getJson(route('admin.school_defaults.country_reference', ['country_id' => $country->id]))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('supported_data.0', 'public_holidays')
            ->assertJsonPath('supported_data.1', 'academic_year_start')
            ->assertJsonPath('unavailable_data.0', 'school_breaks')
            ->assertJsonPath('available_counts.public_holidays', 2)
            ->assertJsonPath('holidays.0.name', 'يوم التأسيس')
            ->assertJsonPath('academic_year.starts_on', '2025-08-24')
            ->assertJsonPath('academic_year.ends_on', '2026-06-25');
    }

    public function test_country_reference_endpoint_uses_saudi_snapshot_when_external_holiday_api_returns_no_content(): void
    {
        $admin = $this->createSuperAdmin();
        $country = Country::query()->create(['name' => 'السعودية']);

        Http::fake([
            'https://restcountries.com/*' => Http::response([
                [
                    'name' => ['common' => 'Saudi Arabia'],
                    'translations' => [
                        'ara' => ['common' => 'السعودية'],
                    ],
                    'cca2' => 'SA',
                ],
            ], 200),
            'https://date.nager.at/*' => Http::response(null, 204),
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.school_defaults.country_reference', ['country_id' => $country->id]))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('source.key', 'saudi_snapshot')
            ->assertJsonPath('supported_data.0', 'public_holidays')
            ->assertJsonPath('supported_data.1', 'academic_year_start')
            ->assertJsonPath('available_counts.public_holidays', 2)
            ->assertJsonPath('holidays.0.name', 'يوم التأسيس')
            ->assertJsonPath('academic_year.starts_on', '2025-08-24')
            ->assertJsonPath('academic_year.ends_on', '2026-06-25');
    }

    public function test_re_saving_same_scope_does_not_duplicate_auto_generated_defaults(): void
    {
        $admin = $this->createSuperAdmin();
        $country = Country::query()->create(['name' => 'السعودية']);
        $educationType = EducationType::query()->create(['name' => 'تعليم أهلي']);

        $this->fakeCountryApis();
        Carbon::setTestNow('2026-04-09 10:00:00');

        $payload = [
            'template_name' => 'قالب السعودية الأهلي',
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
        ];

        $this->actingAs($admin)
            ->postJson(route('admin.school_defaults.scopes.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.template_bootstrap.has_generated_defaults', true);

        $this->actingAs($admin)
            ->postJson(route('admin.school_defaults.scopes.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.template_bootstrap.has_generated_defaults', false)
            ->assertJsonPath('data.template_bootstrap.stages.created', 0)
            ->assertJsonPath('data.template_bootstrap.stage_grades.created', 0)
            ->assertJsonPath('data.template_bootstrap.classrooms.created', 0)
            ->assertJsonPath('data.template_bootstrap.leave_types.created', 0)
            ->assertJsonPath('data.template_bootstrap.subjects.created', 0);

        $this->assertDatabaseCount('school_default_stage_templates', 4);
        $this->assertDatabaseCount('school_default_stage_grade_templates', 15);
        $this->assertDatabaseCount('school_default_classroom_templates', 15);
        $this->assertDatabaseCount('school_default_leave_type_templates', 4);
        $this->assertDatabaseCount('school_default_subject_templates', 6);

        Carbon::setTestNow();
    }

    public function test_partial_existing_template_data_is_completed_by_fallback_defaults_without_overwriting_it(): void
    {
        $admin = $this->createSuperAdmin();
        $country = Country::query()->create(['name' => 'السعودية']);
        $educationType = EducationType::query()->create(['name' => 'تعليم أهلي']);

        $existingStage = SchoolDefaultStageTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'المرحلة الابتدائية',
            'code' => 'STG-EX-1',
            'sort_order' => 20,
            'is_active' => true,
            'school_day_start_time' => '07:00:00',
            'school_day_end_time' => '13:00:00',
        ]);

        SchoolDefaultStageGradeTemplate::query()->create([
            'school_default_stage_template_id' => $existingStage->id,
            'name' => 'الصف الأول',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        SchoolDefaultLeaveTypeTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'إجازة مرضية',
            'code' => 'LEAVE-EX-1',
            'requires_attachment' => true,
            'is_active' => true,
        ]);

        SchoolDefaultSubjectTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'اللغة العربية',
            'code' => 'SUB-EX-1',
            'branches' => [],
            'is_active' => true,
        ]);

        $this->fakeCountryApis();
        Carbon::setTestNow('2026-04-09 10:00:00');

        $response = $this->actingAs($admin)
            ->postJson(route('admin.school_defaults.scopes.store'), [
                'template_name' => 'قالب السعودية الأهلي',
                'country_id' => $country->id,
                'education_type_id' => $educationType->id,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.template_bootstrap.has_generated_defaults', true)
            ->assertJsonPath('data.template_bootstrap.stages.created', 3)
            ->assertJsonPath('data.template_bootstrap.stage_grades.created', 14)
            ->assertJsonPath('data.template_bootstrap.classrooms.created', 15)
            ->assertJsonPath('data.template_bootstrap.leave_types.created', 3)
            ->assertJsonPath('data.template_bootstrap.subjects.created', 5);

        $this->assertDatabaseCount('school_default_stage_templates', 4);
        $this->assertDatabaseCount('school_default_stage_grade_templates', 15);
        $this->assertDatabaseCount('school_default_classroom_templates', 15);
        $this->assertDatabaseCount('school_default_leave_type_templates', 4);
        $this->assertDatabaseCount('school_default_subject_templates', 6);

        $this->assertDatabaseHas('school_default_stage_templates', [
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'المرحلة الابتدائية',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.school_defaults.index', [
                'country_id' => $country->id,
                'education_type_id' => $educationType->id,
            ]))
            ->assertOk()
            ->assertJsonCount(4, 'stageTemplates')
            ->assertJsonCount(4, 'leaveTypeTemplates')
            ->assertJsonCount(6, 'subjectTemplates')
            ->assertJsonPath('scopeConfig.template_name', 'قالب السعودية الأهلي');

        Carbon::setTestNow();
    }

    public function test_template_availability_respects_country_and_education_type_templates(): void
    {
        $country = Country::query()->create(['name' => 'السعودية']);
        $generalEducation = EducationType::query()->create(['name' => 'تعليم عام']);
        $internationalEducation = EducationType::query()->create(['name' => 'تعليم دولي']);

        $generalDirectorate = EducationalDirectorate::query()->create([
            'name' => 'إدارة الرياض',
            'governorate' => 'الرياض',
            'country_id' => $country->id,
            'education_type_id' => $generalEducation->id,
        ]);

        $internationalDirectorate = EducationalDirectorate::query()->create([
            'name' => 'إدارة جدة',
            'governorate' => 'جدة',
            'country_id' => $country->id,
            'education_type_id' => $internationalEducation->id,
        ]);

        SchoolDefaultStageTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $generalEducation->id,
            'directorate_id' => null,
            'name' => 'ابتدائي',
            'code' => 'STG-1',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $generalSchool = School::query()->create([
            'name' => 'مدرسة الرياض',
            'school_id' => 'SCH-100',
            'directorate_id' => $generalDirectorate->id,
        ]);

        $internationalSchool = School::query()->create([
            'name' => 'مدرسة جدة',
            'school_id' => 'SCH-200',
            'directorate_id' => $internationalDirectorate->id,
        ]);

        $service = app(SchoolDefaultDataProvisioningService::class);

        $generalAvailability = $service->templateAvailability($generalSchool->fresh('directorate'));
        $internationalAvailability = $service->templateAvailability($internationalSchool->fresh('directorate'));

        $this->assertTrue($generalAvailability['has_any_templates']);
        $this->assertSame(1, $generalAvailability['counts']['stages']);
        $this->assertFalse($internationalAvailability['has_any_templates']);
    }

    public function test_super_admin_can_store_a_country_and_education_type_scoped_academic_year_template(): void
    {
        $admin = $this->createSuperAdmin();
        $country = Country::query()->create(['name' => 'السعودية']);
        $educationType = EducationType::query()->create(['name' => 'تعليم أهلي']);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.school_defaults.academic_years.store'), [
                'country_id' => $country->id,
                'education_type_id' => $educationType->id,
                'name' => 'العام الدراسي 2026-2027',
                'starts_on' => '2026-08-25',
                'ends_on' => '2027-06-30',
                'is_active' => true,
            ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'العام الدراسي 2026-2027')
            ->assertJsonPath('data.country_id', $country->id)
            ->assertJsonPath('data.education_type_id', $educationType->id)
            ->assertJsonPath('data.directorate_id', null);

        $this->assertDatabaseHas('school_default_academic_year_templates', [
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'العام الدراسي 2026-2027',
        ]);
    }

    public function test_client_reference_snapshot_is_persisted_and_refreshing_it_updates_matching_holidays_without_duplication(): void
    {
        $admin = $this->createSuperAdmin();

        $country = Country::query()->create(['name' => 'السعودية']);
        $educationType = EducationType::query()->create(['name' => 'تعليم أهلي']);

        Http::preventStrayRequests();

        $initialSnapshot = [
            'status' => 'success',
            'year' => 2026,
            'requested_data' => ['public_holidays', 'academic_year_start'],
            'supported_data' => ['public_holidays'],
            'unavailable_data' => ['academic_year_start'],
            'available_counts' => [
                'public_holidays' => 1,
            ],
            'holidays' => [
                [
                    'name' => 'اليوم الوطني',
                    'local_name' => 'اليوم الوطني',
                    'date' => '2026-09-23',
                    'notes' => 'مستورد من API',
                    'types' => ['Public'],
                ],
            ],
            'message' => 'تم جلب العطلات الرسمية المتاحة.',
            'fetched_at' => '2026-04-08T10:00:00+02:00',
            'source' => [
                'key' => 'nager_date',
                'label' => 'Nager.Date Public Holiday API',
            ],
            'country' => [
                'id' => $country->id,
                'name' => $country->name,
                'code' => 'SA',
            ],
        ];

        $this->actingAs($admin)
            ->postJson(route('admin.school_defaults.scopes.store'), [
                'template_name' => 'قالب السعودية الأهلي',
                'country_id' => $country->id,
                'education_type_id' => $educationType->id,
                'reference_snapshot' => $initialSnapshot,
            ])
            ->assertOk()
            ->assertJsonPath('data.country_reference.available_counts.public_holidays', 1)
            ->assertJsonPath('data.holiday_sync.created', 1);

        $this->assertDatabaseCount('school_default_holiday_templates', 1);
        $this->assertDatabaseHas('school_default_holiday_templates', [
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'اليوم الوطني',
            'notes' => 'مستورد من API',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.school_defaults.index', [
                'country_id' => $country->id,
                'education_type_id' => $educationType->id,
            ]))
            ->assertOk()
            ->assertJsonPath('scopeConfig.reference_snapshot.available_counts.public_holidays', 1)
            ->assertJsonPath('scopeConfig.reference_snapshot.unavailable_data.0', 'academic_year_start')
            ->assertJsonPath('scopeConfig.reference_snapshot.source.key', 'nager_date')
            ->assertJsonPath('scopeConfig.reference_snapshot.holidays.0.name', 'اليوم الوطني');

        $refreshedSnapshot = $initialSnapshot;
        $refreshedSnapshot['holidays'][0]['notes'] = 'تم تحديث الملاحظة';
        $refreshedSnapshot['fetched_at'] = '2026-04-08T11:30:00+02:00';

        $this->actingAs($admin)
            ->postJson(route('admin.school_defaults.scopes.store'), [
                'template_name' => 'قالب السعودية الأهلي',
                'country_id' => $country->id,
                'education_type_id' => $educationType->id,
                'reference_snapshot' => $refreshedSnapshot,
            ])
            ->assertOk()
            ->assertJsonPath('data.holiday_sync.created', 0)
            ->assertJsonPath('data.holiday_sync.updated', 1)
            ->assertJsonPath('data.country_reference.holidays.0.notes', 'تم تحديث الملاحظة');

        $this->assertDatabaseCount('school_default_holiday_templates', 1);
        $this->assertDatabaseHas('school_default_holiday_templates', [
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'اليوم الوطني',
            'notes' => 'تم تحديث الملاحظة',
        ]);
    }

    public function test_auto_generated_current_academic_year_is_copied_to_school_when_importing_templates(): void
    {
        $admin = $this->createSuperAdmin();
        $country = Country::query()->create(['name' => 'السعودية']);
        $educationType = EducationType::query()->create(['name' => 'تعليم أهلي']);
        $directorate = EducationalDirectorate::query()->create([
            'name' => 'إدارة الدمام',
            'governorate' => 'الدمام',
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
        ]);

        $this->fakeCountryApis();
        Carbon::setTestNow('2026-04-09 10:00:00');

        $this->actingAs($admin)
            ->postJson(route('admin.school_defaults.scopes.store'), [
                'template_name' => 'قالب الدمام الأهلي',
                'country_id' => $country->id,
                'education_type_id' => $educationType->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.academic_year_sync.status', 'created');

        $school = School::query()->create([
            'name' => 'مدارس الدمام الأهلية',
            'school_id' => 'SCH-301',
            'directorate_id' => $directorate->id,
        ]);

        $result = app(SchoolDefaultDataProvisioningService::class)->importForSchool((int) $school->id, (int) $admin->id);

        $this->assertSame(4, $result['counts']['stages']);
        $this->assertSame(15, $result['counts']['stage_grades']);
        $this->assertSame(15, $result['counts']['classrooms']);
        $this->assertSame(1, $result['counts']['academic_years']);
        $this->assertSame(2, $result['counts']['terms']);
        $this->assertSame(2, $result['counts']['holidays']);
        $this->assertSame(4, $result['counts']['leave_types']);
        $this->assertSame(6, $result['counts']['subjects']);

        $this->assertDatabaseCount('school_stages', 4);
        $this->assertDatabaseCount('school_stage_grades', 15);
        $this->assertDatabaseCount('school_classrooms', 15);
        $this->assertDatabaseCount('school_leave_types', 4);
        $this->assertDatabaseCount('school_subjects', 6);

        $this->assertDatabaseHas('school_stages', [
            'school_id' => $school->id,
            'name' => 'روضة',
        ]);

        $this->assertDatabaseHas('school_academic_years', [
            'school_id' => $school->id,
            'name' => 'العام الدراسي 2025-2026',
            'starts_on' => '2025-08-24',
            'ends_on' => '2026-06-25',
        ]);

        $this->assertDatabaseHas('school_terms', [
            'school_id' => $school->id,
            'name' => 'الترم الأول',
            'start_date' => '2025-08-24',
            'end_date' => '2026-01-23',
        ]);

        $this->assertDatabaseHas('school_terms', [
            'school_id' => $school->id,
            'name' => 'الترم الثاني',
            'start_date' => '2026-01-24',
            'end_date' => '2026-06-25',
        ]);

        $this->assertDatabaseHas('school_leave_types', [
            'school_id' => $school->id,
            'name' => 'إجازة مرضية',
        ]);

        $this->assertDatabaseHas('school_subjects', [
            'school_id' => $school->id,
            'name' => 'اللغة العربية',
        ]);

        Carbon::setTestNow();
    }

    public function test_importing_a_country_and_education_type_template_copies_it_into_school_scoped_records(): void
    {
        $admin = $this->createSuperAdmin();
        $country = Country::query()->create(['name' => 'السعودية']);
        $educationType = EducationType::query()->create(['name' => 'تعليم أهلي']);
        $directorate = EducationalDirectorate::query()->create([
            'name' => 'إدارة الدمام',
            'governorate' => 'الدمام',
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
        ]);

        $template = SchoolDefaultStageTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'المرحلة المتوسطة',
            'code' => 'STG-MID',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $school = School::query()->create([
            'name' => 'مدارس الدمام الأهلية',
            'school_id' => 'SCH-300',
            'directorate_id' => $directorate->id,
        ]);

        $result = app(SchoolDefaultDataProvisioningService::class)->importForSchool((int) $school->id, (int) $admin->id);

        $this->assertSame(1, $result['counts']['stages']);

        $schoolStage = SchoolStage::query()
            ->where('school_id', $school->id)
            ->where('name', 'المرحلة المتوسطة')
            ->first();

        $this->assertNotNull($schoolStage);

        $schoolStage->update([
            'name' => 'المرحلة المتوسطة - مدارس الدمام',
        ]);

        $template->refresh();

        $this->assertSame('المرحلة المتوسطة', $template->name);
        $this->assertDatabaseHas('school_stages', [
            'id' => $schoolStage->id,
            'school_id' => $school->id,
            'name' => 'المرحلة المتوسطة - مدارس الدمام',
        ]);
    }

    public function test_super_admin_can_delete_a_saved_template_scope_without_affecting_school_copies(): void
    {
        $admin = $this->createSuperAdmin();
        $country = Country::query()->create(['name' => 'Ø§Ù„Ø³Ø¹ÙˆØ¯ÙŠØ©']);
        $educationType = EducationType::query()->create(['name' => 'ØªØ¹Ù„ÙŠÙ… Ø£Ù‡Ù„ÙŠ']);
        $directorate = EducationalDirectorate::query()->create([
            'name' => 'Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ø¯Ù…Ø§Ù…',
            'governorate' => 'Ø§Ù„Ø¯Ù…Ø§Ù…',
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.school_defaults.scopes.store'), [
                'template_name' => 'Ù‚Ø§Ù„Ø¨ Ø§Ù„Ø¯Ù…Ø§Ù… Ø§Ù„Ø£Ù‡Ù„ÙŠ',
                'country_id' => $country->id,
                'education_type_id' => $educationType->id,
                'reference_snapshot' => [
                    'status' => 'success',
                    'year' => 2026,
                    'requested_data' => ['public_holidays'],
                    'supported_data' => ['public_holidays'],
                    'unavailable_data' => [],
                    'available_counts' => ['public_holidays' => 0],
                    'holidays' => [],
                    'message' => 'Ù…Ø±Ø¬Ø¹ÙŠØ§Øª Ù…Ø­ÙÙˆØ¸Ø©.',
                    'fetched_at' => '2026-04-09T10:00:00+02:00',
                    'source' => [
                        'key' => 'manual',
                        'label' => 'Manual snapshot',
                    ],
                    'country' => [
                        'id' => $country->id,
                        'name' => $country->name,
                        'code' => 'SA',
                    ],
                ],
            ])
            ->assertOk();

        SchoolDefaultStageTemplate::query()->create([
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'directorate_id' => null,
            'name' => 'Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ø§Ø¨ØªØ¯Ø§Ø¦ÙŠØ©',
            'code' => 'STG-PRI',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $school = School::query()->create([
            'name' => 'Ù…Ø¯Ø§Ø±Ø³ Ø§Ù„Ø¯Ù…Ø§Ù… Ø§Ù„Ø£Ù‡Ù„ÙŠØ©',
            'school_id' => 'SCH-302',
            'directorate_id' => $directorate->id,
        ]);

        $result = app(SchoolDefaultDataProvisioningService::class)->importForSchool((int) $school->id, (int) $admin->id);

        $this->assertSame(5, $result['counts']['stages']);

        $schoolStage = SchoolStage::query()
            ->where('school_id', $school->id)
            ->where('name', 'Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ø§Ø¨ØªØ¯Ø§Ø¦ÙŠØ©')
            ->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.school_defaults.scopes.destroy', [
                'country' => $country->id,
                'educationType' => $educationType->id,
            ]))
            ->assertRedirect(route('admin.school_defaults.index', absolute: false))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('school_default_stage_templates', [
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'name' => 'Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ø§Ø¨ØªØ¯Ø§Ø¦ÙŠØ©',
        ]);

        $this->assertDatabaseMissing('school_default_academic_year_templates', [
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
        ]);

        $this->assertDatabaseMissing('settings', [
            'key' => 'school_default_template_scopes',
        ]);

        $this->assertDatabaseHas('school_stages', [
            'id' => $schoolStage->id,
            'school_id' => $school->id,
            'name' => 'Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ø§Ø¨ØªØ¯Ø§Ø¦ÙŠØ©',
        ]);
    }

    private function fakeCountryApis(): void
    {
        Http::fake([
            'https://restcountries.com/*' => Http::response([
                [
                    'name' => ['common' => 'Saudi Arabia'],
                    'translations' => [
                        'ara' => ['common' => 'السعودية'],
                    ],
                    'cca2' => 'SA',
                ],
            ], 200),
            'https://date.nager.at/*' => Http::response([
                [
                    'date' => '2026-09-23',
                    'localName' => 'اليوم الوطني',
                    'name' => 'National Day',
                    'global' => true,
                    'types' => ['Public'],
                ],
            ], 200),
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
