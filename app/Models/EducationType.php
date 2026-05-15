<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EducationType extends Model
{
    protected $guarded = [];

    public function directorates()
    {
        return $this->hasMany(EducationalDirectorate::class, 'education_type_id');
    }
}
