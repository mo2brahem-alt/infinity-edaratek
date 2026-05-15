<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolExamQuestion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'score' => 'decimal:2',
            'is_required' => 'boolean',
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

    public function question()
    {
        return $this->belongsTo(SchoolQuestionBankItem::class, 'school_question_bank_item_id');
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
