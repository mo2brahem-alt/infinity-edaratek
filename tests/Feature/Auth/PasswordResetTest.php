<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_known_unknown_and_broker_cooldown_have_identical_general_outcome(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
        $status = session('status');
        $known->assertRedirect('/forgot-password')->assertSessionHasNoErrors();
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'missing@example.test'])
            ->assertRedirect('/forgot-password')->assertSessionHas('status', $status)->assertSessionHasNoErrors();
        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password')->assertSessionHas('status', $status)->assertSessionHasNoErrors();
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_identifier_budget_survives_ip_rotation_and_recovers_after_window(): void
    {
        $email = 'unknown-limited@example.test';
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])->post('/forgot-password', ['email' => $email])->assertRedirect();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.100'])->post('/forgot-password', ['email' => $email])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->travel(3601)->seconds();
        $this->post('/forgot-password', ['email' => $email])->assertRedirect();
        $this->travelBack();
    }

    public function test_ip_budget_limits_rotating_identifiers(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->post('/forgot-password', ['email' => 'missing-'.$attempt.'@example.test'])->assertRedirect();
        }
        $this->post('/forgot-password', ['email' => 'missing-final@example.test'])->assertStatus(429)->assertHeader('Retry-After');
    }
}
