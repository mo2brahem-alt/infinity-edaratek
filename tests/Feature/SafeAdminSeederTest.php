<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SafeAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_provisioning_performs_no_account_or_role_write(): void
    {
        config(['provisioning.super_admin.enabled' => false]);
        $users = User::count();
        $roles = Role::count();
        $this->seed(SuperAdminSeeder::class);
        $this->assertSame($users, User::count());
        $this->assertSame($roles, Role::count());
    }

    public function test_explicit_new_provisioning_is_hashed_and_repeat_preserves_credentials_roles_and_state(): void
    {
        $secret = Str::random(32);
        config(['provisioning.super_admin' => ['enabled' => true, 'email' => 'operator@example.test', 'name' => 'Synthetic Operator', 'password' => $secret]]);
        $this->seed(SuperAdminSeeder::class);
        $user = User::where('email', 'operator@example.test')->firstOrFail();
        $this->assertTrue(Hash::check($secret, $user->password));
        $this->assertTrue($user->hasRole('super_admin'));
        $before = $user->getAttributes();
        $roles = $user->roles()->pluck('id')->all();
        config(['provisioning.super_admin.password' => Str::random(32)]);
        $this->seed(SuperAdminSeeder::class);
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertSame($roles, $user->fresh()->roles()->pluck('id')->all());
        $this->assertSame(1, User::where('email', 'operator@example.test')->count());
    }

    public function test_existing_nonadmin_is_not_promoted_or_reactivated(): void
    {
        $user = User::factory()->create(['role' => 'staff', 'is_active' => false, 'approval_status' => 'rejected']);
        $before = $user->fresh()->getAttributes();
        config(['provisioning.super_admin' => ['enabled' => true, 'email' => $user->email, 'name' => 'Operator', 'password' => Str::random(32)]]);
        $this->seed(SuperAdminSeeder::class);
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertFalse($user->fresh()->hasRole('super_admin'));
    }

    public function test_missing_explicit_secret_fails_without_creating_user(): void
    {
        config(['provisioning.super_admin' => ['enabled' => true, 'email' => 'invalid@example.test', 'name' => 'Operator']]);
        $before = User::count();
        try {
            $this->seed(SuperAdminSeeder::class);
            $this->fail('Missing operator input must fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Explicit valid administrator provisioning inputs are required.', $exception->getMessage());
        }
        $this->assertSame($before, User::count());
    }
}
