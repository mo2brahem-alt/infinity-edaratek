<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolSupervisorAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\ValidationException;

class SupervisorAssignmentController extends Controller
{
    public function index(): Response
    {
        $assignments = SchoolSupervisorAssignment::query()
            ->with(['supervisor:id,name,email', 'directorate:id,name,governorate', 'school:id,name,school_id'])
            ->latest('id')
            ->get();

        $supervisors = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'supervisor'))
            ->orWhere('role', 'supervisor')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Admin/SupervisorAssignments/Index', [
            'assignments' => $assignments,
            'supervisors' => $supervisors,
            'directorates' => EducationalDirectorate::query()->orderBy('name')->get(['id', 'name', 'governorate']),
            'schools' => School::query()->orderBy('name')->get(['id', 'name', 'school_id', 'directorate_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supervisor_id' => 'required|exists:users,id',
            'directorate_id' => 'nullable|exists:educational_directorates,id',
            'school_id' => 'nullable|exists:schools,id',
            'is_active' => 'nullable|boolean',
        ]);

        if (empty($validated['directorate_id']) && empty($validated['school_id'])) {
            return back()->withErrors(['scope' => 'Select either a directorate or a school.']);
        }

        if (!empty($validated['directorate_id']) && !empty($validated['school_id'])) {
            return back()->withErrors(['scope' => 'Select either a directorate or a school, not both.']);
        }

        $supervisor = User::query()->findOrFail($validated['supervisor_id']);
        if (!$supervisor->hasSystemRole('supervisor')) {
            return back()->withErrors([
                'supervisor_id' => 'Selected account is not a supervisor.',
            ]);
        }

        DB::transaction(function () use ($validated): void {
            if (!empty($validated['school_id'])) {
                $school = School::query()
                    ->whereKey($validated['school_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($school->supervisor_id && (int) $school->supervisor_id !== (int) $validated['supervisor_id']) {
                    throw ValidationException::withMessages([
                        'school_id' => 'This school is already assigned to another supervisor.',
                    ]);
                }

                $existingAssignment = SchoolSupervisorAssignment::query()
                    ->where('school_id', $school->id)
                    ->lockForUpdate()
                    ->first();

                if ($existingAssignment && (int) $existingAssignment->supervisor_id !== (int) $validated['supervisor_id']) {
                    throw ValidationException::withMessages([
                        'school_id' => 'This school already has a supervisor assignment.',
                    ]);
                }

                if ($existingAssignment) {
                    $existingAssignment->update([
                        'supervisor_id' => $validated['supervisor_id'],
                        'directorate_id' => null,
                        'is_active' => $validated['is_active'] ?? true,
                    ]);
                } else {
                    SchoolSupervisorAssignment::create([
                        'supervisor_id' => $validated['supervisor_id'],
                        'directorate_id' => null,
                        'school_id' => $school->id,
                        'is_active' => $validated['is_active'] ?? true,
                    ]);
                }

                $school->update(['supervisor_id' => $validated['supervisor_id']]);

                return;
            }

            $directorateId = (int) $validated['directorate_id'];

            $hasConflict = SchoolSupervisorAssignment::query()
                ->where('is_active', true)
                ->whereNull('school_id')
                ->where('directorate_id', $directorateId)
                ->where('supervisor_id', '!=', $validated['supervisor_id'])
                ->lockForUpdate()
                ->exists();

            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'directorate_id' => 'This directorate already has another active supervisor assignment.',
                ]);
            }

            $existingAssignment = SchoolSupervisorAssignment::query()
                ->whereNull('school_id')
                ->where('directorate_id', $directorateId)
                ->where('supervisor_id', $validated['supervisor_id'])
                ->lockForUpdate()
                ->first();

            if ($existingAssignment) {
                $existingAssignment->update([
                    'is_active' => $validated['is_active'] ?? true,
                ]);
            } else {
                SchoolSupervisorAssignment::create([
                    'supervisor_id' => $validated['supervisor_id'],
                    'directorate_id' => $directorateId,
                    'school_id' => null,
                    'is_active' => $validated['is_active'] ?? true,
                ]);
            }
        });

        return back();
    }

    public function destroy(SchoolSupervisorAssignment $supervisorAssignment): RedirectResponse
    {
        $supervisorAssignment->delete();

        return back();
    }
}
