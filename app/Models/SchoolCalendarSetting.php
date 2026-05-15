<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolCalendarSetting extends Model
{
    public const SUNDAY = 0;
    public const MONDAY = 1;
    public const TUESDAY = 2;
    public const WEDNESDAY = 3;
    public const THURSDAY = 4;
    public const FRIDAY = 5;
    public const SATURDAY = 6;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'week_start_day' => 'integer',
            'weekly_off_days' => 'array',
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

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

