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

        if (!$user) {
            return redirect('/login');
        }

        $schoolId = (int) ($user->school_id ?? 0);
        if ($schoolId <= 0) {
            if ($user->hasSystemRole('school_manager')) {
                return $this->sendManagerSetupResponse($request, __('messages.manager_school_required'), 409);
            }

            abort(403, 'School context is required.');
        }

        $school = School::query()
            ->whereKey($schoolId)
            ->first(['id', 'status', 'supervision_status', 'manager_user_id', 'supervisor_id']);

        if (!$school) {
            if ($user->hasSystemRole('school_manager')) {
                return $this->sendManagerSetupResponse($request, __('messages.school_context_invalid'), 409);
            }

            abort(403, 'School context is invalid.');
        }

        if ($user->hasSystemRole('school_manager') && (int) $school->manager_user_id !== (int) $user->id) {
            return $this->sendManagerSetupResponse($request, __('messages.assigned_manager_required'), 403);
        }

        if ($user->hasSystemRole('school_manager')) {
            $request->attributes->set('school_context_id', $schoolId);

            return $next($request);
        }

        if (SchoolAssociationState::isActiveAssociation($school)) {
            $request->attributes->set('school_context_id', $schoolId);

            return $next($request);
        }

        $message = SchoolAssociationState::LOCKED_MESSAGE;

        if ($request->expectsJson() || $request->is('api/*')) {
            abort(403, $message);
        }

        abort(403, $message);
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
