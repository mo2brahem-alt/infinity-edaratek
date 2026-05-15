<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolExamStatusLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
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

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
