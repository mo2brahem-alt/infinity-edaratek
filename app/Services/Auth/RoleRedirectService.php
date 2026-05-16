<?php

namespace App\Services\Auth;

use App\Models\School;
use App\Models\User;
use App\Support\SchoolAssociationState;

class RoleRedirectService
{
    public function redirectRouteFor(User $user): string
    {
        if ($user->hasSystemRole('super_admin')) {
            return 'admin.dashboard';
        }

        if ($user->hasSystemRole('supervisor')) {
            $hasExistingScope = $user->supervisedSchools()->exists()
                || $user->supervisionRequestsAsSupervisor()->exists();

            if (!$hasExistingScope) {
                return 'supervisor.onboarding.show';
            }

            return 'supervisor.dashboard';
        }

        if ($user->hasSystemRole('school_manager')) {
            if (!$user->school_id) {
                return 'manager.onboarding.show';
            }

            $school = School::query()
                ->whereKey($user->school_id)
                ->first(['id', 'manager_user_id', 'status', 'supervision_status', 'supervisor_id']);

            if (!$school || (int) $school->manager_user_id !== (int) $user->id) {
                return 'manager.onboarding.show';
            }

            return 'manager.dashboard';
        }

        if ($user->hasSystemRole('staff')) {
            $schoolId = (int) ($user->school_id ?? 0);
            if ($schoolId <= 0) {
                return 'dashboard';
            }

            $school = School::query()
                ->whereKey($schoolId)
                ->first(['id', 'status', 'supervision_status', 'manager_user_id', 'supervisor_id']);

            if (!$school || !SchoolAssociationState::allowsOperationalAccessFor($user, $school)) {
                return 'dashboard';
            }

            return 'staff.dashboard';
        }

        return 'dashboard';
    }
}
