<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolStudentAttendance extends Model
{
    public const STATUS_PRESENT = 'PRESENT';
    public const STATUS_ABSENT = 'ABSENT';
    public const STATUS_EXCUSED = 'EXCUSED';
    public const STATUS_LEAVE = 'LEAVE';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date:Y-m-d',
        ];
    }

    public static function allowedStatuses(): array
    {
        return [
            self::STATUS_PRESENT,
            self::STATUS_ABSENT,
            self::STATUS_EXCUSED,
            self::STATUS_LEAVE,
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function student()
    {
        return $this->belongsTo(SchoolStudent::class, 'school_student_id');
    }

    public function classroom()
    {
        return $this->belongsTo(SchoolClassroom::class, 'school_classroom_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function leaveRequest()
    {
        return $this->belongsTo(SchoolStudentLeaveRequest::class, 'school_student_leave_request_id');
    }
}
