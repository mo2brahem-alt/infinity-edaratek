<?php

namespace App\Support;

use App\Models\AssociationRequest;
use App\Models\School;
use App\Models\SchoolSupervisionRequest;
use App\Models\User;

class SchoolAssociationState
{
    public const LOCKED_MESSAGE = 'خصائص المدرسة موقوفة حتى يكتمل اعتماد الارتباط المتبادل بين مدير المدرسة والمشرف التربوي المعتمد.';
    public const STAFF_SCHOOL_REQUIRED_MESSAGE = 'حسابك غير مرتبط بمدرسة نشطة. يرجى التواصل مع إدارة المدرسة.';
    public const SCHOOL_SUSPENDED_MESSAGE = 'تم إيقاف المدرسة مؤقتًا. يرجى التواصل مع إدارة المنصة.';

    public static function isActiveAssociation(School $school): bool
    {
        $isMarkedActive = (string) $school->status === School::STATUS_ACTIVE
            && (string) $school->supervision_status === School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION;

        if (!$isMarkedActive) {
            return false;
        }

        // Legacy guard:
        // approved association requests from the old manager-only flow
        // must not unlock school features unless a confirmed supervision request exists.
        $hasApprovedAssociationRequests = AssociationRequest::query()
            ->where('school_id', $school->id)
            ->where('status', AssociationRequest::STATUS_APPROVED)
            ->exists();

        if (!$hasApprovedAssociationRequests) {
            return true;
        }

        return self::hasConfirmedSupervisionRequest($school);
    }

    public static function isSchoolActive(School $school): bool
    {
        return (string) $school->status === School::STATUS_ACTIVE;
    }

    public static function allowsOperationalAccessFor(User $user, School $school): bool
    {
        if (self::isSchoolOperationalUser($user)) {
            return self::isSchoolActive($school);
        }

        return self::isActiveAssociation($school);
    }

    public static function operationalAccessDeniedMessageFor(User $user, School $school): string
    {
        if (! self::isSchoolActive($school)) {
            return self::SCHOOL_SUSPENDED_MESSAGE;
        }

        if (self::isSchoolOperationalUser($user)) {
            return self::STAFF_SCHOOL_REQUIRED_MESSAGE;
        }

        return self::LOCKED_MESSAGE;
    }

    private static function isSchoolOperationalUser(User $user): bool
    {
        return $user->hasSystemRole('staff')
            || $user->hasSystemRole('teacher')
            || in_array((string) ($user->school_staff_type ?? ''), [
                User::SCHOOL_STAFF_ADMINISTRATIVE,
                User::SCHOOL_STAFF_EDUCATIONAL,
            ], true);
    }

    private static function hasConfirmedSupervisionRequest(School $school): bool
    {
        $managerId = (int) ($school->manager_user_id ?? 0);
        if ($managerId <= 0) {
            return false;
        }

        $query = SchoolSupervisionRequest::query()
            ->where('school_id', $school->id)
            ->where('status', SchoolSupervisionRequest::STATUS_ACTIVE_ASSOCIATION)
            ->where('manager_id', $managerId);

        $supervisorId = (int) ($school->supervisor_id ?? 0);
        if ($supervisorId > 0) {
            $query->where('supervisor_id', $supervisorId);
        }

        return $query->exists();
    }
}
