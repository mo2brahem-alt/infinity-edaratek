<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentRole extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'can_manage_student_structure' => 'boolean',
            'can_manage_student_attendance' => 'boolean',
            'can_manage_academic_planning' => 'boolean',
            'can_manage_student_leaves' => 'boolean',
            'can_manage_leave_types' => 'boolean',
            'can_manage_school_calendar' => 'boolean',
            'can_manage_school_holidays' => 'boolean',
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'department_role_id');
    }

    public function orgStructureRoleTemplate()
    {
        return $this->belongsTo(OrgStructureRoleTemplate::class, 'org_structure_role_template_id');
    }
}
