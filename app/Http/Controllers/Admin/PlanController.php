<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Pricing\PricingComponentPlanStateService;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function __construct(
        private readonly PricingComponentPlanStateService $pricingComponentPlanStateService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:PENDING,ACTIVE,FROZEN,CANCELED,EXPIRED,DELETED',
            'role_type' => 'nullable|in:SUPERVISOR,SCHOOL_MANAGER',
        ]);

        $plans = Plan::query()
            ->withCount([
                'subscriptions as total_subscriptions_count',
                'subscriptions as all_subscriptions_count' => fn ($query) => $query->withTrashed(),
                'subscriptions as archived_subscriptions_count' => fn ($query) => $query->onlyTrashed(),
                'subscriptions as active_subscriptions_count' => fn ($query) => $query->where('status', Subscription::STATUS_ACTIVE),
                'subscriptions as blocking_subscriptions_count' => fn ($query) => $query->whereIn('status', [
                    Subscription::STATUS_PENDING,
                    Subscription::STATUS_ACTIVE,
                    Subscription::STATUS_FROZEN,
                ]),
            ])
            ->orderByDesc('is_active')
            ->orderBy('role_type')
            ->orderBy('monthly_price')
            ->orderBy('price')
            ->get();

        $subscriptionsQuery = (!empty($validated['status']) && $validated['status'] === 'DELETED')
            ? Subscription::onlyTrashed()
            : Subscription::query();

        $subscriptionsQuery
            ->with(['user:id,name,email,mobile', 'school:id,name,school_id', 'plan:id,name,role_type,price,billing_cycle,monthly_price,yearly_price,included_users_count,extra_user_monthly_price,is_active'])
            ->latest('id');

        if (!empty($validated['status']) && $validated['status'] !== 'DELETED') {
            $subscriptionsQuery->where('status', $validated['status']);
        }

        if (!empty($validated['role_type'])) {
            $roleTypes = Plan::roleAliases($validated['role_type']);
            $subscriptionsQuery->whereHas('plan', fn ($query) => $query->whereIn('role_type', $roleTypes));
        }

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $subscriptionsQuery->where(function ($query) use ($search): void {
                $query->whereHas('user', function ($userQuery) use ($search): void {
                    $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%");
                })->orWhereHas('plan', function ($planQuery) use ($search): void {
                    $planQuery->where('name', 'like', "%{$search}%");
                });
            });
        }

        $subscriptions = $subscriptionsQuery
            ->paginate(25)
            ->withQueryString();

        $statsQuery = Subscription::query();
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'pending' => (clone $statsQuery)->where('status', Subscription::STATUS_PENDING)->count(),
            'active' => (clone $statsQuery)->where('status', Subscription::STATUS_ACTIVE)->count(),
            'frozen' => (clone $statsQuery)->where('status', Subscription::STATUS_FROZEN)->count(),
            'canceled' => (clone $statsQuery)->where('status', Subscription::STATUS_CANCELED)->count(),
            'expired' => (clone $statsQuery)->where('status', Subscription::STATUS_EXPIRED)->count(),
            'deleted' => Subscription::onlyTrashed()->count(),
        ];

        return Inertia::render('Admin/Plans/Index', [
            'plans' => $plans,
            'subscriptions' => $subscriptions,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
                'role_type' => $validated['role_type'] ?? '',
            ],
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role_type' => 'required|in:SUPERVISOR,SCHOOL_MANAGER',
            'price' => 'nullable|numeric|min:0',
            'monthly_price' => 'nullable|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'included_users_count' => 'nullable|integer|min:0|max:100000',
            'extra_user_monthly_price' => 'nullable|numeric|min:0',
            'billing_cycle' => 'nullable|in:MONTHLY,YEARLY',
            'is_active' => 'nullable|boolean',
            'limits' => 'nullable|array',
            'description' => 'nullable|string|max:2000',
        ]);
        $pricing = $this->normalizePlanPricingPayload($validated);

        Plan::create([
            'name' => $validated['name'],
            'role_type' => $validated['role_type'],
            'price' => $pricing['price'],
            'monthly_price' => $pricing['monthly_price'],
            'yearly_price' => $pricing['yearly_price'],
            'included_users_count' => $pricing['included_users_count'],
            'extra_user_monthly_price' => $pricing['extra_user_monthly_price'],
            'billing_cycle' => $validated['billing_cycle'] ?? Plan::BILLING_MONTHLY,
            'is_active' => $validated['is_active'] ?? true,
            'limits' => $validated['limits'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return back();
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role_type' => 'required|in:SUPERVISOR,SCHOOL_MANAGER',
            'price' => 'nullable|numeric|min:0',
            'monthly_price' => 'nullable|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'included_users_count' => 'nullable|integer|min:0|max:100000',
            'extra_user_monthly_price' => 'nullable|numeric|min:0',
            'billing_cycle' => 'nullable|in:MONTHLY,YEARLY',
            'is_active' => 'nullable|boolean',
            'limits' => 'nullable|array',
            'description' => 'nullable|string|max:2000',
        ]);
        $pricing = $this->normalizePlanPricingPayload($validated, $plan);

        $plan->update([
            'name' => $validated['name'],
            'role_type' => $validated['role_type'],
            'price' => $pricing['price'],
            'monthly_price' => $pricing['monthly_price'],
            'yearly_price' => $pricing['yearly_price'],
            'included_users_count' => $pricing['included_users_count'],
            'extra_user_monthly_price' => $pricing['extra_user_monthly_price'],
            'billing_cycle' => $validated['billing_cycle'] ?? Plan::BILLING_MONTHLY,
            'is_active' => $validated['is_active'] ?? true,
            'limits' => $validated['limits'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return back();
    }

    public function destroy(Request $request, Plan $plan): RedirectResponse
    {
        if ($this->hasCurrentSubscriptions($plan)) {
            return back()->withErrors([
                'plan' => 'لا يمكن حذف الخطة لأنها مرتبطة باشتراكات حالية (نشطة أو معلقة أو مجمّدة).',
            ]);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $updatedComponents = $this->pricingComponentPlanStateService->removePlan($plan->id);
        $plan->delete();

        $this->auditLogger->log(
            'plan.deleted',
            'plan',
            $plan->id,
            [
                'reason' => $validated['reason'] ?? null,
                'updated_components_count' => $updatedComponents,
            ],
            $request,
            $request->user()?->id
        );

        return back();
    }

    public function freeze(Request $request, Plan $plan): RedirectResponse
    {
        if ($this->hasCurrentSubscriptions($plan)) {
            return back()->withErrors([
                'plan' => 'لا يمكن تجميد الخطة لأنها مرتبطة باشتراكات حالية (نشطة أو معلقة أو مجمّدة).',
            ]);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $plan->update(['is_active' => false]);
        $updatedComponents = $this->pricingComponentPlanStateService->freezePlan($plan->id);

        $this->auditLogger->log(
            'plan.frozen',
            'plan',
            $plan->id,
            [
                'reason' => $validated['reason'] ?? null,
                'updated_components_count' => $updatedComponents,
            ],
            $request,
            $request->user()?->id
        );

        return back();
    }

    public function activate(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $plan->update(['is_active' => true]);
        $updatedComponents = $this->pricingComponentPlanStateService->activatePlan($plan->id);

        $this->auditLogger->log(
            'plan.activated',
            'plan',
            $plan->id,
            [
                'reason' => $validated['reason'] ?? null,
                'updated_components_count' => $updatedComponents,
            ],
            $request,
            $request->user()?->id
        );

        return back();
    }

    private function hasCurrentSubscriptions(Plan $plan): bool
    {
        return $plan->subscriptions()
            ->whereIn('status', [
                Subscription::STATUS_PENDING,
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_FROZEN,
            ])
            ->exists();
    }

    /**
     * @param array<string, mixed> $validated
     * @return array{price: float, monthly_price: float, yearly_price: float, included_users_count: int, extra_user_monthly_price: float}
     */
    private function normalizePlanPricingPayload(array $validated, ?Plan $plan = null): array
    {
        $billingCycle = $validated['billing_cycle'] ?? $plan?->billing_cycle ?? Plan::BILLING_MONTHLY;
        $legacyPrice = (float) ($validated['price'] ?? $plan?->price ?? 0);
        $monthlyPrice = (float) ($validated['monthly_price'] ?? $plan?->monthly_price ?? 0);
        $yearlyPrice = (float) ($validated['yearly_price'] ?? $plan?->yearly_price ?? 0);

        if ($monthlyPrice <= 0 && $billingCycle === Plan::BILLING_MONTHLY) {
            $monthlyPrice = $legacyPrice;
        }

        if ($yearlyPrice <= 0 && $billingCycle === Plan::BILLING_YEARLY) {
            $yearlyPrice = $legacyPrice;
        }

        if ($yearlyPrice <= 0 && $monthlyPrice > 0) {
            $yearlyPrice = $monthlyPrice * 12;
        }

        if ($legacyPrice <= 0) {
            $legacyPrice = $monthlyPrice > 0 ? $monthlyPrice : $yearlyPrice;
        }

        return [
            'price' => max(0, $legacyPrice),
            'monthly_price' => max(0, $monthlyPrice),
            'yearly_price' => max(0, $yearlyPrice),
            'included_users_count' => max(0, (int) ($validated['included_users_count'] ?? $plan?->included_users_count ?? 0)),
            'extra_user_monthly_price' => max(0, (float) ($validated['extra_user_monthly_price'] ?? $plan?->extra_user_monthly_price ?? 0)),
        ];
    }
}
