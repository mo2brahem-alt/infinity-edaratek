<?php

namespace App\Repositories;

use App\Models\School;
use App\Models\SchoolSupervisorAssignment;

class SchoolRepository
{
    public function find(int $schoolId): School
    {
        return School::query()->with('directorate')->findOrFail($schoolId);
    }

    public function activeSchoolIdsForSupervisor(int $supervisorId): array
    {
        return School::query()
            ->where('status', School::STATUS_ACTIVE)
            ->where('supervisor_id', $supervisorId)
            ->pluck('id')
            ->all();
    }

    public function resolveSupervisorIdForSchool(School $school): ?int
    {
        if ($school->supervisor_id) {
            return (int) $school->supervisor_id;
        }

        $schoolAssignment = SchoolSupervisorAssignment::query()
            ->where('is_active', true)
            ->where('school_id', $school->id)
            ->latest('id')
            ->first();

        if ($schoolAssignment) {
            return (int) $schoolAssignment->supervisor_id;
        }

        $directorateAssignment = SchoolSupervisorAssignment::query()
            ->where('is_active', true)
            ->whereNull('school_id')
            ->where('directorate_id', $school->directorate_id)
            ->latest('id')
            ->first();

        return $directorateAssignment ? (int) $directorateAssignment->supervisor_id : null;
    }
}
