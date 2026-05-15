<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolSubject extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'branches' => 'array',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function teacherAssignments()
    {
        return $this->hasMany(SchoolSubjectTeacherAssignment::class, 'school_subject_id');
    }

    public function teachers()
    {
        return $this->belongsToMany(User::class, 'school_subject_teacher_assignments', 'school_subject_id', 'teacher_user_id')
            ->withTimestamps();
    }

    public function schedules()
    {
        return $this->hasMany(SchoolClassSchedule::class, 'school_subject_id');
    }

    public function courseOfferings()
    {
        return $this->hasMany(SchoolCourseOffering::class, 'school_subject_id');
    }

    public function exams()
    {
        return $this->hasMany(SchoolExam::class, 'school_subject_id');
    }

    public function questionBankItems()
    {
        return $this->hasMany(SchoolQuestionBankItem::class, 'school_subject_id');
    }
}
