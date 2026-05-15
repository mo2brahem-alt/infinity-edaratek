<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolStage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'school_day_start_time' => 'string',
            'school_day_end_time' => 'string',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function classrooms()
    {
        return $this->hasMany(SchoolClassroom::class, 'school_stage_id');
    }

    public function grades()
    {
        return $this->hasMany(SchoolStageGrade::class, 'school_stage_id');
    }

    public function stageTerms()
    {
        return $this->hasMany(SchoolStageTerm::class, 'school_stage_id');
    }

    public function schedules()
    {
        return $this->hasMany(SchoolClassSchedule::class, 'school_stage_id');
    }
}
