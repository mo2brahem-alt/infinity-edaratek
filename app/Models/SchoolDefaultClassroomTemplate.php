<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolDefaultClassroomTemplate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function stage()
    {
        return $this->belongsTo(SchoolDefaultStageTemplate::class, 'school_default_stage_template_id');
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
