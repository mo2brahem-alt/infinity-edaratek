<?php

namespace App\Services\Auth;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class SchoolRoleAssignmentService
{
    /**
     * @return Collection<int, Role>
     */
    public function assignableRolesForSchoolManager(): Collection
    {
        $this->ensureStaffRoleBaseline();

        return Role::query()
            ->where('guard_name', 'web')
            ->where(function ($query): void {
                $query
                    ->where(function ($assignableScope): void {
                        $assignableScope
                            ->where('assignable_by_school_admin', true)
                            ->where('is_system', false);
                    })
                    ->orWhere('name', 'staff');
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @param array<int, string> $roleNames
     * @return array<int, string>
     */
    public function normalizeRoleNamesForSchoolManager(array $roleNames): array
    {
        $requested = collect($roleNames)
            ->map(fn ($roleName) => trim((string) $roleName))
            ->filter()
            ->unique()
            ->values();

        if ($requested->isEmpty()) {
            throw ValidationException::withMessages([
                'role_names' => 'At least one assignable role is required.',
            ]);
        }

        if (!$requested->contains('staff')) {
            $requested->prepend('staff');
        }

        $allowedNames = $this->assignableRolesForSchoolManager()
            ->pluck('name')
            ->map(fn ($name) => (string) $name)
            ->values();

        $diff = $requested->diff($allowedNames);
        if ($diff->isNotEmpty()) {
            throw ValidationException::withMessages([
                'role_names.0' => 'Selected role cannot be assigned by school manager.',
            ]);
        }

        return $requested->unique()->values()->all();
    }

    private function ensureStaffRoleBaseline(): void
    {
        $staffRole = Role::findOrCreate('staff', 'web');

        $updates = [];
        if ($this->rolesTableHasColumn('assignable_by_school_admin') && !$staffRole->assignable_by_school_admin) {
            $updates['assignable_by_school_admin'] = true;
        }

        if ($this->rolesTableHasColumn('is_system') && $staffRole->is_system) {
            $updates['is_system'] = false;
        }

        if (count($updates) > 0) {
            $staffRole->forceFill($updates)->save();
        }
    }

    private function rolesTableHasColumn(string $column): bool
    {
        static $cache = [];
        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }

        $cache[$column] = Schema::hasColumn('roles', $column);

        return $cache[$column];
    }
}

