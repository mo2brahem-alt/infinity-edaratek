<?php

namespace Tests\Feature;

use Database\Seeders\SaudiAcademicYear20252026DefaultTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaudiAcademicYear20252026DefaultTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_saudi_2025_2026_default_academic_year_template_without_manual_country_creation(): void
    {
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
        ]);

        $this->seed(SaudiAcademicYear20252026DefaultTemplateSeeder::class);
        $this->seed(SaudiAcademicYear20252026DefaultTemplateSeeder::class);

        $this->assertDatabaseHas('countries', [
            'name' => 'السعودية',
            'iso2_code' => 'SA',
            'api_source' => 'restcountries',
        ]);

        $this->assertDatabaseHas('education_types', [
            'name' => 'تعليم عام',
        ]);

        $this->assertDatabaseCount('school_default_academic_year_templates', 1);
        $this->assertDatabaseHas('school_default_academic_year_templates', [
            'name' => '2025 / 2026',
            'starts_on' => '2025-08-24',
            'ends_on' => '2026-06-25',
            'is_active' => true,
        ]);
    }
}
