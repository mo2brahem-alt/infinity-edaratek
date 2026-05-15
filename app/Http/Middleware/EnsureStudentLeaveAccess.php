<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Support\SchoolAssociationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentLeaveAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('features.student_leaves.enabled', true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                abort(404);
            }

            $fallbackRoute = $this->resolveDisabledFeatureFallbackRoute($request);

            if ($fallbackRoute !== null) {
                return redirect()
                    ->route($fallbackRoute)
                    ->with('error', 'Student leaves module is disabled.');
            }

            abort(404);
        }

        $user = $request->user();

        if (!$user) {
            return redirect('/login');
        }

        $schoolId = (int) ($user->school_id ?? 0);
        if ($schoolId <= 0) {
            abort(403, 'School context is required.');
        }

        $school = School::query()
            ->whereKey($schoolId)
            ->first(['id', 'status', 'supervision_status', 'manager_user_id', 'supervisor_id']);

        if (!$school) {
            abort(403, 'School context is invalid.');
        }

        if ($user->hasSystemRole('school_manager')) {
            if ((int) $school->manager_user_id !== (int) $user->id) {
                abort(403, 'Only the assigned manager can access student leaves.');
            }

            $request->attributes->set('school_context_id', $schoolId);

            return $next($request);
        }

        if (!SchoolAssociationState::isActiveAssociation($school)) {
            abort(403, SchoolAssociationState::LOCKED_MESSAGE);
        }

        $canAccess = $user->canManageStudentLeaves()
            || $user->canManageLeaveTypes()
            || $user->canManageSchoolCalendar()
            || $user->canManageSchoolHolidays();

        if (!$canAccess) {
            abort(403, 'You do not have permission to manage student leaves.');
        }

        $request->attributes->set('school_context_id', $schoolId);

        return $next($request);
    }

    private function resolveDisabledFeatureFallbackRoute(Request $request): ?string
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        if ($user->hasSystemRole('school_manager')) {
            return 'manager.dashboard';
        }

        if ($user->hasSystemRole('staff')) {
            return 'staff.dashboard';
        }

        if ($user->hasSystemRole('supervisor')) {
            return 'supervisor.dashboard';
        }

        return 'dashboard';
    }
}
