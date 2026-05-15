<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Support\SchoolAssociationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect('/login');
        }

        $hasSpatieRole = method_exists($user, 'hasRole') && $user->hasRole($role);
        $hasLegacyRole = ($user->role ?? null) === $role;

        if (!$hasSpatieRole && !$hasLegacyRole) {
            abort(403, 'You are not authorized to access this page.');
        }

        if ($role === 'staff') {
            $schoolId = (int) ($user->school_id ?? 0);
            if ($schoolId <= 0) {
                abort(403, 'School context is required.');
            }

            $school = School::query()
                ->whereKey($schoolId)
                ->first(['id', 'status', 'supervision_status', 'manager_user_id', 'supervisor_id']);

            if (!$school || !SchoolAssociationState::isActiveAssociation($school)) {
                abort(403, SchoolAssociationState::LOCKED_MESSAGE);
            }
        }

        return $next($request);
    }
}
