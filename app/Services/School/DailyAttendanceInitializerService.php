<?php

namespace App\Services\School;

use App\Models\SchoolClassroom;
use App\Models\SchoolStudent;
use App\Models\SchoolStudentAttendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DailyAttendanceInitializerService
{
    public function __construct(
        private readonly SchoolCalendarService $schoolCalendarService,
    ) {
    }

    /**
     * @return array{
     *   school_id:int,
     *   classroom_id:int,
     *   attendance_date:string,
     *   day_type:string,
     *   total_students:int,
     *   existing_records:int,
     *   inserted_records:int,
     *   skipped:bool,
     *   skip_reason:?string
     * }
     */
    public function ensureDailyRecordsForClassroom(
        int $schoolId,
        int $classroomId,
        string $attendanceDate,
        ?int $actorId = null,
        bool $dryRun = false
    ): array {
        $normalizedDate = $this->normalizeDate($attendanceDate);
        $hasAcademicYears = $this->schoolCalendarService->hasAcademicYears($schoolId);
        $hasAcademicTerms = $this->schoolCalendarService->hasAcademicTerms($schoolId);
        $shouldEnforceAcademicPeriod = $hasAcademicYears || $hasAcademicTerms;
        $academicContext = $shouldEnforceAcademicPeriod
            ? $this->schoolCalendarService->resolveOperationalAcademicContextForDate($schoolId, $normalizedDate)
            : ['academic_year' => null, 'term' => null];

        if (
            ($hasAcademicYears && !$academicContext['academic_year'])
            || ($hasAcademicTerms && !$academicContext['term'])
        ) {
            return [
                'school_id' => $schoolId,
                'classroom_id' => $classroomId,
                'attendance_date' => $normalizedDate,
                'day_type' => 'OUTSIDE_ACADEMIC_PERIOD',
                'total_students' => 0,
                'existing_records' => 0,
                'inserted_records' => 0,
                'skipped' => true,
                'skip_reason' => 'outside_academic_period',
            ];
        }

        $dayState = $this->schoolCalendarService->resolveDayTypeForDate($schoolId, $normalizedDate);
        $dayType = strtoupper((string) ($dayState['day_type'] ?? 'SCHOOL_DAY'));

        if ($dayType !== 'SCHOOL_DAY') {
            return [
                'school_id' => $schoolId,
                'classroom_id' => $classroomId,
                'attendance_date' => $normalizedDate,
                'day_type' => $dayType,
                'total_students' => 0,
                'existing_records' => 0,
                'inserted_records' => 0,
                'skipped' => true,
                'skip_reason' => 'non_school_day',
            ];
        }

        $classroomExists = SchoolClassroom::query()
            ->whereKey($classroomId)
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->whereHas('stage', fn ($stageQuery) => $stageQuery
                ->where('school_id', $schoolId)
                ->where('is_active', true))
            ->exists();

        if (!$classroomExists) {
            return [
                'school_id' => $schoolId,
                'classroom_id' => $classroomId,
                'attendance_date' => $normalizedDate,
                'day_type' => $dayType,
                'total_students' => 0,
                'existing_records' => 0,
                'inserted_records' => 0,
                'skipped' => true,
                'skip_reason' => 'classroom_not_eligible',
            ];
        }

        $studentIds = SchoolStudent::query()
            ->where('school_id', $schoolId)
            ->where('school_classroom_id', $classroomId)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($studentIds->isEmpty()) {
            return [
                'school_id' => $schoolId,
                'classroom_id' => $classroomId,
                'attendance_date' => $normalizedDate,
                'day_type' => $dayType,
                'total_students' => 0,
                'existing_records' => 0,
                'inserted_records' => 0,
                'skipped' => true,
                'skip_reason' => 'no_active_students',
            ];
        }

        $existingStudentIds = SchoolStudentAttendance::query()
            ->where('school_id', $schoolId)
            ->whereDate('attendance_date', $normalizedDate)
            ->whereIn('school_student_id', $studentIds->all())
            ->pluck('school_student_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $missingStudentIds = $studentIds
            ->diff($existingStudentIds)
            ->values();

        $inserted = 0;
        if (!$dryRun && $missingStudentIds->isNotEmpty()) {
            $inserted = $this->insertDefaultPresentRecords(
                $schoolId,
                $classroomId,
                $normalizedDate,
                $missingStudentIds,
                $actorId
            );
        } else {
            $inserted = $missingStudentIds->count();
        }

        return [
            'school_id' => $schoolId,
            'classroom_id' => $classroomId,
            'attendance_date' => $normalizedDate,
            'day_type' => $dayType,
            'total_students' => $studentIds->count(),
            'existing_records' => $existingStudentIds->count(),
            'inserted_records' => $inserted,
            'skipped' => false,
            'skip_reason' => null,
        ];
    }

    /**
     * @return array{
     *   school_id:int,
     *   attendance_date:string,
     *   day_type:string,
     *   total_classrooms:int,
     *   total_students:int,
     *   existing_records:int,
     *   inserted_records:int,
     *   skipped:bool,
     *   skip_reason:?string,
     *   classrooms:array<int, array<string, mixed>>
     * }
     */
    public function ensureDailyRecordsForSchool(
        int $schoolId,
        string $attendanceDate,
        ?int $actorId = null,
        ?int $classroomId = null,
        bool $dryRun = false
    ): array {
        $normalizedDate = $this->normalizeDate($attendanceDate);
        $hasAcademicYears = $this->schoolCalendarService->hasAcademicYears($schoolId);
        $hasAcademicTerms = $this->schoolCalendarService->hasAcademicTerms($schoolId);
        $shouldEnforceAcademicPeriod = $hasAcademicYears || $hasAcademicTerms;
        $academicContext = $shouldEnforceAcademicPeriod
            ? $this->schoolCalendarService->resolveOperationalAcademicContextForDate($schoolId, $normalizedDate)
            : ['academic_year' => null, 'term' => null];
        $dayState = $this->schoolCalendarService->resolveDayTypeForDate($schoolId, $normalizedDate);
        $dayType = strtoupper((string) ($dayState['day_type'] ?? 'SCHOOL_DAY'));

        $classroomQuery = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->whereHas('stage', fn ($stageQuery) => $stageQuery
                ->where('school_id', $schoolId)
                ->where('is_active', true))
            ->orderBy('id');

        if ($classroomId !== null && $classroomId > 0) {
            $classroomQuery->whereKey($classroomId);
        }

        /** @var Collection<int, int> $classroomIds */
        $classroomIds = $classroomQuery->pluck('id')->map(fn ($id) => (int) $id)->values();

        if (
            ($hasAcademicYears && !$academicContext['academic_year'])
            || ($hasAcademicTerms && !$academicContext['term'])
        ) {
            return [
                'school_id' => $schoolId,
                'attendance_date' => $normalizedDate,
                'day_type' => 'OUTSIDE_ACADEMIC_PERIOD',
                'total_classrooms' => $classroomIds->count(),
                'total_students' => 0,
                'existing_records' => 0,
                'inserted_records' => 0,
                'skipped' => true,
                'skip_reason' => 'outside_academic_period',
                'classrooms' => [],
            ];
        }

        if ($dayType !== 'SCHOOL_DAY') {
            return [
                'school_id' => $schoolId,
                'attendance_date' => $normalizedDate,
                'day_type' => $dayType,
                'total_classrooms' => $classroomIds->count(),
                'total_students' => 0,
                'existing_records' => 0,
                'inserted_records' => 0,
                'skipped' => true,
                'skip_reason' => 'non_school_day',
                'classrooms' => [],
            ];
        }

        $rows = [];
        $totalStudents = 0;
        $existingRecords = 0;
        $insertedRecords = 0;

        foreach ($classroomIds as $id) {
            $row = $this->ensureDailyRecordsForClassroom($schoolId, $id, $normalizedDate, $actorId, $dryRun);
            $rows[] = $row;
            $totalStudents += (int) ($row['total_students'] ?? 0);
            $existingRecords += (int) ($row['existing_records'] ?? 0);
            $insertedRecords += (int) ($row['inserted_records'] ?? 0);
        }

        return [
            'school_id' => $schoolId,
            'attendance_date' => $normalizedDate,
            'day_type' => $dayType,
            'total_classrooms' => $classroomIds->count(),
            'total_students' => $totalStudents,
            'existing_records' => $existingRecords,
            'inserted_records' => $insertedRecords,
            'skipped' => false,
            'skip_reason' => null,
            'classrooms' => $rows,
        ];
    }

    /**
     * @param Collection<int, int> $studentIds
     */
    private function insertDefaultPresentRecords(
        int $schoolId,
        int $classroomId,
        string $attendanceDate,
        Collection $studentIds,
        ?int $actorId = null
    ): int {
        if ($studentIds->isEmpty()) {
            return 0;
        }

        $actor = $actorId !== null && $actorId > 0 ? $actorId : null;
        $now = now();
        $inserted = 0;

        DB::transaction(function () use (
            $studentIds,
            $schoolId,
            $classroomId,
            $attendanceDate,
            $actor,
            $now,
            &$inserted
        ): void {
            foreach ($studentIds->chunk(500) as $chunk) {
                $payload = $chunk
                    ->map(fn (int $studentId): array => [
                        'school_id' => $schoolId,
                        'school_student_id' => $studentId,
                        'school_classroom_id' => $classroomId,
                        'attendance_date' => $attendanceDate,
                        'status' => SchoolStudentAttendance::STATUS_PRESENT,
                        'recorded_by' => $actor,
                        'updated_by' => $actor,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->values()
                    ->all();

                $inserted += (int) SchoolStudentAttendance::query()->insertOrIgnore($payload);
            }
        });

        return $inserted;
    }

    private function normalizeDate(string $value): string
    {
        try {
            return trim($value) !== '' ? Carbon::parse($value)->toDateString() : now()->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }
}
