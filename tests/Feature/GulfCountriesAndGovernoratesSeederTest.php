<?php

namespace Tests\Feature;

use Database\Seeders\GulfCountriesAndGovernoratesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GulfCountriesAndGovernoratesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_gulf_countries_and_governorates_from_external_apis_without_duplicates(): void
    {
        $statesByCountry = [
            'Saudi Arabia' => ['Riyadh Region', 'Makkah Region'],
            'United Arab Emirates' => ['Abu Dhabi', 'Dubai'],
            'Kuwait' => ['Al Asimah', 'Hawalli'],
            'Bahrain' => ['Capital Governorate', 'Muharraq Governorate'],
            'Qatar' => ['Doha', 'Al Rayyan'],
            'Oman' => ['Muscat', 'Dhofar'],
        ];

        Http::fake([
            'https://restcountries.com/v3.1/all*' => Http::response($this->gulfCountriesApiPayload(), 200),
            'https://countriesnow.space/api/v0.1/countries/states' => function (Request $request) use ($statesByCountry) {
                $payload = json_decode($request->body(), true) ?: [];
                $countryName = (string) ($payload['country'] ?? '');

                return Http::response([
                    'error' => false,
                    'msg' => 'states retrieved',
                    'data' => [
                        'name' => $countryName,
                        'states' => collect($statesByCountry[$countryName] ?? [])
                            ->map(fn (string $stateName): array => ['name' => $stateName])
                            ->all(),
                    ],
                ], 200);
            },
        ]);

        $this->seed(GulfCountriesAndGovernoratesSeeder::class);
        $this->seed(GulfCountriesAndGovernoratesSeeder::class);

        $this->assertDatabaseCount('countries', 6);
        $this->assertDatabaseCount('governorates', 12);

        $this->assertDatabaseHas('countries', [
            'name' => 'السعودية',
            'iso2_code' => 'SA',
            'api_source' => 'restcountries',
        ]);
        $this->assertDatabaseHas('countries', ['name' => 'الإمارات العربية المتحدة']);
        $this->assertDatabaseHas('countries', ['name' => 'عمان']);

        $this->assertDatabaseHas('governorates', ['name' => 'Riyadh Region']);
        $this->assertDatabaseHas('governorates', ['name' => 'Dubai']);
        $this->assertDatabaseHas('governorates', ['name' => 'Doha']);
        $this->assertDatabaseHas('governorates', ['name' => 'Muscat']);
    }

    private function gulfCountriesApiPayload(): array
    {
        return collect([
            ['SA', 'Saudi Arabia', 'Kingdom of Saudi Arabia', 'السعودية', 'المملكة العربية السعودية'],
            ['AE', 'United Arab Emirates', 'United Arab Emirates', 'الإمارات العربية المتحدة', 'دولة الإمارات العربية المتحدة'],
            ['KW', 'Kuwait', 'State of Kuwait', 'الكويت', 'دولة الكويت'],
            ['BH', 'Bahrain', 'Kingdom of Bahrain', 'البحرين', 'مملكة البحرين'],
            ['QA', 'Qatar', 'State of Qatar', 'قطر', 'دولة قطر'],
            ['OM', 'Oman', 'Sultanate of Oman', 'عمان', 'سلطنة عمان'],
        ])->map(fn (array $country): array => [
            'cca2' => $country[0],
            'name' => [
                'common' => $country[1],
                'official' => $country[2],
            ],
            'translations' => [
                'ara' => [
                    'common' => $country[3],
                    'official' => $country[4],
                ],
            ],
        ])->all();
    }
}
