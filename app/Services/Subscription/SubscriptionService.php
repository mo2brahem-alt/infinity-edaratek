<?php

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Support\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionPricingService $pricingService,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function createForUser(
        User $user,
        Plan $plan,
        bool $activateNow = true,
        ?array $meta = null,
        ?string $billingCycle = null,
        ?int $schoolId = null
    ): Subscription
    {
        $snapshot = $this->pricingService->snapshotForPlan($plan, $billingCycle);
        $status = $activateNow ? Subscription::STATUS_ACTIVE : Subscription::STATUS_PENDING;
        $startsAt = $activateNow ? now() : null;
        $endsAt = $activateNow ? $this->pricingService->endsAtForBillingCycle($startsAt, $snapshot['billing_cycle']) : null;

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'school_id' => $schoolId ?? $user->school_id,
            'status' => $status,
            'billing_cycle' => $snapshot['billing_cycle'],
            'base_price' => $snapshot['base_price'],
            'included_users_count' => $snapshot['included_users_count'],
            'extra_user_monthly_price' => $snapshot['extra_user_monthly_price'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'meta' => $meta,
        ]);

        $this->notifySubscriptionCreated($subscription->refresh(), $user, $plan, $meta);

        return $subscription;
    }

    public function activate(Subscription $subscription, ?int $actorId = null, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $actorId, $reason): Subscription {
            $plan = $subscription->plan;
            $startsAt = $subscription->starts_at;
            $endsAt = $subscription->ends_at;

            $shouldResetDates = !$startsAt
                || !$endsAt
                || $endsAt->isPast()
                || in_array($subscription->status, [Subscription::STATUS_CANCELED, Subscription::STATUS_EXPIRED], true);

            if ($shouldResetDates) {
                $startsAt = now();
                $billingCycle = $subscription->billing_cycle
                    ?: ($plan ? $this->pricingService->normalizeBillingCycle(null, $plan) : Plan::BILLING_MONTHLY);
                $endsAt = $this->pricingService->endsAtForBillingCycle($startsAt, $billingCycle);
            }

            $snapshot = $plan
                ? $this->pricingService->snapshotForPlan($plan, $subscription->billing_cycle)
                : [
                    'billing_cycle' => $subscription->billing_cycle ?: Plan::BILLING_MONTHLY,
                    'base_price' => $subscription->base_price ?? 0,
                    'included_users_count' => $subscription->included_users_count ?? 0,
                    'extra_user_monthly_price' => $subscription->extra_user_monthly_price ?? 0,
                ];

            $meta = (array) ($subscription->meta ?? []);
            unset($meta['frozen_at']);
            $meta['activated_at'] = now()->toIso8601String();
            $meta['activated_by'] = $actorId;
            if ($reason) {
                $meta['activated_reason'] = $reason;
            }

            $subscription->update([
                'status' => Subscription::STATUS_ACTIVE,
                'billing_cycle' => $subscription->billing_cycle ?: $snapshot['billing_cycle'],
                'base_price' => $subscription->base_price ?: $snapshot['base_price'],
                'included_users_count' => $subscription->included_users_count ?: $snapshot['included_users_count'],
                'extra_user_monthly_price' => $subscription->extra_user_monthly_price ?: $snapshot['extra_user_monthly_price'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'meta' => $meta,
            ]);

            return $subscription->refresh();
        });
    }

    public function freeze(Subscription $subscription, ?int $actorId = null, ?string $reason = null): Subscription
    {
        if (in_array($subscription->status, [Subscription::STATUS_CANCELED, Subscription::STATUS_EXPIRED], true)) {
            throw ValidationException::withMessages([
                'subscription' => 'لا يمكن تجميد اشتراك ملغي أو منتهي.',
            ]);
        }

        $meta = (array) ($subscription->meta ?? []);
        $meta['frozen_at'] = now()->toIso8601String();
        $meta['frozen_by'] = $actorId;
        if ($reason) {
            $meta['frozen_reason'] = $reason;
        }

        $subscription->update([
            'status' => Subscription::STATUS_FROZEN,
            'meta' => $meta,
        ]);

        return $subscription->refresh();
    }

    public function cancel(Subscription $subscription, ?int $actorId = null, ?string $reason = null): Subscription
    {
        $meta = (array) ($subscription->meta ?? []);
        $meta['canceled_at'] = now()->toIso8601String();
        $meta['canceled_by'] = $actorId;
        if ($reason) {
            $meta['canceled_reason'] = $reason;
        }

        $subscription->update([
            'status' => Subscription::STATUS_CANCELED,
            'meta' => $meta,
        ]);

        return $subscription->refresh();
    }

    public function delete(Subscription $subscription, ?int $actorId = null, ?string $reason = null): Subscription
    {
        if (in_array($subscription->status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_PENDING], true)) {
            throw ValidationException::withMessages([
                'subscription' => 'لا يمكن حذف اشتراك نشط أو معلق. قم بتجميده أو إلغائه أولاً.',
            ]);
        }

        $meta = (array) ($subscription->meta ?? []);
        $meta['deleted_at'] = now()->toIso8601String();
        $meta['deleted_by'] = $actorId;
        if ($reason) {
            $meta['deleted_reason'] = $reason;
        }

        $subscription->update([
            'meta' => $meta,
        ]);

        $subscription->delete();

        return $subscription;
    }

    public function syncSchoolContextForUser(User $user, int $schoolId): void
    {
        $this->pricingService->syncSchoolContextForUser($user, $schoolId);
    }

    /**
     * @param array<string, mixed>|null $meta
     */
    private function notifySubscriptionCreated(Subscription $subscription, User $user, Plan $plan, ?array $meta): void
    {
        try {
            $roleLabel = $user->hasSystemRole('school_manager') ? 'مدير مدرسة' : ($user->hasSystemRole('supervisor') ? 'مشرف' : 'مستخدم');
            $source = is_array($meta) ? ($meta['source'] ?? null) : null;

            $payload = [
                'subscription_id' => (int) $subscription->id,
                'plan_id' => (int) $plan->id,
                'plan_name' => (string) $plan->name,
                'role' => $user->primaryRole(),
                'source' => $source,
                'status' => (string) $subscription->status,
            ];

            $this->notificationService->notifyUser(
                (int) $user->id,
                'subscription.created',
                'تم تسجيل طلب الاشتراك',
                'تم تسجيل اشتراكك في باقة ' . $plan->name . ' وسيتم إشعارك عند اكتمال المراجعة.',
                $payload
            );

            $this->notificationService->notifySuperAdmins(
                'subscription.created.admin',
                'اشتراك جديد يحتاج مراجعة',
                'تم تسجيل اشتراك جديد لحساب ' . $roleLabel . ' باسم ' . $user->name . ' على باقة ' . $plan->name . '.',
                $payload + ['user_id' => (int) $user->id]
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
