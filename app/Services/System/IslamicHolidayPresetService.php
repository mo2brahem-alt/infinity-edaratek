<?php

namespace App\Services\System;

use App\Models\Country;

class IslamicHolidayPresetService
{
    private const COMMON_ISLAMIC_HOLIDAYS = [
        'eid_al_fitr',
        'arafah_day',
        'eid_al_adha',
        'islamic_new_year',
        'mawlid_al_nabi',
    ];

    private const COUNTRY_PRESETS = [
        'SA' => ['eid_al_fitr', 'arafah_day', 'eid_al_adha'],
        'AE' => self::COMMON_ISLAMIC_HOLIDAYS,
        'EG' => self::COMMON_ISLAMIC_HOLIDAYS,
        'BH' => self::COMMON_ISLAMIC_HOLIDAYS,
        'DZ' => self::COMMON_ISLAMIC_HOLIDAYS,
        'ID' => self::COMMON_ISLAMIC_HOLIDAYS,
        'IQ' => self::COMMON_ISLAMIC_HOLIDAYS,
        'JO' => self::COMMON_ISLAMIC_HOLIDAYS,
        'KW' => self::COMMON_ISLAMIC_HOLIDAYS,
        'MA' => self::COMMON_ISLAMIC_HOLIDAYS,
        'MY' => self::COMMON_ISLAMIC_HOLIDAYS,
        'OM' => self::COMMON_ISLAMIC_HOLIDAYS,
        'PK' => self::COMMON_ISLAMIC_HOLIDAYS,
        'QA' => self::COMMON_ISLAMIC_HOLIDAYS,
        'TN' => self::COMMON_ISLAMIC_HOLIDAYS,
    ];

    private const COUNTRY_NAME_MAP = [
        'السعودية' => 'SA',
        'المملكة العربية السعودية' => 'SA',
        'saudi arabia' => 'SA',
        'kingdom of saudi arabia' => 'SA',
        'مصر' => 'EG',
        'جمهورية مصر العربية' => 'EG',
        'egypt' => 'EG',
        'arab republic of egypt' => 'EG',
        'الإمارات' => 'AE',
        'الإمارات العربية المتحدة' => 'AE',
        'united arab emirates' => 'AE',
        'uae' => 'AE',
        'الكويت' => 'KW',
        'kuwait' => 'KW',
        'قطر' => 'QA',
        'qatar' => 'QA',
        'عمان' => 'OM',
        'سلطنة عمان' => 'OM',
        'oman' => 'OM',
        'البحرين' => 'BH',
        'bahrain' => 'BH',
        'الأردن' => 'JO',
        'jordan' => 'JO',
        'المغرب' => 'MA',
        'morocco' => 'MA',
        'الجزائر' => 'DZ',
        'algeria' => 'DZ',
        'تونس' => 'TN',
        'tunisia' => 'TN',
        'العراق' => 'IQ',
        'iraq' => 'IQ',
        'باكستان' => 'PK',
        'pakistan' => 'PK',
        'ماليزيا' => 'MY',
        'malaysia' => 'MY',
        'إندونيسيا' => 'ID',
        'اندونيسيا' => 'ID',
        'indonesia' => 'ID',
    ];

    private const HOLIDAY_DEFINITIONS = [
        'eid_al_fitr' => [
            'name' => 'عيد الفطر',
            'local_name' => 'Eid al-Fitr',
        ],
        'arafah_day' => [
            'name' => 'وقفة عرفة',
            'local_name' => 'Arafat Day',
        ],
        'eid_al_adha' => [
            'name' => 'عيد الأضحى',
            'local_name' => 'Eid al-Adha',
        ],
        'islamic_new_year' => [
            'name' => 'رأس السنة الهجرية',
            'local_name' => 'Islamic New Year',
        ],
        'mawlid_al_nabi' => [
            'name' => 'المولد النبوي الشريف',
            'local_name' => 'Mawlid al-Nabi',
        ],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function presetsForCountry(Country $country, int $year): array
    {
        $countryCode = $this->resolveCountryCode($country);

        if ($countryCode === null) {
            return [];
        }

        $holidayKeys = self::COUNTRY_PRESETS[$countryCode] ?? [];

        return collect($holidayKeys)
            ->map(function (string $holidayKey) use ($country, $year): ?array {
                $definition = self::HOLIDAY_DEFINITIONS[$holidayKey] ?? null;

                if (!is_array($definition)) {
                    return null;
                }

                return [
                    'name' => $definition['name'],
                    'local_name' => $definition['local_name'],
                    'date' => null,
                    'notes' => sprintf(
                        'إجازة إسلامية افتراضية مرتبطة بدولة %s. حدّث التاريخ الرسمي لسنة %d قبل اعتمادها داخل المدرسة.',
                        (string) $country->name,
                        $year
                    ),
                    'types' => ['Islamic'],
                    'reference_key' => $holidayKey,
                    'holiday_category' => 'islamic',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function supports(Country $country): bool
    {
        return $this->resolveCountryCode($country) !== null;
    }

    private function resolveCountryCode(Country $country): ?string
    {
        $iso2 = strtoupper(trim((string) ($country->iso2_code ?? '')));

        if ($iso2 !== '' && array_key_exists($iso2, self::COUNTRY_PRESETS)) {
            return $iso2;
        }

        $normalizedName = mb_strtolower(trim((string) $country->name));

        return self::COUNTRY_NAME_MAP[$normalizedName] ?? null;
    }
}
