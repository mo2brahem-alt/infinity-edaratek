<?php

namespace App\Policies;

use App\Models\SchoolSupervisionRequest;
use App\Models\User;

class SchoolSupervisionRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasSystemRole('super_admin')
            || $user->hasSystemRole('supervisor')
            || $user->hasSystemRole('school_manager');
    }

    public function view(User $user, SchoolSupervisionRequest $requestItem): bool
    {
        if ($user->hasSystemRole('super_admin')) {
            return true;
        }

        if ($user->hasSystemRole('supervisor')) {
            return (int) $requestItem->supervisor_id === (int) $user->id;
        }

        if ($user->hasSystemRole('school_manager')) {
            return (int) $requestItem->school_id === (int) $user->school_id;
        }

        return false;
    }

    public function approve(User $user, SchoolSupervisionRequest $requestItem): bool
    {
        return $this->managerRespond($user, $requestItem);
    }

    public function reject(User $user, SchoolSupervisionRequest $requestItem): bool
    {
        return $this->managerRespond($user, $requestItem);
    }

    public function managerRespond(User $user, SchoolSupervisionRequest $requestItem): bool
    {
        return $user->hasSystemRole('school_manager')
            && (int) $requestItem->school_id === (int) $user->school_id
            && (!$requestItem->manager_id || (int) $requestItem->manager_id === (int) $user->id);
    }

    public function confirm(User $user, SchoolSupervisionRequest $requestItem): bool
    {
        return $this->supervisorRespond($user, $requestItem);
    }

    public function cancel(User $user, SchoolSupervisionRequest $requestItem): bool
    {
        return $this->supervisorRespond($user, $requestItem);
    }

    public function supervisorRespond(User $user, SchoolSupervisionRequest $requestItem): bool
    {
        return $user->hasSystemRole('supervisor')
            && (int) $requestItem->supervisor_id === (int) $user->id;
    }
}
