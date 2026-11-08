<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use App\Services\Mobile\NativeTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class NativeAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_a_hashed_finite_native_bearer_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/login', $this->credentials($user));

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_at', 'user']]);

        $plainTextToken = (string) $response->json('data.access_token');
        $accessToken = PersonalAccessToken::findOrFail((int) explode('|', $plainTextToken, 2)[0]);

        $this->assertNotSame($plainTextToken, $accessToken->token);
        $this->assertSame(hash('sha256', explode('|', $plainTextToken, 2)[1]), $accessToken->token);
        $this->assertSame([NativeTokenService::ABILITY], $accessToken->abilities);
        $this->assertNotNull($accessToken->expires_at);
        $this->assertTrue($accessToken->expires_at->isFuture());

        $this->native($plainTextToken)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token')
            ->assertJsonMissingPath('data.approval_notes');
    }

    public function test_unknown_and_wrong_credentials_have_the_same_safe_response(): void
    {
        $user = User::factory()->create();
        $unknown = $this->postJson('/api/v1/auth/login', $this->credentials($user, ['email' => 'missing@example.test']));
        $wrong = $this->postJson('/api/v1/auth/login', $this->credentials($user, ['password' => 'incorrect-password']));

        foreach ([$unknown, $wrong] as $response) {
            $response->assertStatus(401)
                ->assertJsonPath('code', 'INVALID_CREDENTIALS')
                ->assertJsonMissing(['data', 'access_token', 'user']);
        }
    }

    public function test_native_routes_reject_web_cookie_query_token_and_non_native_ability(): void
    {
        $nativeUser = User::factory()->create();
        $webUser = User::factory()->create();
        $plainTextToken = $this->issue($nativeUser);

        $this->actingAs($webUser)->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->actingAs($webUser)->getJson('/api/v1/me?access_token='.$plainTextToken)
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->actingAs($webUser)->withHeader('Authorization', 'Bearer '.$plainTextToken)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $nativeUser->id);

        $nonNative = $nativeUser->createToken('integration-client', ['*'], now()->addDay());
        $this->native($nonNative->plainTextToken)->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_expired_deleted_and_malformed_tokens_are_unauthenticated(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $this->issue($user);
        $tokenId = (int) explode('|', $plainTextToken, 2)[0];

        PersonalAccessToken::findOrFail($tokenId)->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->native($plainTextToken)->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->withHeader('Authorization', 'Bearer malformed')->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_login_rejects_tenant_or_privilege_payloads(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', $this->credentials($user, [
            'school_id' => 123,
            'role' => 'super_admin',
        ]))->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['errors' => ['school_id', 'role']]);
    }

    public function test_native_login_rate_limit_returns_retry_after_without_raw_keys(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', $this->credentials($user, ['password' => 'wrong-password']))
                ->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', $this->credentials($user, ['password' => 'wrong-password']))
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('code', 'RATE_LIMITED')
            ->assertJsonMissing(['email', 'ip']);
    }

    /** @param array<string, mixed> $overrides */
    private function credentials(User $user, array $overrides = []): array
    {
        return array_merge([
            'email' => $user->email,
            'password' => 'password',
            'device_uuid' => (string) Str::uuid(),
            'device_name' => 'Test device',
        ], $overrides);
    }

    private function issue(User $user, string $name = 'Test device'): string
    {
        return app(NativeTokenService::class)->issue($user, (string) Str::uuid(), $name)->plainTextToken;
    }

    private function native(string $plainTextToken): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$plainTextToken);
    }
}
