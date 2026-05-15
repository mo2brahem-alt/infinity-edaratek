<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolCoursePlanTopic extends Model
{
    protected $guarded = [];

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function lesson()
    {
        return $this->belongsTo(SchoolCoursePlanLesson::class, 'school_course_plan_lesson_id');
    }
}
