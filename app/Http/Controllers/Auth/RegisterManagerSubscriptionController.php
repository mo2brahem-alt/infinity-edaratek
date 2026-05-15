<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\HandlesUserIdentityUniqueness;
use App\Http\Controllers\Concerns\NormalizesSaudiPhoneInputs;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Rules\SaudiMobile;
use App\Services\Auth\UserApprovalService;
use App\Services\Subscription\SubscriptionPricingService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RegisterManagerSubscriptionController extends Controller
{
    use NormalizesSaudiPhoneInputs, HandlesUserIdentityUniqueness;

    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly SubscriptionPricingService $pricingService,
        private readonly UserApprovalService $userApprovalService,
    ) {
    }

    public function create(Request $request): Response
    {
        $roleTypes = Plan::roleAliases(Plan::ROLE_SCHOOL_MANAGER);

        $plans = Plan::query()
            ->whereIn('role_type', $roleTypes)
            ->where('is_active', true)
            ->orderBy('monthly_price')
            ->orderBy('price')
            ->get();

        $requestedPlanId = $request->integer('plan_id') ?: null;
        $selectedPlan = $requestedPlanId
            ? $plans->firstWhere('id', $requestedPlanId)
            : null;

        if (! $selectedPlan && $requestedPlanId) {
            $selectedPlan = Plan::query()
                ->where('id', $requestedPlanId)
                ->whereIn('role_type', $roleTypes)
                ->where('is_active', true)
                ->first();
        }

        if (! $selectedPlan) {
            $selectedPlan = $plans->first();
        }

        $initialBillingCycle = strtoupper((string) $request->query('billing_cycle', Plan::BILLING_MONTHLY));
        if (! in_array($initialBillingCycle, [Plan::BILLING_MONTHLY, Plan::BILLING_YEARLY], true)) {
            $initialBillingCycle = Plan::BILLING_MONTHLY;
        }

        return Inertia::render('Auth/RegisterManagerSubscription', [
            'selectedPlan' => $selectedPlan,
            'initialBillingCycle' => $initialBillingCycle,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeSaudiPhoneInputs($request, ['mobile']);

        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'nullable|in:MONTHLY,YEARLY,monthly,yearly',
            'extra_users_count' => 'nullable|integer|min:0|max:100000',
            'requested_users_count' => 'nullable|integer|min:0|max:100000',
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'mobile' => ['required', 'string', 'max:20', new SaudiMobile, 'unique:users,mobile'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], $this->duplicateUserValidationMessages());

        $plan = Plan::query()
            ->where('id', $validated['plan_id'])
            ->whereIn('role_type', Plan::roleAliases(Plan::ROLE_SCHOOL_MANAGER))
            ->where('is_active', true)
            ->firstOrFail();

        $includedUsersCount = max(0, (int) ($plan->included_users_count ?? 0));
        $requestedUsersCount = array_key_exists('extra_users_count', $validated) && $validated['extra_users_count'] !== null
            ? $includedUsersCount + max(0, (int) $validated['extra_users_count'])
            : (int) ($validated['requested_users_count'] ?? $includedUsersCount);

        $estimate = $this->pricingService->initialSubscriptionEstimate(
            $plan,
            $validated['billing_cycle'] ?? null,
            $requestedUsersCount
        );

        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'mobile' => $validated['mobile'],
                'password' => Hash::make($validated['password']),
                'role' => 'school_manager',
                ...$this->userApprovalService->initialStateForRole('school_manager'),
            ]);
        } catch (QueryException $exception) {
            $this->rethrowAsDuplicateUserValidation($exception);
            throw $exception;
        }

        if (method_exists($user, 'assignRole')) {
            Role::findOrCreate('school_manager', 'web');
            $user->assignRole('school_manager');
        }

        $this->subscriptionService->createForUser($user, $plan, false, [
            'source' => 'public_manager_registration',
            'requested_users_count' => $estimate['requested_users_count'],
            'initial_extra_users_count' => $estimate['extra_users_count'],
            'initial_extra_user_monthly_price' => $estimate['extra_user_monthly_price'],
            'initial_extra_users_amount' => $estimate['extra_users_amount'],
            'initial_subscription_base_price' => $estimate['base_price'],
            'initial_subscription_total_price' => $estimate['total_price'],
            'initial_subscription_billing_cycle' => $estimate['billing_cycle'],
            'initial_subscription_billing_months' => $estimate['billing_months'],
        ], $estimate['billing_cycle']);

        event(new Registered($user));
        $this->userApprovalService->notifyPendingApproval($user, 'public_manager_registration');

        return redirect()
            ->route('welcome', ['registration' => 'pending-approval'])
            ->with('success', 'تم إرسال طلب الانضمام للمسؤول، وسيتم تفعيل الحساب بعد المراجعة.');
    }
}
