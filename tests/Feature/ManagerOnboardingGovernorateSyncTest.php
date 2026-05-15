<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Governorate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManagerOnboardingGovernorateSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_load_governorates_for_selected_country_from_global_api(): void
    {
        $manager = $this->createManager();
        $country = Country::query()->create([
            'name' => 'السعودية',
        ]);

        Http::fake([
            'https://restcountries.com/v3.1/all*' => Http::response([
                [
                    'name' => [
                        'common' => 'Saudi Arabia',
                        'official' => 'Kingdom of Saudi Arabia',
                    ],
                    'translations' => [
                        'ara' => [
                            'common' => 'السعودية',
                            'official' => 'المملكة العربية السعودية',
                        ],
                    ],
                ],
            ], 200),
            'https://countriesnow.space/api/v0.1/countries/states' => Http::response([
                'error' => false,
                'msg' => 'states in Saudi Arabia retrieved',
                'data' => [
                    'name' => 'Saudi Arabia',
                    'states' => [
                        ['name' => 'Riyadh Region', 'state_code' => '01'],
                        ['name' => 'Makkah Region', 'state_code' => '02'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this
            ->actingAs($manager)
            ->getJson(route('manager.onboarding.governorates', [
                'country_id' => $country->id,
            ]));

        $response
            ->assertOk()
            ->assertJsonCount(2, 'governorates')
            ->assertJsonFragment([
                'country_id' => $country->id,
                'name' => 'Riyadh Region',
            ])
            ->assertJsonFragment([
                'country_id' => $country->id,
                'name' => 'Makkah Region',
            ]);

        $this->assertDatabaseHas('governorates', [
            'country_id' => $country->id,
            'name' => 'Riyadh Region',
        ]);
    }

    public function test_manager_governorate_endpoint_reuses_local_governorates_without_external_request(): void
    {
        $manager = $this->createManager();
        $country = Country::query()->create([
            'name' => 'السعودية',
        ]);

        Governorate::query()->create([
            'country_id' => $country->id,
            'name' => 'الرياض',
        ]);

        Http::fake();

        $response = $this
            ->actingAs($manager)
            ->getJson(route('manager.onboarding.governorates', [
                'country_id' => $country->id,
            ]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'governorates')
            ->assertJsonFragment([
                'country_id' => $country->id,
                'name' => 'الرياض',
            ]);

        Http::assertNothingSent();
    }

    private function createManager(): User
    {
        Role::firstOrCreate([
            'name' => 'school_manager',
            'guard_name' => 'web',
        ]);

        $manager = User::factory()->create([
            'role' => 'school_manager',
        ]);
        $manager->assignRole('school_manager');

        return $manager;
    }
}
