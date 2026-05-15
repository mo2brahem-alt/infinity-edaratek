<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolQuestionOption extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function question()
    {
        return $this->belongsTo(SchoolQuestionBankItem::class, 'school_question_bank_item_id');
    }
}
