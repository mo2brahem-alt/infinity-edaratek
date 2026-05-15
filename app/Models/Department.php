<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    public const STAFF_TYPE_ADMINISTRATIVE = 'ADMINISTRATIVE';
    public const STAFF_TYPE_EDUCATIONAL = 'EDUCATIONAL';

    protected $guarded = [];

    public static function allowedStaffTypes(): array
    {
        return [
            self::STAFF_TYPE_ADMINISTRATIVE,
            self::STAFF_TYPE_EDUCATIONAL,
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function roles()
    {
        return $this->hasMany(DepartmentRole::class, 'department_id');
    }
}
