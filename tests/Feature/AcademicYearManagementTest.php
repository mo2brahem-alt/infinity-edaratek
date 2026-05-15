<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolAcademicYear;
use App\Models\SchoolTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicYearManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_academic_year_and_link_term_to_it(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'Central',
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Planning School',
            'school_id' => 'SCH-981001',
            'phone' => '0500009811',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $this->from(route('school.academic_planning.index'))
            ->actingAs($manager)
            ->post(route('school.academic_planning.years.store'), [
                'name' => 'العام 2026-2027',
                'starts_on' => '2026-08-20',
                'ends_on' => '2027-06-20',
                'is_active' => true,
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $academicYear = SchoolAcademicYear::query()
            ->where('school_id', $school->id)
            ->where('name', 'العام 2026-2027')
            ->firstOrFail();

        $this->from(route('school.academic_planning.index'))
            ->actingAs($manager)
            ->post(route('school.academic_planning.terms.store'), [
                'school_academic_year_id' => $academicYear->id,
                'name' => 'الترم الأول',
                'start_date' => '2026-09-01',
                'end_date' => '2027-01-10',
                'is_active' => true,
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $this->assertDatabaseHas('school_terms', [
            'school_id' => $school->id,
            'school_academic_year_id' => $academicYear->id,
            'name' => 'الترم الأول',
        ]);
    }

    public function test_term_cannot_be_linked_to_academic_year_from_other_school(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::create([
            'name' => 'تنظيم الجداول',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $plannerRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'منسق أكاديمي',
            'is_active' => true,
            'can_manage_academic_planning' => true,
        ]);

        $region = EducationalDirectorate::create([
            'name' => 'South',
            'governorate' => 'Jazan',
        ]);

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A',
            'school_id' => 'SCH-981002',
            'phone' => '0500009812',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'School B',
            'school_id' => 'SCH-981003',
            'phone' => '0500009813',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $foreignYear = SchoolAcademicYear::create([
            'school_id' => $schoolB->id,
            'name' => 'Foreign Year',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-01',
            'is_active' => true,
        ]);

        $planner = User::factory()->create([
            'role' => 'staff',
            'school_id' => $schoolA->id,
            'department_id' => $department->id,
            'department_role_id' => $plannerRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
        ]);
        $planner->assignRole('staff');

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($planner)
            ->post(route('school.academic_planning.terms.store'), [
                'school_academic_year_id' => $foreignYear->id,
                'name' => 'Term A',
                'start_date' => '2026-09-01',
                'end_date' => '2027-01-10',
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('school_academic_year_id');

        $this->assertDatabaseMissing('school_terms', [
            'school_id' => $schoolA->id,
            'name' => 'Term A',
        ]);
    }

    public function test_staff_cannot_update_academic_year_of_another_school(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::create([
            'name' => 'التنظيم',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $plannerRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'منسق أكاديمي',
            'is_active' => true,
            'can_manage_academic_planning' => true,
        ]);

        $region = EducationalDirectorate::create([
            'name' => 'West',
            'governorate' => 'Makkah',
        ]);

        $schoolA = School::create([
            'directorate_id' => $region->id,
            'name' => 'School A',
            'school_id' => 'SCH-981004',
            'phone' => '0500009814',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $schoolB = School::create([
            'directorate_id' => $region->id,
            'name' => 'School B',
            'school_id' => 'SCH-981005',
            'phone' => '0500009815',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);

        $foreignYear = SchoolAcademicYear::create([
            'school_id' => $schoolB->id,
            'name' => 'Year B',
            'starts_on' => '2026-08-15',
            'ends_on' => '2027-06-20',
            'is_active' => true,
        ]);

        $planner = User::factory()->create([
            'role' => 'staff',
            'school_id' => $schoolA->id,
            'department_id' => $department->id,
            'department_role_id' => $plannerRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
        ]);
        $planner->assignRole('staff');

        $this->actingAs($planner)
            ->put(route('school.academic_planning.years.update', $foreignYear->id), [
                'name' => 'Updated Year',
                'starts_on' => '2026-09-01',
                'ends_on' => '2027-06-01',
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('school_academic_years', [
            'id' => $foreignYear->id,
            'school_id' => $schoolB->id,
            'name' => 'Year B',
        ]);
    }

    public function test_deleting_academic_year_nulls_related_term_reference(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name' => 'North',
            'governorate' => 'Tabuk',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Managed School',
            'school_id' => 'SCH-981006',
            'phone' => '0500009816',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $year = SchoolAcademicYear::create([
            'school_id' => $school->id,
            'name' => 'Year X',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-01',
            'is_active' => true,
        ]);

        $term = SchoolTerm::create([
            'school_id' => $school->id,
            'school_academic_year_id' => $year->id,
            'name' => 'Term X',
            'start_date' => '2026-09-10',
            'end_date' => '2027-01-05',
            'is_active' => true,
        ]);

        $this->from(route('school.academic_planning.index'))
            ->actingAs($manager)
            ->delete(route('school.academic_planning.years.destroy', $year->id))
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $term->refresh();
        $this->assertNull($term->school_academic_year_id);
    }

    public function test_manager_cannot_create_overlapping_academic_year_in_same_school(): void
    {
        $context = $this->createManagerPlanningContext('SCH-981007', 'Overlap Region');

        SchoolAcademicYear::create([
            'school_id' => $context['school']->id,
            'name' => 'Year A',
            'starts_on' => '2026-08-20',
            'ends_on' => '2027-06-20',
            'is_active' => true,
        ]);

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['manager'])
            ->post(route('school.academic_planning.years.store'), [
                'name' => 'Year B',
                'starts_on' => '2027-01-01',
                'ends_on' => '2027-12-31',
                'is_active' => true,
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('starts_on');

        $this->assertDatabaseMissing('school_academic_years', [
            'school_id' => $context['school']->id,
            'name' => 'Year B',
        ]);
    }

    public function test_manager_cannot_create_term_outside_linked_academic_year(): void
    {
        $context = $this->createManagerPlanningContext('SCH-981008', 'Term Range Region');

        $academicYear = SchoolAcademicYear::create([
            'school_id' => $context['school']->id,
            'name' => 'Year A',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-20',
            'is_active' => true,
        ]);

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['manager'])
            ->post(route('school.academic_planning.terms.store'), [
                'school_academic_year_id' => $academicYear->id,
                'name' => 'Out of Range Term',
                'start_date' => '2026-08-20',
                'end_date' => '2026-12-20',
                'is_active' => true,
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('start_date');

        $this->assertDatabaseMissing('school_terms', [
            'school_id' => $context['school']->id,
            'name' => 'Out of Range Term',
        ]);
    }

    public function test_manager_cannot_create_overlapping_term_in_same_school(): void
    {
        $context = $this->createManagerPlanningContext('SCH-981009', 'Term Overlap Region');

        $academicYear = SchoolAcademicYear::create([
            'school_id' => $context['school']->id,
            'name' => 'Year A',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-20',
            'is_active' => true,
        ]);

        SchoolTerm::create([
            'school_id' => $context['school']->id,
            'school_academic_year_id' => $academicYear->id,
            'name' => 'Term 1',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-10',
            'is_active' => true,
        ]);

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['manager'])
            ->post(route('school.academic_planning.terms.store'), [
                'school_academic_year_id' => $academicYear->id,
                'name' => 'Term 2',
                'start_date' => '2026-12-20',
                'end_date' => '2027-03-15',
                'is_active' => true,
            ]);

        $response
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasErrors('start_date');

        $this->assertDatabaseMissing('school_terms', [
            'school_id' => $context['school']->id,
            'name' => 'Term 2',
        ]);
    }

    public function test_manager_can_update_legacy_overlapping_year_name_when_dates_do_not_change(): void
    {
        $context = $this->createManagerPlanningContext('SCH-981010', 'Legacy Region');

        $year = SchoolAcademicYear::create([
            'school_id' => $context['school']->id,
            'name' => 'Legacy Year 1',
            'starts_on' => '2026-08-20',
            'ends_on' => '2027-06-20',
            'is_active' => true,
        ]);

        SchoolAcademicYear::create([
            'school_id' => $context['school']->id,
            'name' => 'Legacy Year 2',
            'starts_on' => '2027-01-01',
            'ends_on' => '2027-12-31',
            'is_active' => true,
        ]);

        $this->from(route('school.academic_planning.index'))
            ->actingAs($context['manager'])
            ->put(route('school.academic_planning.years.update', $year->id), [
                'name' => 'Legacy Year 1 Updated',
                'starts_on' => '2026-08-20',
                'ends_on' => '2027-06-20',
                'is_active' => true,
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('school_academic_years', [
            'id' => $year->id,
            'school_id' => $context['school']->id,
            'name' => 'Legacy Year 1 Updated',
        ]);
    }

    /**
     * @return array{manager:User,school:School}
     */
    private function createManagerPlanningContext(string $schoolCode, string $regionName): array
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $digits = preg_replace('/\D+/', '', $schoolCode) ?: '0';
        $phone = '05' . str_pad(substr($digits, -8), 8, '0', STR_PAD_LEFT);

        $region = EducationalDirectorate::create([
            'name' => $regionName,
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'Planning School ' . $schoolCode,
            'school_id' => $schoolCode,
            'phone' => $phone,
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        return [
            'manager' => $manager,
            'school' => $school,
        ];
    }
}

