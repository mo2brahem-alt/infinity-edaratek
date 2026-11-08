<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsureManagerOwnsSchool
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasSystemRole('school_manager')) {
            abort(403, 'Manager access is required.');
        }

        try {
            $school = app(\App\Services\School\SchoolContextResolver::class)->current($user, operational: false, purpose: 'manager');
        } catch (\App\Services\School\SchoolContextException $error) {
            return $this->sendManagerSetupResponse($request, $error->getMessage(), $error->getStatusCode());
        }

        $request->attributes->set('school_context_id', (int) $school->id);

        return $next($request);
    }

    private function sendManagerSetupResponse(Request $request, string $message, int $status): Response|RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $message,
            ], $status);
        }

        return redirect()
            ->route('manager.onboarding.show')
            ->with('warning', $message);
    }
}
