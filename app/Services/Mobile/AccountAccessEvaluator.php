<?php

namespace App\Services\Mobile;

use App\Models\User;

final class AccountAccessEvaluator
{
    public function freshUser(int $userId): ?User
    {
        return User::query()
            ->with(['roles.permissions', 'permissions', 'school'])
            ->find($userId);
    }

    /** @return list<string> */
    public function roles(User $user): array
    {
        $roles = $user->relationLoaded('roles')
            ? $user->roles->pluck('name')->all()
            : $user->roles()->pluck('name')->all();

        if (is_string($user->role) && $user->role !== '') {
            $roles[] = $user->role;
        }

        $roles = array_values(array_unique(array_filter($roles, static fn (mixed $role): bool => is_string($role) && $role !== '')));
        sort($roles, SORT_STRING);

        return $roles;
    }

    /** @return list<string> */
    public function capabilities(User $user): array
    {
        $permissions = $user->getAllPermissions()->pluck('name')->filter()->unique()->sort()->values()->all();

        return array_values($permissions);
    }

    public function approvalRequired(User $user): bool
    {
        return array_intersect($this->roles($user), User::APPROVAL_REQUIRED_ROLES) !== [];
    }

    public function denialCode(User $user): ?string
    {
        if ($this->approvalRequired($user)) {
            $status = (string) ($user->approval_status ?? User::APPROVAL_APPROVED);

            // Approval states take precedence over activity so clients receive
            // a stable account-state response when both are set.
            if ($status === User::APPROVAL_PENDING) {
                return 'ACCOUNT_PENDING_APPROVAL';
            }

            if ($status === User::APPROVAL_REJECTED) {
                return 'ACCOUNT_REJECTED';
            }

            if ($status !== User::APPROVAL_APPROVED) {
                return 'ACCOUNT_DISABLED';
            }
        }

        return $user->is_active ? null : 'ACCOUNT_DISABLED';
    }
}
