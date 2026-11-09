<?php

namespace App\Http\Requests\Api\V1;

final class NativeLoginRequest extends ContractRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['bail', 'required', 'string', 'email', 'max:255'],
            'password' => ['bail', 'required', 'string', 'max:255'],
            'device_uuid' => ['bail', 'required', 'string', 'uuid'],
            'device_name' => ['bail', 'required', 'string', 'min:1', 'max:80'],

            // Tenant and privilege claims are never accepted from the device.
            'school_id' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'role' => ['prohibited'],
            'roles' => ['prohibited'],
            'permissions' => ['prohibited'],
            'abilities' => ['prohibited'],
            'is_active' => ['prohibited'],
            'approval_status' => ['prohibited'],
            'expires_at' => ['prohibited'],
        ];
    }

}
