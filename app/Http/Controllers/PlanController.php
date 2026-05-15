<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_type' => 'nullable|in:SUPERVISOR,SCHOOL_MANAGER',
        ]);

        $query = Plan::query()
            ->where('is_active', true)
            ->orderBy('monthly_price')
            ->orderBy('price');

        if (!empty($validated['role_type'])) {
            $query->whereIn('role_type', Plan::roleAliases($validated['role_type']));
        }

        return response()->json($query->get());
    }
}
