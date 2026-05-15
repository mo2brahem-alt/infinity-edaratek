<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolSupervisorAssignment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function directorate()
    {
        return $this->belongsTo(EducationalDirectorate::class, 'directorate_id');
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }
}
