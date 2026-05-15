<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolSubjectTeacherAssignment extends Model
{
    protected $guarded = [];

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function subject()
    {
        return $this->belongsTo(SchoolSubject::class, 'school_subject_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }
}
