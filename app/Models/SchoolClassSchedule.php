<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class SchoolClassSchedule extends Model
{
    public const SCOPE_WEEKLY = 'WEEKLY';
    public const SCOPE_MONTHLY = 'MONTHLY';
    public const SCOPE_TERM = 'TERM';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'day_of_month' => 'integer',
            'session_date' => 'date:Y-m-d',
            'session_index' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function allowedScopes(): array
    {
        return [
            self::SCOPE_WEEKLY,
            self::SCOPE_MONTHLY,
            self::SCOPE_TERM,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function term()
    {
        return $this->belongsTo(SchoolTerm::class, 'school_term_id');
    }

    public function timetableVersion()
    {
        return $this->belongsTo(SchoolTimetableVersion::class, 'school_timetable_version_id');
    }

    public function stage()
    {
        return $this->belongsTo(SchoolStage::class, 'school_stage_id');
    }

    public function classroom()
    {
        return $this->belongsTo(SchoolClassroom::class, 'school_classroom_id');
    }

    public function subject()
    {
        return $this->belongsTo(SchoolSubject::class, 'school_subject_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
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
