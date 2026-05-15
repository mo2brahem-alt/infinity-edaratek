<?php

namespace App\Http\Requests\School\Leaves;

use App\Models\SchoolStudentLeaveRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListStudentLeavesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-student-leaves');
    }

    public function rules(): array
    {
        $schoolId = (int) ($this->user()?->school_id ?? 0);

        return [
            'school_student_id' => [
                'nullable',
                'integer',
                Rule::exists('school_students', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_stage_id' => [
                'nullable',
                'integer',
                Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_classroom_id' => [
                'nullable',
                'integer',
                Rule::exists('school_classrooms', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'classroom_grade_name' => ['nullable', 'string', 'max:100'],
            'school_leave_type_id' => [
                'nullable',
                'integer',
                Rule::exists('school_leave_types', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'status' => ['nullable', Rule::in(SchoolStudentLeaveRequest::allowedStatuses())],
            'source' => ['nullable', Rule::in(SchoolStudentLeaveRequest::allowedSources())],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
