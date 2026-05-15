<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolStageGradeTerm extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function grade()
    {
        return $this->belongsTo(SchoolStageGrade::class, 'school_stage_grade_id');
    }
}
