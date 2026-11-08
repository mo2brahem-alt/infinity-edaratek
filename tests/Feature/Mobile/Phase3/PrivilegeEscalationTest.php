<?php

namespace Tests\Feature\Mobile\Phase3;

use App\Models\User;
use App\Services\Mobile\NativeTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_rejects_privilege_and_tenant_fields_from_body_and_query(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $before = $user->fresh()->getAttributes();
        $this->actingAs($user)->patchJson('/profile?school_id=999', [
            'name' => 'Changed', 'email' => $user->email, 'roles' => ['super_admin'],
            'is_active' => true, 'approval_status' => 'approved',
        ])->assertUnprocessable()->assertJsonValidationErrors(['roles', 'school_id', 'approval_status']);
        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public function test_public_registration_cannot_assign_privileged_fields_even_if_empty(): void
    {
        $this->postJson('/register', ['name' => 'Synthetic Registration', 'email' => 'new@example.test',
            'password' => Str::random(24), 'role' => null, 'permissions' => [], 'school_id' => null,
        ])->assertUnprocessable()->assertJsonValidationErrors(['role', 'permissions', 'school_id']);
        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
    }

    public function test_current_permission_and_role_removal_change_me_without_reissuing_token(): void
    {
        Role::findOrCreate('teacher', 'web');
        $permission = Permission::findOrCreate('mobile.fixture.read', 'web');
        $user = User::factory()->create(['role' => 'staff']);
        $user->assignRole('teacher');
        $user->givePermissionTo($permission);
        $token = app(NativeTokenService::class)->issue($user, (string) Str::uuid(), 'Fresh')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('data.capabilities', ['mobile.fixture.read'])->assertJsonPath('data.roles', ['staff', 'teacher']);
        $user->revokePermissionTo($permission);
        $user->removeRole('teacher');
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('data.capabilities', [])->assertJsonPath('data.roles', ['staff']);
    }

    public function test_sensitive_registration_never_accepts_account_authority_from_client(): void
    {
        $before = User::count();
        foreach (['/auth/register/supervisor', '/auth/register/manager'] as $uri) {
            $this->postJson($uri, ['role' => 'super_admin', 'approval_status' => 'approved', 'is_active' => true])
                ->assertUnprocessable()->assertJsonValidationErrors(['role', 'approval_status', 'is_active']);
        }
        $this->assertSame($before, User::count());
    }

    public function test_nonadmin_cannot_reach_system_role_or_approval_mutations(): void
    {
        $actor = User::factory()->create(['role' => 'staff']);
        $target = User::factory()->create(['role' => 'supervisor', 'approval_status' => 'pending_approval']);
        $this->actingAs($actor)->postJson('/api/system/roles', ['name' => 'forged'])->assertForbidden();
        $this->actingAs($actor)->postJson(route('users.approve', $target))->assertForbidden();
        $this->assertSame('pending_approval', $target->fresh()->approval_status);
        $this->assertDatabaseMissing('roles', ['name' => 'forged']);
    }
}
