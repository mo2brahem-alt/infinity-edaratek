<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\EducationalDirectorate;
use App\Models\Governorate;
use App\Models\School;
use App\Models\SchoolSupervisionRequest;
use App\Services\Supervision\SchoolSupervisionRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(private readonly SchoolSupervisionRequestService $requestService)
    {
    }

    public function show(Request $request): Response
    {
        $recentRequests = SchoolSupervisionRequest::query()
            ->with(['school:id,name,school_id,directorate_id', 'manager:id,name,email'])
            ->where('supervisor_id', $request->user()->id)
            ->latest('id')
            ->limit(20)
            ->get();

        return Inertia::render('Supervisor/Onboarding', [
            'currentRegionId' => $request->user()->onboarding_region_id,
            'recentRequests' => $recentRequests,
        ]);
    }

    public function regions(): JsonResponse
    {
        $regions = EducationalDirectorate::query()
            ->with([
                'country:id,name',
                'governorateModel:id,country_id,name',
            ])
            ->orderBy('governorate')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'governorate',
                'country_id',
                'governorate_id',
            ]);

        return response()->json([
            'countries' => Country::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'governorates' => Governorate::query()
                ->orderBy('name')
                ->get(['id', 'country_id', 'name']),
            'regions' => $regions->map(fn (EducationalDirectorate $region): array => [
                'id' => (int) $region->id,
                'name' => (string) $region->name,
                'governorate' => (string) $region->governorate,
                'country_id' => $region->country_id ? (int) $region->country_id : null,
                'governorate_id' => $region->governorate_id ? (int) $region->governorate_id : null,
                'country' => $region->country ? [
                    'id' => (int) $region->country->id,
                    'name' => (string) $region->country->name,
                ] : null,
                'governorate_model' => $region->governorateModel ? [
                    'id' => (int) $region->governorateModel->id,
                    'country_id' => (int) $region->governorateModel->country_id,
                    'name' => (string) $region->governorateModel->name,
                ] : null,
            ])->all(),
        ]);
    }

    public function locationSchools(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            'governorate_id' => [
                'required',
                'integer',
                Rule::exists('governorates', 'id')->where(
                    fn ($query) => $query->where('country_id', (int) $request->input('country_id'))
                ),
            ],
        ], [], [
            'country_id' => 'الدولة',
            'governorate_id' => 'المحافظة',
        ]);

        $supervisorId = (int) $request->user()->id;
        $countryId = (int) $validated['country_id'];
        $governorateId = (int) $validated['governorate_id'];

        $schools = School::query()
            ->with('manager:id,name,email')
            ->whereHas('directorate', function ($query) use ($countryId, $governorateId): void {
                $query
                    ->where('country_id', $countryId)
                    ->where('governorate_id', $governorateId);
            })
            ->where(function ($query) use ($supervisorId): void {
                $query->whereNull('supervisor_id')
                    ->orWhere('supervisor_id', $supervisorId);
            })
            ->whereDoesntHave('supervisionRequests', function ($query) use ($supervisorId): void {
                $query->whereIn('status', SchoolSupervisionRequest::OPEN_STATUSES)
                    ->where('supervisor_id', '!=', $supervisorId);
            })
            ->whereDoesntHave('supervisorAssignments', function ($query) use ($supervisorId): void {
                $query->where('is_active', true)
                    ->whereNotNull('school_id')
                    ->where('supervisor_id', '!=', $supervisorId);
            })
            ->whereNotExists(function ($query) use ($supervisorId): void {
                $query->selectRaw('1')
                    ->from('school_supervisor_assignments')
                    ->whereColumn('school_supervisor_assignments.directorate_id', 'schools.directorate_id')
                    ->whereNull('school_supervisor_assignments.school_id')
                    ->where('school_supervisor_assignments.is_active', true)
                    ->where('school_supervisor_assignments.supervisor_id', '!=', $supervisorId);
            })
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'school_id',
                'directorate_id',
                'status',
                'supervision_status',
                'manager_user_id',
            ]);

        return response()->json($schools);
    }

    public function schools(Request $request, EducationalDirectorate $region): JsonResponse
    {
        $supervisorId = (int) $request->user()->id;

        $schools = School::query()
            ->with('manager:id,name,email')
            ->where('directorate_id', $region->id)
            ->where(function ($query) use ($supervisorId): void {
                $query->whereNull('supervisor_id')
                    ->orWhere('supervisor_id', $supervisorId);
            })
            ->whereDoesntHave('supervisionRequests', function ($query) use ($supervisorId): void {
                $query->whereIn('status', SchoolSupervisionRequest::OPEN_STATUSES)
                    ->where('supervisor_id', '!=', $supervisorId);
            })
            ->whereDoesntHave('supervisorAssignments', function ($query) use ($supervisorId): void {
                $query->where('is_active', true)
                    ->whereNotNull('school_id')
                    ->where('supervisor_id', '!=', $supervisorId);
            })
            ->whereNotExists(function ($query) use ($supervisorId): void {
                $query->selectRaw('1')
                    ->from('school_supervisor_assignments')
                    ->whereColumn('school_supervisor_assignments.directorate_id', 'schools.directorate_id')
                    ->whereNull('school_supervisor_assignments.school_id')
                    ->where('school_supervisor_assignments.is_active', true)
                    ->where('school_supervisor_assignments.supervisor_id', '!=', $supervisorId);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'school_id', 'directorate_id', 'status', 'supervision_status', 'manager_user_id']);

        return response()->json($schools);
    }

    public function select(Request $request): JsonResponse
    {
        if ($request->filled('region_id')) {
            $validated = $request->validate([
                'region_id' => 'required|exists:educational_directorates,id',
                'school_ids' => 'required|array|min:1',
                'school_ids.*' => 'required|integer|distinct|exists:schools,id',
            ]);

            $result = $this->requestService->createBySupervisorSelection(
                $request->user(),
                (int) $validated['region_id'],
                $validated['school_ids'],
                $request
            );

            if (count($result['created']) === 0) {
                return response()->json([
                    'message' => 'المدارس المختارة غير متاحة حاليًا أو مرتبطة بالفعل بمشرف آخر.',
                    'skipped_school_ids' => $result['skipped_school_ids'],
                ], 422);
            }

            $request->user()->update([
                'onboarding_region_id' => $validated['region_id'],
                'onboarding_completed_at' => now(),
            ]);

            return response()->json([
                'created_count' => count($result['created']),
                'skipped_school_ids' => $result['skipped_school_ids'],
            ]);
        }

        $validated = $request->validate([
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            'governorate_id' => [
                'required',
                'integer',
                Rule::exists('governorates', 'id')->where(
                    fn ($query) => $query->where('country_id', (int) $request->input('country_id'))
                ),
            ],
            'school_ids' => ['required', 'array', 'min:1'],
            'school_ids.*' => ['required', 'integer', 'distinct', Rule::exists('schools', 'id')],
        ], [], [
            'country_id' => 'الدولة',
            'governorate_id' => 'المحافظة',
            'school_ids' => 'المدارس',
            'school_ids.*' => 'المدرسة',
        ]);

        $result = $this->requestService->createBySupervisorLocationSelection(
            $request->user(),
            (int) $validated['country_id'],
            (int) $validated['governorate_id'],
            $validated['school_ids'],
            $request
        );

        if (count($result['created']) === 0) {
            return response()->json([
                'message' => 'المدارس المختارة غير متاحة حاليًا أو مرتبطة بالفعل بمشرف آخر.',
                'skipped_school_ids' => $result['skipped_school_ids'],
            ], 422);
        }

        $firstSelectedSchool = School::query()
            ->whereIn('id', $validated['school_ids'])
            ->whereHas('directorate', function ($query) use ($validated): void {
                $query
                    ->where('country_id', (int) $validated['country_id'])
                    ->where('governorate_id', (int) $validated['governorate_id']);
            })
            ->orderBy('id')
            ->first(['directorate_id']);

        $request->user()->update([
            'onboarding_region_id' => $firstSelectedSchool?->directorate_id,
            'onboarding_completed_at' => now(),
        ]);

        return response()->json([
            'created_count' => count($result['created']),
            'skipped_school_ids' => $result['skipped_school_ids'],
        ]);
    }
}
