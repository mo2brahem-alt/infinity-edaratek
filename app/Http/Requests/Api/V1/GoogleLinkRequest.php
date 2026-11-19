<?php

namespace App\Http\Requests\Api\V1;

final class GoogleLinkRequest extends ContractRequest
{
    public function rules(): array
    {
        return ['firebase_id_token' => ['bail', 'required', 'string', 'max:16384'],
            'current_password' => ['bail', 'required', 'string', 'max:255']];
    }
}
