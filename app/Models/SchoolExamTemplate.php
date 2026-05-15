<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolExamTemplate extends Model
{
    public const TYPE_WEEKLY = 'weekly';
    public const TYPE_MONTHLY = 'monthly';
    public const TYPE_MIDTERM = 'midterm';
    public const TYPE_FINAL_TERM = 'final_term';
    public const TYPE_FINAL_YEAR = 'final_year';
    public const TYPE_ORAL = 'oral';
    public const TYPE_PRACTICAL = 'practical';
    public const TYPE_CUSTOM = 'custom';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'default_max_score' => 'decimal:2',
            'default_passing_score' => 'decimal:2',
            'requires_approval' => 'boolean',
            'teacher_can_override_max_score' => 'boolean',
            'teacher_can_override_passing_score' => 'boolean',
            'affects_final_result' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public static function allowedTypes(): array
    {
        return [
            self::TYPE_WEEKLY,
            self::TYPE_MONTHLY,
            self::TYPE_MIDTERM,
            self::TYPE_FINAL_TERM,
            self::TYPE_FINAL_YEAR,
            self::TYPE_ORAL,
            self::TYPE_PRACTICAL,
            self::TYPE_CUSTOM,
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function exams()
    {
        return $this->hasMany(SchoolExam::class, 'school_exam_template_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
