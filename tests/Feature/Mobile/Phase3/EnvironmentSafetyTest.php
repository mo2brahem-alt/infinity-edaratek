<?php

namespace Tests\Feature\Mobile\Phase3;

use App\Support\AuthSecurityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnvironmentSafetyTest extends TestCase
{
    use RefreshDatabase;

    public static function debugModes(): array
    {
        return ['disabled' => [false], 'enabled' => [true]];
    }

    #[DataProvider('debugModes')]
    public function test_v1_unhandled_error_has_only_safe_fields_and_sanitized_log(bool $debug): void
    {
        $secret = Str::random(32);
        config(['app.debug' => $debug]);
        Route::get('/api/v1/__phase3_error', static function () use ($secret) {
            throw new \RuntimeException('SQL SELECT private_value /app/private/config.php '.$secret);
        });
        Log::shouldReceive('error')->once()->with('native.internal_error', ['code' => 'INTERNAL_ERROR']);
        $response = $this->getJson('/api/v1/__phase3_error', ['X-Request-ID' => $secret]);
        $response->assertStatus(500)->assertJsonPath('code', 'INTERNAL_ERROR');
        $this->assertSame(['code', 'message', 'request_id'], array_keys($response->json()));
        $this->assertTrue(Str::isUuid($response->json('request_id')));
        foreach ([$secret, 'SELECT', '/app/', 'RuntimeException', 'trace', 'APP_KEY'] as $value) {
            $this->assertStringNotContainsString($value, $response->getContent());
        }
    }

    public function test_v1_http_500_is_also_generic_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/v1/__phase3_http_error', static fn () => abort(500, 'private SQL /app/sentinel'));
        $response = $this->getJson('/api/v1/__phase3_http_error')->assertStatus(500)->assertJsonPath('code', 'INTERNAL_ERROR');
        $this->assertStringNotContainsString('sentinel', $response->getContent());
    }

    public function test_auth_denial_logging_accepts_only_safe_bounded_metadata_and_throttles_duplicates(): void
    {
        Log::shouldReceive('notice')->once()->with('auth.security', ['event' => 'ACCOUNT_ACCESS_DENIED', 'user_id' => 123]);
        AuthSecurityLog::event('ACCOUNT_ACCESS_DENIED', 123);
        AuthSecurityLog::event('ACCOUNT_ACCESS_DENIED', 123);
        AuthSecurityLog::event('unrecognized:'.Str::random(32), 123);
    }
}
