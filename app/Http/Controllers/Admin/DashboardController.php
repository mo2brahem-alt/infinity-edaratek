<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolStudent;
use App\Models\Subscription;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $totalSchools = School::query()->count();
        $activeSchools = School::query()->where('status', School::STATUS_ACTIVE)->count();
        $suspendedSchools = School::query()->where('status', School::STATUS_SUSPENDED)->count();
        $linkedSchools = School::query()->whereNotNull('manager_user_id')->count();

        $totalStudents = SchoolStudent::query()->count();
        $activeSubscriptions = Subscription::query()->where('status', Subscription::STATUS_ACTIVE)->count();
        $pendingSubscriptions = Subscription::query()->where('status', Subscription::STATUS_PENDING)->count();
        $directorateCount = EducationalDirectorate::query()->count();
        $managerCount = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'school_manager'))
            ->count();

        $recentSchools = School::query()
            ->with([
                'manager:id,name',
                'directorate:id,name',
            ])
            ->latest()
            ->take(6)
            ->get([
                'id',
                'name',
                'school_id',
                'directorate_id',
                'manager_user_id',
                'status',
                'supervision_status',
                'created_at',
            ])
            ->map(fn (School $school) => [
                'id' => $school->id,
                'name' => $school->name,
                'school_id' => $school->school_id,
                'directorate_name' => $school->directorate?->name,
                'manager_name' => $school->manager?->name,
                'status' => $school->status,
                'supervision_status' => $school->supervision_status,
                'created_at' => optional($school->created_at)->format('Y-m-d'),
            ])
            ->values();

        return Inertia::render('Admin/Dashboard', [
            'metrics' => [
                'total_schools' => $totalSchools,
                'active_schools' => $activeSchools,
                'suspended_schools' => $suspendedSchools,
                'linked_schools' => $linkedSchools,
                'total_students' => $totalStudents,
                'active_subscriptions' => $activeSubscriptions,
                'pending_subscriptions' => $pendingSubscriptions,
                'directorates' => $directorateCount,
                'managers' => $managerCount,
            ],
            'recentSchools' => $recentSchools,
        ]);
    }

    public function __invoke(): Response
    {
        return $this->index();
    }
}
