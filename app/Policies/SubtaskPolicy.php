<?php

namespace App\Policies;

use App\Models\Subtask;
use App\Models\User;

class SubtaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasSystemRole('staff') || $user->hasSystemRole('school_manager') || $user->hasSystemRole('super_admin');
    }

    public function view(User $user, Subtask $subtask): bool
    {
        $matchesSchool = (int) $subtask->school_id === (int) ($user->school_id ?? 0);

        if ($user->hasSystemRole('super_admin')) {
            return true;
        }

        if ($user->hasSystemRole('school_manager')) {
            return (int) $subtask->ticket->assigned_to === (int) $user->id
                && $matchesSchool;
        }

        if ($user->hasSystemRole('staff')) {
            return (int) $subtask->assigned_to === (int) $user->id
                && $matchesSchool;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasSystemRole('school_manager') || $user->hasSystemRole('super_admin');
    }

    public function update(User $user, Subtask $subtask): bool
    {
        return $user->hasSystemRole('school_manager')
            && (int) $subtask->ticket->assigned_to === (int) $user->id
            && (int) $subtask->school_id === (int) ($user->school_id ?? 0);
    }

    public function reply(User $user, Subtask $subtask): bool
    {
        return $this->view($user, $subtask);
    }

    public function submit(User $user, Subtask $subtask): bool
    {
        return $user->hasSystemRole('staff')
            && (int) $subtask->assigned_to === (int) $user->id
            && (int) $subtask->school_id === (int) ($user->school_id ?? 0);
    }

    public function approve(User $user, Subtask $subtask): bool
    {
        return $user->hasSystemRole('school_manager')
            && (int) $subtask->ticket->assigned_to === (int) $user->id
            && (int) $subtask->school_id === (int) ($user->school_id ?? 0);
    }
}
