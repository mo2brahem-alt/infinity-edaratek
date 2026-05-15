<?php

namespace App\Services\System;

use App\Models\Country;
use App\Models\Governorate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GlobalLocationTaxonomySyncService
{
    /**
     * Compatibility aliases to avoid duplicating existing manual Gulf entries.
     *
     * @var array<string, array<int, string>>
     */
    private const COUNTRY_ALIASES = [
        'Saudi Arabia' => ['السعودية', 'المملكة العربية السعودية', 'Kingdom of Saudi Arabia'],
        'United Arab Emirates' => ['الإمارات العربية المتحدة', 'دولة الإمارات العربية المتحدة'],
        'Kuwait' => ['الكويت', 'دولة الكويت', 'State of Kuwait'],
        'Bahrain' => ['البحرين', 'مملكة البحرين', 'Kingdom of Bahrain'],
        'Qatar' => ['قطر', 'دولة قطر', 'State of Qatar'],
        'Oman' => ['عمان', 'عُمان', 'سلطنة عمان', 'Sultanate of Oman'],
    ];

    /**
     * @return array{created: int, matched: int, total: int}
     */
    public function syncCountries(): array
    {
        $apiCountries = $this->fetchCountries();
        $existingCountries = Country::query()->get(['id', 'name']);

        $created = 0;
        $matched = 0;

        foreach ($apiCountries as $apiCountry) {
            $existing = $this->findExistingCountry($existingCountries, $apiCountry);

            if ($existing) {
                $this->syncCountryApiIdentity($existing, $apiCountry);
                $matched++;
                continue;
            }

            $country = Country::query()->create([
                'name' => $apiCountry['display_name'],
                'iso2_code' => $apiCountry['country_code'] !== '' ? $apiCountry['country_code'] : null,
                'api_source' => 'restcountries',
                'api_synced_at' => now(),
            ]);

            $existingCountries->push($country);
            $created++;
        }

        return [
            'created' => $created,
            'matched' => $matched,
            'total' => $apiCountries->count(),
        ];
    }

    /**
     * @param  array{country_code: string}  $apiCountry
     */
    private function syncCountryApiIdentity(Country $country, array $apiCountry): void
    {
        $countryCode = strtoupper(trim((string) ($apiCountry['country_code'] ?? '')));

        $updates = [
            'api_source' => 'restcountries',
            'api_synced_at' => now(),
        ];

        if ($countryCode !== '') {
            $updates['iso2_code'] = $countryCode;
        }

        $country->fill($updates);

        if ($country->isDirty()) {
            $country->save();
        }
    }

    /**
     * @return array{status: string, country: string, total: int, created: int, matched: int, reason?: string}
     */
    public function syncGovernoratesForCountry(Country $country): array
    {
        $existingGovernoratesCount = $country->governorates()->count();

        if ($existingGovernoratesCount > 0) {
            return [
                'status' => 'skipped_existing',
                'country' => (string) $country->name,
                'total' => $existingGovernoratesCount,
                'created' => 0,
                'matched' => $existingGovernoratesCount,
                'reason' => 'existing_governorates',
            ];
        }

        $apiCountry = $this->locateApiCountryForLocalCountry($country);

        if (!$apiCountry) {
            throw new RuntimeException('تعذر مطابقة الدولة المختارة مع المصدر العالمي للمحافظات.');
        }

        $stateNames = $this->fetchStatesForCountry($apiCountry);

        if ($stateNames->isEmpty()) {
            return [
                'status' => 'empty',
                'country' => (string) $country->name,
                'total' => 0,
                'created' => 0,
                'matched' => 0,
                'reason' => 'empty_api_response',
            ];
        }

        $created = 0;
        $matched = 0;

        foreach ($stateNames as $stateName) {
            $governorate = Governorate::query()->firstOrCreate([
                'country_id' => (int) $country->id,
                'name' => $stateName,
            ]);

            if ($governorate->wasRecentlyCreated) {
                $created++;
                continue;
            }

            $matched++;
        }

        return [
            'status' => 'synced',
            'country' => (string) $country->name,
            'total' => $stateNames->count(),
            'created' => $created,
            'matched' => $matched,
        ];
    }

    /**
     * @return Collection<int, array{common_name: string, official_name: string, display_name: string, country_code: string, aliases: array<int, string>}>
     */
    private function fetchCountries(): Collection
    {
        $response = $this->restCountriesRequest()
            ->get('/all', [
                'fields' => 'name,translations,cca2',
            ]);

        try {
            $response->throw();
        } catch (ConnectionException $exception) {
            throw new RuntimeException('تعذر الاتصال بمصدر الدول العالمي.', previous: $exception);
        } catch (\Throwable $exception) {
            throw new RuntimeException('فشل جلب بيانات الدول من المصدر العالمي.', previous: $exception);
        }

        return collect($response->json())
            ->map(function (array $country): ?array {
                $commonName = trim((string) data_get($country, 'name.common'));
                $officialName = trim((string) data_get($country, 'name.official'));
                $displayName = $this->resolveCountryDisplayName($country);

                if ($commonName === '' || $displayName === '') {
                    return null;
                }

                return [
                    'common_name' => $commonName,
                    'official_name' => $officialName,
                    'display_name' => $displayName,
                    'country_code' => strtoupper(trim((string) ($country['cca2'] ?? ''))),
                    'aliases' => $this->buildCountryAliases($country, $commonName, $officialName),
                ];
            })
            ->filter()
            ->sortBy('display_name', SORT_NATURAL)
            ->values();
    }

    /**
     * @param  Collection<int, Country>  $existingCountries
     * @param  array{aliases: array<int, string>}  $apiCountry
     */
    private function findExistingCountry(Collection $existingCountries, array $apiCountry): ?Country
    {
        $normalizedAliases = collect($apiCountry['aliases'])
            ->map(fn (string $alias): string => $this->normalizeName($alias))
            ->filter()
            ->unique()
            ->values();

        return $existingCountries->first(function (Country $country) use ($normalizedAliases): bool {
            return $normalizedAliases->contains($this->normalizeName((string) $country->name));
        });
    }

    /**
     * @return array{common_name: string, official_name: string, display_name: string, country_code: string, aliases: array<int, string>}|null
     */
    private function locateApiCountryForLocalCountry(Country $country): ?array
    {
        /** @var array{common_name: string, official_name: string, display_name: string, aliases: array<int, string>}|null $matched */
        $matched = $this->fetchCountries()->first(function (array $apiCountry) use ($country): bool {
            $localCountryName = $this->normalizeName((string) $country->name);

            return collect($apiCountry['aliases'])
                ->map(fn (string $alias): string => $this->normalizeName($alias))
                ->contains($localCountryName);
        });

        return $matched;
    }

    /**
     * @return array{common_name: string, official_name: string, display_name: string, country_code: string, aliases: array<int, string>}|null
     */
    public function resolveCountryApiMetadata(Country $country): ?array
    {
        return $this->locateApiCountryForLocalCountry($country);
    }

    /**
     * @param  array{common_name: string, official_name: string, display_name: string, aliases: array<int, string>}  $apiCountry
     * @return Collection<int, string>
     */
    private function fetchStatesForCountry(array $apiCountry): Collection
    {
        $attemptedNames = collect([
            $apiCountry['common_name'],
            $apiCountry['official_name'],
        ])->filter(fn (string $name): bool => trim($name) !== '')->unique()->values();

        foreach ($attemptedNames as $countryName) {
            $response = $this->countriesNowRequest()->post('/countries/states', [
                'country' => $countryName,
            ]);

            if (!$response->successful()) {
                continue;
            }

            $payload = $response->json();

            if (($payload['error'] ?? true) === true) {
                continue;
            }

            $states = collect(data_get($payload, 'data.states', []))
                ->map(fn (array $state): string => trim((string) ($state['name'] ?? '')))
                ->filter()
                ->unique()
                ->values();

            if ($states->isNotEmpty()) {
                return $states;
            }
        }

        return collect();
    }

    private function resolveCountryDisplayName(array $country): string
    {
        $arabicCommon = trim((string) data_get($country, 'translations.ara.common'));
        $arabicOfficial = trim((string) data_get($country, 'translations.ara.official'));
        $nativeArabicCommon = trim((string) data_get($country, 'name.nativeName.ara.common'));
        $nativeArabicOfficial = trim((string) data_get($country, 'name.nativeName.ara.official'));
        $englishCommon = trim((string) data_get($country, 'name.common'));

        foreach ([$arabicCommon, $nativeArabicCommon, $arabicOfficial, $nativeArabicOfficial, $englishCommon] as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    private function buildCountryAliases(array $country, string $commonName, string $officialName): array
    {
        $aliases = [
            $this->resolveCountryDisplayName($country),
            trim((string) data_get($country, 'translations.ara.official')),
            trim((string) data_get($country, 'name.nativeName.ara.common')),
            trim((string) data_get($country, 'name.nativeName.ara.official')),
            $commonName,
            $officialName,
        ];

        foreach (self::COUNTRY_ALIASES[$commonName] ?? [] as $alias) {
            $aliases[] = $alias;
        }

        return collect($aliases)
            ->map(fn (string $alias): string => trim($alias))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeName(string $value): string
    {
        $normalized = Str::of($value)
            ->replace(['’', '\'', '-', '_'], ' ')
            ->squish()
            ->lower()
            ->value();

        return trim((string) preg_replace('/\s+/u', ' ', $normalized));
    }

    private function restCountriesRequest()
    {
        return Http::acceptJson()
            ->baseUrl((string) config('services.restcountries.base_url'))
            ->connectTimeout((int) config('services.restcountries.connect_timeout', 5))
            ->timeout((int) config('services.restcountries.timeout', 15))
            ->retry(2, 250, throw: false);
    }

    private function countriesNowRequest()
    {
        return Http::acceptJson()
            ->baseUrl((string) config('services.countriesnow.base_url'))
            ->connectTimeout((int) config('services.countriesnow.connect_timeout', 5))
            ->timeout((int) config('services.countriesnow.timeout', 20))
            ->retry(2, 250, throw: false);
    }
}
