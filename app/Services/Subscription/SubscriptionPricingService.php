<?php

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionUserAddon;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionPricingService
{
    private const ACCOUNTING_MONTH_DAYS = 30;

    public function normalizeBillingCycle(?string $billingCycle, ?Plan $plan = null): string
    {
        $cycle = strtoupper(trim((string) ($billingCycle ?: $plan?->billing_cycle ?: Plan::BILLING_MONTHLY)));

        return in_array($cycle, [Plan::BILLING_MONTHLY, Plan::BILLING_YEARLY], true)
            ? $cycle
            : Plan::BILLING_MONTHLY;
    }

    public function priceForPlan(Plan $plan, ?string $billingCycle = null): string
    {
        $cycle = $this->normalizeBillingCycle($billingCycle, $plan);
        $price = $plan->priceForBillingCycle($cycle);

        if ($this->decimalToCents($price) > 0) {
            return $this->normalizeDecimal($price);
        }

        return $this->normalizeDecimal($plan->price ?? 0);
    }

    public function endsAtForBillingCycle(Carbon $startsAt, ?string $billingCycle): Carbon
    {
        return match ($this->normalizeBillingCycle($billingCycle)) {
            Plan::BILLING_YEARLY => $startsAt->copy()->addYear(),
            default => $startsAt->copy()->addMonth(),
        };
    }

    /**
     * @return array{billing_cycle: string, base_price: string, included_users_count: int, extra_user_monthly_price: string}
     */
    public function snapshotForPlan(Plan $plan, ?string $billingCycle = null): array
    {
        $cycle = $this->normalizeBillingCycle($billingCycle, $plan);

        return [
            'billing_cycle' => $cycle,
            'base_price' => $this->priceForPlan($plan, $cycle),
            'included_users_count' => max(0, (int) ($plan->included_users_count ?? 0)),
            'extra_user_monthly_price' => $this->normalizeDecimal($plan->extra_user_monthly_price ?? 0),
        ];
    }

    /**
     * Calculates the upfront registration total for a manager plan without trusting client-side numbers.
     *
     * @return array{billing_cycle: string, base_price: string, included_users_count: int, requested_users_count: int, extra_users_count: int, extra_user_monthly_price: string, billing_months: int, extra_users_amount: string, total_price: string}
     */
    public function initialSubscriptionEstimate(Plan $plan, ?string $billingCycle, int $requestedUsersCount): array
    {
        $snapshot = $this->snapshotForPlan($plan, $billingCycle);
        $includedUsersCount = max(0, (int) $snapshot['included_users_count']);
        $requestedUsersCount = max($includedUsersCount, $requestedUsersCount);
        $extraUsersCount = max(0, $requestedUsersCount - $includedUsersCount);
        $billingMonths = $snapshot['billing_cycle'] === Plan::BILLING_YEARLY ? 12 : 1;

        $baseCents = $this->decimalToCents($snapshot['base_price']);
        $extraUserMonthlyCents = $this->decimalToCents($snapshot['extra_user_monthly_price']);
        $extraUsersAmountCents = $extraUserMonthlyCents * $extraUsersCount * $billingMonths;

        return [
            'billing_cycle' => $snapshot['billing_cycle'],
            'base_price' => $this->centsToDecimal($baseCents),
            'included_users_count' => $includedUsersCount,
            'requested_users_count' => $requestedUsersCount,
            'extra_users_count' => $extraUsersCount,
            'extra_user_monthly_price' => $this->centsToDecimal($extraUserMonthlyCents),
            'billing_months' => $billingMonths,
            'extra_users_amount' => $this->centsToDecimal($extraUsersAmountCents),
            'total_price' => $this->centsToDecimal($baseCents + $extraUsersAmountCents),
        ];
    }

    public function remainingDays(Subscription $subscription, ?Carbon $now = null): int
    {
        if (! $subscription->ends_at) {
            return 0;
        }

        $today = ($now ?: now())->copy()->startOfDay();
        $endsAt = $subscription->ends_at->copy()->startOfDay();

        return max(0, (int) $today->diffInDays($endsAt, false));
    }

    public function dailyExtraSeatPrice(Subscription $subscription): string
    {
        $monthlyCents = $this->decimalToCents($subscription->extra_user_monthly_price ?? 0);
        $dailyCents = $this->divideCents($monthlyCents, self::ACCOUNTING_MONTH_DAYS);

        return $this->centsToDecimal($dailyCents);
    }

    /**
     * @return array{added_seats_count: int, extra_user_monthly_price: string, daily_price: string, remaining_days: int, amount: string, starts_at: Carbon, ends_at: Carbon}
     */
    public function calculateExtraSeats(Subscription $subscription, int $seatsCount, ?Carbon $now = null): array
    {
        $this->ensureSubscriptionCanAddSeats($subscription, $now);

        $seatsCount = max(1, $seatsCount);
        $remainingDays = $this->remainingDays($subscription, $now);
        $monthlyCents = $this->decimalToCents($subscription->extra_user_monthly_price ?? 0);

        if ($monthlyCents <= 0) {
            throw ValidationException::withMessages([
                'subscription' => 'سعر المستخدم الإضافي غير محدد لهذه الباقة. يرجى تحديث إعدادات الباقة قبل إضافة مستخدمين يتجاوزون الحد المضمن.',
            ]);
        }

        $dailyCents = $this->divideCents($monthlyCents, self::ACCOUNTING_MONTH_DAYS);
        $amountCents = $dailyCents * $remainingDays * $seatsCount;

        return [
            'added_seats_count' => $seatsCount,
            'extra_user_monthly_price' => $this->centsToDecimal($monthlyCents),
            'daily_price' => $this->centsToDecimal($dailyCents),
            'remaining_days' => $remainingDays,
            'amount' => $this->centsToDecimal($amountCents),
            'starts_at' => ($now ?: now())->copy(),
            'ends_at' => $subscription->ends_at->copy(),
        ];
    }

    public function activeExtraSeatsForSchool(int $schoolId, ?Carbon $now = null): int
    {
        $today = ($now ?: now())->copy()->startOfDay();

        return (int) SubscriptionUserAddon::query()
            ->where('school_id', $schoolId)
            ->where('status', SubscriptionUserAddon::STATUS_ACTIVE)
            ->where('ends_at', '>', $today)
            ->sum('added_seats_count');
    }

    public function allowedSeatsForSchool(Subscription $subscription, int $schoolId, ?Carbon $now = null): int
    {
        return max(0, (int) ($subscription->included_users_count ?? 0))
            + $this->activeExtraSeatsForSchool($schoolId, $now);
    }

    /**
     * Creates an active extra-seat record only for the overflow beyond included and already-purchased seats.
     */
    public function reserveSeatsForSchoolStaff(User $manager, int $schoolId, int $requestedSeats = 1): ?SubscriptionUserAddon
    {
        return DB::transaction(function () use ($manager, $schoolId, $requestedSeats): ?SubscriptionUserAddon {
            $subscription = $this->activeSubscriptionForManager($manager, $schoolId, true);

            if (! $subscription) {
                throw ValidationException::withMessages([
                    'subscription' => 'لا يوجد اشتراك نشط مرتبط بهذه المدرسة. لا يمكن إضافة مستخدمين قبل تفعيل الاشتراك.',
                ]);
            }

            $this->ensureSubscriptionBelongsToSchool($subscription, $schoolId);
            $this->ensureSubscriptionCanAddSeats($subscription);

            $currentUsersCount = $this->currentSchoolStaffUsersCount($schoolId);
            $includedUsersCount = max(0, (int) ($subscription->included_users_count ?? 0));
            $activeExtraSeats = $this->activeExtraSeatsForSchool($schoolId);
            $allowedSeats = $includedUsersCount + $activeExtraSeats;
            $requestedSeats = max(1, $requestedSeats);

            if (($currentUsersCount + $requestedSeats) <= $allowedSeats) {
                return null;
            }

            $seatsToPurchase = ($currentUsersCount + $requestedSeats) - $allowedSeats;
            $calculation = $this->calculateExtraSeats($subscription, $seatsToPurchase);

            return SubscriptionUserAddon::query()->create([
                'subscription_id' => $subscription->id,
                'school_id' => $schoolId,
                'added_seats_count' => $calculation['added_seats_count'],
                'extra_user_monthly_price' => $calculation['extra_user_monthly_price'],
                'daily_price' => $calculation['daily_price'],
                'remaining_days' => $calculation['remaining_days'],
                'amount' => $calculation['amount'],
                'starts_at' => $calculation['starts_at'],
                'ends_at' => $subscription->ends_at,
                'status' => SubscriptionUserAddon::STATUS_ACTIVE,
                'created_by' => $manager->id,
            ]);
        });
    }

    public function currentSchoolStaffUsersCount(int $schoolId): int
    {
        return User::query()
            ->where('school_id', $schoolId)
            ->where(function ($query): void {
                $query->where('role', 'staff')
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'staff'));
            })
            ->count();
    }

    public function activeSubscriptionForManager(User $manager, int $schoolId, bool $lock = false): ?Subscription
    {
        $query = $manager->subscriptions()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where(function ($scope) use ($schoolId): void {
                $scope->where('school_id', $schoolId)
                    ->orWhereNull('school_id');
            })
            ->latest('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function ensureSubscriptionBelongsToSchool(Subscription $subscription, int $schoolId): void
    {
        if ($subscription->school_id === null) {
            $subscription->update(['school_id' => $schoolId]);

            return;
        }

        if ((int) $subscription->school_id !== $schoolId) {
            throw ValidationException::withMessages([
                'subscription' => 'هذا الاشتراك لا يتبع المدرسة الحالية.',
            ]);
        }
    }

    public function ensureSubscriptionCanAddSeats(Subscription $subscription, ?Carbon $now = null): void
    {
        if ($subscription->status !== Subscription::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'subscription' => 'لا يمكن إضافة مقاعد لأن الاشتراك غير نشط.',
            ]);
        }

        if ($this->remainingDays($subscription, $now) <= 0) {
            throw ValidationException::withMessages([
                'subscription' => 'لا يمكن إضافة مقاعد لأن الاشتراك منتهي أو لا يحتوي على تاريخ نهاية صالح.',
            ]);
        }
    }

    public function syncSchoolContextForUser(User $user, int $schoolId): void
    {
        $user->subscriptions()
            ->whereNull('school_id')
            ->whereIn('status', [Subscription::STATUS_PENDING, Subscription::STATUS_ACTIVE, Subscription::STATUS_FROZEN])
            ->update(['school_id' => $schoolId]);
    }

    public function formatAddonSummary(?SubscriptionUserAddon $addon): ?string
    {
        if (! $addon) {
            return null;
        }

        return sprintf(
            'تم تسجيل %d مقعد إضافي حتى %s. سعر اليوم %s ريال، والإجمالي %s ريال.',
            (int) $addon->added_seats_count,
            $addon->ends_at?->format('Y-m-d') ?? '-',
            $addon->daily_price,
            $addon->amount
        );
    }

    private function normalizeDecimal(mixed $value): string
    {
        return $this->centsToDecimal($this->decimalToCents($value));
    }

    private function decimalToCents(mixed $value): int
    {
        $normalized = preg_replace('/[^0-9.\-]/', '', trim((string) $value));

        if ($normalized === '' || $normalized === '-' || $normalized === '.') {
            return 0;
        }

        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');

        if (! preg_match('/^\d+(\.\d+)?$/', $normalized)) {
            return 0;
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    private function centsToDecimal(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        $whole = intdiv($absolute, 100);
        $fraction = $absolute % 100;

        return ($negative ? '-' : '').$whole.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    private function divideCents(int $cents, int $divisor): int
    {
        if ($divisor <= 0) {
            return 0;
        }

        $negative = $cents < 0;
        $absolute = abs($cents);
        $result = intdiv($absolute + intdiv($divisor, 2), $divisor);

        return $negative ? -$result : $result;
    }
}
