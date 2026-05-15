<?php

namespace App\Services\School;

use App\Models\SchoolAcademicYear;
use App\Models\SchoolCalendarSetting;
use App\Models\SchoolHoliday;
use App\Models\SchoolTerm;
use App\Services\Integrity\IntegrityImpactService;
use App\Services\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SchoolCalendarService
{
    private const DEFAULT_WEEK_START_DAY = SchoolCalendarSetting::SUNDAY;
    private const DEFAULT_WEEKLY_OFF_DAYS = [];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly IntegrityImpactService $integrityImpactService,
    ) {
    }

    public function getOrCreateSettings(int $schoolId, ?int $actorId = null): SchoolCalendarSetting
    {
        $settings = SchoolCalendarSetting::query()
            ->where('school_id', $schoolId)
            ->first();

        if ($settings) {
            return $settings;
        }

        return SchoolCalendarSetting::query()->create([
            'school_id' => $schoolId,
            'week_start_day' => self::DEFAULT_WEEK_START_DAY,
            'weekly_off_days' => self::DEFAULT_WEEKLY_OFF_DAYS,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateSettings(int $schoolId, int $actorId, array $payload, ?Request $request = null): SchoolCalendarSetting
    {
        return DB::transaction(function () use ($schoolId, $actorId, $payload, $request): SchoolCalendarSetting {
            $settings = $this->getOrCreateSettings($schoolId, $actorId);

            $weekStartDay = array_key_exists('week_start_day', $payload)
                ? (int) $payload['week_start_day']
                : (int) $settings->week_start_day;

            if ($weekStartDay < 0 || $weekStartDay > 6) {
                throw ValidationException::withMessages([
                    'week_start_day' => 'Week start day must be between 0 and 6.',
                ]);
            }

            $weeklyOffDays = array_key_exists('weekly_off_days', $payload)
                ? $this->normalizeWeeklyOffDays($payload['weekly_off_days'])
                : $this->normalizeWeeklyOffDays($settings->weekly_off_days);

            $settings->update([
                'week_start_day' => $weekStartDay,
                'weekly_off_days' => $weeklyOffDays,
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->log(
                'school_calendar.updated',
                'school_calendar_setting',
                (int) $settings->id,
                [
                    'school_id' => $schoolId,
                    'week_start_day' => $weekStartDay,
                    'weekly_off_days' => $weeklyOffDays,
                ],
                $request,
                $actorId
            );

            return $settings->fresh();
        });
    }

    /**
     * @param array<string, mixed> $filters
     * @return Collection<int, SchoolHoliday>
     */
    public function listHolidays(int $schoolId, array $filters = []): Collection
    {
        $query = SchoolHoliday::query()
            ->where('school_id', $schoolId)
            ->orderBy('start_date')
            ->orderBy('id');

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));

        if ($from !== '' && $to !== '') {
            $query
                ->whereDate('start_date', '<=', $to)
                ->whereDate('end_date', '>=', $from);
        } elseif ($from !== '') {
            $query->whereDate('end_date', '>=', $from);
        } elseif ($to !== '') {
            $query->whereDate('start_date', '<=', $to);
        }

        return $query->get();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createHoliday(int $schoolId, int $actorId, array $payload, ?Request $request = null): SchoolHoliday
    {
        return DB::transaction(function () use ($schoolId, $actorId, $payload, $request): SchoolHoliday {
            [$startDate, $endDate, $returnDate] = $this->resolveHolidayDates($schoolId, $payload);
            $this->ensureHolidayWithinAcademicYear($schoolId, $startDate, $endDate);
            $this->ensureNoHolidayOverlap($schoolId, $startDate, $endDate);

            $holiday = SchoolHoliday::query()->create([
                'school_id' => $schoolId,
                'name' => trim((string) $payload['name']),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'return_date' => $returnDate,
                'notes' => $this->cleanText($payload['notes'] ?? null),
                'is_active' => (bool) ($payload['is_active'] ?? true),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->log(
                'school_holiday.created',
                'school_holiday',
                (int) $holiday->id,
                [
                    'school_id' => $schoolId,
                    'name' => $holiday->name,
                    'start_date' => $holiday->start_date?->toDateString(),
                    'end_date' => $holiday->end_date?->toDateString(),
                    'return_date' => $holiday->return_date?->toDateString(),
                    'is_active' => (bool) $holiday->is_active,
                ],
                $request,
                $actorId
            );

            return $holiday;
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateHoliday(
        SchoolHoliday $holiday,
        int $schoolId,
        int $actorId,
        array $payload,
        bool $confirmedImpact = false,
        ?Request $request = null
    ): SchoolHoliday {
        $this->ensureHolidayBelongsToSchool($holiday, $schoolId);

        $impact = $this->integrityImpactService->checkUpdateImpact(
            'school_holiday',
            (int) $holiday->id,
            $payload,
            $schoolId
        );
        if (($impact['requires_confirmation'] ?? false) && !$confirmedImpact) {
            throw ValidationException::withMessages([
                'confirm_impact' => (string) ($impact['message'] ?? 'تأكيد العملية مطلوب بسبب وجود بيانات مرتبطة.'),
            ]);
        }

        return DB::transaction(function () use ($holiday, $schoolId, $actorId, $payload, $request, $impact): SchoolHoliday {
            [$startDate, $endDate, $returnDate] = $this->resolveHolidayDates($schoolId, $payload);
            $this->ensureHolidayWithinAcademicYear($schoolId, $startDate, $endDate);
            $this->ensureNoHolidayOverlap($schoolId, $startDate, $endDate, (int) $holiday->id);

            $holiday->update([
                'name' => trim((string) $payload['name']),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'return_date' => $returnDate,
                'notes' => $this->cleanText($payload['notes'] ?? null),
                'is_active' => array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : (bool) $holiday->is_active,
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->log(
                'school_holiday.updated',
                'school_holiday',
                (int) $holiday->id,
                [
                    'school_id' => $schoolId,
                    'name' => $holiday->name,
                    'start_date' => $holiday->start_date?->toDateString(),
                    'end_date' => $holiday->end_date?->toDateString(),
                    'return_date' => $holiday->return_date?->toDateString(),
                    'is_active' => (bool) $holiday->is_active,
                    'impact' => $this->auditImpact($impact),
                ],
                $request,
                $actorId
            );

            return $holiday->fresh();
        });
    }

    public function disableHoliday(SchoolHoliday $holiday, int $schoolId, int $actorId, ?Request $request = null): SchoolHoliday
    {
        $this->ensureHolidayBelongsToSchool($holiday, $schoolId);

        $impact = $this->integrityImpactService->checkDeleteImpact(
            'school_holiday',
            (int) $holiday->id,
            $schoolId
        );
        $confirmedImpact = (bool) ($request?->boolean('confirm_impact') ?? false);
        if (($impact['requires_confirmation'] ?? false) && !$confirmedImpact) {
            throw ValidationException::withMessages([
                'confirm_impact' => (string) ($impact['message'] ?? 'تأكيد العملية مطلوب بسبب وجود بيانات مرتبطة.'),
            ]);
        }

        if (!(bool) $holiday->is_active) {
            return $holiday;
        }

        $holiday->update([
            'is_active' => false,
            'updated_by' => $actorId,
        ]);

        $this->auditLogger->log(
            'school_holiday.disabled',
            'school_holiday',
            (int) $holiday->id,
            [
                'school_id' => $schoolId,
                'impact' => $this->auditImpact($impact),
            ],
            $request,
            $actorId
        );

        return $holiday->fresh();
    }

    /**
     * @return array{day_type:string,holiday_name:?string}
     */
    public function resolveDayTypeForDate(int $schoolId, string $date): array
    {
        $holiday = SchoolHoliday::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderBy('start_date')
            ->orderBy('id')
            ->first();

        if ($holiday) {
            return [
                'day_type' => 'HOLIDAY',
                'holiday_name' => $holiday->name,
            ];
        }

        $settings = $this->getOrCreateSettings($schoolId);
        $weeklyOffDays = $this->normalizeWeeklyOffDays($settings->weekly_off_days);
        $dayOfWeek = Carbon::parse($date)->dayOfWeek;

        if (in_array($dayOfWeek, $weeklyOffDays, true)) {
            return [
                'day_type' => 'WEEKLY_OFF',
                'holiday_name' => null,
            ];
        }

        return [
            'day_type' => 'SCHOOL_DAY',
            'holiday_name' => null,
        ];
    }

    public function hasAcademicYears(int $schoolId): bool
    {
        return SchoolAcademicYear::query()->where('school_id', $schoolId)->exists();
    }

    public function hasAcademicTerms(int $schoolId): bool
    {
        return SchoolTerm::query()->where('school_id', $schoolId)->exists();
    }

    public function hasAcademicDateReferences(int $schoolId): bool
    {
        return $this->hasAcademicYears($schoolId) || $this->hasAcademicTerms($schoolId);
    }

    /**
     * @return array{
     *   school_id:int,
     *   date:string,
     *   academic_year:?SchoolAcademicYear,
     *   term:?SchoolTerm
     * }
     */
    public function resolveOperationalAcademicContextForDate(int $schoolId, string $date): array
    {
        $normalizedDate = Carbon::parse($date)->toDateString();

        $academicYear = SchoolAcademicYear::query()
            ->where('school_id', $schoolId)
            ->whereDate('starts_on', '<=', $normalizedDate)
            ->whereDate('ends_on', '>=', $normalizedDate)
            ->orderByDesc('is_active')
            ->orderBy('starts_on')
            ->orderBy('id')
            ->first();

        $term = SchoolTerm::query()
            ->where('school_id', $schoolId)
            ->whereDate('start_date', '<=', $normalizedDate)
            ->whereDate('end_date', '>=', $normalizedDate)
            ->orderByDesc('is_active')
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->sortByDesc(function (SchoolTerm $candidate) use ($academicYear): int {
                if (!$academicYear) {
                    return 0;
                }

                return (int) ((int) $candidate->school_academic_year_id === (int) $academicYear->id);
            })
            ->values()
            ->first();

        return [
            'school_id' => $schoolId,
            'date' => $normalizedDate,
            'academic_year' => $academicYear,
            'term' => $term instanceof SchoolTerm ? $term : null,
        ];
    }

    /**
     * @return array{
     *   school_id:int,
     *   date:string,
     *   academic_year:SchoolAcademicYear,
     *   term:SchoolTerm
     * }
     */
    public function ensureDateWithinOperationalAcademicTerm(
        int $schoolId,
        string $date,
        string $field = 'attendance_date'
    ): array {
        $context = $this->resolveOperationalAcademicContextForDate($schoolId, $date);
        $hasAcademicYears = $this->hasAcademicYears($schoolId);
        $hasAcademicTerms = $this->hasAcademicTerms($schoolId);

        if ($hasAcademicYears && !($context['academic_year'] instanceof SchoolAcademicYear)) {
            throw ValidationException::withMessages([
                $field => 'التاريخ المحدد يقع خارج أي عام دراسي مسجل لهذه المدرسة.',
            ]);
        }

        if ($hasAcademicTerms && !($context['term'] instanceof SchoolTerm)) {
            throw ValidationException::withMessages([
                $field => 'التاريخ المحدد يقع خارج نطاق أي ترم دراسي يغطي هذا اليوم داخل المدرسة.',
            ]);
        }

        return $context;
    }

    public function ensureAcademicYearRangeIntegrity(
        int $schoolId,
        string $startsOn,
        string $endsOn,
        ?int $ignoreAcademicYearId = null
    ): void {
        $overlapQuery = SchoolAcademicYear::query()
            ->where('school_id', $schoolId)
            ->whereDate('starts_on', '<=', $endsOn)
            ->whereDate('ends_on', '>=', $startsOn);

        if ($ignoreAcademicYearId !== null) {
            $overlapQuery->whereKeyNot($ignoreAcademicYearId);
        }

        if ($overlapQuery->exists()) {
            throw ValidationException::withMessages([
                'starts_on' => 'لا يمكن حفظ العام الدراسي لأن نطاقه الزمني يتداخل مع عام دراسي آخر داخل نفس المدرسة.',
            ]);
        }
    }

    public function ensureTermRangeIntegrity(
        int $schoolId,
        ?int $academicYearId,
        string $startDate,
        string $endDate,
        ?int $ignoreTermId = null
    ): void {
        if ($academicYearId !== null) {
            $academicYear = SchoolAcademicYear::query()
                ->where('school_id', $schoolId)
                ->whereKey($academicYearId)
                ->first();

            if (!$academicYear) {
                throw ValidationException::withMessages([
                    'school_academic_year_id' => 'العام الدراسي المحدد غير صالح ضمن نفس المدرسة.',
                ]);
            }

            $yearStartsOn = $academicYear->starts_on?->toDateString();
            $yearEndsOn = $academicYear->ends_on?->toDateString();

            if (
                $yearStartsOn === null
                || $yearEndsOn === null
                || $startDate < $yearStartsOn
                || $endDate > $yearEndsOn
            ) {
                throw ValidationException::withMessages([
                    'start_date' => 'يجب أن يقع الترم بالكامل داخل نطاق العام الدراسي المرتبط به.',
                ]);
            }
        }

        $overlapQuery = SchoolTerm::query()
            ->where('school_id', $schoolId)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate);

        if ($ignoreTermId !== null) {
            $overlapQuery->whereKeyNot($ignoreTermId);
        }

        if ($overlapQuery->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'لا يمكن حفظ الترم لأن نطاقه الزمني يتداخل مع ترم آخر داخل نفس المدرسة.',
            ]);
        }
    }

    /**
     * @param mixed $value
     * @return array<int, int>
     */
    public function normalizeWeeklyOffDays(mixed $value): array
    {
        $days = collect(is_array($value) ? $value : [])
            ->map(fn ($day): int => (int) $day)
            ->filter(fn (int $day): bool => $day >= 0 && $day <= 6)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return count($days) > 0 ? $days : self::DEFAULT_WEEKLY_OFF_DAYS;
    }

    private function ensureNoHolidayOverlap(int $schoolId, string $startDate, string $endDate, ?int $ignoreHolidayId = null): void
    {
        $query = SchoolHoliday::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate);

        if ($ignoreHolidayId !== null) {
            $query->whereKeyNot($ignoreHolidayId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'There is already an active holiday that overlaps this period.',
            ]);
        }
    }

    private function ensureHolidayWithinAcademicYear(int $schoolId, string $startDate, string $endDate): void
    {
        $coveringYear = SchoolAcademicYear::query()
            ->where('school_id', $schoolId)
            ->whereDate('starts_on', '<=', $startDate)
            ->whereDate('ends_on', '>=', $endDate)
            ->orderByDesc('is_active')
            ->orderByDesc('starts_on')
            ->first();

        if ($coveringYear) {
            return;
        }

        $startDateYear = SchoolAcademicYear::query()
            ->where('school_id', $schoolId)
            ->whereDate('starts_on', '<=', $startDate)
            ->whereDate('ends_on', '>=', $startDate)
            ->orderByDesc('is_active')
            ->orderByDesc('starts_on')
            ->first();

        if ($startDateYear) {
            throw ValidationException::withMessages([
                'end_date' => 'يجب أن تنتهي العطلة داخل نفس العام الدراسي المعتمد لهذه المدرسة.',
            ]);
        }

        $activeYear = SchoolAcademicYear::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderByDesc('starts_on')
            ->first();

        if ($activeYear) {
            $startsOn = $activeYear->starts_on?->toDateString() ?? '';
            $endsOn = $activeYear->ends_on?->toDateString() ?? '';

            throw ValidationException::withMessages([
                'start_date' => "يجب أن تكون العطلة ضمن عام دراسي معتمد لنفس المدرسة. العام النشط الحالي يمتد من {$startsOn} إلى {$endsOn}.",
            ]);
        }

        throw ValidationException::withMessages([
            'start_date' => 'يجب إضافة عام دراسي معتمد لهذه المدرسة قبل تسجيل العطلات.',
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0:string,1:string,2:string}
     */
    private function resolveHolidayDates(int $schoolId, array $payload): array
    {
        $startDate = Carbon::parse((string) $payload['start_date'])->toDateString();
        $weeklyOffDays = $this->resolveWeeklyOffDaysForSchool($schoolId);

        $daysCount = (int) ($payload['days_count'] ?? 0);
        if (!empty($payload['end_date'])) {
            $endDate = Carbon::parse((string) $payload['end_date'])->toDateString();
            if ($endDate < $startDate) {
                throw ValidationException::withMessages([
                    'end_date' => 'End date must be on or after start date.',
                ]);
            }
        } elseif ($daysCount > 0) {
            $endDate = $this->calculateEndDateFromDaysCount($startDate, $daysCount, $weeklyOffDays);
        } else {
            throw ValidationException::withMessages([
                'days_count' => 'Either days_count or end_date is required.',
            ]);
        }

        $returnDate = !empty($payload['return_date'])
            ? Carbon::parse((string) $payload['return_date'])->toDateString()
            : $this->calculateReturnDate($endDate, $weeklyOffDays);

        if ($returnDate <= $endDate) {
            throw ValidationException::withMessages([
                'return_date' => 'Return date must be after end date.',
            ]);
        }

        return [$startDate, $endDate, $returnDate];
    }

    /**
     * @return array<int, int>
     */
    private function resolveWeeklyOffDaysForSchool(int $schoolId): array
    {
        $settings = $this->getOrCreateSettings($schoolId);
        $weeklyOffDays = $this->normalizeWeeklyOffDays($settings->weekly_off_days);

        if (count($weeklyOffDays) >= 7) {
            throw ValidationException::withMessages([
                'weekly_off_days' => 'Weekly off days cannot include every day of the week.',
            ]);
        }

        return $weeklyOffDays;
    }

    /**
     * @param array<int, int> $weeklyOffDays
     */
    private function calculateEndDateFromDaysCount(string $startDate, int $daysCount, array $weeklyOffDays): string
    {
        $cursor = Carbon::parse($startDate)->startOfDay();
        $countedDays = 0;
        $iterations = 0;

        while ($countedDays < $daysCount) {
            if (!$this->isWeeklyOffDay($cursor, $weeklyOffDays)) {
                $countedDays++;
                if ($countedDays >= $daysCount) {
                    break;
                }
            }

            $cursor->addDay();
            $iterations++;

            if ($iterations > 3700) {
                throw ValidationException::withMessages([
                    'days_count' => 'Unable to calculate holiday dates with current weekly off settings.',
                ]);
            }
        }

        return $cursor->toDateString();
    }

    /**
     * @param array<int, int> $weeklyOffDays
     */
    private function calculateReturnDate(string $endDate, array $weeklyOffDays): string
    {
        $cursor = Carbon::parse($endDate)->addDay()->startOfDay();
        $iterations = 0;

        while ($this->isWeeklyOffDay($cursor, $weeklyOffDays)) {
            $cursor->addDay();
            $iterations++;

            if ($iterations > 7) {
                throw ValidationException::withMessages([
                    'return_date' => 'Unable to calculate return date with current weekly off settings.',
                ]);
            }
        }

        return $cursor->toDateString();
    }

    /**
     * @param array<int, int> $weeklyOffDays
     */
    private function isWeeklyOffDay(Carbon $date, array $weeklyOffDays): bool
    {
        return in_array($date->dayOfWeek, $weeklyOffDays, true);
    }

    private function ensureHolidayBelongsToSchool(SchoolHoliday $holiday, int $schoolId): void
    {
        if ((int) $holiday->school_id !== $schoolId) {
            abort(403, 'You are not allowed to access this holiday.');
        }
    }

    private function cleanText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }

    /**
     * @param array<string, mixed> $impact
     * @return array<string, mixed>
     */
    private function auditImpact(array $impact): array
    {
        return [
            'severity' => (string) ($impact['severity'] ?? ''),
            'message_code' => (string) ($impact['message_code'] ?? ''),
            'affected' => $impact['affected'] ?? [],
            'requires_confirmation' => (bool) ($impact['requires_confirmation'] ?? false),
        ];
    }
}
