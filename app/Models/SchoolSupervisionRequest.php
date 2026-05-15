<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolSupervisionRequest extends Model
{
    public const STATUS_SUPERVISOR_REQUESTED = 'SUPERVISOR_REQUESTED';
    public const STATUS_MANAGER_APPROVED = 'MANAGER_APPROVED';
    public const STATUS_ACTIVE_ASSOCIATION = 'ACTIVE_ASSOCIATION';
    public const STATUS_MANAGER_REJECTED = 'MANAGER_REJECTED';
    public const STATUS_SUPERVISOR_REJECTED = 'SUPERVISOR_REJECTED';
    public const STATUS_CANCELED = 'CANCELED';

    public const OPEN_STATUSES = [
        self::STATUS_SUPERVISOR_REQUESTED,
        self::STATUS_MANAGER_APPROVED,
        self::STATUS_ACTIVE_ASSOCIATION,
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'manager_action_at' => 'datetime',
            'supervisor_confirmed_at' => 'datetime',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function region()
    {
        return $this->belongsTo(EducationalDirectorate::class, 'region_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
