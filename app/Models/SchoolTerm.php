<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolTerm extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(SchoolAcademicYear::class, 'school_academic_year_id');
    }

    public function schedules()
    {
        return $this->hasMany(SchoolClassSchedule::class, 'school_term_id');
    }

    public function timetableVersions()
    {
        return $this->hasMany(SchoolTimetableVersion::class, 'school_term_id');
    }

    public function courseOfferings()
    {
        return $this->hasMany(SchoolCourseOffering::class, 'school_term_id');
    }

    public function exams()
    {
        return $this->hasMany(SchoolExam::class, 'school_term_id');
    }
}
