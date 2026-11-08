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
        if (! $user->hasApprovedAccountAccess()) { return false; }
        if ($user->hasSystemRole('super_admin')) {
            return true;
        }

        if ($user->hasSystemRole('supervisor')) {
            try {
                app(\App\Services\School\SchoolContextResolver::class)->supervised($user, (int) $ticket->school_id);
                if ((int) $ticket->created_by === (int) $user->id) { return true; }
            } catch (\App\Services\School\SchoolContextException) { /* Other legitimate roles still need their own scope. */ }
        }

        if ($user->hasSystemRole('school_manager')) {
            try {
                $school = app(\App\Services\School\SchoolContextResolver::class)->current($user, purpose: 'manager');
                return (int) $ticket->assigned_to === (int) $user->id && (int) $ticket->school_id === (int) $school->id;
            } catch (\App\Services\School\SchoolContextException) { return false; }
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
        return $user->hasSystemRole('supervisor') && (int) $ticket->created_by === (int) $user->id && $this->view($user, $ticket);
    }

    public function addFinalReport(User $user, Ticket $ticket): bool
    {
        if (! $user->hasSystemRole('school_manager') || (int) $ticket->assigned_to !== (int) $user->id) { return false; }
        try {
            return (int) app(\App\Services\School\SchoolContextResolver::class)->current($user, purpose: 'manager')->id === (int) $ticket->school_id;
        } catch (\App\Services\School\SchoolContextException) { return false; }
    }

    public function assignSubtask(User $user, Ticket $ticket): bool
    {
        // Viewing a ticket as supervisor must not grant manager assignment powers.
        return $this->addFinalReport($user, $ticket);
    }
}
