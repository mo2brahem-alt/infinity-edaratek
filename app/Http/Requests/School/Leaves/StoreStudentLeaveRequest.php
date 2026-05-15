<?php

namespace App\Http\Requests\School\Leaves;

use App\Models\SchoolStudentLeaveRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreStudentLeaveRequest extends FormRequest
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
                'required',
                'integer',
                Rule::exists('school_students', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('is_active', true)),
            ],
            'school_leave_type_id' => [
                'required',
                'integer',
                Rule::exists('school_leave_types', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('is_active', true)),
            ],
            'source' => ['required', Rule::in(SchoolStudentLeaveRequest::allowedSources())],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in([SchoolStudentLeaveRequest::STATUS_PENDING])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $source = strtoupper((string) $this->input('source', ''));
            if ($source !== SchoolStudentLeaveRequest::SOURCE_RETROACTIVE) {
                return;
            }

            if (!$this->filled('start_date') || !$this->filled('end_date')) {
                return;
            }

            try {
                $startDate = Carbon::parse((string) $this->input('start_date'))->startOfDay();
                $endDate = Carbon::parse((string) $this->input('end_date'))->startOfDay();
            } catch (\Throwable) {
                return;
            }

            $today = now()->startOfDay();
            if ($endDate->greaterThan($today)) {
                $validator->errors()->add('end_date', 'Retroactive leave must be for past dates only.');
            }

            $maxDays = max(0, (int) config('features.student_leaves.retroactive_max_days', 30));
            $requestedDays = $startDate->diffInDays($endDate) + 1;
            if ($maxDays > 0 && $requestedDays > $maxDays) {
                $validator->errors()->add('end_date', "Retroactive leave cannot exceed {$maxDays} days.");
            }

            $graceDays = max(0, (int) config('features.student_leaves.retroactive_grace_days', 30));
            if ($graceDays > 0) {
                $oldestAllowed = $today->copy()->subDays($graceDays);
                if ($endDate->lessThan($oldestAllowed)) {
                    $validator->errors()->add(
                        'end_date',
                        "Retroactive leave must be within the last {$graceDays} days."
                    );
                }
            }
        });
    }
}

