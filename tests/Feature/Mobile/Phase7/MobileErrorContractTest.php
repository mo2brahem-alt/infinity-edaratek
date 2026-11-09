<?php

namespace Tests\Feature\Mobile\Phase7;

use App\Models\User;
use App\Support\ApiError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Log, Route};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MobileContractFixtures;
use Tests\TestCase;

class MobileErrorContractTest extends TestCase
{
    use RefreshDatabase, MobileContractFixtures;

    public static function protectedEndpoints(): array
    {
        return array_map(static fn ($x) => [$x[0], $x[1]], [['GET','/me'], ['POST','/auth/logout'], ['GET','/auth/devices'], ['DELETE','/auth/devices/1'],
            ['DELETE','/auth/devices'], ['GET','/staff/tasks'], ['GET','/staff/tasks/1'], ['GET','/attachments/1/download'], ['GET','/student-leaves/1/attachments/1/download']]);
    }

    #[DataProvider('protectedEndpoints')]
    public function test_every_protected_endpoint_rejects_anonymous_callers(string $method, string $path): void
    {
        $this->assertSafeError($this->json($method, '/api/v1'.$path), 401, 'UNAUTHENTICATED');
    }

    public function test_wrong_role_foreign_missing_suspended_and_unlinked_context_errors_are_stable(): void
    {
        $s = $this->schools(); $foreign = $this->task($s['B']);
        $teacher = User::factory()->create(['role' => 'teacher', 'school_id' => $s['A']['school']->id]);
        $this->assertSafeError($this->bearer($teacher)->getJson('/api/v1/staff/tasks'), 403, 'FORBIDDEN');
        $this->bearer($s['A']['staff']);
        $this->assertSafeError($this->getJson('/api/v1/staff/tasks/'.$foreign->id), 404, 'NOT_FOUND');
        $this->assertSafeError($this->getJson('/api/v1/staff/tasks/999999999'), 404, 'NOT_FOUND');
        $s['A']['school']->update(['status' => \App\Models\School::STATUS_SUSPENDED]);
        $this->assertSafeError($this->getJson('/api/v1/staff/tasks'), 403, 'SCHOOL_SUSPENDED');
        $s['A']['staff']->update(['school_id' => null]);
        $this->assertSafeError($this->getJson('/api/v1/staff/tasks'), 409, 'SCHOOL_CONTEXT_REQUIRED');
    }

    public static function badIds(): array
    {
        return array_map(static fn ($x) => [$x], ['0', '-1', '1.2', '01', '9223372036854775808', '999999999999999999999999999999', 'not-an-id']);
    }

