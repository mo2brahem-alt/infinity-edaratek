<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolCourseOffering extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'usable_in_exams' => 'boolean',
            'alert_before_term_end_days' => 'integer',
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

    public function stage()
    {
        return $this->belongsTo(SchoolStage::class, 'school_stage_id');
    }

    public function stageGrade()
    {
        return $this->belongsTo(SchoolStageGrade::class, 'school_stage_grade_id');
    }

    public function classroom()
    {
        return $this->belongsTo(SchoolClassroom::class, 'school_classroom_id');
    }

    public function subject()
    {
        return $this->belongsTo(SchoolSubject::class, 'school_subject_id');
    }

    public function teachingAssignment()
    {
        return $this->hasOne(SchoolTeachingAssignment::class, 'school_course_offering_id');
    }

    public function studyPlanUnits()
    {
        return $this->hasMany(SchoolCoursePlanUnit::class, 'school_course_offering_id')
            ->orderBy('sort_order')
            ->orderBy('id');
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
