<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EducationalDirectorate extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function governorateModel()
    {
        return $this->belongsTo(Governorate::class, 'governorate_id');
    }

    public function educationType()
    {
        return $this->belongsTo(EducationType::class, 'education_type_id');
    }

    public function schools()
    {
        return $this->hasMany(School::class, 'directorate_id');
    }

    public function supervisorAssignments()
    {
        return $this->hasMany(SchoolSupervisorAssignment::class, 'directorate_id');
    }

    public function supervisionRequests()
    {
        return $this->hasMany(SchoolSupervisionRequest::class, 'region_id');
    }
}
