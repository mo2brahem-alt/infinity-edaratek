<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Support\SchoolAssociationState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSchoolAssociation
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) { return redirect('/login'); }
        try {
            $school = app(\App\Services\School\SchoolContextResolver::class)->current($user);
        } catch (\App\Services\School\SchoolContextException $error) {
            if ($user->hasSystemRole('school_manager') && $error->errorCode !== 'SCHOOL_SUSPENDED') {
                return $this->sendManagerSetupResponse($request, $error->getMessage(), $error->getStatusCode());
            }
            abort($error->getStatusCode() === 409 ? 403 : $error->getStatusCode(),
                $error->getStatusCode() === 409 ? SchoolAssociationState::STAFF_SCHOOL_REQUIRED_MESSAGE : $error->getMessage());
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
