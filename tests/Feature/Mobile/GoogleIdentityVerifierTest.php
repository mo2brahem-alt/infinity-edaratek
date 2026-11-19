<?php

namespace Tests\Feature\Mobile;

use App\Services\Mobile\Google\FirebaseGateway;
use App\Services\Mobile\Google\FirebaseSigningKeys;
use App\Services\Mobile\Google\GoogleAuthFailure;
use App\Services\Mobile\Google\KreaitFirebaseGateway;
use App\Services\Mobile\Google\StrictFirebaseIdentityVerifier;
use App\Services\Mobile\NativeRateLimiter;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class GoogleIdentityVerifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'firebase_google.enabled' => true]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    private function claims(): array
    {
        return ['aud' => config('firebase_google.project_id'), 'iss' => 'https://securetoken.google.com/'.config('firebase_google.project_id'),
            'sub' => 'synthetic-firebase-uid', 'exp' => time() + 3600, 'iat' => time(), 'auth_time' => time(),
            'email' => 'synthetic@example.test', 'email_verified' => true,
            'firebase' => ['sign_in_provider' => 'google.com', 'identities' => ['google.com' => ['synthetic-google-uid']]]];
    }

    private function gateway(array $claims, array $changes = []): FirebaseGateway
    {
        return new class($claims, $changes) implements FirebaseGateway
        {
            public function __construct(private array $claims, private array $changes) {}

            public function verifiedClaims(string $token): array
            {
                return $this->claims;
            }

            public function userMetadata(string $uid): array
            {
                return array_replace(['uid' => 'synthetic-firebase-uid', 'disabled' => false,
                    'email_verified' => true, 'google_uids' => ['synthetic-google-uid'], 'valid_since' => 0], $this->changes);
            }
        };
    }

    public static function invalidClaims(): array
    {
        return ['audience' => ['aud', 'another-project'], 'audience-type' => ['aud', ['project']],
            'issuer' => ['iss', 'https://accounts.google.com'], 'empty-subject' => ['sub', ''],
            'subject-type' => ['sub', 7], 'expiry-type' => ['exp', 'later'],
            'expired' => ['exp', 1], 'future-issued' => ['iat', PHP_INT_MAX],
            'future-auth' => ['auth_time', PHP_INT_MAX], 'unverified' => ['email_verified', false],
            'missing-email' => ['email', null], 'provider' => ['firebase', ['sign_in_provider' => 'password']],
            'tenant' => ['firebase', ['sign_in_provider' => 'google.com', 'tenant' => 'foreign', 'identities' => ['google.com' => ['synthetic-google-uid']]]],
            'malformed-provider-map' => ['firebase', ['sign_in_provider' => 'google.com', 'identities' => 'invalid']]];
    }

    #[DataProvider('invalidClaims')]
    public function test_rejects_invalid_signed_claims(string $field, mixed $value): void
    {
        $claims = $this->claims();
        $claims[$field] = $value;
        $this->expectException(GoogleAuthFailure::class);
        (new StrictFirebaseIdentityVerifier($this->gateway($claims), app(NativeRateLimiter::class)))->verify('synthetic-proof');
    }

    public function test_valid_identity_requires_current_provider_and_unrevoked_user(): void
    {
        $claims = $this->claims();
        $verified = (new StrictFirebaseIdentityVerifier($this->gateway($claims), app(NativeRateLimiter::class)))->verify('synthetic-proof');
        $this->assertSame('synthetic-google-uid', $verified->googleUid);
        foreach ([['disabled' => true], ['google_uids' => ['different']], ['uid' => 'different'],
            ['valid_since' => time() + 10], ['email_verified' => false]] as $changes) {
            Cache::flush();
            try {
                (new StrictFirebaseIdentityVerifier($this->gateway($claims, $changes), app(NativeRateLimiter::class)))->verify('synthetic-proof');
                $this->fail('Invalid current user metadata was accepted');
            } catch (GoogleAuthFailure $failure) {
                $this->assertSame('GOOGLE_IDENTITY_INVALID', $failure->errorCode);
            }
        }
    }

    public function test_token_refresh_cannot_make_old_authentication_recent(): void
    {
        $claims = $this->claims();
        $claims['auth_time'] = time() - 301;
        try {
            (new StrictFirebaseIdentityVerifier($this->gateway($claims), app(NativeRateLimiter::class)))->verify('synthetic-proof');
            $this->fail('Stale authentication was accepted');
        } catch (GoogleAuthFailure $failure) {
            $this->assertSame('IDENTITY_REAUTHENTICATION_REQUIRED', $failure->errorCode);
        }
    }

    public function test_real_rsa_verification_rejects_bad_signature_algorithm_and_unknown_key(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        $this->assertNotFalse($other);
        $public = openssl_pkey_get_details($key)['key'];
        Http::fake(['www.googleapis.com/*' => Http::response(['synthetic-key' => $public], 200, ['Cache-Control' => 'public, max-age=3600'])]);
        $gateway = new KreaitFirebaseGateway(new FirebaseSigningKeys);
        $good = JWT::encode($this->claims(), $key, 'RS256', 'synthetic-key');
        $this->assertSame('synthetic-firebase-uid', $gateway->verifiedClaims($good)['sub']);
        foreach ([JWT::encode($this->claims(), $other, 'RS256', 'synthetic-key'),
            JWT::encode($this->claims(), str_repeat('synthetic', 8), 'HS256', 'synthetic-key'),
            JWT::encode($this->claims(), $key, 'RS256', 'unknown-key'), 'malformed'] as $proof) {
            try {
                $gateway->verifiedClaims($proof);
                $this->fail('Invalid cryptographic proof was accepted');
            } catch (GoogleAuthFailure $failure) {
                $this->assertSame('GOOGLE_IDENTITY_INVALID', $failure->errorCode);
            }
        }
        Http::assertSentCount(1); // Unknown kid refresh attempts are bounded.
    }

    public function test_signing_key_outage_is_safe_and_cannot_accept_a_proof(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response('Synthetic private upstream detail', 503)]);
        $proof = JWT::encode($this->claims(), str_repeat('synthetic', 8), 'HS256', 'synthetic-key');
        // An RS256 header is sufficient to exercise key fetching; signature remains synthetic.
        $parts = explode('.', $proof);
        $parts[0] = JWT::urlsafeB64Encode(json_encode(['alg' => 'RS256', 'kid' => 'synthetic-key']));
        try {
            (new KreaitFirebaseGateway(new FirebaseSigningKeys))->verifiedClaims(implode('.', $parts));
            $this->fail('Outage accepted');
        } catch (GoogleAuthFailure $failure) {
            $this->assertSame('AUTH_PROVIDER_UNAVAILABLE', $failure->errorCode);
            $this->assertSame(503, $failure->status);
            $this->assertStringNotContainsString('private', $failure->getMessage());
        }
    }

    public function test_sdk_user_metadata_and_configuration_fail_closed_without_credentials(): void
    {
        $auth = \Mockery::mock(\Kreait\Firebase\Contract\Auth::class);
        $record = \Kreait\Firebase\Auth\UserRecord::fromResponseData([
            'localId' => 'synthetic-firebase-uid', 'createdAt' => '1700000000000',
            'emailVerified' => true, 'disabled' => false, 'validSince' => '1700000000',
            'providerUserInfo' => [['providerId' => 'google.com', 'rawId' => 'synthetic-google-uid']],
        ]);
        $auth->shouldReceive('getUser')->once()->with('synthetic-firebase-uid')->andReturn($record);
        $metadata = (new KreaitFirebaseGateway(new FirebaseSigningKeys, $auth))->userMetadata('synthetic-firebase-uid');
        $this->assertSame(['synthetic-google-uid'], $metadata['google_uids']);
        $this->assertSame(1700000000, $metadata['valid_since']);
        $this->assertArrayNotHasKey('passwordHash', $metadata);
        config(['firebase_google.enabled' => false]);
        try {
            (new KreaitFirebaseGateway(new FirebaseSigningKeys))->userMetadata('synthetic');
            $this->fail('Unconfigured verifier accepted');
        } catch (GoogleAuthFailure $failure) {
            $this->assertSame('AUTH_PROVIDER_UNAVAILABLE', $failure->errorCode);
        }
    }

    public function test_signed_proof_budget_precedes_expensive_user_lookup(): void
    {
        $gateway = new class($this->claims()) implements FirebaseGateway
        {
            public int $lookups = 0;

            public function __construct(private array $claims) {}

            public function verifiedClaims(string $token): array
            {
                return $this->claims;
            }

            public function userMetadata(string $uid): array
            {
                $this->lookups++;

                return ['uid' => 'synthetic-firebase-uid', 'disabled' => false, 'email_verified' => true,
                    'google_uids' => ['synthetic-google-uid'], 'valid_since' => 0];
            }
        };
        $verifier = new StrictFirebaseIdentityVerifier($gateway, app(NativeRateLimiter::class));
        for ($i = 0; $i < 5; $i++) {
            $verifier->verify('synthetic-proof');
        }
        try {
            $verifier->verify('synthetic-proof');
            $this->fail('Proof budget bypassed');
        } catch (GoogleAuthFailure $failure) {
            $this->assertSame('RATE_LIMITED', $failure->errorCode);
            $this->assertGreaterThan(0, $failure->retryAfter);
        }
        $this->assertSame(5, $gateway->lookups);
    }

    public function test_repository_credentials_and_emulator_configuration_are_rejected(): void
    {
        config(['firebase_google.credentials_file' => base_path('composer.json')]);
        try {
            (new KreaitFirebaseGateway(new FirebaseSigningKeys))->userMetadata('synthetic');
            $this->fail('Repository credentials accepted');
        } catch (GoogleAuthFailure $failure) {
            $this->assertSame('AUTH_PROVIDER_UNAVAILABLE', $failure->errorCode);
        }
        $previous = $_ENV['FIREBASE_AUTH_EMULATOR_HOST'] ?? null;
        $_ENV['FIREBASE_AUTH_EMULATOR_HOST'] = 'localhost:9099';
        try {
            (new KreaitFirebaseGateway(new FirebaseSigningKeys))->verifiedClaims('synthetic-proof');
            $this->fail('Emulator mode accepted');
        } catch (GoogleAuthFailure $failure) {
            $this->assertSame('AUTH_PROVIDER_UNAVAILABLE', $failure->errorCode);
        } finally {
            if ($previous === null) {
                unset($_ENV['FIREBASE_AUTH_EMULATOR_HOST']);
            } else {
                $_ENV['FIREBASE_AUTH_EMULATOR_HOST'] = $previous;
            }
        }
    }
}
