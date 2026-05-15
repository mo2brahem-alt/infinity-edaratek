<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolCoursePlanLesson extends Model
{
    protected $guarded = [];

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function unit()
    {
        return $this->belongsTo(SchoolCoursePlanUnit::class, 'school_course_plan_unit_id');
    }

    public function topics()
    {
        return $this->hasMany(SchoolCoursePlanTopic::class, 'school_course_plan_lesson_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
