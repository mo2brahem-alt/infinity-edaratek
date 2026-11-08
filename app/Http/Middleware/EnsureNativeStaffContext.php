<?php

namespace App\Http\Middleware;

use App\Services\School\SchoolContextResolver;
use App\Support\NativeApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureNativeStaffContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasSystemRole('staff')) { return NativeApiResponse::error('FORBIDDEN', 403); }
        $school = app(SchoolContextResolver::class)->current($request->user(), purpose: 'staff');
        $request->attributes->set('school_context_id', (int) $school->id);
        return $next($request);
    }
}
