<?php

namespace App\Services\Ticketing;

use App\Models\Subtask;
use App\Models\User;
use App\Services\School\SchoolContextException;
use App\Services\School\SchoolContextResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class SubtaskAccessQuery
{
    public function __construct(private readonly SchoolContextResolver $schools) {}

    public function forStaff(User $user): Builder
    {
        $school = $this->schools->current($user, purpose: 'staff');
        return Subtask::query()->where('subtasks.school_id', $school->id)->where('subtasks.assigned_to', $user->id)
            ->whereHas('ticket', fn (Builder $query) => $query->where('tickets.school_id', $school->id));
    }

    public function consistentParent(Builder $query): Builder
    {
        return $query->whereHas('ticket', fn (Builder $parent) => $parent->whereColumn('tickets.school_id', 'subtasks.school_id'));
    }

    public function permits(User $user, Subtask $subtask, string $action): bool
    {
        $user = User::query()->find($user->id);
        if (! $user || ! $user->hasApprovedAccountAccess()) { return false; }
        if ($user->hasSystemRole('super_admin') && in_array($action, ['view', 'reply'], true)) { return true; }
        try {
            if (in_array($action, ['view', 'reply', 'submit'], true) && $user->hasSystemRole('staff')) {
                if ($this->forStaff($user)->whereKey($subtask->id)->exists()) { return true; }
            }
            if ($action === 'submit' || ! $user->hasSystemRole('school_manager')) { return false; }
            $school = $this->schools->current($user, purpose: 'manager');
            return Subtask::query()->whereKey($subtask->id)->where('school_id', $school->id)
                ->whereHas('ticket', fn (Builder $q) => $q->where('school_id', $school->id)->where('assigned_to', $user->id))->exists();
        } catch (SchoolContextException) { return false; }
    }

    public function rejectAuthority(Request $request, array $allowed = []): void
    {
        $errors = [];
        foreach (array_diff(['school_id', 'tenant_id', 'organization_id', 'created_by', 'owner_id', 'assigned_by',
            'assigned_to', 'ticket_id', 'subtask_id', 'department_id', 'user_id', 'assignee_id',
            'role', 'roles', 'permissions', 'approval_status', 'is_active'], $allowed) as $key) {
            if (array_key_exists($key, $request->all())) { $errors[$key] = ['This field cannot select or change the authorized scope.']; }
        }
        if ($errors) { throw ValidationException::withMessages($errors); }
    }

    public function assignee(int $schoolId, int $id): User
    {
        $user = User::query()->where('school_id', $schoolId)->whereKey($id)->first();
        if (! $user || ! $user->hasSystemRole('staff') || ! $user->hasApprovedAccountAccess()) {
            throw ValidationException::withMessages(['assigned_to' => ['An eligible staff member of the current school is required.']]);
        }
        return $user;
    }
}
