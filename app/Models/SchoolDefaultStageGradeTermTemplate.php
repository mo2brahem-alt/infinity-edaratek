<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolDefaultStageGradeTermTemplate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function grade()
    {
        return $this->belongsTo(SchoolDefaultStageGradeTemplate::class, 'school_default_stage_grade_template_id');
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
