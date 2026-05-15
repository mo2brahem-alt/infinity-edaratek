<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolStudentLeaveRequest extends Model
{
    public const SOURCE_PRE_APPROVED = 'PRE_APPROVED';
    public const SOURCE_RETROACTIVE = 'RETROACTIVE';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function allowedSources(): array
    {
        return [
            self::SOURCE_PRE_APPROVED,
            self::SOURCE_RETROACTIVE,
        ];
    }

    public static function allowedStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
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

    public function leaveType()
    {
        return $this->belongsTo(SchoolLeaveType::class, 'school_leave_type_id');
    }

    public function attachments()
    {
        return $this->hasMany(SchoolStudentLeaveAttachment::class, 'school_student_leave_request_id');
    }

    public function attendanceRecords()
    {
        return $this->hasMany(SchoolStudentAttendance::class, 'school_student_leave_request_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
