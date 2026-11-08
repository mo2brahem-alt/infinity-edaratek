<?php

namespace Tests\Feature\Mobile\Phase3;

use App\Models\User;
use App\Services\Auth\UserApprovalService;
use App\Services\Mobile\AccountAccessEvaluator;
use App\Services\Mobile\NativeTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountApprovalMatrixTest extends TestCase
{
    use RefreshDatabase;

    public static function matrix(): array
    {
        $cases = [];
        foreach ([['staff', ['staff', 'teacher'], false], ['school_manager', ['staff'], true],
            ['staff', ['staff', 'school_manager'], true], ['staff', ['school_manager', 'staff'], true],
            ['staff', ['staff', 'supervisor'], true], ['staff', ['supervisor', 'staff'], true],
            ['supervisor', [], true], ['school_admin', [], false]] as $index => [$legacy, $roles, $required]) {
            foreach (['approved', 'pending_approval', 'rejected'] as $status) {
                foreach ([true, false] as $active) {
                    $cases['case_'.$index.'_'.$status.'_'.(int) $active] = [$legacy, $roles, $required, $status, $active];
                }
            }
        }
        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_account_union_state_is_identical_for_native_and_web_helpers(string $legacy, array $roles, bool $required, string $status, bool $active): void
    {
        foreach ($roles as $name) {
            Role::findOrCreate($name, 'web');
        }
        $user = User::factory()->create(['role' => $legacy, 'approval_status' => $status, 'is_active' => $active]);
        $user->syncRoles($roles);
        $user->load('roles');
        $expected = $active && (! $required || $status === 'approved');
        $this->assertSame($required, $user->requiresSuperAdminApproval());
        $this->assertSame($expected, $user->hasApprovedAccountAccess());
        $code = app(AccountAccessEvaluator::class)->denialCode($user);
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'password',
            'device_uuid' => (string) Str::uuid(), 'device_name' => 'Matrix login',
        ])->assertStatus($expected ? 200 : 403);
        $this->assertSame($expected ? 1 : 0, $user->tokens()->count());
        if (! $expected) {
            $login->assertJsonPath('code', $code)->assertJsonMissingPath('data');
        }
        $token = app(NativeTokenService::class)->issue($user, (string) Str::uuid(), 'Matrix')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/v1/me');
        $response->assertStatus($expected ? 200 : 403);
        if (! $expected) {
            $response->assertJsonPath('code', $code)->assertJsonMissingPath('data');
        }
    }

    public function test_missing_approval_does_not_default_to_approved_for_sensitive_account(): void
    {
        $user = User::factory()->create(['role' => 'school_manager']);
        $user->setAttribute('approval_status', null);
        $this->assertFalse($user->hasApprovedAccountAccess());
    }

    public function test_sensitive_role_added_after_loaded_collection_blocks_web_and_native(): void
    {
        Role::findOrCreate('staff', 'web');
        Role::findOrCreate('school_manager', 'web');
        $user = User::factory()->create(['role' => 'staff', 'approval_status' => 'pending_approval']);
        $user->assignRole('staff');
        $user->load('roles');
        User::findOrFail($user->id)->assignRole('school_manager');
        $this->assertFalse($user->hasApprovedAccountAccess());
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_queue_discovers_sensitive_role_in_either_representation_without_duplicates(): void
    {
        foreach (['super_admin', 'staff', 'school_manager', 'supervisor'] as $name) {
            Role::findOrCreate($name, 'web');
        }
        $admin = User::factory()->create(['role' => 'super_admin']);
        $legacy = User::factory()->create(['role' => 'school_manager', 'approval_status' => 'pending_approval']);
        $spatie = User::factory()->create(['role' => 'staff', 'approval_status' => 'pending_approval']);
        $spatie->assignRole('supervisor');
        $both = User::factory()->create(['role' => 'school_manager', 'approval_status' => 'pending_approval']);
        $both->assignRole('school_manager');
        $this->actingAs($admin)->get(route('users.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('pendingApprovals', 3)
                ->where('approvalStats.pending', 3));
    }

    public function test_role_changes_never_approve_or_activate_and_removed_sensitive_role_does_not_revive_token(): void
    {
        Role::findOrCreate('school_manager', 'web');
        $user = User::factory()->create(['role' => 'staff', 'is_active' => false, 'approval_status' => 'rejected']);
        $token = app(NativeTokenService::class)->issue($user, (string) Str::uuid(), 'Removed')->plainTextToken;
        $user->assignRole('school_manager');
        $user->syncRoles([]);
        $this->assertFalse($user->fresh()->is_active);
        $this->assertSame('rejected', $user->fresh()->approval_status);
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $user->update(['is_active' => true]);
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_approval_service_rejects_nonadmin_actor_without_state_mutation(): void
    {
        $actor = User::factory()->create(['role' => 'staff']);
        $target = User::factory()->create(['role' => 'supervisor', 'is_active' => false, 'approval_status' => 'pending_approval']);
        try {
            app(UserApprovalService::class)->approve($target, $actor);
            $this->fail('Non-admin approval must fail.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame('pending_approval', $target->fresh()->approval_status);
        $this->assertFalse($target->fresh()->is_active);
    }
}
