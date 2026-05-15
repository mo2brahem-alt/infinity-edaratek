<?php

namespace App\Services\Pricing;

use App\Models\PageComponent;

class PricingComponentPlanStateService
{
    public function freezePlan(int $planId): int
    {
        return $this->mutatePricingComponents(function (array $plans) use ($planId): array {
            return array_map(function (array $plan) use ($planId): array {
                if ($this->extractPlanId($plan) === $planId) {
                    $plan['is_disabled'] = true;
                }

                return $plan;
            }, $plans);
        });
    }

    public function activatePlan(int $planId): int
    {
        return $this->mutatePricingComponents(function (array $plans) use ($planId): array {
            return array_map(function (array $plan) use ($planId): array {
                if ($this->extractPlanId($plan) === $planId) {
                    $plan['is_disabled'] = false;
                }

                return $plan;
            }, $plans);
        });
    }

    public function removePlan(int $planId): int
    {
        return $this->mutatePricingComponents(function (array $plans) use ($planId): array {
            return array_values(array_filter($plans, fn (array $plan): bool => $this->extractPlanId($plan) !== $planId));
        });
    }

    /**
     * @param  callable(array<int, array<string, mixed>>): array<int, array<string, mixed>>  $mutator
     */
    private function mutatePricingComponents(callable $mutator): int
    {
        $updated = 0;

        $components = PageComponent::query()
            ->where('content', 'like', '%"type":"pricing"%')
            ->orWhere('content', 'like', '%"type": "pricing"%')
            ->get();

        foreach ($components as $component) {
            $decoded = json_decode((string) $component->content, true);
            if (! is_array($decoded) || ($decoded['type'] ?? null) !== 'pricing') {
                continue;
            }

            $plans = $decoded['plans'] ?? null;
            if (! is_array($plans)) {
                continue;
            }

            $normalizedPlans = array_map(fn ($plan) => is_array($plan) ? $plan : [], $plans);
            $mutatedPlans = $mutator($normalizedPlans);

            if ($mutatedPlans === $normalizedPlans) {
                continue;
            }

            $decoded['plans'] = $mutatedPlans;
            $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE);

            if ($encoded === false) {
                continue;
            }

            $component->update([
                'content' => $encoded,
            ]);
            $updated++;
        }

        return $updated;
    }

    private function extractPlanId(array $plan): ?int
    {
        $candidate = $plan['plan_id'] ?? $plan['planId'] ?? $plan['id'] ?? null;
        if ($candidate === null || $candidate === '') {
            return null;
        }

        $id = (int) $candidate;

        return $id > 0 ? $id : null;
    }
}
