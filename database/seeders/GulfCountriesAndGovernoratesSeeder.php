<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Services\System\GlobalLocationTaxonomySyncService;
use Illuminate\Database\Seeder;

class GulfCountriesAndGovernoratesSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const GULF_COUNTRY_CODES = ['SA', 'AE', 'KW', 'BH', 'QA', 'OM'];

    public function run(): void
    {
        $taxonomySyncService = app(GlobalLocationTaxonomySyncService::class);
        $taxonomySyncService->syncCountries();

        Country::query()
            ->whereIn('iso2_code', self::GULF_COUNTRY_CODES)
            ->orderBy('name')
            ->get()
            ->each(function (Country $country) use ($taxonomySyncService): void {
                $taxonomySyncService->syncGovernoratesForCountry($country);
            });
    }
}
