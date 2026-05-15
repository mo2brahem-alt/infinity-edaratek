<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (Schema::hasColumn('roles', 'is_system') || Schema::hasColumn('roles', 'assignable_by_school_admin')) {
            $payload = [];
            if (Schema::hasColumn('roles', 'is_system')) {
                $payload['is_system'] = true;
            }
            if (Schema::hasColumn('roles', 'assignable_by_school_admin')) {
                $payload['assignable_by_school_admin'] = false;
            }

            Role::query()->updateOrCreate(
                ['name' => 'super_admin', 'guard_name' => 'web'],
                $payload
            );
        }

        foreach ([
            [
                'email' => 'admin@edaratek.com',
                'name' => 'Super Admin',
                'password' => 'password123',
            ],
            [
                'email' => 'sultan@edaratek.com',
                'name' => 'Sultan',
                'password' => 'AdminP@ss2000',
            ],
        ] as $adminData) {
            $user = User::updateOrCreate(
                ['email' => $adminData['email']],
                [
                    'name' => $adminData['name'],
                    'password' => Hash::make($adminData['password']),
                    'role' => 'super_admin',
                    'is_active' => true,
                    'approval_status' => User::APPROVAL_APPROVED,
                    'approved_at' => now(),
                    'approved_by' => null,
                    'rejected_at' => null,
                    'rejected_by' => null,
                    'approval_notes' => null,
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles(['super_admin']);
        }
    }
}
