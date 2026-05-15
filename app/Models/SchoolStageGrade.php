<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolStageGrade extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function stage()
    {
        return $this->belongsTo(SchoolStage::class, 'school_stage_id');
    }

    public function courseOfferings()
    {
        return $this->hasMany(SchoolCourseOffering::class, 'school_stage_grade_id');
    }

    public function gradeTerms()
    {
        return $this->hasMany(SchoolStageGradeTerm::class, 'school_stage_grade_id');
    }
}
