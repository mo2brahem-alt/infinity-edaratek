<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Support\SchoolAssociationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolCertificatesAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect('/login');
        }

        $schoolId = (int) ($user->school_id ?? 0);
        if ($schoolId <= 0) {
            abort(403, 'لا يمكن فتح وحدة الشهادات دون تحديد مدرسة للحساب الحالي.');
        }

        $school = School::query()
            ->whereKey($schoolId)
            ->first(['id', 'status', 'supervision_status', 'manager_user_id', 'supervisor_id']);

        if (!$school) {
            abort(403, 'تعذر التحقق من المدرسة الحالية لهذا الحساب.');
        }

        if ($user->hasSystemRole('school_manager')) {
            if ((int) $school->manager_user_id !== (int) $user->id) {
                abort(403, 'غير مسموح بالوصول لأن هذا الحساب ليس مدير المدرسة المعتمد.');
            }

            $request->attributes->set('school_context_id', $schoolId);

            return $next($request);
        }

        if (!SchoolAssociationState::allowsOperationalAccessFor($user, $school)) {
            abort(403, SchoolAssociationState::operationalAccessDeniedMessageFor($user, $school));
        }

        if (!$user->canAccessCertificates()) {
            abort(403, 'ليست لديك صلاحية لإدارة الشهادات.');
        }

        $request->attributes->set('school_context_id', $schoolId);

        return $next($request);
    }
}
