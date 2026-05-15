<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasSystemRole('supervisor') || $user->hasSystemRole('school_manager') || $user->hasSystemRole('super_admin');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->hasSystemRole('super_admin')) {
            return true;
        }

        if ($user->hasSystemRole('supervisor')) {
            return (int) $ticket->created_by === (int) $user->id;
        }

        if ($user->hasSystemRole('school_manager')) {
            return (int) $ticket->assigned_to === (int) $user->id
                && (int) $ticket->school_id === (int) ($user->school_id ?? 0);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasSystemRole('supervisor') || $user->hasSystemRole('super_admin');
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function close(User $user, Ticket $ticket): bool
    {
        return $user->hasSystemRole('supervisor') && (int) $ticket->created_by === (int) $user->id;
    }

    public function addFinalReport(User $user, Ticket $ticket): bool
    {
        return $user->hasSystemRole('school_manager')
            && (int) $ticket->assigned_to === (int) $user->id
            && (int) $ticket->school_id === (int) ($user->school_id ?? 0);
    }
}
