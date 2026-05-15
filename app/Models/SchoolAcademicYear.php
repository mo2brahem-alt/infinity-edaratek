<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolAcademicYear extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function terms()
    {
        return $this->hasMany(SchoolTerm::class, 'school_academic_year_id');
    }
}
