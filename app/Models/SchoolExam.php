<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolExam extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_GRADES_RECORDED = 'grades_recorded';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_POSTPONED = 'postponed';
    public const STATUS_CANCELED = 'canceled';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date:Y-m-d',
            'max_score' => 'decimal:2',
            'passing_score' => 'decimal:2',
            'duration_minutes' => 'integer',
            'requires_approval' => 'boolean',
            'allow_subject_schedule_overlap' => 'boolean',
            'affects_final_result' => 'boolean',
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public static function allowedStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_PUBLISHED,
            self::STATUS_COMPLETED,
            self::STATUS_GRADES_RECORDED,
            self::STATUS_CLOSED,
            self::STATUS_POSTPONED,
            self::STATUS_CANCELED,
        ];
    }

    public static function statusesAllowScoreRecording(): array
    {
        return [
            self::STATUS_APPROVED,
            self::STATUS_PUBLISHED,
            self::STATUS_COMPLETED,
            self::STATUS_GRADES_RECORDED,
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function template()
    {
        return $this->belongsTo(SchoolExamTemplate::class, 'school_exam_template_id');
    }

    public function term()
    {
        return $this->belongsTo(SchoolTerm::class, 'school_term_id');
    }

    public function stage()
    {
        return $this->belongsTo(SchoolStage::class, 'school_stage_id');
    }

    public function classroom()
    {
        return $this->belongsTo(SchoolClassroom::class, 'school_classroom_id');
    }

    public function subject()
    {
        return $this->belongsTo(SchoolSubject::class, 'school_subject_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function questions()
    {
        return $this->hasMany(SchoolExamQuestion::class, 'school_exam_id');
    }

    public function scores()
    {
        return $this->hasMany(SchoolExamStudentScore::class, 'school_exam_id');
    }

    public function statusLogs()
    {
        return $this->hasMany(SchoolExamStatusLog::class, 'school_exam_id');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
