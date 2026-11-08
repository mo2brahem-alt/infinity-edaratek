<?php

namespace App\Services\Mobile;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class NativeSanctumAuthenticator
{
    public function __construct(
        private readonly AccountAccessEvaluator $accounts,
        private readonly NativeTokenService $tokens,
    ) {
    }

    public function authenticate(Request $request): ?User
    {
        $plainTextToken = $this->bearerToken($request);

        if ($plainTextToken === null) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($plainTextToken);

        if (! $accessToken instanceof PersonalAccessToken
            || ! $this->tokens->isActiveNative($accessToken)
            || $accessToken->tokenable_type !== (new User())->getMorphClass()) {
            return null;
        }

        $user = $this->accounts->freshUser((int) $accessToken->tokenable_id);

        if (! $user instanceof User) {
            return null;
        }

        // A freshly loaded user and relations prevent a previously issued PAT
        // from retaining an outdated role, permission, activity, or approval state.
        $accessToken->forceFill(['last_used_at' => now()])->save();
        $user->withAccessToken($accessToken);

        return $user;
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (! is_string($header) || preg_match('/^Bearer\\s+([0-9]+\\|[^\\s,]+)$/D', $header, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
