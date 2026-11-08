<?php

namespace App\Http\Requests\Mobile;

use App\Models\Subtask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StaffTaskListRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'required', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'required', 'string', 'max:100'],
            'status' => ['sometimes', 'required', Rule::in([Subtask::STATUS_OPEN, Subtask::STATUS_IN_PROGRESS, Subtask::STATUS_SUBMITTED, Subtask::STATUS_APPROVED])],
            'sort' => ['sometimes', 'required', Rule::in(['id', '-id', 'due_date', '-due_date'])],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            foreach (array_diff(array_keys($this->all()), ['page', 'per_page', 'search', 'status', 'sort']) as $key) {
                $validator->errors()->add($key, 'This filter is not supported.');
            }
        }];
    }
}
