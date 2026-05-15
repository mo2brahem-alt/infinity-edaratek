<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'super_admin', 'is_system' => true, 'assignable_by_school_admin' => false],
            ['name' => 'supervisor', 'is_system' => true, 'assignable_by_school_admin' => false],
            ['name' => 'school_manager', 'is_system' => true, 'assignable_by_school_admin' => false],
            ['name' => 'staff', 'is_system' => false, 'assignable_by_school_admin' => true],
            ['name' => 'teacher', 'is_system' => false, 'assignable_by_school_admin' => true],
            ['name' => 'student', 'is_system' => true, 'assignable_by_school_admin' => false],
            ['name' => 'parent', 'is_system' => true, 'assignable_by_school_admin' => false],
        ];

        foreach ($roles as $role) {
            $payload = [];
            if (Schema::hasColumn('roles', 'is_system')) {
                $payload['is_system'] = $role['is_system'];
            }
            if (Schema::hasColumn('roles', 'assignable_by_school_admin')) {
                $payload['assignable_by_school_admin'] = $role['assignable_by_school_admin'];
            }

            Role::query()->updateOrCreate(
                ['name' => $role['name'], 'guard_name' => 'web'],
                $payload
            );
        }
    }
}
