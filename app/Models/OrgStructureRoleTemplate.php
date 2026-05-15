<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrgStructureRoleTemplate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function departmentRoles()
    {
        return $this->hasMany(DepartmentRole::class, 'org_structure_role_template_id');
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

