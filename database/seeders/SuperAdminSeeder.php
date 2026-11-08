<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $input = config('provisioning.super_admin', []);
        if (! ($input['enabled'] ?? false)) {
            return;
        }
        $email = $input['email'] ?? null;
        $name = $input['name'] ?? null;
        $password = $input['password'] ?? null;
        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! is_string($name) || trim($name) === '' || mb_strlen($name) > 255
            || ! is_string($password) || strlen($password) < 16) {
            throw new \RuntimeException('Explicit valid administrator provisioning inputs are required.');
        }

        // Never reset, promote, activate, or replace roles of an existing user.
        DB::transaction(function () use ($email, $name, $password): void {
            if (User::query()->where('email', $email)->exists()) {
                return;
            }
            $role = Role::findOrCreate('super_admin', 'web');
            $flags = [];
            if (Schema::hasColumn('roles', 'is_system')) {
                $flags['is_system'] = true;
            }
            if (Schema::hasColumn('roles', 'assignable_by_school_admin')) {
                $flags['assignable_by_school_admin'] = false;
            }
            $role->forceFill($flags)->save();
            $user = User::create([
                'email' => $email,
                'name' => trim($name),
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'is_active' => true,
                'approval_status' => User::APPROVAL_APPROVED,
                'approved_at' => now(),
                'email_verified_at' => now(),
            ]);
            $user->assignRole($role);
        });
    }
}
