<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\HandlesUserIdentityUniqueness;
use App\Http\Controllers\Concerns\NormalizesSaudiPhoneInputs;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Rules\SaudiMobile;
use App\Services\Auth\UserApprovalService;
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

class RegisterSupervisorController extends Controller
{
    use NormalizesSaudiPhoneInputs, HandlesUserIdentityUniqueness;

    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly UserApprovalService $userApprovalService,
    ) {
    }

    public function create(Request $request): Response
    {
        $roleTypes = Plan::roleAliases(Plan::ROLE_SUPERVISOR);

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

        return Inertia::render('Auth/RegisterSupervisor', [
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
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'mobile' => ['required', 'string', 'max:20', new SaudiMobile, 'unique:users,mobile'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], $this->duplicateUserValidationMessages());

        $plan = Plan::query()
            ->where('id', $validated['plan_id'])
            ->whereIn('role_type', Plan::roleAliases(Plan::ROLE_SUPERVISOR))
            ->where('is_active', true)
            ->firstOrFail();

        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'mobile' => $validated['mobile'],
                'password' => Hash::make($validated['password']),
                'role' => 'supervisor',
                ...$this->userApprovalService->initialStateForRole('supervisor'),
            ]);
        } catch (QueryException $exception) {
            $this->rethrowAsDuplicateUserValidation($exception);
            throw $exception;
        }

        if (method_exists($user, 'assignRole')) {
            Role::findOrCreate('supervisor', 'web');
            $user->assignRole('supervisor');
        }

        $this->subscriptionService->createForUser($user, $plan, false, [
            'source' => 'public_supervisor_registration',
        ], $validated['billing_cycle'] ?? null);

        event(new Registered($user));
        $this->userApprovalService->notifyPendingApproval($user, 'public_supervisor_registration');

        return redirect()
            ->route('welcome', ['registration' => 'pending-approval'])
            ->with('success', 'تم إرسال طلب الانضمام للمسؤول، وسيتم تفعيل الحساب بعد المراجعة.');
    }
}
