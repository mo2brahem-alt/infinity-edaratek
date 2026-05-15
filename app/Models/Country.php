<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $guarded = [];

    public function governorates()
    {
        return $this->hasMany(Governorate::class, 'country_id');
    }

    public function directorates()
    {
        return $this->hasMany(EducationalDirectorate::class, 'country_id');
    }
}
