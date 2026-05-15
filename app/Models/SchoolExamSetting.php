<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolExamSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'allow_subject_schedule_slot_overlap' => 'boolean',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
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
