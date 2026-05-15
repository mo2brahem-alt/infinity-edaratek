<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_WAITING_MANAGER_REVIEW = 'WAITING_MANAGER_REVIEW';
    public const STATUS_WAITING_SUPERVISOR_REVIEW = 'WAITING_SUPERVISOR_REVIEW';
    public const STATUS_CLOSED = 'CLOSED';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'closed_at' => 'datetime',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function subtasks()
    {
        return $this->hasMany(Subtask::class, 'ticket_id');
    }

    public function messages()
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id');
    }
}
