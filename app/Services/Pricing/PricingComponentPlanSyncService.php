<?php

namespace App\Services\Pricing;

use App\Models\Plan;

class PricingComponentPlanSyncService
{
    public function syncContent(string $content): string
    {
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return $content;
        }

        if (($decoded['type'] ?? null) !== 'pricing') {
            return $content;
        }

        if (! isset($decoded['plans']) || ! is_array($decoded['plans'])) {
            return $content;
        }

        $decoded['plans'] = array_values(array_map(
            fn ($plan) => $this->syncPlanDefinition($plan),
            $decoded['plans']
        ));

        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE);

        return $encoded === false ? $content : $encoded;
    }

    private function syncPlanDefinition(mixed $plan): array
    {
        $plan = is_array($plan) ? $plan : [];

        $roleType = Plan::normalizeRoleType((string) ($plan['role_type'] ?? $plan['roleType'] ?? Plan::ROLE_SUPERVISOR))
            ?? Plan::ROLE_SUPERVISOR;

        if (! in_array($roleType, [Plan::ROLE_SUPERVISOR, Plan::ROLE_SCHOOL_MANAGER], true)) {
            $roleType = Plan::ROLE_SUPERVISOR;
        }

        $providedPlanId = (int) ($plan['plan_id'] ?? $plan['planId'] ?? 0);

        $name = trim((string) ($plan['name'] ?? ''));
        if ($name === '') {
            $name = $roleType === Plan::ROLE_SCHOOL_MANAGER ? 'School Manager Plan' : 'Supervisor Plan';
        }

        $price = $this->normalizePrice($plan['price'] ?? null);
        $hasMonthlyPrice = array_key_exists('monthly_price', $plan) || array_key_exists('monthlyPrice', $plan);
        $hasYearlyPrice = array_key_exists('yearly_price', $plan) || array_key_exists('yearlyPrice', $plan);
        $hasIncludedUsersCount = array_key_exists('included_users_count', $plan) || array_key_exists('includedUsersCount', $plan);
        $hasExtraUserMonthlyPrice = array_key_exists('extra_user_monthly_price', $plan) || array_key_exists('extraUserMonthlyPrice', $plan);
        $monthlyPrice = $this->normalizePrice($plan['monthly_price'] ?? $plan['monthlyPrice'] ?? null);
        $yearlyPrice = $this->normalizePrice($plan['yearly_price'] ?? $plan['yearlyPrice'] ?? null);
        $includedUsersCount = max(0, (int) ($plan['included_users_count'] ?? $plan['includedUsersCount'] ?? ($roleType === Plan::ROLE_SCHOOL_MANAGER ? 10 : 0)));
        $extraUserMonthlyPrice = $this->normalizePrice($plan['extra_user_monthly_price'] ?? $plan['extraUserMonthlyPrice'] ?? null);

        $billingCycle = strtoupper(trim((string) ($plan['billing_cycle'] ?? $plan['billingCycle'] ?? Plan::BILLING_MONTHLY)));
        if (! in_array($billingCycle, [Plan::BILLING_MONTHLY, Plan::BILLING_YEARLY], true)) {
            $billingCycle = Plan::BILLING_MONTHLY;
        }

        if ($monthlyPrice <= 0 && $billingCycle === Plan::BILLING_MONTHLY) {
            $monthlyPrice = $price;
        }

        if ($yearlyPrice <= 0 && $billingCycle === Plan::BILLING_YEARLY) {
            $yearlyPrice = $price;
        }

        if ($yearlyPrice <= 0 && $monthlyPrice > 0) {
            $yearlyPrice = $monthlyPrice * 12;
        }

        if ($price <= 0) {
            $price = $monthlyPrice > 0 ? $monthlyPrice : $yearlyPrice;
        }

        $matchedPlan = null;

        if ($providedPlanId > 0) {
            $matchedPlan = Plan::query()
                ->whereKey($providedPlanId)
                ->whereIn('role_type', Plan::roleAliases($roleType))
                ->first();
        }

        if (! $matchedPlan) {
            $matchedPlan = Plan::query()
                ->whereIn('role_type', Plan::roleAliases($roleType))
                ->where('name', $name)
                ->where('price', $price)
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->first();
        }

        if (! $matchedPlan) {
            $matchedPlan = Plan::query()
                ->whereIn('role_type', Plan::roleAliases($roleType))
                ->where('name', $name)
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->first();
        }

        if ($matchedPlan) {
            if (! $hasMonthlyPrice && (float) ($matchedPlan->monthly_price ?? 0) > 0) {
                $monthlyPrice = (float) $matchedPlan->monthly_price;
            }

            if (! $hasYearlyPrice && (float) ($matchedPlan->yearly_price ?? 0) > 0) {
                $yearlyPrice = (float) $matchedPlan->yearly_price;
            }

            if (! $hasIncludedUsersCount) {
                $includedUsersCount = (int) ($matchedPlan->included_users_count ?? $includedUsersCount);
            }

            if (! $hasExtraUserMonthlyPrice) {
                $extraUserMonthlyPrice = (float) ($matchedPlan->extra_user_monthly_price ?? $extraUserMonthlyPrice);
            }
        }

        if ($price <= 0) {
            $price = $monthlyPrice > 0 ? $monthlyPrice : $yearlyPrice;
        }

        if (! $matchedPlan) {
            $matchedPlan = Plan::create([
                'name' => $name,
                'role_type' => $roleType,
                'price' => $price,
                'monthly_price' => $monthlyPrice,
                'yearly_price' => $yearlyPrice,
                'included_users_count' => $includedUsersCount,
                'extra_user_monthly_price' => $extraUserMonthlyPrice,
                'billing_cycle' => $billingCycle,
                'is_active' => true,
            ]);
        } else {
            $matchedPlan->update([
                'name' => $name,
                'role_type' => $roleType,
                'price' => $price,
                'monthly_price' => $monthlyPrice,
                'yearly_price' => $yearlyPrice,
                'included_users_count' => $includedUsersCount,
                'extra_user_monthly_price' => $extraUserMonthlyPrice,
                'billing_cycle' => $billingCycle,
                'is_active' => true,
            ]);
        }

        $plan['role_type'] = $roleType;
        $plan['plan_id'] = $matchedPlan->id;
        $plan['price'] = $price;
        $plan['monthly_price'] = $monthlyPrice;
        $plan['yearly_price'] = $yearlyPrice;
        $plan['included_users_count'] = $includedUsersCount;
        $plan['extra_user_monthly_price'] = $extraUserMonthlyPrice;
        $plan['billing_cycle'] = $billingCycle;
        unset($plan['roleType'], $plan['planId'], $plan['monthlyPrice'], $plan['yearlyPrice'], $plan['includedUsersCount'], $plan['extraUserMonthlyPrice'], $plan['billingCycle']);

        return $plan;
    }

    private function normalizePrice(mixed $price): float
    {
        if (is_numeric($price)) {
            return max(0, (float) $price);
        }

        $normalized = preg_replace('/[^0-9.]/', '', (string) $price);

        if (is_numeric($normalized)) {
            return max(0, (float) $normalized);
        }

        return 0;
    }
}
