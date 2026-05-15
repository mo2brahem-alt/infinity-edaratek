<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolDefaultStageTemplate extends Model
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

    public function grades()
    {
        return $this->hasMany(SchoolDefaultStageGradeTemplate::class, 'school_default_stage_template_id');
    }

    public function classrooms()
    {
        return $this->hasMany(SchoolDefaultClassroomTemplate::class, 'school_default_stage_template_id');
    }

    public function stageTerms()
    {
        return $this->hasMany(SchoolDefaultStageTermTemplate::class, 'school_default_stage_template_id');
    }

    public function educationStage()
    {
        return $this->belongsTo(EducationStage::class, 'education_stage_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function educationType()
    {
        return $this->belongsTo(EducationType::class, 'education_type_id');
    }

    public function directorate()
    {
        return $this->belongsTo(EducationalDirectorate::class, 'directorate_id');
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
