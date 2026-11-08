<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use App\Services\Mobile\NativeTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NativeAccountStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    }

    public function test_pending_and_rejected_statuses_precede_inactive_for_approval_roles(): void
    {
        foreach ([User::APPROVAL_PENDING => 'ACCOUNT_PENDING_APPROVAL', User::APPROVAL_REJECTED => 'ACCOUNT_REJECTED'] as $status => $code) {
            $user = User::factory()->create([
                'role' => 'staff',
                'approval_status' => $status,
                'is_active' => false,
            ]);
            $user->assignRole('school_manager');

            $response = $this->login($user);

            $response->assertStatus(403)->assertJsonPath('code', $code);
            $this->assertSame(0, $user->fresh()->tokens()->count());
        }
    }

    public function test_inactive_account_cannot_login_or_use_an_existing_native_token_but_can_logout(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $this->issue($user);
        $user->forceFill(['is_active' => false])->save();

        $this->native($plainTextToken)->getJson('/api/v1/me')
            ->assertStatus(403)
            ->assertJsonPath('code', 'ACCOUNT_DISABLED');

        $this->native($plainTextToken)->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->native($plainTextToken)->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_current_role_permission_and_approval_changes_are_reloaded_for_each_request(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $user->assignRole('staff');
        $permission = Permission::firstOrCreate(['name' => 'mobile.read', 'guard_name' => 'web']);
        $user->givePermissionTo($permission);
        $plainTextToken = $this->issue($user);

        $this->native($plainTextToken)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.roles.0', 'staff')
            ->assertJsonPath('data.capabilities.0', 'mobile.read');

        $user->revokePermissionTo($permission);
        $user->syncRoles(['school_manager']);
        $user->forceFill(['approval_status' => User::APPROVAL_PENDING])->save();

        $this->native($plainTextToken)->getJson('/api/v1/me')
            ->assertStatus(403)
            ->assertJsonPath('code', 'ACCOUNT_PENDING_APPROVAL');
    }

    public function test_legacy_and_spatie_roles_are_combined_for_approval(): void
    {
        $user = User::factory()->create([
            'role' => 'school_manager',
            'approval_status' => User::APPROVAL_PENDING,
        ]);
        $user->assignRole('staff');

        $this->login($user)
            ->assertStatus(403)
            ->assertJsonPath('code', 'ACCOUNT_PENDING_APPROVAL');
    }

    private function login(User $user)
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_uuid' => (string) Str::uuid(),
            'device_name' => 'Account test device',
        ]);
    }

    private function issue(User $user): string
    {
        return app(NativeTokenService::class)->issue($user, (string) Str::uuid(), 'Account test device')->plainTextToken;
    }

    private function native(string $plainTextToken): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$plainTextToken);
    }
}
