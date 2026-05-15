<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\EducationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CountryReferenceHolidayTemplateImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_preview_and_import_country_holidays_into_scoped_template(): void
    {
        $admin = $this->createSuperAdmin();
        $country = Country::query()->create([
            'name' => 'Saudi Arabia',
        ]);
        $educationType = EducationType::query()->create([
            'name' => 'General Education',
        ]);

        Http::fake([
            'https://restcountries.com/v3.1/all*' => Http::response([
                [
                    'cca2' => 'SA',
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
            'https://date.nager.at/api/v3/PublicHolidays/2026/SA' => Http::response([
                [
                    'date' => '2026-02-22',
                    'localName' => 'يوم التأسيس',
                    'name' => 'Founding Day',
                    'global' => true,
                    'types' => ['Public'],
                ],
                [
                    'date' => '2026-09-23',
                    'localName' => 'اليوم الوطني السعودي',
                    'name' => 'Saudi National Day',
                    'global' => true,
                    'types' => ['Public'],
                ],
            ], 200),
        ]);

        $this
            ->actingAs($admin)
            ->getJson(route('admin.school_defaults.reference.holidays.preview', [
                'country_id' => $country->id,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertJsonPath('country.code', 'SA')
            ->assertJsonPath('available_counts.public_holidays', 2)
            ->assertJsonPath('available_counts.islamic_holidays', 3)
            ->assertJsonCount(5, 'holidays');

        $this->assertDatabaseHas('countries', [
            'id' => $country->id,
            'iso2_code' => 'SA',
            'api_source' => 'restcountries',
        ]);

        $this
            ->actingAs($admin)
            ->postJson(route('admin.school_defaults.reference.holidays.import'), [
                'country_id' => $country->id,
                'education_type_id' => $educationType->id,
                'year' => 2026,
            ])
            ->assertOk()
            ->assertJsonPath('created', 5)
            ->assertJsonPath('skipped', 0);

        $this->assertDatabaseHas('school_default_holiday_templates', [
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'name' => 'يوم التأسيس',
            'start_date' => '2026-02-22',
            'end_date' => '2026-02-22',
        ]);

        $this->assertDatabaseHas('school_default_holiday_templates', [
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'name' => 'عيد الفطر',
            'reference_key' => 'eid_al_fitr',
            'holiday_category' => 'islamic',
            'start_date' => null,
            'end_date' => null,
        ]);

        $this
            ->actingAs($admin)
            ->postJson(route('admin.school_defaults.reference.holidays.import'), [
                'country_id' => $country->id,
                'education_type_id' => $educationType->id,
                'year' => 2026,
            ])
            ->assertOk()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('skipped', 5);

        $this->assertDatabaseCount('school_default_holiday_templates', 5);
    }

    public function test_non_islamic_country_does_not_receive_islamic_holiday_defaults_automatically(): void
    {
        $admin = $this->createSuperAdmin();
        $country = Country::query()->create([
            'name' => 'France',
        ]);
        $educationType = EducationType::query()->create([
            'name' => 'General Education',
        ]);

        Http::fake([
            'https://restcountries.com/v3.1/all*' => Http::response([
                [
                    'cca2' => 'FR',
                    'name' => [
                        'common' => 'France',
                        'official' => 'French Republic',
                    ],
                ],
            ], 200),
            'https://date.nager.at/api/v3/PublicHolidays/2026/FR' => Http::response([
                [
                    'date' => '2026-07-14',
                    'localName' => 'Bastille Day',
                    'name' => 'Bastille Day',
                    'global' => true,
                    'types' => ['Public'],
                ],
            ], 200),
        ]);

        $this
            ->actingAs($admin)
            ->getJson(route('admin.school_defaults.reference.holidays.preview', [
                'country_id' => $country->id,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertJsonPath('country.code', 'FR')
            ->assertJsonPath('available_counts.public_holidays', 1)
            ->assertJsonPath('available_counts.islamic_holidays', 0)
            ->assertJsonCount(1, 'holidays');

        $this
            ->actingAs($admin)
            ->postJson(route('admin.school_defaults.reference.holidays.import'), [
                'country_id' => $country->id,
                'education_type_id' => $educationType->id,
                'year' => 2026,
            ])
            ->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('skipped', 0);

        $this->assertDatabaseHas('school_default_holiday_templates', [
            'country_id' => $country->id,
            'education_type_id' => $educationType->id,
            'name' => 'Bastille Day',
            'reference_key' => null,
            'holiday_category' => null,
            'start_date' => '2026-07-14',
            'end_date' => '2026-07-14',
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
