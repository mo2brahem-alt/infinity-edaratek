<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $schools = School::query()
            ->with('manager:id,name,email')
            ->where('status', School::STATUS_ACTIVE)
            ->where('supervisor_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name', 'school_id', 'manager_user_id', 'status']);

        return Inertia::render('Supervisor/Dashboard', [
            'schools' => $schools,
        ]);
    }
}
