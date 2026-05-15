<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolExamStudentScore extends Model
{
    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_DEPRIVED = 'deprived';
    public const STATUS_POSTPONED = 'postponed';
    public const STATUS_RETAKE = 'retake';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'is_finalized' => 'boolean',
            'recorded_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public static function allowedAttendanceStatuses(): array
    {
        return [
            self::STATUS_PRESENT,
            self::STATUS_ABSENT,
            self::STATUS_DEPRIVED,
            self::STATUS_POSTPONED,
            self::STATUS_RETAKE,
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function exam()
    {
        return $this->belongsTo(SchoolExam::class, 'school_exam_id');
    }

    public function student()
    {
        return $this->belongsTo(SchoolStudent::class, 'school_student_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
