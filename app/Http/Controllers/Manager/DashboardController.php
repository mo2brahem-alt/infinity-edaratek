<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\AssociationRequest;
use App\Models\User;
use App\Services\School\SchoolDashboardAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, SchoolDashboardAnalyticsService $analyticsService): Response
    {
        $manager = $request->user();
        $manager->loadMissing(['managedSchool', 'school']);
        $school = $manager->managedSchool ?: $manager->school;

        abort_unless($school, 403, __('messages.manager_school_required'));

        $staff = User::query()
            ->with([
                'department:id,name,staff_type',
                'departmentRole:id,name,department_id',
            ])
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('role', 'staff')
                    ->orWhereHas('roles', fn ($r) => $r->where('name', 'staff'));
            })
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'school_id',
                'school_staff_type',
                'department_id',
                'department_role_id',
            ]);

        $associationRequests = AssociationRequest::query()
            ->where('manager_user_id', $manager->id)
            ->with([
                'school:id,name,school_id,status',
                'supervisor:id,name,email',
            ])
            ->latest('id')
            ->get();

        return Inertia::render('Manager/Dashboard', [
            'staff' => $staff,
            'associationRequests' => $associationRequests,
            'analytics' => $analyticsService->build($school, $request->only([
                'period',
                'stage_id',
                'grade_id',
                'classroom_id',
                'subject_id',
                'teacher_id',
            ])),
        ]);
    }
}
