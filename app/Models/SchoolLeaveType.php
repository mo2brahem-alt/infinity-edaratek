<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolLeaveType extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requires_attachment' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public const CATEGORY_STUDENT = 'STUDENT';

    public static function allowedCategories(): array
    {
        return [
            self::CATEGORY_STUDENT,
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(SchoolStudentLeaveRequest::class, 'school_leave_type_id');
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
