<?php

namespace App\Services\System;

use App\Models\Country;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CountryReferenceDataService
{
    private const REQUESTED_REFERENCE_DATA = [
        'public_holidays',
        'academic_year_start',
        'school_breaks',
        'seasonal_breaks',
        'leave_types',
        'islamic_holidays',
    ];

    private const ISLAMIC_HOLIDAY_PRESETS = [
        'SA' => [
            ['name' => 'عيد الأضحى', 'local_name' => 'Eid al-Adha', 'reference_key' => 'eid_al_adha'],
            ['name' => 'عيد الفطر', 'local_name' => 'Eid al-Fitr', 'reference_key' => 'eid_al_fitr'],
            ['name' => 'وقفة عرفة', 'local_name' => 'Arafat Day', 'reference_key' => 'arafah_day'],
        ],
        'EG' => [
            ['name' => 'عيد الأضحى', 'local_name' => 'Eid al-Adha', 'reference_key' => 'eid_al_adha'],
            ['name' => 'عيد الفطر', 'local_name' => 'Eid al-Fitr', 'reference_key' => 'eid_al_fitr'],
            ['name' => 'رأس السنة الهجرية', 'local_name' => 'Islamic New Year', 'reference_key' => 'islamic_new_year'],
            ['name' => 'المولد النبوي الشريف', 'local_name' => 'Prophet Birthday', 'reference_key' => 'prophet_birthday'],
        ],
        'AE' => [
            ['name' => 'عيد الأضحى', 'local_name' => 'Eid al-Adha', 'reference_key' => 'eid_al_adha'],
            ['name' => 'عيد الفطر', 'local_name' => 'Eid al-Fitr', 'reference_key' => 'eid_al_fitr'],
            ['name' => 'وقفة عرفة', 'local_name' => 'Arafat Day', 'reference_key' => 'arafah_day'],
            ['name' => 'رأس السنة الهجرية', 'local_name' => 'Islamic New Year', 'reference_key' => 'islamic_new_year'],
            ['name' => 'المولد النبوي الشريف', 'local_name' => 'Prophet Birthday', 'reference_key' => 'prophet_birthday'],
        ],
        'QA' => [
            ['name' => 'عيد الأضحى', 'local_name' => 'Eid al-Adha', 'reference_key' => 'eid_al_adha'],
            ['name' => 'عيد الفطر', 'local_name' => 'Eid al-Fitr', 'reference_key' => 'eid_al_fitr'],
            ['name' => 'وقفة عرفة', 'local_name' => 'Arafat Day', 'reference_key' => 'arafah_day'],
            ['name' => 'رأس السنة الهجرية', 'local_name' => 'Islamic New Year', 'reference_key' => 'islamic_new_year'],
            ['name' => 'المولد النبوي الشريف', 'local_name' => 'Prophet Birthday', 'reference_key' => 'prophet_birthday'],
        ],
        'KW' => [
            ['name' => 'عيد الأضحى', 'local_name' => 'Eid al-Adha', 'reference_key' => 'eid_al_adha'],
            ['name' => 'عيد الفطر', 'local_name' => 'Eid al-Fitr', 'reference_key' => 'eid_al_fitr'],
            ['name' => 'وقفة عرفة', 'local_name' => 'Arafat Day', 'reference_key' => 'arafah_day'],
            ['name' => 'رأس السنة الهجرية', 'local_name' => 'Islamic New Year', 'reference_key' => 'islamic_new_year'],
            ['name' => 'المولد النبوي الشريف', 'local_name' => 'Prophet Birthday', 'reference_key' => 'prophet_birthday'],
        ],
        'BH' => [
            ['name' => 'عيد الأضحى', 'local_name' => 'Eid al-Adha', 'reference_key' => 'eid_al_adha'],
            ['name' => 'عيد الفطر', 'local_name' => 'Eid al-Fitr', 'reference_key' => 'eid_al_fitr'],
            ['name' => 'وقفة عرفة', 'local_name' => 'Arafat Day', 'reference_key' => 'arafah_day'],
            ['name' => 'رأس السنة الهجرية', 'local_name' => 'Islamic New Year', 'reference_key' => 'islamic_new_year'],
            ['name' => 'المولد النبوي الشريف', 'local_name' => 'Prophet Birthday', 'reference_key' => 'prophet_birthday'],
        ],
        'OM' => [
            ['name' => 'عيد الأضحى', 'local_name' => 'Eid al-Adha', 'reference_key' => 'eid_al_adha'],
            ['name' => 'عيد الفطر', 'local_name' => 'Eid al-Fitr', 'reference_key' => 'eid_al_fitr'],
            ['name' => 'وقفة عرفة', 'local_name' => 'Arafat Day', 'reference_key' => 'arafah_day'],
            ['name' => 'رأس السنة الهجرية', 'local_name' => 'Islamic New Year', 'reference_key' => 'islamic_new_year'],
            ['name' => 'المولد النبوي الشريف', 'local_name' => 'Prophet Birthday', 'reference_key' => 'prophet_birthday'],
        ],
    ];

    public function __construct(
        private readonly GlobalLocationTaxonomySyncService $taxonomySyncService,
        private readonly SaudiCountryReferenceSnapshotProvider $saudiSnapshotProvider,
    ) {
    }

    /**
     * @return array{
     *     status: string,
     *     year: int,
     *     country: array{id: int, name: string, code: string|null},
     *     requested_data: array<int, string>,
     *     supported_data: array<int, string>,
     *     unavailable_data: array<int, string>,
     *     available_counts: array<string, int>,
     *     holidays: array<int, array{name: string, local_name: string|null, date: string|null, notes: string|null, reference_key: string|null, holiday_category: string|null, types: array<int, string>}>,
     *     academic_year?: array{name: string, starts_on: string, ends_on: string, source: string}|null,
     *     source: array{key: string, label: string},
     *     fetched_at: string|null,
     *     message: string
     * }
     */
    public function fetchForCountry(Country $country, ?int $year = null): array
    {
        $resolvedYear = $year ?: (int) now()->year;
        $saudiSnapshot = $this->saudiSnapshotProvider->resolve($country, $resolvedYear);

        $metadata = $this->taxonomySyncService->resolveCountryApiMetadata($country);
        $countryCode = strtoupper(trim((string) ($metadata['country_code'] ?? '')));

        if ($metadata !== null) {
            $this->syncCountryApiIdentity($country, $countryCode);
        }

        if ($countryCode === '') {
            return $saudiSnapshot ?? $this->unsupportedPayload(
                country: $country,
                year: $resolvedYear,
                countryCode: null,
                message: 'لا تتوفر هوية دولية صالحة لهذه الدولة لقراءة مرجعياتها من الواجهة الخارجية.',
            );
        }

        try {
            $response = $this->publicHolidayRequest()->get(sprintf('/PublicHolidays/%d/%s', $resolvedYear, $countryCode));
        } catch (ConnectionException $exception) {
            if ($saudiSnapshot !== null) {
                return $saudiSnapshot;
            }

            throw new RuntimeException('تعذر الاتصال بواجهة مرجعيات الدولة الخارجية.', previous: $exception);
        }

        if ($response->status() === 404) {
            return $saudiSnapshot ?? $this->unsupportedPayload(
                country: $country,
                year: $resolvedYear,
                countryCode: $countryCode,
                message: 'واجهة الدولة لا توفر مرجعيات عطلات لهذا البلد في السنة المحددة.',
            );
        }

        if (! $response->successful()) {
            if ($saudiSnapshot !== null) {
                return $saudiSnapshot;
            }

            throw new RuntimeException('فشل جلب مرجعيات الدولة من الواجهة الخارجية.');
        }

        $responsePayload = $response->json();
        if ($saudiSnapshot !== null && ($response->status() === 204 || ! is_array($responsePayload) || $responsePayload === [])) {
            return $saudiSnapshot;
        }

        $externalPayload = $this->buildExternalHolidayPayload(
            country: $country,
            countryCode: $countryCode,
            year: $resolvedYear,
            responsePayload: $responsePayload,
        );

        if ($saudiSnapshot !== null) {
            return $this->mergeReferencePayloads($externalPayload, $saudiSnapshot);
        }

        return $externalPayload;
    }

    /**
     * @param  mixed  $responsePayload
     * @return array<string, mixed>
     */
    private function buildExternalHolidayPayload(
        Country $country,
        string $countryCode,
        int $year,
        mixed $responsePayload
    ): array {
        $publicHolidays = collect($responsePayload)
            ->filter(fn ($holiday) => is_array($holiday))
            ->map(function (array $holiday): ?array {
                $isGlobal = (bool) ($holiday['global'] ?? false);
                $date = trim((string) ($holiday['date'] ?? ''));

                if (! $isGlobal || $date === '') {
                    return null;
                }

                $name = trim((string) ($holiday['localName'] ?? $holiday['name'] ?? ''));
                $englishName = trim((string) ($holiday['name'] ?? ''));
                $types = collect($holiday['types'] ?? [])
                    ->map(fn ($type): string => trim((string) $type))
                    ->filter()
                    ->values()
                    ->all();

                if ($name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'local_name' => $englishName !== '' && $englishName !== $name ? $englishName : null,
                    'date' => Carbon::parse($date)->toDateString(),
                    'notes' => $this->buildHolidayNote($holiday, $englishName, $types),
                    'reference_key' => null,
                    'holiday_category' => null,
                    'types' => $types,
                ];
            })
            ->filter()
            ->values();

        $islamicHolidays = $this->resolveIslamicHolidayPresets($country, $countryCode, $year);
        $islamicPresetLookup = collect($islamicHolidays)
            ->keyBy(fn (array $holiday) => $this->normalizeHolidayName((string) $holiday['name']));

        $publicHolidays = $publicHolidays->map(function (array $holiday) use ($islamicPresetLookup): array {
            $matchingPreset = $islamicPresetLookup->get($this->normalizeHolidayName((string) $holiday['name']));

            if (! is_array($matchingPreset)) {
                return $holiday;
            }

            return [
                ...$holiday,
                'reference_key' => $matchingPreset['reference_key'] ?? null,
                'holiday_category' => 'islamic',
                'notes' => $holiday['notes'] ?: ($matchingPreset['notes'] ?? null),
                'types' => collect([...(array) ($holiday['types'] ?? []), 'Islamic'])
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ];
        });

        $holidays = $this->mergeHolidayCollections($publicHolidays->all(), $islamicHolidays);

        $supportedData = ['public_holidays'];
        if ($islamicHolidays !== []) {
            $supportedData[] = 'islamic_holidays';
        }

        $messageParts = [];
        if ($publicHolidays->isNotEmpty()) {
            $messageParts[] = 'تم جلب العطلات الرسمية الوطنية من الواجهة الخارجية بنجاح.';
        }
        if ($islamicHolidays !== []) {
            $messageParts[] = 'تمت إضافة الإجازات الإسلامية الافتراضية القابلة لتعديل التاريخ لاحقًا بحسب الإعلان الرسمي للدولة.';
        }
        if ($messageParts === []) {
            $messageParts[] = 'تمت قراءة الواجهة بنجاح، لكن لم تُرجع عطلات رسمية صالحة للاستخدام.';
        }
        $messageParts[] = 'لا يوفر المصدر الحالي بداية العام الدراسي أو الإجازات الدراسية المتخصصة للمدارس.';

        return [
            'status' => 'success',
            'year' => $year,
            'country' => [
                'id' => (int) $country->id,
                'name' => (string) $country->name,
                'code' => $countryCode,
            ],
            'requested_data' => self::REQUESTED_REFERENCE_DATA,
            'supported_data' => $supportedData,
            'unavailable_data' => array_values(array_diff(self::REQUESTED_REFERENCE_DATA, $supportedData)),
            'available_counts' => [
                'public_holidays' => $this->countHolidaysByCategory($holidays, false),
                'islamic_holidays' => $this->countHolidaysByCategory($holidays, true),
                'academic_year_start' => 0,
            ],
            'holidays' => $holidays,
            'source' => $this->sourceDescriptor(),
            'fetched_at' => now()->toISOString(),
            'message' => implode(' ', $messageParts),
        ];
    }

    /**
     * @param  array<string, mixed>  $externalPayload
     * @param  array<string, mixed>  $snapshotPayload
     * @return array<string, mixed>
     */
    private function mergeReferencePayloads(array $externalPayload, array $snapshotPayload): array
    {
        $mergedHolidays = $this->mergeHolidayCollections(
            $snapshotPayload['holidays'] ?? [],
            $externalPayload['holidays'] ?? [],
        );

        $academicYear = is_array($snapshotPayload['academic_year'] ?? null)
            ? $snapshotPayload['academic_year']
            : null;

        $supportedLookup = array_fill_keys([
            ...($externalPayload['supported_data'] ?? []),
            ...($snapshotPayload['supported_data'] ?? []),
        ], true);

        $supportedData = collect(self::REQUESTED_REFERENCE_DATA)
            ->filter(fn (string $item) => isset($supportedLookup[$item]))
            ->values()
            ->all();

        $externalHolidayCount = count($externalPayload['holidays'] ?? []);
        $isHybrid = $externalHolidayCount > 0;

        return [
            'status' => 'success',
            'year' => (int) ($externalPayload['year'] ?? $snapshotPayload['year'] ?? now()->year),
            'country' => $snapshotPayload['country'] ?? $externalPayload['country'] ?? null,
            'requested_data' => self::REQUESTED_REFERENCE_DATA,
            'supported_data' => $supportedData,
            'unavailable_data' => array_values(array_diff(self::REQUESTED_REFERENCE_DATA, $supportedData)),
            'available_counts' => [
                'public_holidays' => $this->countHolidaysByCategory($mergedHolidays, false),
                'islamic_holidays' => $this->countHolidaysByCategory($mergedHolidays, true),
                'academic_year_start' => $academicYear !== null ? 1 : 0,
            ],
            'holidays' => $mergedHolidays,
            'academic_year' => $academicYear,
            'source' => [
                'key' => $isHybrid ? 'saudi_hybrid_reference' : 'saudi_snapshot',
                'label' => $isHybrid
                    ? 'Saudi hybrid reference (external holidays + local school snapshot)'
                    : 'Saudi school reference snapshot',
            ],
            'fetched_at' => now()->toISOString(),
            'message' => $isHybrid
                ? 'تم دمج العطلات العامة المتاحة من المزود الخارجي مع مرجع سعودي محلي لبداية العام الدراسي والإجازات التعليمية والإسلامية الافتراضية.'
                : 'تم استخدام مرجع سعودي محلي احتياطي لتعبئة العطلات الرسمية وبداية العام الدراسي، مع إضافة الإجازات الإسلامية الافتراضية القابلة لتعديل التاريخ لاحقًا.',
        ];
    }

    private function unsupportedPayload(
        Country $country,
        int $year,
        ?string $countryCode,
        string $message
    ): array {
        return [
            'status' => 'unsupported',
            'year' => $year,
            'country' => [
                'id' => (int) $country->id,
                'name' => (string) $country->name,
                'code' => $countryCode,
            ],
            'requested_data' => self::REQUESTED_REFERENCE_DATA,
            'supported_data' => [],
            'unavailable_data' => self::REQUESTED_REFERENCE_DATA,
            'available_counts' => [
                'public_holidays' => 0,
                'islamic_holidays' => 0,
                'academic_year_start' => 0,
            ],
            'holidays' => [],
            'source' => $this->sourceDescriptor(),
            'fetched_at' => null,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $holiday
     */
    private function buildHolidayNote(array $holiday, string $englishName, array $types): ?string
    {
        $parts = collect();

        if ($englishName !== '') {
            $parts->push('الاسم الدولي: ' . $englishName);
        }

        if ($types !== []) {
            $parts->push('النوع: ' . implode('، ', $types));
        }

        $launchYear = isset($holiday['launchYear']) ? (int) $holiday['launchYear'] : null;
        if ($launchYear) {
            $parts->push('سنة الإطلاق: ' . $launchYear);
        }

        return $parts->isNotEmpty() ? $parts->implode(' | ') : null;
    }

    /**
     * @return array<int, array{name: string, local_name: string|null, date: string|null, notes: string, reference_key: string, holiday_category: string, types: array<int, string>}>
     */
    private function resolveIslamicHolidayPresets(Country $country, string $countryCode, int $year): array
    {
        $resolvedCountryCode = $countryCode !== '' ? $countryCode : strtoupper(trim((string) ($country->iso2_code ?? '')));
        $definitions = self::ISLAMIC_HOLIDAY_PRESETS[$resolvedCountryCode] ?? [];

        return collect($definitions)
            ->map(function (array $holiday) use ($country, $year): array {
                return [
                    'name' => (string) $holiday['name'],
                    'local_name' => $holiday['local_name'] ?? null,
                    'date' => null,
                    'notes' => sprintf(
                        'إجازة إسلامية افتراضية مرتبطة بدولة %s. حدّث التاريخ الرسمي لسنة %d قبل اعتمادها داخل المدرسة.',
                        (string) $country->name,
                        $year
                    ),
                    'reference_key' => (string) $holiday['reference_key'],
                    'holiday_category' => 'islamic',
                    'types' => ['Islamic'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  ...$collections
     * @return array<int, array<string, mixed>>
     */
    private function mergeHolidayCollections(array ...$collections): array
    {
        return collect($collections)
            ->flatten(1)
            ->filter(fn ($holiday) => is_array($holiday))
            ->map(function (array $holiday): array {
                return [
                    'name' => trim((string) ($holiday['name'] ?? '')),
                    'local_name' => trim((string) ($holiday['local_name'] ?? '')) ?: null,
                    'date' => ($holiday['date'] ?? null) !== null && trim((string) $holiday['date']) !== ''
                        ? Carbon::parse((string) $holiday['date'])->toDateString()
                        : null,
                    'notes' => trim((string) ($holiday['notes'] ?? '')) ?: null,
                    'reference_key' => trim((string) ($holiday['reference_key'] ?? '')) ?: null,
                    'holiday_category' => trim((string) ($holiday['holiday_category'] ?? '')) ?: null,
                    'types' => collect($holiday['types'] ?? [])
                        ->map(fn ($value) => trim((string) $value))
                        ->filter()
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $holiday) => $holiday['name'] !== '' && ($holiday['date'] !== null || $holiday['reference_key'] !== null))
            ->unique(fn (array $holiday) => $this->holidayIdentity($holiday))
            ->sortBy(fn (array $holiday) => sprintf(
                '%s|%s|%s',
                $holiday['date'] ?? '9999-12-31',
                $holiday['reference_key'] ?? 'zzzz',
                $holiday['name']
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $holidays
     */
    private function countHolidaysByCategory(array $holidays, bool $isIslamic): int
    {
        return collect($holidays)
            ->filter(fn ($holiday) => is_array($holiday))
            ->filter(function (array $holiday) use ($isIslamic): bool {
                $holidayCategory = trim((string) ($holiday['holiday_category'] ?? ''));

                return $isIslamic
                    ? $holidayCategory === 'islamic'
                    : $holidayCategory !== 'islamic';
            })
            ->count();
    }

    /**
     * @return array{key: string, label: string}
     */
    private function sourceDescriptor(): array
    {
        return [
            'key' => 'nager_date',
            'label' => 'Nager.Date Public Holiday API',
        ];
    }

    private function publicHolidayRequest()
    {
        return Http::acceptJson()
            ->baseUrl((string) config('services.nager_date.base_url'))
            ->connectTimeout((int) config('services.nager_date.connect_timeout', 5))
            ->timeout((int) config('services.nager_date.timeout', 20))
            ->retry(2, 250, throw: false);
    }

    private function syncCountryApiIdentity(Country $country, string $countryCode): void
    {
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
     * @param  array{name?: string, date?: string|null, reference_key?: string|null}  $holiday
     */
    private function holidayIdentity(array $holiday): string
    {
        $referenceKey = trim((string) ($holiday['reference_key'] ?? ''));

        if ($referenceKey !== '') {
            return 'reference:' . $referenceKey;
        }

        $date = trim((string) ($holiday['date'] ?? ''));
        if ($date !== '') {
            return 'date:' . $date;
        }

        $identityName = trim((string) ($holiday['name'] ?? ''));
        if ($identityName === '') {
            $identityName = trim((string) ($holiday['local_name'] ?? ''));
        }

        return 'name:' . $this->normalizeHolidayName($identityName);
    }

    private function normalizeHolidayName(string $name): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim($name))) ?: '';
    }
}
