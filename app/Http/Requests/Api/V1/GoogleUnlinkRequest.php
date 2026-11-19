<?php

namespace App\Http\Requests\Api\V1;

final class GoogleUnlinkRequest extends ContractRequest
{
    public function rules(): array
    {
        return ['current_password' => ['bail', 'required', 'string', 'max:255']];
    }
}
