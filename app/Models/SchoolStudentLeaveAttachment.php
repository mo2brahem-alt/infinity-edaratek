<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolStudentLeaveAttachment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function leaveRequest()
    {
        return $this->belongsTo(SchoolStudentLeaveRequest::class, 'school_student_leave_request_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

