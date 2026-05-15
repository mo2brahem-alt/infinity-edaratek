<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolCoursePlanUnit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function courseOffering()
    {
        return $this->belongsTo(SchoolCourseOffering::class, 'school_course_offering_id');
    }

    public function lessons()
    {
        return $this->hasMany(SchoolCoursePlanLesson::class, 'school_course_plan_unit_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
