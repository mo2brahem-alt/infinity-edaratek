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

        $schoolId = (int) ($user->school_id ?? 0);
        if ($schoolId <= 0) {
            return $this->sendManagerSetupResponse($request, __('messages.manager_school_required'), 409);
        }

        $school = School::query()
            ->whereKey($schoolId)
            ->first(['id', 'status', 'supervision_status', 'manager_user_id', 'supervisor_id']);

        if (!$school || (int) $school->manager_user_id !== (int) $user->id) {
            return $this->sendManagerSetupResponse($request, __('messages.assigned_manager_required'), 403);
        }

        $request->attributes->set('school_context_id', $schoolId);

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
