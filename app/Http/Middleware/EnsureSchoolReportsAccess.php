<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Support\SchoolAssociationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolReportsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
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
                abort(403, 'Only the assigned manager can access school reports.');
            }

            $request->attributes->set('school_context_id', $schoolId);

            return $next($request);
        }

        if (!SchoolAssociationState::allowsOperationalAccessFor($user, $school)) {
            abort(403, SchoolAssociationState::operationalAccessDeniedMessageFor($user, $school));
        }

        if (!$user->canManageSchoolReports()) {
            abort(403, 'You do not have permission to access school reports.');
        }

        $request->attributes->set('school_context_id', $schoolId);

        return $next($request);
    }
}
