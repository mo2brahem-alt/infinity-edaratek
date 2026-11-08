<?php

namespace App\Services\School;

use App\Models\School;
use App\Models\User;
use App\Support\SchoolAssociationState;

final class SchoolContextResolver
{
    // No static/worker cache: authority is read from current server state.
    public function current(User $user, bool $operational = true, string $purpose = 'school'): School
    {
        $current = User::query()->find($user->id);
        if (! $current || ! $current->hasApprovedAccountAccess()) {
            throw new SchoolContextException('FORBIDDEN', 403, 'Account access is required.');
        }
        if ($purpose === 'manager' && ! $current->hasSystemRole('school_manager')) {
            throw new SchoolContextException('FORBIDDEN', 403, 'Manager access is required.');
        }
        if ($purpose === 'staff' && ! $current->hasSystemRole('staff')) {
            throw new SchoolContextException('FORBIDDEN', 403, 'Staff access is required.');
        }
        $school = (int) $current->school_id > 0 ? School::query()->find($current->school_id) : null;
        if (! $school) {
            throw new SchoolContextException('SCHOOL_CONTEXT_REQUIRED', 409, __('messages.manager_school_required'));
        }
        if ($purpose === 'manager' || ($purpose === 'school' && $current->hasSystemRole('school_manager'))) {
            if ((int) $school->manager_user_id !== (int) $current->id) {
                throw new SchoolContextException('FORBIDDEN', 403, __('messages.assigned_manager_required'));
            }
        } elseif ($purpose !== 'staff' && ! $current->hasSystemRole('staff') && ! $current->hasSystemRole('teacher')
            && ! in_array($current->school_staff_type, [User::SCHOOL_STAFF_ADMINISTRATIVE, User::SCHOOL_STAFF_EDUCATIONAL], true)) {
            // A global role or a raw school_id is not a membership by itself.
            if ($current->hasSystemRole('supervisor')) {
                return $this->supervised($current, (int) $school->id);
            }
            throw new SchoolContextException('FORBIDDEN', 403, 'School membership is required.');
        }
        if ($operational && ! SchoolAssociationState::isSchoolActive($school)) {
            throw new SchoolContextException('SCHOOL_SUSPENDED', 403, SchoolAssociationState::SCHOOL_SUSPENDED_MESSAGE);
        }
        if ($operational && $purpose !== 'manager' && ! $current->hasSystemRole('school_manager')
            && ! SchoolAssociationState::allowsOperationalAccessFor($current, $school)) {
            throw new SchoolContextException('FORBIDDEN', 403, SchoolAssociationState::operationalAccessDeniedMessageFor($current, $school));
        }
        return $school;
    }

    // Used only by the existing, explicitly selected supervisor operation.
    public function supervised(User $user, int $schoolId): School
    {
        $current = User::query()->find($user->id);
        $school = School::query()->find($schoolId);
        if (! $current || ! $current->hasApprovedAccountAccess() || ! $current->hasSystemRole('supervisor')
            || ! $school || (int) $school->supervisor_id !== (int) $current->id) {
            throw new SchoolContextException('FORBIDDEN', 403, 'The current supervision relationship is required.');
        }
        if (! SchoolAssociationState::isSchoolActive($school)) {
            throw new SchoolContextException('SCHOOL_SUSPENDED', 403, SchoolAssociationState::SCHOOL_SUSPENDED_MESSAGE);
        }
        if (! SchoolAssociationState::isActiveAssociation($school)) {
            throw new SchoolContextException('FORBIDDEN', 403, SchoolAssociationState::LOCKED_MESSAGE);
        }
        return $school;
    }

    public function supervisedSchools(User $user): \Illuminate\Database\Eloquent\Builder
    {
        return School::query()->where('supervisor_id', $user->id)->where('status', School::STATUS_ACTIVE)
            ->where('supervision_status', School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION)
            ->where(function ($query) use ($user): void {
                $query->whereDoesntHave('associationRequests', fn ($q) => $q->where('status', \App\Models\AssociationRequest::STATUS_APPROVED))
                    ->orWhereHas('supervisionRequests', fn ($q) => $q->where('status', \App\Models\SchoolSupervisionRequest::STATUS_ACTIVE_ASSOCIATION)
                        ->whereColumn('manager_id', 'schools.manager_user_id')->where('supervisor_id', $user->id));
            });
    }

    public function identitySchool(User $user): ?School
    {
        try { return $this->current($user); }
        catch (SchoolContextException) { return null; }
    }
}
