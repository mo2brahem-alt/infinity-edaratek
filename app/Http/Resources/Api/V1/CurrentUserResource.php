<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use App\Services\Mobile\AccountAccessEvaluator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class CurrentUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $accounts = app(AccountAccessEvaluator::class);
        $school = app(\App\Services\School\SchoolContextResolver::class)->identitySchool($user);

        return [
            'id' => (int) $user->getKey(),
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'roles' => $accounts->roles($user),
            'capabilities' => $accounts->capabilities($user),
            'is_active' => (bool) $user->is_active,
            'approval_required' => $accounts->approvalRequired($user),
            'approval_status' => $user->approval_status,
            'school' => $school === null ? null : [
                'id' => (int) $school->getKey(),
                'name' => (string) $school->name,
            ],
            // Reuse the existing pure phone utility; no messaging/OTP service.
            'phone_verified' => $user->phone_verified_at !== null
                && app(\App\Services\WhatsApp\PhoneNumberNormalizer::class)->normalize($user->mobile ?: $user->phone) !== null,
        ];
    }
}
