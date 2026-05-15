<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolStudent extends Model
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

    public function classroom()
    {
        return $this->belongsTo(SchoolClassroom::class, 'school_classroom_id');
    }

    public function attendances()
    {
        return $this->hasMany(SchoolStudentAttendance::class, 'school_student_id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(SchoolStudentLeaveRequest::class, 'school_student_id');
    }

    public function examScores()
    {
        return $this->hasMany(SchoolExamStudentScore::class, 'school_student_id');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function certificates()
    {
        return $this->hasMany(StudentCertificate::class, 'school_student_id');
    }
}
