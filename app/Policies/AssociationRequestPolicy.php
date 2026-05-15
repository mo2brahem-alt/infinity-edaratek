<?php

namespace App\Policies;

use App\Models\AssociationRequest;
use App\Models\User;

class AssociationRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasSystemRole('school_manager') || $user->hasSystemRole('super_admin');
    }

    public function view(User $user, AssociationRequest $associationRequest): bool
    {
        if ($user->hasSystemRole('super_admin')) {
            return true;
        }

        return $user->id === $associationRequest->manager_user_id;
    }

    public function respond(User $user, AssociationRequest $associationRequest): bool
    {
        return $user->hasSystemRole('school_manager') && $user->id === $associationRequest->manager_user_id;
    }
}
