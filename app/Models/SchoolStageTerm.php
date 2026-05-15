<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolStageTerm extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function stage()
    {
        return $this->belongsTo(SchoolStage::class, 'school_stage_id');
    }
}
