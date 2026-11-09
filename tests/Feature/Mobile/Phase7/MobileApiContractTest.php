<?php

namespace Tests\Feature\Mobile\Phase7;

use App\Models\{School, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\Support\MobileContractFixtures;
use Tests\TestCase;

class MobileApiContractTest extends TestCase
{
    use RefreshDatabase, MobileContractFixtures;

    protected function setUp(): void
    {
        parent::setUp(); Storage::fake('school_attachments'); Storage::fake('local');
    }

    public function test_login_and_identity_have_only_declared_fields_and_native_types(): void
    {
        $user = User::factory()->create(['role' => 'staff', 'name' => 'مستخدم مصطنع', 'mobile' => '0500000114', 'phone_verified_at' => now()]);
        $response = $this->postJson('/api/v1/auth/login', $this->credentials($user))->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->exactKeys(['data'], $response->json());
        $this->exactKeys(['access_token', 'token_type', 'expires_at', 'user'], $response->json('data'));
        $this->assertSame('Bearer', $response->json('data.token_type'));
        $this->assertMatchesRegularExpression('/Z$/', $response->json('data.expires_at'));
        $this->withHeader('Authorization', 'Bearer '.$response->json('data.access_token'));
        $me = $this->getJson('/api/v1/me')->assertOk()->json('data');
        $this->exactKeys(['id', 'name', 'email', 'roles', 'capabilities', 'is_active', 'approval_required', 'approval_status', 'school', 'phone_verified'], $me);
        $this->assertIsInt($me['id']); $this->assertSame('مستخدم مصطنع', $me['name']); $this->assertIsArray($me['roles']); $this->assertIsArray($me['capabilities']);
        foreach (['is_active', 'approval_required', 'phone_verified'] as $field) { $this->assertIsBool($me[$field]); }
        $this->assertNull($me['school']); $this->assertTrue($me['phone_verified']);
        $user->update(['mobile' => '0500000115']); $this->getJson('/api/v1/me')->assertJsonPath('data.phone_verified', false);
        $user->update(['mobile' => null, 'phone' => null, 'phone_verified_at' => now()]);
        $this->getJson('/api/v1/me')->assertJsonPath('data.phone_verified', false);
    }

    public function test_device_dto_expiry_owner_and_all_three_empty_mutation_contracts(): void
    {
        $a = User::factory()->create(); $b = User::factory()->create(); $token = $this->nativeToken($a);
        $other = $this->nativeToken($a); $otherId = (int) explode('|', $other)[0];
        $foreign = $this->nativeToken($b); $a->createToken('integration', ['*'], now()->addDay());
        $expired = $a->createToken('native:'.\Illuminate\Support\Str::uuid().':Expired', ['native:access'], now()->subSecond());
        $this->withHeader('Authorization', 'Bearer '.$token);
        $r = $this->getJson('/api/v1/auth/devices')->assertOk()->assertJsonPath('meta.total', 2);
        foreach ($r->json('data') as $item) {
            $this->exactKeys(['id', 'device_name', 'created_at', 'last_used_at', 'expires_at', 'is_current'], $item);
            $this->assertIsInt($item['id']); $this->assertIsBool($item['is_current']);
            foreach (['created_at', 'expires_at'] as $date) { $this->assertMatchesRegularExpression('/Z$/', $item[$date]); }
            $this->assertTrue($item['last_used_at'] === null || is_string($item['last_used_at']));
        }
        $this->assertSafeError($this->deleteJson('/api/v1/auth/devices/'.explode('|', $foreign)[0]), 404, 'NOT_FOUND');
        $this->deleteJson('/api/v1/auth/devices/'.$otherId)->assertNoContent()->assertContent('');
        $this->postJson('/api/v1/auth/logout')->assertNoContent()->assertContent('');
        $this->assertSafeError($this->getJson('/api/v1/me'), 401, 'UNAUTHENTICATED');
        $this->bearer($a)->deleteJson('/api/v1/auth/devices')->assertNoContent()->assertContent('');
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'integration']);
    }

    public function test_task_resources_and_private_downloads_include_no_internal_or_unbounded_relations(): void
    {
        $s = $this->schools(); $task = $this->task($s['A'], attributes: ['description' => null, 'due_date' => null]);
        $file = $this->contractFile($s['A'], $task);
        $this->bearer($s['A']['staff']);
        $list = $this->getJson('/api/v1/staff/tasks')->assertOk()->json('data.0');
        $this->exactKeys(['id', 'title', 'status', 'due_date'], $list); $this->assertNull($list['due_date']);
        $detail = $this->getJson('/api/v1/staff/tasks/'.$task->id)->assertOk()->json('data');
        $this->exactKeys(['id', 'title', 'status', 'due_date', 'description', 'attachments', 'attachments_meta', 'attachments_links'], $detail);
        $this->assertNull($detail['description']);
        $metadata = $detail['attachments'][0];
        $this->exactKeys(['id', 'name', 'mime_type', 'size_bytes', 'download_url'], $metadata);
        $this->assertIsInt($metadata['size_bytes']); $this->assertGreaterThanOrEqual(0, $metadata['size_bytes']);
        $this->assertStringStartsWith('/api/v1/attachments/', $metadata['download_url']);
        $bytes = $this->get($metadata['download_url'])->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('synthetic-bytes', $bytes->streamedContent());
        $this->assertStringContainsString('no-store', $bytes->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $bytes->headers->get('Cache-Control'));
        [$leave, $leaveFile] = $this->contractLeave($s['A']);
        $this->bearer($s['A']['manager'])->get('/api/v1/student-leaves/'.$leave->id.'/attachments/'.$leaveFile->id.'/download')->assertOk();
    }

    public function test_native_reads_do_not_provision_domain_defaults_or_roles_and_capabilities_recompute(): void
    {
        $s = $this->schools(); $task = $this->task($s['A']);
        $permission = \Spatie\Permission\Models\Permission::findOrCreate('school.student_structure.manage', 'web');
        $s['A']['staff']->givePermissionTo($permission);
        $this->bearer($s['A']['staff']);
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.school.id', $s['A']['school']->id)
            ->assertJsonFragment(['capabilities' => [$permission->name]]);
        $this->getJson('/api/v1/auth/devices')->assertOk(); $this->getJson('/api/v1/staff/tasks')->assertOk();
        $this->getJson('/api/v1/staff/tasks/'.$task->id)->assertOk();
        $writes = array_filter(DB::getQueryLog(), static fn ($q) => preg_match('/^\s*(insert|update|delete|replace)\b/i', $q['query']) && ! str_contains($q['query'], 'personal_access_tokens'));
        DB::disableQueryLog(); $this->assertSame([], array_values($writes));
        $s['A']['staff']->revokePermissionTo($permission); $this->getJson('/api/v1/me')->assertJsonPath('data.capabilities', []);
        $s['A']['staff']->update(['school_id' => null]); $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.school', null);
    }
}
