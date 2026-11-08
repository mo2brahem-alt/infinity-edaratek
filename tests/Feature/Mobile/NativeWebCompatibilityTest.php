<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use App\Services\Mobile\NativeTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NativeWebCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_login_stays_session_based_while_bearer_identity_stays_native_only(): void
    {
        $webUser = User::factory()->create();
        $nativeUser = User::factory()->create();
        $plainTextToken = app(NativeTokenService::class)->issue(
            $nativeUser,
            (string) Str::uuid(),
            'Compatibility device',
        )->plainTextToken;

        $this->post('/login', [
            'email' => $webUser->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated('web');
        $this->get('/dashboard')->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $nativeUser->id);

        $this->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($webUser, 'web');
    }
}
