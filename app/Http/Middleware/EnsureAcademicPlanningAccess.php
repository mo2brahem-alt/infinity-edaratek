<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Support\SchoolAssociationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAcademicPlanningAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) { return redirect('/login'); }
        $school = app(\App\Services\School\SchoolContextResolver::class)->current($user);
        if (! $user->canManageAcademicPlanning()) { abort(403, 'You do not have permission to manage academic planning.'); }
        $request->attributes->set('school_context_id', (int) $school->id);
        return $next($request);
    }
}
