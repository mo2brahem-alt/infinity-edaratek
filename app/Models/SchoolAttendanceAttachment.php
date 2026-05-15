<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolAttendanceAttachment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date:Y-m-d',
            'uploaded_at' => 'datetime',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function classroom()
    {
        return $this->belongsTo(SchoolClassroom::class, 'school_classroom_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
