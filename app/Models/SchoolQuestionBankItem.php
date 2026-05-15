<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolQuestionBankItem extends Model
{
    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';
    public const TYPE_TRUE_FALSE = 'true_false';
    public const TYPE_SHORT_ANSWER = 'short_answer';
    public const TYPE_ESSAY = 'essay';
    public const TYPE_FILL_IN_BLANK = 'fill_in_blank';
    public const TYPE_MATCHING = 'matching';
    public const TYPE_ORDERING = 'ordering';
    public const TYPE_ORAL = 'oral';
    public const TYPE_PRACTICAL = 'practical';

    public const SELECTION_REQUIRED = 'required';
    public const SELECTION_OPTIONAL = 'optional';

    public const DIFFICULTY_EASY = 'easy';
    public const DIFFICULTY_MEDIUM = 'medium';
    public const DIFFICULTY_HARD = 'hard';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'question_score' => 'decimal:2',
            'tags' => 'array',
        ];
    }

    public static function allowedTypes(): array
    {
        return [
            self::TYPE_MULTIPLE_CHOICE,
            self::TYPE_TRUE_FALSE,
            self::TYPE_SHORT_ANSWER,
            self::TYPE_ESSAY,
            self::TYPE_FILL_IN_BLANK,
            self::TYPE_MATCHING,
            self::TYPE_ORDERING,
            self::TYPE_ORAL,
            self::TYPE_PRACTICAL,
        ];
    }

    public static function allowedSelectionModes(): array
    {
        return [
            self::SELECTION_REQUIRED,
            self::SELECTION_OPTIONAL,
        ];
    }

    public static function allowedDifficulties(): array
    {
        return [
            self::DIFFICULTY_EASY,
            self::DIFFICULTY_MEDIUM,
            self::DIFFICULTY_HARD,
        ];
    }

    public static function allowedStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_ACTIVE,
            self::STATUS_ARCHIVED,
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

    public function subject()
    {
        return $this->belongsTo(SchoolSubject::class, 'school_subject_id');
    }

    public function stage()
    {
        return $this->belongsTo(SchoolStage::class, 'school_stage_id');
    }

    public function term()
    {
        return $this->belongsTo(SchoolTerm::class, 'school_term_id');
    }

    public function options()
    {
        return $this->hasMany(SchoolQuestionOption::class, 'school_question_bank_item_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function examQuestions()
    {
        return $this->hasMany(SchoolExamQuestion::class, 'school_question_bank_item_id');
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
