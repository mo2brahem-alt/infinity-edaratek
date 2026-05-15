<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EducationStage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function schools()
    {
        return $this->belongsToMany(School::class, 'education_stage_school')
            ->withTimestamps();
    }
}
