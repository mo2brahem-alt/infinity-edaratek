<?php

namespace App\Repositories;

use App\Models\Ticket;

class TicketRepository
{
    public function forSupervisor(int $supervisorId)
    {
        return Ticket::query()
            ->with(['school', 'manager'])
            ->where('created_by', $supervisorId)
            ->latest('id');
    }

    public function forManager(int $managerId)
    {
        return Ticket::query()
            ->with(['school', 'creator'])
            ->where('assigned_to', $managerId)
            ->latest('id');
    }
}
