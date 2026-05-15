<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\EducationalDirectorate;
use App\Models\Governorate;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupervisorOnboardingGovernorateSchoolFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_load_only_schools_for_selected_country_and_governorate(): void
    {
        $supervisor = $this->createSupervisor();
        [$country, $governorate, $directorate] = $this->createLocation('السعودية', 'الرياض', 'تعليم عام - الرياض');
        [, $otherGovernorate, $otherDirectorate] = $this->createLocation('السعودية', 'جدة', 'تعليم عام - جدة', $country);

        $matchingSchool = School::query()->create([
            'directorate_id' => $directorate->id,
            'name' => 'مدرسة الرياض الأولى',
            'school_id' => 'SCH-100001',
            'phone' => '0501000001',
            'status' => School::STATUS_SUSPENDED,
        ]);

        School::query()->create([
            'directorate_id' => $otherDirectorate->id,
            'name' => 'مدرسة جدة الأولى',
            'school_id' => 'SCH-100002',
            'phone' => '0501000002',
            'status' => School::STATUS_SUSPENDED,
        ]);

        $response = $this->actingAs($supervisor)->getJson(route('supervisor.onboarding.location_schools', [
            'country_id' => $country->id,
            'governorate_id' => $governorate->id,
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $matchingSchool->id)
            ->assertJsonPath('0.name', 'مدرسة الرياض الأولى');
    }

    public function test_supervisor_selection_rejects_schools_from_other_governorates_even_if_posted_manually(): void
    {
        $supervisor = $this->createSupervisor();
        [$country, $governorate, $directorate] = $this->createLocation('مصر', 'القاهرة', 'تعليم عام - القاهرة');
        [, , $otherDirectorate] = $this->createLocation('مصر', 'الجيزة', 'تعليم عام - الجيزة', $country);

        $allowedSchool = School::query()->create([
            'directorate_id' => $directorate->id,
            'name' => 'مدرسة القاهرة',
            'school_id' => 'SCH-200001',
            'phone' => '0502000001',
            'status' => School::STATUS_SUSPENDED,
        ]);

        $blockedSchool = School::query()->create([
            'directorate_id' => $otherDirectorate->id,
            'name' => 'مدرسة الجيزة',
            'school_id' => 'SCH-200002',
            'phone' => '0502000002',
            'status' => School::STATUS_SUSPENDED,
        ]);

        $response = $this->actingAs($supervisor)->postJson(route('supervisor.onboarding.select'), [
            'country_id' => $country->id,
            'governorate_id' => $governorate->id,
            'school_ids' => [$allowedSchool->id, $blockedSchool->id],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.school_ids.0', 'واحدة أو أكثر من المدارس المختارة لا تنتمي إلى الدولة والمحافظة المحددتين.');

        $this->assertDatabaseCount('school_supervision_requests', 0);
    }

    private function createSupervisor(): User
    {
        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);

        $supervisor = User::factory()->create([
            'role' => 'supervisor',
            'is_active' => true,
        ]);
        $supervisor->assignRole('supervisor');

        return $supervisor;
    }

    /**
     * @return array{0: Country, 1: Governorate, 2: EducationalDirectorate}
     */
    private function createLocation(string $countryName, string $governorateName, string $directorateName, ?Country $country = null): array
    {
        $country ??= Country::query()->create(['name' => $countryName]);

        $governorate = Governorate::query()->create([
            'country_id' => $country->id,
            'name' => $governorateName,
        ]);

        $directorate = EducationalDirectorate::query()->create([
            'country_id' => $country->id,
            'governorate_id' => $governorate->id,
            'governorate' => $governorate->name,
            'name' => $directorateName,
        ]);

        return [$country, $governorate, $directorate];
    }
}