    #[DataProvider('badIds')]
    public function test_malformed_or_out_of_range_ids_cannot_coerce_to_existing_records(string $id): void
    {
        $s = $this->schools(); $s['A']['staff']->update(['can_manage_student_leaves' => true]); $this->bearer($s['A']['staff']);
        foreach ([['GET','/staff/tasks/'.$id], ['DELETE','/auth/devices/'.$id], ['GET','/attachments/'.$id.'/download'], ['GET','/student-leaves/'.$id.'/attachments/1/download']] as [$method,$path]) {
            $this->assertSafeError($this->json($method, '/api/v1'.$path), 404, 'NOT_FOUND');
        }
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_leave_capability_denial_precedes_malformed_or_foreign_resource_disclosure(): void
    {
        $s = $this->schools(); $this->bearer($s['A']['staff']);
        foreach (['not-an-id', '99999999'] as $id) {
            $this->assertSafeError($this->getJson('/api/v1/student-leaves/'.$id.'/attachments/1/download'), 403, 'FORBIDDEN');
        }
    }

    public static function accountStates(): array
    {
        return [['pending_approval', false, 'ACCOUNT_PENDING_APPROVAL'], ['rejected', false, 'ACCOUNT_REJECTED'], ['approved', false, 'ACCOUNT_DISABLED']];
    }

    #[DataProvider('accountStates')]
    public function test_account_denial_precedes_business_access_and_has_no_user_payload(string $status, bool $active, string $code): void
    {
        $user = User::factory()->create(['role' => 'school_manager']); $this->bearer($user);
        $user->update(['approval_status' => $status, 'is_active' => $active]);
        $this->assertSafeError($this->getJson('/api/v1/me'), 403, $code);
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
    }

    public function test_validation_and_invalid_credentials_never_echo_input_or_arbitrary_exception_messages(): void
    {
        $user = User::factory()->create();
        $this->assertSafeError($this->postJson('/api/v1/auth/login', $this->credentials($user, ['password' => 'wrong'])), 401, 'INVALID_CREDENTIALS');
        $this->assertSafeError($this->postJson('/api/v1/auth/login', $this->credentials($user, ['email' => 'unknown@example.test'])), 401, 'INVALID_CREDENTIALS');
        $r = $this->postJson('/api/v1/auth/login', ['email' => 'invalid', 'password' => 'sensitive-fixture', 'role' => 'super_admin']);
        $this->assertSafeError($r, 422, 'VALIDATION_FAILED'); $r->assertJsonStructure(['errors' => ['email', 'device_uuid', 'role']]);
        $this->assertStringNotContainsString('sensitive-fixture', $r->getContent());
        Route::get('/api/v1/__validation_fixture', static function () { throw \Illuminate\Validation\ValidationException::withMessages(['email' => ['private SQL sensitive-fixture']]); });
        $r = $this->getJson('/api/v1/__validation_fixture'); $this->assertSafeError($r, 422, 'VALIDATION_FAILED');
        $this->assertStringNotContainsString('private SQL', $r->getContent());
        $this->assertSafeError($this->call('POST', '/api/v1/auth/login', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{invalid'), 422, 'VALIDATION_FAILED');
    }

    public static function rendererCases(): array
    {
        return [[409,'BUSINESS_CONFLICT'],[409,'IMPACT_CONFIRMATION_REQUIRED'],[429,'RATE_LIMITED'],[500,'INTERNAL_ERROR'],[405,'METHOD_NOT_ALLOWED'],[400,'BAD_REQUEST']];
    }

    public static function privilegedInputs(): array
    {
        return [['role', null], ['school_id', null], ['abilities', []], ['permissions', []], ['approval_status', ''], ['extra', 'ignored']];
    }

    #[DataProvider('privilegedInputs')]
    public function test_login_rejects_even_empty_privileged_or_unsupported_fields(string $field, mixed $value): void
    {
        $user = User::factory()->create();
        $r = $this->postJson('/api/v1/auth/login', $this->credentials($user, [$field => $value]));
        $this->assertSafeError($r, 422, 'VALIDATION_FAILED'); $r->assertJsonStructure(['errors' => [$field]]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[DataProvider('rendererCases')]
    public function test_scoped_renderer_covers_conflict_throttle_and_routing_without_secret_text(int $status, string $code): void
    {
        config(['app.debug' => true]);
        Route::get('/api/v1/__error_fixture', static function () use ($status, $code) {
            if ($status === 409) { throw new ApiError($code, $status); }
            abort($status, 'private-sentinel /app/config token=synthetic', ['Retry-After' => '7', 'X-Private' => 'private-sentinel']);
        });
        $r = $this->getJson('/api/v1/__error_fixture', ['X-Request-ID' => 'private-sentinel']);
        $this->assertSafeError($r, $status, $code); $this->assertStringNotContainsString('private-sentinel', $r->getContent());
        $r->assertHeaderMissing('X-Private');
        if ($status === 429) { $r->assertHeader('Retry-After', '7')->assertJsonPath('retry_after', 7); }
    }

    public function test_debug_error_correlates_only_generated_request_id_in_safe_log(): void
    {
        config(['app.debug' => true]); $logged = null;
        Log::shouldReceive('error')->once()->with('native.internal_error', \Mockery::on(function ($context) use (&$logged) {
            $logged = $context; return array_keys($context) === ['code', 'request_id'] && $context['code'] === 'INTERNAL_ERROR' && \Illuminate\Support\Str::isUuid($context['request_id']);
        }));
        Route::get('/api/v1/__secret_fixture', static fn () => throw new \RuntimeException('SQL private-sentinel password=fixture'));
        $r = $this->getJson('/api/v1/__secret_fixture', ['X-Request-ID' => str_repeat('private-sentinel', 20)]);
        $this->assertSafeError($r, 500, 'INTERNAL_ERROR'); $this->assertSame($logged['request_id'], $r->json('request_id'));
        $this->assertStringNotContainsString('private-sentinel', $r->getContent());
    }
}
