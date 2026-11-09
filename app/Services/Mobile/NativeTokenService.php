<?php

namespace App\Services\Mobile;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

final class NativeTokenService
{
    public const ABILITY = 'native:access';
    private const NAME_PREFIX = 'native:';

    public function issue(User $user, string $deviceUuid, string $deviceName): NewAccessToken
    {
        $expiresAt = now()->addMinutes((int) config('sanctum.native_expiration', config('mobile.native_token_expiration_minutes')));

        return $user->createToken(
            self::NAME_PREFIX.$deviceUuid.':'.$deviceName,
            [self::ABILITY],
            $expiresAt,
        );
    }

    public function isNative(PersonalAccessToken $token): bool
    {
        $parts = explode(':', (string) $token->name, 3);

        return count($parts) === 3
            && $parts[0] === 'native'
            && Str::isUuid($parts[1])
            && $parts[2] !== ''
            && array_values((array) $token->abilities) === [self::ABILITY]
            && $token->created_at !== null
            && $token->expires_at !== null;
    }

    public function isActiveNative(PersonalAccessToken $token): bool
    {
        return $this->isNative($token) && $token->expires_at->isFuture();
    }

    public function deviceName(PersonalAccessToken $token): string
    {
        return explode(':', (string) $token->name, 3)[2] ?? '';
    }

    /** @return MorphMany<PersonalAccessToken, User> */
    public function nativeTokens(User $user): MorphMany
    {
        return $user->tokens()
            ->whereNotNull('created_at')
            // The normal MySQL collation is case-insensitive. The binary
            // expression keeps an unrelated token called "NATIVE:..." out of
            // the native lifecycle and its deletion path.
            ->whereRaw('BINARY `name` REGEXP ?', [
                '^native:[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}:.+$',
            ])
            ->whereJsonContains('abilities', self::ABILITY)
            ->whereJsonLength('abilities', 1);
    }
}
