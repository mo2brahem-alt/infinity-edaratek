<?php

namespace App\Http\Requests\Api\V1;

final class GoogleLoginRequest extends ContractRequest
{
    public function rules(): array
    {
        return ['firebase_id_token' => ['bail', 'required', 'string', 'max:16384'],
            'device_uuid' => ['bail', 'required', 'string', 'uuid'],
            'device_name' => ['bail', 'required', 'string', 'min:1', 'max:80']];
    }
}
