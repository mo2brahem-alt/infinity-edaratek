<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Governorate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminGlobalTaxonomySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_cannot_create_country_manually_anymore(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this
            ->from(route('admin.schools.index'))
            ->actingAs($admin)
            ->post(route('admin.countries.store'), [
                'name' => 'السعودية',
            ]);

        $response->assertRedirect(route('admin.schools.index', absolute: false));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('countries', 0);
    }

    public function test_super_admin_cannot_create_governorate_manually_anymore(): void
    {
        $admin = $this->createSuperAdmin();
        $country = Country::query()->create([
            'name' => 'السعودية',
        ]);

        $response = $this
            ->from(route('admin.schools.index'))
            ->actingAs($admin)
            ->post(route('admin.governorates.store'), [
                'country_id' => $country->id,
                'name' => 'الرياض',
            ]);

        $response->assertRedirect(route('admin.schools.index', absolute: false));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('governorates', 0);
    }

    public function test_super_admin_can_sync_countries_from_global_api_without_duplicating_existing_aliases(): void
    {
        $admin = $this->createSuperAdmin();

        Country::query()->create([
            'name' => 'دولة قطر',
        ]);

        Http::fake([
            'https://restcountries.com/v3.1/all*' => Http::response([
                [
                    'name' => [
                        'common' => 'Qatar',
                        'official' => 'State of Qatar',
                    ],
                    'translations' => [
                        'ara' => [
                            'common' => 'قطر',
                            'official' => 'دولة قطر',
                        ],
                    ],
                ],
                [
                    'name' => [
                        'common' => 'Bahrain',
                        'official' => 'Kingdom of Bahrain',
                    ],
                    'translations' => [
                        'ara' => [
                            'common' => 'البحرين',
                            'official' => 'مملكة البحرين',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this
            ->from(route('admin.schools.index'))
            ->actingAs($admin)
            ->post(route('admin.countries.sync_global'));

        $response->assertRedirect(route('admin.schools.index', absolute: false));
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('countries', 2);
        $this->assertDatabaseHas('countries', ['name' => 'دولة قطر']);
        $this->assertDatabaseHas('countries', ['name' => 'البحرين']);
        $this->assertDatabaseMissing('countries', ['name' => 'قطر']);
    }

    public function test_super_admin_can_sync_governorates_for_selected_country_from_global_api(): void
    {
        $admin = $this->createSuperAdmin();
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
            ->from(route('admin.schools.index'))
            ->actingAs($admin)
            ->post(route('admin.governorates.sync_global'), [
                'country_id' => $country->id,
            ]);

        $response->assertRedirect(route('admin.schools.index', absolute: false));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('governorates', [
            'country_id' => $country->id,
            'name' => 'Riyadh Region',
        ]);

        $this->assertDatabaseHas('governorates', [
            'country_id' => $country->id,
            'name' => 'Makkah Region',
        ]);
    }

    public function test_super_admin_governorate_sync_skips_country_that_already_has_local_governorates(): void
    {
        $admin = $this->createSuperAdmin();
        $country = Country::query()->create([
            'name' => 'السعودية',
        ]);

        Governorate::query()->create([
            'country_id' => $country->id,
            'name' => 'الرياض',
        ]);

        Http::fake();

        $response = $this
            ->from(route('admin.schools.index'))
            ->actingAs($admin)
            ->post(route('admin.governorates.sync_global'), [
                'country_id' => $country->id,
            ]);

        $response->assertRedirect(route('admin.schools.index', absolute: false));
        $response->assertSessionHas('warning');
        $this->assertDatabaseCount('governorates', 1);

        Http::assertNothingSent();
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
