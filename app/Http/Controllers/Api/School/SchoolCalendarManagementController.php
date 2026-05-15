<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\Calendar\DisableSchoolHolidayRequest;
use App\Http\Requests\School\Calendar\ListSchoolHolidaysRequest;
use App\Http\Requests\School\Calendar\ShowSchoolCalendarSettingsRequest;
use App\Http\Requests\School\Calendar\StoreSchoolHolidayRequest;
use App\Http\Requests\School\Calendar\UpdateSchoolCalendarSettingsRequest;
use App\Http\Requests\School\Calendar\UpdateSchoolHolidayRequest;
use App\Models\SchoolHoliday;
use App\Services\Integrity\IntegrityImpactService;
use App\Services\School\SchoolCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SchoolCalendarManagementController extends Controller
{
    public function __construct(
        private readonly SchoolCalendarService $schoolCalendarService,
        private readonly IntegrityImpactService $integrityImpactService,
    ) {
    }

    public function showSettings(ShowSchoolCalendarSettingsRequest $request): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) ($request->user()?->id ?? 0);
        $settings = $this->schoolCalendarService->getOrCreateSettings($schoolId, $actorId > 0 ? $actorId : null);

        return response()->json([
            'data' => $this->serializeSettings($settings),
        ]);
    }

    public function updateSettings(UpdateSchoolCalendarSettingsRequest $request): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) $request->user()->id;

        $settings = $this->schoolCalendarService->updateSettings(
            $schoolId,
            $actorId,
            $request->validated(),
            $request
        );

        return response()->json([
            'data' => $this->serializeSettings($settings),
        ]);
    }

    public function indexHolidays(ListSchoolHolidaysRequest $request): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $holidays = $this->schoolCalendarService->listHolidays($schoolId, $request->validated());

        return response()->json([
            'data' => $holidays->map(fn (SchoolHoliday $holiday) => $this->serializeHoliday($holiday))->values()->all(),
        ]);
    }

    public function storeHoliday(StoreSchoolHolidayRequest $request): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) $request->user()->id;

        $holiday = $this->schoolCalendarService->createHoliday(
            $schoolId,
            $actorId,
            $request->validated(),
            $request
        );

        return response()->json([
            'data' => $this->serializeHoliday($holiday),
        ], 201);
    }

    public function updateHoliday(
        UpdateSchoolHolidayRequest $request,
        SchoolHoliday $schoolHoliday
    ): JsonResponse {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) $request->user()->id;
        $validated = $request->validated();
        $impact = $this->integrityImpactService->checkUpdateImpact(
            'school_holiday',
            (int) $schoolHoliday->id,
            $validated,
            $schoolId
        );
        if (($impact['requires_confirmation'] ?? false) && !$request->boolean('confirm_impact')) {
            return response()->json([
                'message' => (string) $impact['message'],
                'message_code' => (string) $impact['message_code'],
                'impact' => $impact,
            ], 409);
        }

        $holiday = $this->schoolCalendarService->updateHoliday(
            $schoolHoliday,
            $schoolId,
            $actorId,
            $validated,
            $request->boolean('confirm_impact'),
            $request
        );

        return response()->json([
            'data' => $this->serializeHoliday($holiday),
        ]);
    }

    public function disableHoliday(
        DisableSchoolHolidayRequest $request,
        SchoolHoliday $schoolHoliday
    ): JsonResponse {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) $request->user()->id;
        $impact = $this->integrityImpactService->checkDeleteImpact(
            'school_holiday',
            (int) $schoolHoliday->id,
            $schoolId
        );
        if (($impact['requires_confirmation'] ?? false) && !$request->boolean('confirm_impact')) {
            return response()->json([
                'message' => (string) $impact['message'],
                'message_code' => (string) $impact['message_code'],
                'impact' => $impact,
            ], 409);
        }

        $holiday = $this->schoolCalendarService->disableHoliday(
            $schoolHoliday,
            $schoolId,
            $actorId,
            $request
        );

        return response()->json([
            'data' => $this->serializeHoliday($holiday),
        ]);
    }

    public function holidayDeleteImpact(Request $request, SchoolHoliday $schoolHoliday): JsonResponse
    {
        if (!$request->user()?->can('manage-school-holidays')) {
            abort(403, 'You do not have permission to view holiday impact.');
        }

        $schoolId = $this->resolveSchoolId($request);
        $impact = $this->integrityImpactService->checkDeleteImpact(
            'school_holiday',
            (int) $schoolHoliday->id,
            $schoolId
        );

        return response()->json([
            'data' => $impact,
        ]);
    }

    public function holidayUpdateImpact(Request $request, SchoolHoliday $schoolHoliday): JsonResponse
    {
        if (!$request->user()?->can('manage-school-holidays')) {
            abort(403, 'You do not have permission to view holiday impact.');
        }

        $schoolId = $this->resolveSchoolId($request);
        $patch = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'days_count' => ['nullable', 'integer', 'min:1', 'max:365'],
            'end_date' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $impact = $this->integrityImpactService->checkUpdateImpact(
            'school_holiday',
            (int) $schoolHoliday->id,
            $patch,
            $schoolId
        );

        return response()->json([
            'data' => $impact,
        ]);
    }

    private function resolveSchoolId(Request $request): int
    {
        $schoolId = (int) $request->attributes->get('school_context_id', (int) ($request->user()?->school_id ?? 0));
        if ($schoolId <= 0) {
            throw ValidationException::withMessages([
                'school' => 'School context is required.',
            ]);
        }

        return $schoolId;
    }

    /**
     * @param \App\Models\SchoolCalendarSetting $settings
     * @return array<string, mixed>
     */
    private function serializeSettings($settings): array
    {
        return [
            'id' => (int) $settings->id,
            'school_id' => (int) $settings->school_id,
            'week_start_day' => (int) $settings->week_start_day,
            'weekly_off_days' => collect($settings->weekly_off_days ?? [])->map(fn ($day) => (int) $day)->values()->all(),
            'created_by' => $settings->created_by,
            'updated_by' => $settings->updated_by,
            'created_at' => optional($settings->created_at)->toISOString(),
            'updated_at' => optional($settings->updated_at)->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeHoliday(SchoolHoliday $holiday): array
    {
        return [
            'id' => (int) $holiday->id,
            'school_id' => (int) $holiday->school_id,
            'name' => $holiday->name,
            'start_date' => $holiday->start_date?->toDateString(),
            'end_date' => $holiday->end_date?->toDateString(),
            'days_count' => $holiday->start_date && $holiday->end_date
                ? ($holiday->start_date->diffInDays($holiday->end_date) + 1)
                : null,
            'return_date' => $holiday->return_date?->toDateString(),
            'notes' => $holiday->notes,
            'is_active' => (bool) $holiday->is_active,
            'created_by' => $holiday->created_by,
            'updated_by' => $holiday->updated_by,
            'created_at' => optional($holiday->created_at)->toISOString(),
            'updated_at' => optional($holiday->updated_at)->toISOString(),
        ];
    }
}
