<?php

namespace App\Http\Requests\Api\V1;

class NativePaginationRequest extends ContractRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'required', 'integer', 'min:1', 'max:2147483647'],
            'per_page' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function page(): int
    {
        return (int) $this->validated('page', 1);
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 25);
    }
}
