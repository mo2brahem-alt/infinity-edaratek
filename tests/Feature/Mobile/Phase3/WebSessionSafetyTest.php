<?php

namespace Tests\Feature\Mobile\Phase3;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebSessionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_csrf_remains_required_and_valid_web_session_survives_native_denial(): void
    {
        // Exercise real token matching rather than Laravel's test-mode shortcut.
        $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken {
            protected function runningUnitTests() { return false; }
        });
        $user = User::factory()->create();
        $this->get('/login')->assertOk();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(419);
        $csrf = $this->app['session']->token();
        $this->post('/login', ['email' => $user->email, 'password' => 'password', '_token' => $csrf])->assertRedirect();
        $this->assertAuthenticatedAs($user, 'web');
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($user, 'web');
    }
}
