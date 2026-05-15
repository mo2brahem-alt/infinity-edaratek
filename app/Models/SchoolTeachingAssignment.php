<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolTeachingAssignment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'can_create_exam' => 'boolean',
            'can_update_exam' => 'boolean',
            'can_delete_exam' => 'boolean',
            'can_approve_exam' => 'boolean',
            'can_enter_exam_scores' => 'boolean',
            'can_edit_exam_scores' => 'boolean',
            'can_use_question_bank' => 'boolean',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function courseOffering()
    {
        return $this->belongsTo(SchoolCourseOffering::class, 'school_course_offering_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    public function classrooms()
    {
        return $this->belongsToMany(
            SchoolClassroom::class,
            'school_teaching_assignment_classrooms',
            'school_teaching_assignment_id',
            'school_classroom_id'
        )
            ->withPivot('school_id')
            ->withTimestamps();
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
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
