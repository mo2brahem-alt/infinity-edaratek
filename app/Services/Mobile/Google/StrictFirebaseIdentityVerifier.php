<?php

namespace App\Services\Mobile\Google;

final class StrictFirebaseIdentityVerifier implements FirebaseIdentityVerifier
{
    public function __construct(private readonly FirebaseGateway $gateway, private readonly \App\Services\Mobile\NativeRateLimiter $limiter) {}

    public function verify(string $token): VerifiedGoogleIdentity
    {
        if ($token === '' || strlen($token) > 16384) {
            $this->invalid();
        }
        $claims = $this->gateway->verifiedClaims($token);
        $project = (string) config('firebase_google.project_id');
        $uid = $claims['sub'] ?? null;
        $firebase = $claims['firebase'] ?? null;
        $google = is_array($firebase) && is_array($firebase['identities'] ?? null) ? ($firebase['identities']['google.com'] ?? null) : null;
        // Kreait returns DateTimeImmutable for registered time claims.
        $instant = static fn ($value): ?int => $value instanceof \DateTimeInterface
            ? $value->getTimestamp() : (is_int($value) ? $value : null);
        $expires = $instant($claims['exp'] ?? null);
        $issued = $instant($claims['iat'] ?? null);
        $authenticated = $instant($claims['auth_time'] ?? null);
        $now = now()->getTimestamp();
        if (($claims['aud'] ?? null) !== $project || ($claims['iss'] ?? null) !== 'https://securetoken.google.com/'.$project
            || ! is_string($uid) || $uid === '' || strlen($uid) > 128
            || $expires === null || $expires <= $now || $issued === null || $issued > $now
            || $authenticated === null || $authenticated > $now || $authenticated > $issued
            || ! is_array($firebase) || ($firebase['sign_in_provider'] ?? null) !== 'google.com'
            || array_key_exists('tenant', $firebase)
            || ($claims['email_verified'] ?? null) !== true || ! is_string($claims['email'] ?? null) || $claims['email'] === ''
            || ! is_array($google) || count($google) !== 1 || ! isset($google[0])
            || ! is_string($google[0]) || $google[0] === '' || strlen($google[0]) > 255) {
            $this->invalid();
        }
        if ($now - $authenticated > (int) config('firebase_google.max_auth_age_seconds', 300)) {
            throw new GoogleAuthFailure('IDENTITY_REAUTHENTICATION_REQUIRED', 403);
        }
        if (($retry = $this->limiter->consumeGoogleProof($project, $uid)) !== null) {
            throw new GoogleAuthFailure('RATE_LIMITED', 429, $retry);
        }
        $user = $this->gateway->userMetadata($uid);
        if (($user['uid'] ?? null) !== $uid || ($user['disabled'] ?? null) !== false
            || ($user['email_verified'] ?? null) !== true || ($user['google_uids'] ?? null) !== $google
            || ! is_int($user['valid_since'] ?? null) || $authenticated < $user['valid_since']) {
            $this->invalid();
        }

        return new VerifiedGoogleIdentity($project, $uid, $google[0]);
    }

    private function invalid(): never
    {
        throw new GoogleAuthFailure('GOOGLE_IDENTITY_INVALID');
    }
}
