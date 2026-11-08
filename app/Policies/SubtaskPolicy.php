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
        return app(\App\Services\Ticketing\SubtaskAccessQuery::class)->permits($user, $subtask, 'view');
    }

    public function create(User $user): bool
    {
        return $user->hasSystemRole('school_manager') || $user->hasSystemRole('super_admin');
    }

    public function update(User $user, Subtask $subtask): bool
    {
        return app(\App\Services\Ticketing\SubtaskAccessQuery::class)->permits($user, $subtask, 'update');
    }

    public function reply(User $user, Subtask $subtask): bool
    {
        return app(\App\Services\Ticketing\SubtaskAccessQuery::class)->permits($user, $subtask, 'reply');
    }

    public function submit(User $user, Subtask $subtask): bool
    {
        return app(\App\Services\Ticketing\SubtaskAccessQuery::class)->permits($user, $subtask, 'submit');
    }

    public function approve(User $user, Subtask $subtask): bool
    {
        return app(\App\Services\Ticketing\SubtaskAccessQuery::class)->permits($user, $subtask, 'approve');
    }
}
