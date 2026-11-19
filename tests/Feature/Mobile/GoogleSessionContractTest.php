<?php

namespace Tests\Feature\Mobile;

use App\Models\ExternalIdentity;
use App\Models\User;
use App\Services\Mobile\Google\FirebaseIdentityVerifier;
use App\Services\Mobile\Google\GoogleAuthFailure;
use App\Services\Mobile\Google\GoogleIdentityService;
use App\Services\Mobile\Google\VerifiedGoogleIdentity;
use App\Services\Mobile\NativeTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

final class GoogleSessionContractTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Synthetic test-only password';

    private int $verifications = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
        Http::preventStrayRequests();
        $this->app->instance(FirebaseIdentityVerifier::class, new class($this) implements FirebaseIdentityVerifier
        {
            public function __construct(private GoogleSessionContractTest $test) {}

            public function verify(string $token): VerifiedGoogleIdentity
            {
                return $this->test->fakeVerify($token);
            }
        });
    }

    public function fakeVerify(string $proof): VerifiedGoogleIdentity
    {
        $this->verifications++;
        if ($proof === 'rejected') {
            throw new GoogleAuthFailure('GOOGLE_IDENTITY_INVALID');
        }
        if ($proof === 'unavailable') {
            throw new GoogleAuthFailure('AUTH_PROVIDER_UNAVAILABLE', 503);
        }

        return new VerifiedGoogleIdentity(config('firebase_google.project_id'), 'synthetic-firebase-uid', 'synthetic-google-uid');
    }

    private function user(array $changes = []): User
    {
        return User::factory()->create(array_replace(['role' => 'staff', 'is_active' => true,
            'approval_status' => 'approved', 'password' => Hash::make(self::PASSWORD)], $changes));
    }

    private function native(User $user): string
    {
        return app(NativeTokenService::class)->issue($user, (string) Str::uuid(), 'Synthetic UAT')->plainTextToken;
    }

    private function body(array $changes = []): array
    {
        return array_replace(['firebase_id_token' => 'synthetic-proof', 'device_uuid' => (string) Str::uuid(), 'device_name' => 'Synthetic UAT'], $changes);
    }

    private function link(User $user): void
    {
        app(GoogleIdentityService::class)->link($user->id, self::PASSWORD, $this->fakeVerify('synthetic-proof'));
    }

    public function test_email_match_without_explicit_link_never_creates_or_authenticates_a_user(): void
    {
        $this->user(['email' => 'synthetic@example.test']);
        $this->postJson('/api/v1/auth/google/login', $this->body())->assertForbidden()->assertJsonPath('code', 'GOOGLE_ACCOUNT_NOT_LINKED');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_explicit_link_is_idempotent_and_login_reuses_the_native_contract(): void
    {
        $user = $this->user();
        $passwordSession = $this->native($user);
        $this->withToken($passwordSession)->getJson('/api/v1/auth/google/link')->assertOk()->assertExactJson(['data' => ['linked' => false, 'provider' => 'google.com', 'linked_at' => null]]);
        for ($i = 0; $i < 2; $i++) {
            $this->withToken($passwordSession)->putJson('/api/v1/auth/google/link', ['current_password' => self::PASSWORD, 'firebase_id_token' => 'synthetic-proof'])
                ->assertOk()->assertJsonPath('data.linked', true)->assertJsonMissingPath('data.firebase_uid');
        }
        $this->assertDatabaseCount('external_identities', 1);
        $response = $this->postJson('/api/v1/auth/google/login', $this->body())->assertOk()->assertJsonPath('data.user.id', $user->id)->assertJsonPath('data.token_type', 'Bearer');
        $issued = PersonalAccessToken::findToken($response->json('data.access_token'));
        $this->assertNotNull($issued->external_identity_id);
        $this->assertSame(['native:access'], $issued->abilities);
        $this->assertTrue($issued->expires_at->isFuture());
        $this->withToken($response->json('data.access_token'))->getJson('/api/v1/me')->assertOk();
    }

    public function test_link_requires_both_native_ownership_and_password_proof(): void
    {
        $user = $this->user();
        $data = ['current_password' => self::PASSWORD, 'firebase_id_token' => 'synthetic-proof'];
        $this->putJson('/api/v1/auth/google/link', $data)->assertUnauthorized();
        $before = $this->verifications;
        $this->withToken($this->native($user))->putJson('/api/v1/auth/google/link', array_replace($data, ['current_password' => 'wrong']))
            ->assertForbidden()->assertJsonPath('code', 'REAUTHENTICATION_FAILED');
        $this->assertSame($before, $this->verifications);
        $this->assertDatabaseCount('external_identities', 0);
    }

    public function test_foreign_identity_and_client_authority_are_rejected(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $this->link($owner);
        $token = $this->native($other);
        $this->withToken($token)->putJson('/api/v1/auth/google/link', ['current_password' => self::PASSWORD, 'firebase_id_token' => 'synthetic-proof'])
            ->assertConflict()->assertJsonPath('code', 'IDENTITY_LINK_CONFLICT');
        $this->postJson('/api/v1/auth/google/login', $this->body(['school_id' => null, 'role' => 'super_admin']))->assertUnprocessable();
        $this->withToken($token)->getJson('/api/v1/auth/google/link?user_id='.$owner->id)->assertUnprocessable();
    }

    public function test_unlink_revokes_only_sessions_from_that_identity_and_preserves_password_sessions(): void
    {
        $user = $this->user();
        $password = $this->native($user);
        $this->link($user);
        $google = $this->postJson('/api/v1/auth/google/login', $this->body())->assertOk()->json('data.access_token');
        $this->withToken($google)->deleteJson('/api/v1/auth/google/link', ['current_password' => self::PASSWORD])->assertNoContent();
        $this->assertNull(PersonalAccessToken::findToken($google));
        $this->assertNotNull(PersonalAccessToken::findToken($password));
        $this->withToken($google)->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken($password)->getJson('/api/v1/me')->assertOk();
        $this->assertDatabaseCount('external_identities', 0);
    }

    public function test_current_account_restrictions_apply_after_linking(): void
    {
        foreach ([['role' => 'school_manager', 'approval_status' => 'pending_approval'], ['role' => 'supervisor', 'approval_status' => 'rejected'], ['is_active' => false]] as $changes) {
            Cache::flush();
            $user = $this->user();
            $this->link($user);
            $user->forceFill($changes)->save();
            $this->postJson('/api/v1/auth/google/login', $this->body())->assertForbidden();
            ExternalIdentity::query()->delete();
        }
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_verifier_failures_are_safe_and_never_issue_credentials(): void
    {
        $this->postJson('/api/v1/auth/google/login', $this->body(['firebase_id_token' => 'rejected']))->assertUnauthorized()->assertJsonPath('code', 'GOOGLE_IDENTITY_INVALID');
        $this->postJson('/api/v1/auth/google/login', $this->body(['firebase_id_token' => 'unavailable']))->assertStatus(503)->assertJsonPath('code', 'AUTH_PROVIDER_UNAVAILABLE');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_verified_identity_and_ip_limits_stop_repeated_expensive_requests(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/google/login', $this->body())->assertForbidden();
        }
        $limited = $this->postJson('/api/v1/auth/google/login', $this->body())->assertStatus(429)->assertJsonPath('code', 'RATE_LIMITED');
        $this->assertSame((string) $limited->json('retry_after'), $limited->headers->get('Retry-After'));
        Cache::flush();
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/v1/auth/google/login', [])->assertUnprocessable();
        }
        $before = $this->verifications;
        $this->postJson('/api/v1/auth/google/login', $this->body())->assertStatus(429);
        $this->assertSame($before, $this->verifications);
    }

    public function test_google_and_password_login_share_the_existing_account_budget(): void
    {
        $user = $this->user();
        $this->link($user);
        $body = ['email' => $user->email, 'password' => self::PASSWORD, 'device_uuid' => (string) Str::uuid(), 'device_name' => 'Synthetic password client'];
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/login', $body)->assertOk();
        }
        $this->postJson('/api/v1/auth/google/login', $this->body())->assertOk();
        $this->postJson('/api/v1/auth/login', $body)->assertStatus(429);
        $this->assertDatabaseCount('personal_access_tokens', 5);
    }

    public function test_production_binding_without_server_configuration_fails_closed(): void
    {
        $this->app->forgetInstance(FirebaseIdentityVerifier::class);
        $this->app->bind(FirebaseIdentityVerifier::class, \App\Services\Mobile\Google\StrictFirebaseIdentityVerifier::class);
        config(['firebase_google.enabled' => false]);
        $this->postJson('/api/v1/auth/google/login', $this->body())->assertStatus(503)->assertJsonPath('code', 'AUTH_PROVIDER_UNAVAILABLE');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
