<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\EducationType;
use App\Models\SchoolDefaultAcademicYearTemplate;
use App\Models\User;
use App\Services\System\GlobalLocationTaxonomySyncService;
use Illuminate\Database\Seeder;

class SaudiAcademicYear20252026DefaultTemplateSeeder extends Seeder
{
    public function run(): void
    {
        app(GlobalLocationTaxonomySyncService::class)->syncCountries();

        $country = Country::query()
            ->where('iso2_code', 'SA')
            ->first()
            ?? Country::query()->firstOrCreate([
                'name' => 'المملكة العربية السعودية',
            ]);

        $educationType = EducationType::query()->firstOrCreate([
            'name' => 'تعليم عام',
        ]);

        $actorId = User::query()
            ->where('email', 'admin@edaratek.com')
            ->value('id');

        $template = SchoolDefaultAcademicYearTemplate::query()->firstOrNew([
            'country_id' => (int) $country->id,
            'education_type_id' => (int) $educationType->id,
            'name' => '2025 / 2026',
        ]);

        if (! $template->exists) {
            $template->created_by = $actorId ? (int) $actorId : null;
        }

        $template->starts_on = '2025-08-24';
        $template->ends_on = '2026-06-25';
        $template->is_active = true;
        $template->updated_by = $actorId ? (int) $actorId : null;
        $template->save();
    }
}
