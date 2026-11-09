<?php

namespace App\Http\Resources\Api\V1;

use App\Services\Mobile\NativeTokenService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/** @mixin PersonalAccessToken */
final class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PersonalAccessToken $token */
        $token = $this->resource;

        return [
            'id' => (int) $token->getKey(),
            'device_name' => app(NativeTokenService::class)->deviceName($token),
            'created_at' => $token->created_at?->utc()->toISOString(),
            'last_used_at' => $token->last_used_at?->utc()->toISOString(),
            'expires_at' => $token->expires_at?->utc()->toISOString(),
            'is_current' => (bool) $token->getAttribute('mobile_is_current'),
        ];
    }
}
