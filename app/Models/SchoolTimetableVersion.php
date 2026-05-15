<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolTimetableVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function term()
    {
        return $this->belongsTo(SchoolTerm::class, 'school_term_id');
    }

    public function schedules()
    {
        return $this->hasMany(SchoolClassSchedule::class, 'school_timetable_version_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
