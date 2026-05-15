<?php

namespace App\Support;

use App\Models\AssociationRequest;
use App\Models\School;
use App\Models\SchoolSupervisionRequest;

class SchoolAssociationState
{
    public const LOCKED_MESSAGE = 'خصائص المدرسة موقوفة حتى يكتمل اعتماد الارتباط المتبادل بين مدير المدرسة والمشرف التربوي المعتمد.';

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
