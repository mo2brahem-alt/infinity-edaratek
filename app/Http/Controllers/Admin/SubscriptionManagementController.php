<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\Subscription\SubscriptionService;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubscriptionManagementController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function activate(Request $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $this->subscriptionService->activate(
            $subscription,
            $request->user()?->id,
            $validated['reason'] ?? null
        );

        $this->auditLogger->log(
            'subscription.activated',
            'subscription',
            $subscription->id,
            ['reason' => $validated['reason'] ?? null],
            $request,
            $request->user()?->id
        );

        return back();
    }

    public function cancel(Request $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $this->subscriptionService->cancel(
            $subscription,
            $request->user()?->id,
            $validated['reason'] ?? null
        );

        $this->auditLogger->log(
            'subscription.canceled',
            'subscription',
            $subscription->id,
            ['reason' => $validated['reason'] ?? null],
            $request,
            $request->user()?->id
        );

        return back();
    }

    public function freeze(Request $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $this->subscriptionService->freeze(
            $subscription,
            $request->user()?->id,
            $validated['reason'] ?? null
        );

        $this->auditLogger->log(
            'subscription.frozen',
            'subscription',
            $subscription->id,
            ['reason' => $validated['reason'] ?? null],
            $request,
            $request->user()?->id
        );

        return back();
    }

    public function destroy(Request $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->subscriptionService->delete(
            $subscription,
            $request->user()?->id,
            $validated['reason']
        );

        $this->auditLogger->log(
            'subscription.deleted',
            'subscription',
            $subscription->id,
            ['reason' => $validated['reason']],
            $request,
            $request->user()?->id
        );

        return back();
    }
}
