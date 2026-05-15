<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Inertia\Inertia;
use Inertia\Response;

class PricingController extends Controller
{
    public function index(): Response
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('monthly_price')
            ->orderBy('price')
            ->get();

        $supervisorPlans = $plans
            ->filter(fn (Plan $plan) => Plan::normalizeRoleType($plan->role_type) === Plan::ROLE_SUPERVISOR)
            ->values();

        $managerPlans = $plans
            ->filter(fn (Plan $plan) => Plan::normalizeRoleType($plan->role_type) === Plan::ROLE_SCHOOL_MANAGER)
            ->values();

        return Inertia::render('Pricing/Index', [
            'supervisorPlans' => $supervisorPlans,
            'managerPlans' => $managerPlans,
        ]);
    }
}
