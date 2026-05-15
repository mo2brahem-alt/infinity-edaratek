<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolClassroom extends Model
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

    public function students()
    {
        return $this->hasMany(SchoolStudent::class, 'school_classroom_id');
    }

    public function attendances()
    {
        return $this->hasMany(SchoolStudentAttendance::class, 'school_classroom_id');
    }

    public function schedules()
    {
        return $this->hasMany(SchoolClassSchedule::class, 'school_classroom_id');
    }

    public function courseOfferings()
    {
        return $this->hasMany(SchoolCourseOffering::class, 'school_classroom_id');
    }

    public function exams()
    {
        return $this->hasMany(SchoolExam::class, 'school_classroom_id');
    }

    public function teachingAssignments()
    {
        return $this->belongsToMany(
            SchoolTeachingAssignment::class,
            'school_teaching_assignment_classrooms',
            'school_classroom_id',
            'school_teaching_assignment_id'
        )
            ->withPivot('school_id')
            ->withTimestamps();
    }
}
