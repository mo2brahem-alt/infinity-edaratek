<?php

namespace App\Services\System;

use App\Models\Country;

class SaudiCountryReferenceSnapshotProvider
{
    /**
     * @return array<string, mixed>|null
     */
    public function resolve(Country $country, int $year): ?array
    {
        if (! $this->supports($country)) {
            return null;
        }

        $publicHolidays = [
            [
                'name' => 'يوم التأسيس',
                'local_name' => 'Founding Day',
                'date' => sprintf('%d-02-22', $year),
                'notes' => 'مرجعية سعودية محلية احتياطية للعطلات الرسمية عند نقص بيانات مزود العطلات العامة.',
                'reference_key' => null,
                'holiday_category' => null,
                'types' => ['Public'],
            ],
            [
                'name' => 'اليوم الوطني',
                'local_name' => 'Saudi National Day',
                'date' => sprintf('%d-09-23', $year),
                'notes' => 'مرجعية سعودية محلية احتياطية للعطلات الرسمية عند نقص بيانات مزود العطلات العامة.',
                'reference_key' => null,
                'holiday_category' => null,
                'types' => ['Public'],
            ],
        ];

        $islamicHolidays = [
            [
                'name' => 'عيد الأضحى',
                'local_name' => 'Eid al-Adha',
                'date' => null,
                'notes' => sprintf('إجازة إسلامية افتراضية مرتبطة بدولة %s. حدّث التاريخ الرسمي لسنة %d قبل اعتمادها داخل المدرسة.', (string) $country->name, $year),
                'reference_key' => 'eid_al_adha',
                'holiday_category' => 'islamic',
                'types' => ['Islamic'],
            ],
            [
                'name' => 'عيد الفطر',
                'local_name' => 'Eid al-Fitr',
                'date' => null,
                'notes' => sprintf('إجازة إسلامية افتراضية مرتبطة بدولة %s. حدّث التاريخ الرسمي لسنة %d قبل اعتمادها داخل المدرسة.', (string) $country->name, $year),
                'reference_key' => 'eid_al_fitr',
                'holiday_category' => 'islamic',
                'types' => ['Islamic'],
            ],
            [
                'name' => 'وقفة عرفة',
                'local_name' => 'Arafat Day',
                'date' => null,
                'notes' => sprintf('إجازة إسلامية افتراضية مرتبطة بدولة %s. حدّث التاريخ الرسمي لسنة %d قبل اعتمادها داخل المدرسة.', (string) $country->name, $year),
                'reference_key' => 'arafah_day',
                'holiday_category' => 'islamic',
                'types' => ['Islamic'],
            ],
        ];

        $holidays = [
            ...$publicHolidays,
            ...$islamicHolidays,
        ];

        return [
            'status' => 'success',
            'year' => $year,
            'country' => [
                'id' => (int) $country->id,
                'name' => (string) $country->name,
                'code' => 'SA',
            ],
            'requested_data' => [
                'public_holidays',
                'academic_year_start',
                'school_breaks',
                'seasonal_breaks',
                'leave_types',
                'islamic_holidays',
            ],
            'supported_data' => [
                'public_holidays',
                'academic_year_start',
                'islamic_holidays',
            ],
            'unavailable_data' => [
                'school_breaks',
                'seasonal_breaks',
                'leave_types',
            ],
            'available_counts' => [
                'public_holidays' => count($publicHolidays),
                'islamic_holidays' => count($islamicHolidays),
                'academic_year_start' => 1,
            ],
            'holidays' => $holidays,
            'academic_year' => [
                'name' => sprintf('العام الدراسي %d-%d', $year - 1, $year),
                'starts_on' => sprintf('%d-08-24', $year - 1),
                'ends_on' => sprintf('%d-06-25', $year),
                'source' => 'saudi_snapshot',
            ],
            'source' => [
                'key' => 'saudi_snapshot',
                'label' => 'Saudi school reference snapshot',
            ],
            'fetched_at' => now()->toISOString(),
            'message' => 'تم استخدام مرجع سعودي محلي احتياطي لتعبئة العطلات الرسمية وبداية العام الدراسي عند غياب بيانات المزود الخارجي. تمت إضافة الإجازات الإسلامية الافتراضية القابلة لتعديل التاريخ لاحقًا بحسب الإعلان الرسمي للدولة.',
        ];
    }

    public function supports(Country $country): bool
    {
        $iso2 = strtoupper(trim((string) ($country->iso2_code ?? '')));

        if ($iso2 === 'SA') {
            return true;
        }

        $normalizedName = mb_strtolower(trim((string) $country->name));

        return in_array($normalizedName, [
            'السعودية',
            'المملكة العربية السعودية',
            'saudi arabia',
            'kingdom of saudi arabia',
        ], true);
    }
}
