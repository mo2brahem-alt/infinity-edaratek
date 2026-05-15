<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Models\SchoolSubjectTeacherAssignment;
use App\Models\User;
use App\Support\SchoolAssociationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolExamsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect('/login');
        }

        $schoolId = (int) ($user->school_id ?? 0);
        if ($schoolId <= 0) {
            abort(403, 'لا يمكن متابعة صفحة الاختبارات بدون تحديد مدرسة للحساب الحالي.');
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

        if (!SchoolAssociationState::isActiveAssociation($school)) {
            abort(403, SchoolAssociationState::LOCKED_MESSAGE);
        }

        $canAccessExams = $user->canManageSchoolExams()
            || $this->canAccessAsEducationalTeacher($schoolId, $user);

        if (!$canAccessExams) {
            abort(403, 'لا تملك صلاحية إدارة الاختبارات وبنك الأسئلة.');
        }

        $request->attributes->set('school_context_id', $schoolId);

        return $next($request);
    }

    private function canAccessAsEducationalTeacher(int $schoolId, User $user): bool
    {
        $isEducationalContext = ($user->school_staff_type ?? null) === User::SCHOOL_STAFF_EDUCATIONAL
            || $user->hasSystemRole('teacher');

        if (!$isEducationalContext) {
            return false;
        }

        if ((int) ($user->school_id ?? 0) !== $schoolId || !(bool) ($user->is_active ?? false)) {
            return false;
        }

        return SchoolSubjectTeacherAssignment::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', (int) $user->id)
            ->exists();
    }
}