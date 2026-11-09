<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Subtask;
use Illuminate\Validation\Rule;

final class StaffTaskListRequest extends NativePaginationRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return parent::rules() + [
            'search' => ['sometimes', 'required', 'string', 'max:100'],
            'status' => ['sometimes', 'required', Rule::in([Subtask::STATUS_OPEN, Subtask::STATUS_IN_PROGRESS, Subtask::STATUS_SUBMITTED, Subtask::STATUS_APPROVED])],
            'sort' => ['sometimes', 'required', Rule::in(['id', '-id', 'due_date', '-due_date'])],
        ];
    }

}
