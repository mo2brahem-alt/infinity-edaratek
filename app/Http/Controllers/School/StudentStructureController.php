<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolClassroom;
use App\Models\SchoolStage;
use App\Models\SchoolStageGrade;
use App\Models\SchoolStageGradeTerm;
use App\Models\SchoolStageTerm;
use App\Models\SchoolStudent;
use App\Services\Integrity\IntegrityImpactService;
use App\Services\School\SchoolDefaultDataProvisioningService;
use App\Services\School\StudentImportService;
use App\Services\Support\AttachmentService;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentStructureController extends Controller
{
    public function __construct(
        private readonly IntegrityImpactService $integrityImpactService,
        private readonly AuditLogger $auditLogger,
        private readonly SchoolDefaultDataProvisioningService $schoolDefaultDataProvisioningService,
        private readonly AttachmentService $attachmentService,
    ) {
    }

    public function index(Request $request): Response
    {
        $schoolId = $this->resolveSchoolId($request);
        $user = $request->user();

        $school = School::query()
            ->whereKey($schoolId)
            ->with(['defaultDataImporter:id,name'])
            ->first(['id', 'name', 'school_id', 'default_data_imported_at', 'default_data_imported_by']);

        $stages = SchoolStage::query()
            ->where('school_id', $schoolId)
            ->with([
                'stageTerms' => function ($stageTerms) use ($schoolId): void {
                    $stageTerms
                        ->where('school_id', $schoolId)
                        ->orderBy('sort_order')
                        ->orderBy('name');
                },
                'grades' => function ($grades) use ($schoolId): void {
                    $grades
                        ->where('school_id', $schoolId)
                        ->with([
                            'gradeTerms' => fn ($gradeTerms) => $gradeTerms
                                ->where('school_id', $schoolId)
                                ->orderBy('sort_order')
                                ->orderBy('name'),
                        ])
                        ->orderBy('sort_order')
                        ->orderBy('name');
                },
                'classrooms' => function ($classrooms) use ($schoolId): void {
                    $classrooms
                        ->where('school_id', $schoolId)
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->with([
                            'students' => fn ($students) => $students
                                ->where('school_id', $schoolId)
                                ->orderBy('full_name')
                                ->with([
                                    'attachments' => fn ($attachments) => $attachments
                                        ->whereNull('deleted_at')
                                        ->with('uploader:id,name')
                                        ->orderByDesc('id'),
                                ]),
                        ]);
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('School/StudentStructure', [
            'school' => $school,
            'stages' => $stages,
            'isManager' => $user?->hasSystemRole('school_manager') ?? false,
            'defaultDataProvisioning' => $school
                ? $this->schoolDefaultDataProvisioningService->schoolProvisioningStatus(
                    $school,
                    $user?->canImportSchoolDefaultData() ?? false
                )
                : null,
            'permissions' => [
                'can_manage_student_structure' => $user?->canManageStudentStructure() ?? false,
                'can_manage_student_attendance' => $user?->canManageStudentAttendance() ?? false,
                'can_manage_academic_planning' => $user?->canManageAcademicPlanning() ?? false,
                'can_manage_student_leaves' => $user?->canManageStudentLeaves() ?? false,
            ],
        ]);
    }

    public function storeStage(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) ($request->user()?->id ?? 0);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('school_stages', 'name')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('school_stages', 'code')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'school_day_start_time' => ['nullable', 'date_format:H:i', 'required_with:school_day_end_time', 'before:school_day_end_time'],
            'school_day_end_time' => ['nullable', 'date_format:H:i', 'required_with:school_day_start_time', 'after:school_day_start_time'],
        ], [
            'name.unique' => 'Ø§Ø³Ù… Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠØ© Ù…ÙˆØ¬ÙˆØ¯ Ù…Ø³Ø¨Ù‚Ù‹Ø§ ÙÙŠ Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø¯Ø±Ø³Ø©.',
        ]);

        try {
            $stage = DB::transaction(function () use ($validated, $schoolId): SchoolStage {
                $providedCode = $this->normalizeCodeInput($validated['code'] ?? null);
                $resolvedCode = $providedCode ?: $this->generateScopedCode('school_stages', 'code', 'STG', $schoolId);
                $this->assertScopedCodeAvailable(
                    'school_stages',
                    'code',
                    $resolvedCode,
                    $schoolId,
                    null,
                    'code',
                    'Stage code already exists in this school.'
                );

                return SchoolStage::create([
                    'school_id' => $schoolId,
                    'name' => $validated['name'],
                    'code' => $resolvedCode,
                    'sort_order' => (int) ($validated['sort_order'] ?? 0),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                    'school_day_start_time' => $this->normalizeTimeInput($validated['school_day_start_time'] ?? null),
                    'school_day_end_time' => $this->normalizeTimeInput($validated['school_day_end_time'] ?? null),
                ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'Ø§Ø³Ù… Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠØ© Ù…ÙˆØ¬ÙˆØ¯ Ù…Ø³Ø¨Ù‚Ù‹Ø§ ÙÙŠ Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø¯Ø±Ø³Ø©.',
                'code' => 'Stage code already exists in this school.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'student_structure.stage.created',
            'school_stage',
            (int) $stage->id,
            [
                'school_id' => $schoolId,
                'payload' => $stage->only(['name', 'code', 'sort_order', 'is_active', 'school_day_start_time', 'school_day_end_time']),
                'code_source' => $this->normalizeCodeInput($validated['code'] ?? null) ? 'provided' : 'generated',
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    public function updateStage(Request $request, SchoolStage $schoolStage): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureStageInSchool($schoolStage, $schoolId);
        $actorId = (int) ($request->user()?->id ?? 0);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('school_stages', 'name')
                    ->where(fn ($query) => $query->where('school_id', $schoolId))
                    ->ignore($schoolStage->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('school_stages', 'code')
                    ->where(fn ($query) => $query->where('school_id', $schoolId))
                    ->ignore($schoolStage->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'school_day_start_time' => ['nullable', 'date_format:H:i', 'required_with:school_day_end_time', 'before:school_day_end_time'],
            'school_day_end_time' => ['nullable', 'date_format:H:i', 'required_with:school_day_start_time', 'after:school_day_start_time'],
        ], [
            'name.unique' => 'Ø§Ø³Ù… Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠØ© Ù…ÙˆØ¬ÙˆØ¯ Ù…Ø³Ø¨Ù‚Ù‹Ø§ ÙÙŠ Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø¯Ø±Ø³Ø©.',
        ]);

        $before = $schoolStage->only(['name', 'code', 'sort_order', 'is_active', 'school_day_start_time', 'school_day_end_time']);

        try {
            DB::transaction(function () use ($schoolStage, $validated, $schoolId): void {
                $providedCode = $this->normalizeCodeInput($validated['code'] ?? null);
                $resolvedCode = $providedCode ?: ($schoolStage->code ?: $this->generateScopedCode('school_stages', 'code', 'STG', $schoolId));
                $this->assertScopedCodeAvailable(
                    'school_stages',
                    'code',
                    $resolvedCode,
                    $schoolId,
                    (int) $schoolStage->id,
                    'code',
                    'Stage code already exists in this school.'
                );

                $schoolStage->update([
                    'name' => $validated['name'],
                    'code' => $resolvedCode,
                    'sort_order' => (int) ($validated['sort_order'] ?? 0),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                    'school_day_start_time' => $this->normalizeTimeInput($validated['school_day_start_time'] ?? null),
                    'school_day_end_time' => $this->normalizeTimeInput($validated['school_day_end_time'] ?? null),
                ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'Ø§Ø³Ù… Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠØ© Ù…ÙˆØ¬ÙˆØ¯ Ù…Ø³Ø¨Ù‚Ù‹Ø§ ÙÙŠ Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø¯Ø±Ø³Ø©.',
                'code' => 'Stage code already exists in this school.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'student_structure.stage.updated',
            'school_stage',
            (int) $schoolStage->id,
            [
                'school_id' => $schoolId,
                'before' => $before,
                'after' => $schoolStage->only(['name', 'code', 'sort_order', 'is_active', 'school_day_start_time', 'school_day_end_time']),
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    public function destroyStage(Request $request, SchoolStage $schoolStage): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureStageInSchool($schoolStage, $schoolId);
        $actorId = (int) ($request->user()?->id ?? 0);
        $impact = $this->integrityImpactService->checkDeleteImpact(
            'school_stage',
            (int) $schoolStage->id,
            $schoolId
        );

        if (!($impact['allowed'] ?? false)) {
            $this->auditLogger->log(
                'student_structure.stage.delete_blocked',
                'school_stage',
                (int) $schoolStage->id,
                [
                    'school_id' => $schoolId,
                    'impact' => $this->normalizeImpact($impact),
                ],
                $request,
                $actorId > 0 ? $actorId : null
            );

            throw ValidationException::withMessages([
                'stage' => (string) ($impact['message'] ?? 'Ù„Ø§ ÙŠÙ…ÙƒÙ† Ø­Ø°Ù Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠØ© Ù„ÙˆØ¬ÙˆØ¯ Ø¨ÙŠØ§Ù†Ø§Øª Ù…Ø±ØªØ¨Ø·Ø©.'),
            ]);
        }

        if (($impact['requires_confirmation'] ?? false) && !$request->boolean('confirm_impact')) {
            throw ValidationException::withMessages([
                'confirm_impact' => (string) ($impact['message'] ?? 'ØªØ£ÙƒÙŠØ¯ Ø§Ù„Ø¹Ù…Ù„ÙŠØ© Ù…Ø·Ù„ÙˆØ¨ Ø¨Ø³Ø¨Ø¨ ÙˆØ¬ÙˆØ¯ Ø¨ÙŠØ§Ù†Ø§Øª Ù…Ø±ØªØ¨Ø·Ø©.'),
            ]);
        }

        $snapshot = $schoolStage->only(['school_id', 'name', 'code', 'sort_order', 'is_active', 'school_day_start_time', 'school_day_end_time']);

        $schoolStage->delete();
        $this->auditLogger->log(
            'student_structure.stage.deleted',
            'school_stage',
            (int) $schoolStage->id,
            [
                'school_id' => $schoolId,
                'before' => $snapshot,
                'impact' => $this->normalizeImpact($impact),
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    public function storeStageTerm(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) ($request->user()?->id ?? 0);

        $validated = $request->validate([
            'school_stage_id' => [
                'required',
                Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('school_stage_terms', 'name')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('school_stage_id', (int) $request->input('school_stage_id'))),
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'Ø§Ø³Ù… Ø§Ù„ÙØµÙ„ Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠ Ù…Ø³ØªØ®Ø¯Ù… Ù…Ø³Ø¨Ù‚Ù‹Ø§ Ø¯Ø§Ø®Ù„ Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø±Ø­Ù„Ø©.',
        ]);

        $this->assertDateRangeOrder(
            startDate: $validated['start_date'] ?? null,
            endDate: $validated['end_date'] ?? null,
            startField: 'start_date',
            message: 'ØªØ§Ø±ÙŠØ® Ù†Ù‡Ø§ÙŠØ© Ø§Ù„ÙØµÙ„ Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠ ÙŠØ¬Ø¨ Ø£Ù† ÙŠÙƒÙˆÙ† Ø¨Ø¹Ø¯ ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¨Ø¯Ø§ÙŠØ© Ø£Ùˆ Ù…Ø³Ø§ÙˆÙŠÙ‹Ø§ Ù„Ù‡.'
        );

        try {
            $stageTerm = SchoolStageTerm::query()->create([
                'school_id' => $schoolId,
                'school_stage_id' => (int) $validated['school_stage_id'],
                'name' => trim((string) $validated['name']),
                'start_date' => $this->normalizeDateInput($validated['start_date'] ?? null),
                'end_date' => $this->normalizeDateInput($validated['end_date'] ?? null),
                'source' => 'manual',
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'Ø§Ø³Ù… Ø§Ù„ÙØµÙ„ Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠ Ù…Ø³ØªØ®Ø¯Ù… Ù…Ø³Ø¨Ù‚Ù‹Ø§ Ø¯Ø§Ø®Ù„ Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø±Ø­Ù„Ø©.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'student_structure.stage_term.created',
            'school_stage_term',
            (int) $stageTerm->id,
            [
                'school_id' => $schoolId,
                'payload' => $stageTerm->only(['school_stage_id', 'name', 'start_date', 'end_date', 'source', 'sort_order', 'is_active']),
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    public function updateStageTerm(Request $request, SchoolStageTerm $schoolStageTerm): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureStageTermInSchool($schoolStageTerm, $schoolId);
        $actorId = (int) ($request->user()?->id ?? 0);

        $validated = $request->validate([
            'school_stage_id' => [
                'required',
                Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('school_stage_terms', 'name')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('school_stage_id', (int) $request->input('school_stage_id')))
                    ->ignore($schoolStageTerm->id),
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'Ø§Ø³Ù… Ø§Ù„ÙØµÙ„ Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠ Ù…Ø³ØªØ®Ø¯Ù… Ù…Ø³Ø¨Ù‚Ù‹Ø§ Ø¯Ø§Ø®Ù„ Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø±Ø­Ù„Ø©.',
        ]);

        $this->assertDateRangeOrder(
            startDate: $validated['start_date'] ?? null,
            endDate: $validated['end_date'] ?? null,
            startField: 'start_date',
            message: 'ØªØ§Ø±ÙŠØ® Ù†Ù‡Ø§ÙŠØ© Ø§Ù„ÙØµÙ„ Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠ ÙŠØ¬Ø¨ Ø£Ù† ÙŠÙƒÙˆÙ† Ø¨Ø¹Ø¯ ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¨Ø¯Ø§ÙŠØ© Ø£Ùˆ Ù…Ø³Ø§ÙˆÙŠÙ‹Ø§ Ù„Ù‡.'
        );

        $before = $schoolStageTerm->only(['school_stage_id', 'name', 'start_date', 'end_date', 'source', 'sort_order', 'is_active']);

        try {
            $schoolStageTerm->update([
                'school_stage_id' => (int) $validated['school_stage_id'],
                'name' => trim((string) $validated['name']),
                'start_date' => $this->normalizeDateInput($validated['start_date'] ?? null),
                'end_date' => $this->normalizeDateInput($validated['end_date'] ?? null),
                'source' => 'manual',
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'Ø§Ø³Ù… Ø§Ù„ÙØµÙ„ Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠ Ù…Ø³ØªØ®Ø¯Ù… Ù…Ø³Ø¨Ù‚Ù‹Ø§ Ø¯Ø§Ø®Ù„ Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø±Ø­Ù„Ø©.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'student_structure.stage_term.updated',
            'school_stage_term',
            (int) $schoolStageTerm->id,
            [
                'school_id' => $schoolId,
                'before' => $before,
                'after' => $schoolStageTerm->only(['school_stage_id', 'name', 'start_date', 'end_date', 'source', 'sort_order', 'is_active']),
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    public function destroyStageTerm(Request $request, SchoolStageTerm $schoolStageTerm): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureStageTermInSchool($schoolStageTerm, $schoolId);
        $actorId = (int) ($request->user()?->id ?? 0);

        $snapshot = $schoolStageTerm->only(['school_id', 'school_stage_id', 'name', 'start_date', 'end_date', 'source', 'sort_order', 'is_active']);
        $schoolStageTerm->delete();

        $this->auditLogger->log(
            'student_structure.stage_term.deleted',
            'school_stage_term',
            (int) $schoolStageTerm->id,
            [
                'school_id' => $schoolId,
                'before' => $snapshot,
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    public function storeStageGrade(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) ($request->user()?->id ?? 0);

        $validated = $request->validate([
            'school_stage_id' => [
                'required',
                Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('school_stage_grades', 'name')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('school_stage_id', (int) $request->input('school_stage_id'))),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'Ø§Ø³Ù… Ø§Ù„ØµÙ Ù…ÙˆØ¬ÙˆØ¯ Ù…Ø³Ø¨Ù‚Ù‹Ø§ Ø¯Ø§Ø®Ù„ Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ù…Ø­Ø¯Ø¯Ø©.',
        ]);

        $normalizedGradeName = $this->normalizeGradeInput($validated['name']) ?? '';
        if ($normalizedGradeName === '') {
            throw ValidationException::withMessages([
                'name' => 'Ø§Ø³Ù… Ø§Ù„ØµÙ Ù…Ø·Ù„ÙˆØ¨.',
            ]);
        }

        try {
            $stageGrade = DB::transaction(function () use ($validated, $schoolId, $normalizedGradeName): SchoolStageGrade {
                return SchoolStageGrade::query()->create([
                    'school_id' => $schoolId,
                    'school_stage_id' => (int) $validated['school_stage_id'],
                    'name' => $normalizedGradeName,
                    'sort_order' => (int) ($validated['sort_order'] ?? 0),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'Ø§Ø³Ù… Ø§Ù„ØµÙ Ù…ÙˆØ¬ÙˆØ¯ Ù…Ø³Ø¨Ù‚Ù‹Ø§ Ø¯Ø§Ø®Ù„ Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ù…Ø­Ø¯Ø¯Ø©.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'student_structure.stage_grade.created',
            'school_stage_grade',
            (int) $stageGrade->id,
            [
                'school_id' => $schoolId,
                'payload' => $stageGrade->only(['school_stage_id', 'name', 'sort_order', 'is_active']),
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    public function updateStageGrade(Request $request, SchoolStageGrade $schoolStageGrade): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureStageGradeInSchool($schoolStageGrade, $schoolId);
        $actorId = (int) ($request->user()?->id ?? 0);

        $validated = $request->validate([
            'school_stage_id' => [
                'required',
                Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('school_stage_grades', 'name')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('school_stage_id', (int) $request->input('school_stage_id')))
                    ->ignore($schoolStageGrade->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'Ø§Ø³Ù… Ø§Ù„ØµÙ Ù…ÙˆØ¬ÙˆØ¯ Ù…Ø³Ø¨Ù‚Ù‹Ø§ Ø¯Ø§Ø®Ù„ Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ù…Ø­Ø¯Ø¯Ø©.',
        ]);

        $normalizedGradeName = $this->normalizeGradeInput($validated['name']) ?? '';
        if ($normalizedGradeName === '') {
            throw ValidationException::withMessages([
                'name' => 'Ø§Ø³Ù… Ø§Ù„ØµÙ Ù…Ø·Ù„ÙˆØ¨.',
            ]);
        }

        $before = $schoolStageGrade->only(['school_stage_id', 'name', 'sort_order', 'is_active']);
        $oldStageId = (int) $schoolStageGrade->school_stage_id;
        $newStageId = (int) $validated['school_stage_id'];

        if ($newStageId !== $oldStageId) {
            $hasRelatedClassrooms = SchoolClassroom::query()
                ->where('school_id', $schoolId)
                ->where('school_stage_id', $oldStageId)
                ->where('grade_name', (string) $schoolStageGrade->name)
                ->exists();

            if ($hasRelatedClassrooms) {
                throw ValidationException::withMessages([
                    'school_stage_id' => 'Ù„Ø§ ÙŠÙ…ÙƒÙ† Ù†Ù‚Ù„ Ø§Ù„ØµÙ Ø¥Ù„Ù‰ Ù…Ø±Ø­Ù„Ø© Ø£Ø®Ø±Ù‰ Ù„ÙˆØ¬ÙˆØ¯ ÙØµÙˆÙ„ Ù…Ø±ØªØ¨Ø·Ø© Ø¨Ù‡.',
                ]);
            }
        }

        try {
            DB::transaction(function () use ($schoolStageGrade, $validated, $normalizedGradeName): void {
                $oldStageId = (int) $schoolStageGrade->school_stage_id;
                $oldGradeName = (string) $schoolStageGrade->name;
                $newStageId = (int) $validated['school_stage_id'];

                $schoolStageGrade->update([
                    'school_stage_id' => $newStageId,
                    'name' => $normalizedGradeName,
                    'sort_order' => (int) ($validated['sort_order'] ?? 0),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                ]);

                SchoolClassroom::query()
                    ->where('school_id', (int) $schoolStageGrade->school_id)
                    ->where('school_stage_id', $oldStageId)
                    ->where('grade_name', $oldGradeName)
                    ->update([
                        'grade_name' => $normalizedGradeName,
                    ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'Ø§Ø³Ù… Ø§Ù„ØµÙ Ù…ÙˆØ¬ÙˆØ¯ Ù…Ø³Ø¨Ù‚Ù‹Ø§ Ø¯Ø§Ø®Ù„ Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ù…Ø­Ø¯Ø¯Ø©.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'student_structure.stage_grade.updated',
            'school_stage_grade',
            (int) $schoolStageGrade->id,
            [
                'school_id' => $schoolId,
                'before' => $before,
                'after' => $schoolStageGrade->only(['school_stage_id', 'name', 'sort_order', 'is_active']),
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    public function destroyStageGrade(Request $request, SchoolStageGrade $schoolStageGrade): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureStageGradeInSchool($schoolStageGrade, $schoolId);
        $actorId = (int) ($request->user()?->id ?? 0);

        $hasClassrooms = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->where('school_stage_id', (int) $schoolStageGrade->school_stage_id)
            ->where('grade_name', (string) $schoolStageGrade->name)
            ->exists();

        if ($hasClassrooms) {
            $this->auditLogger->log(
                'student_structure.stage_grade.delete_blocked',
                'school_stage_grade',
                (int) $schoolStageGrade->id,
                [
                    'school_id' => $schoolId,
                    'reason' => 'linked_classrooms',
                ],
                $request,
                $actorId > 0 ? $actorId : null
            );

            throw ValidationException::withMessages([
                'stage_grade' => 'Ù„Ø§ ÙŠÙ…ÙƒÙ† Ø­Ø°Ù Ø§Ù„ØµÙ Ù„ÙˆØ¬ÙˆØ¯ ÙØµÙˆÙ„ Ù…Ø±ØªØ¨Ø·Ø© Ø¨Ù‡.',
            ]);
        }

        $snapshot = $schoolStageGrade->only(['school_id', 'school_stage_id', 'name', 'sort_order', 'is_active']);
        $schoolStageGrade->delete();

        $this->auditLogger->log(
            'student_structure.stage_grade.deleted',
            'school_stage_grade',
            (int) $schoolStageGrade->id,
            [
                'school_id' => $schoolId,
                'before' => $snapshot,
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    public function storeStageGradeTerm(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) ($request->user()?->id ?? 0);

        $validated = $request->validate([
            'school_stage_id' => [
                'required',
                Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_stage_grade_id' => [
                'required',
                Rule::exists('school_stage_grades', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('school_stage_grade_terms', 'name')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('school_stage_grade_id', (int) $request->input('school_stage_grade_id'))),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'Ø§Ø³Ù… Ø§Ù„ØªØ±Ù… Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠ Ù…Ø³ØªØ®Ø¯Ù… Ù…Ø³Ø¨Ù‚Ù‹Ø§ Ø¯Ø§Ø®Ù„ Ù‡Ø°Ø§ Ø§Ù„ØµÙ.',
        ]);

        $this->assertSchoolStageGradeMatchesStage(
            schoolId: $schoolId,
            stageId: (int) $validated['school_stage_id'],
            gradeId: (int) $validated['school_stage_grade_id']
        );

        try {
            $gradeTerm = SchoolStageGradeTerm::query()->create([
                'school_id' => $schoolId,
                'school_stage_grade_id' => (int) $validated['school_stage_grade_id'],
                'name' => trim((string) $validated['name']),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'Ø§Ø³Ù… Ø§Ù„ØªØ±Ù… Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠ Ù…Ø³ØªØ®Ø¯Ù… Ù…Ø³Ø¨Ù‚Ù‹Ø§ Ø¯Ø§Ø®Ù„ Ù‡Ø°Ø§ Ø§Ù„ØµÙ.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'student_structure.stage_grade_term.created',
            'school_stage_grade_term',
            (int) $gradeTerm->id,
            [
                'school_id' => $schoolId,
                'payload' => $gradeTerm->only(['school_stage_grade_id', 'name', 'sort_order', 'is_active']),
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    public function updateStageGradeTerm(Request $request, SchoolStageGradeTerm $schoolStageGradeTerm): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureStageGradeTermInSchool($schoolStageGradeTerm, $schoolId);
        $actorId = (int) ($request->user()?->id ?? 0);

        $validated = $request->validate([
            'school_stage_id' => [
                'required',
                Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_stage_grade_id' => [
                'required',
                Rule::exists('school_stage_grades', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('school_stage_grade_terms', 'name')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('school_stage_grade_id', (int) $request->input('school_stage_grade_id')))
                    ->ignore($schoolStageGradeTerm->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'Ø§Ø³Ù… Ø§Ù„ØªØ±Ù… Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠ Ù…Ø³ØªØ®Ø¯Ù… Ù…Ø³Ø¨Ù‚Ù‹Ø§ Ø¯Ø§Ø®Ù„ Ù‡Ø°Ø§ Ø§Ù„ØµÙ.',
        ]);

        $this->assertSchoolStageGradeMatchesStage(
            schoolId: $schoolId,
            stageId: (int) $validated['school_stage_id'],
            gradeId: (int) $validated['school_stage_grade_id']
        );

        $before = $schoolStageGradeTerm->only(['school_stage_grade_id', 'name', 'sort_order', 'is_active']);

        try {
            $schoolStageGradeTerm->update([
                'school_stage_grade_id' => (int) $validated['school_stage_grade_id'],
                'name' => trim((string) $validated['name']),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'Ø§Ø³Ù… Ø§Ù„ØªØ±Ù… Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠ Ù…Ø³ØªØ®Ø¯Ù… Ù…Ø³Ø¨Ù‚Ù‹Ø§ Ø¯Ø§Ø®Ù„ Ù‡Ø°Ø§ Ø§Ù„ØµÙ.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'student_structure.stage_grade_term.updated',
            'school_stage_grade_term',
            (int) $schoolStageGradeTerm->id,
            [
                'school_id' => $schoolId,
                'before' => $before,
                'after' => $schoolStageGradeTerm->only(['school_stage_grade_id', 'name', 'sort_order', 'is_active']),
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    public function destroyStageGradeTerm(Request $request, SchoolStageGradeTerm $schoolStageGradeTerm): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureStageGradeTermInSchool($schoolStageGradeTerm, $schoolId);
        $actorId = (int) ($request->user()?->id ?? 0);

        $snapshot = $schoolStageGradeTerm->only(['school_id', 'school_stage_grade_id', 'name', 'sort_order', 'is_active']);
        $schoolStageGradeTerm->delete();

        $this->auditLogger->log(
            'student_structure.stage_grade_term.deleted',
            'school_stage_grade_term',
            (int) $schoolStageGradeTerm->id,
            [
                'school_id' => $schoolId,
                'before' => $snapshot,
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    public function storeClassroom(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) ($request->user()?->id ?? 0);
        $normalizedGradeName = $this->normalizeGradeInput($request->input('grade_name'));

        $validated = $request->validate([
            'school_stage_id' => [
                'required',
                Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'grade_name' => ['nullable', 'string', 'max:100'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('school_classrooms', 'name')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('school_stage_id', (int) $request->input('school_stage_id'))
                    ->where('grade_name', (string) ($normalizedGradeName ?? 'ØºÙŠØ± Ù…Ø­Ø¯Ø¯'))),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('school_classrooms', 'code')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'Ø§Ø³Ù… Ø§Ù„ÙØµÙ„ Ù…ÙˆØ¬ÙˆØ¯ Ù…Ø³Ø¨Ù‚Ù‹Ø§ ÙÙŠ Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠØ©.',
        ]);

        try {
            $classroom = DB::transaction(function () use ($validated, $schoolId, $normalizedGradeName): SchoolClassroom {
                $resolvedGradeName = $this->resolveStageGradeNameForClassroom(
                    $schoolId,
                    (int) $validated['school_stage_id'],
                    $normalizedGradeName
                );

                $providedCode = $this->normalizeCodeInput($validated['code'] ?? null);
                $resolvedCode = $providedCode ?: $this->generateScopedCode('school_classrooms', 'code', 'CLS', $schoolId);
                $this->assertScopedCodeAvailable(
                    'school_classrooms',
                    'code',
                    $resolvedCode,
                    $schoolId,
                    null,
                    'code',
                    'Classroom code already exists in this school.'
                );

                return SchoolClassroom::create([
                    'school_id' => $schoolId,
                    'school_stage_id' => (int) $validated['school_stage_id'],
                    'grade_name' => $resolvedGradeName,
                    'name' => $validated['name'],
                    'code' => $resolvedCode,
                    'sort_order' => (int) ($validated['sort_order'] ?? 0),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'Ø§Ø³Ù… Ø§Ù„ÙØµÙ„ Ù…ÙˆØ¬ÙˆØ¯ Ù…Ø³Ø¨Ù‚Ù‹Ø§ ÙÙŠ Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠØ©.',
                'code' => 'Classroom code already exists in this school.',
            ]);
            throw $exception;
        }


        $this->auditLogger->log(
            'student_structure.classroom.created',
            'school_classroom',
            (int) $classroom->id,
            [
                'school_id' => $schoolId,
                'payload' => $classroom->only(['school_stage_id', 'grade_name', 'name', 'code', 'sort_order', 'is_active']),
                'code_source' => $this->normalizeCodeInput($validated['code'] ?? null) ? 'provided' : 'generated',
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    public function updateClassroom(Request $request, SchoolClassroom $schoolClassroom): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureClassroomInSchool($schoolClassroom, $schoolId);
        $actorId = (int) ($request->user()?->id ?? 0);
        $normalizedGradeName = $this->normalizeGradeInput($request->input('grade_name'));

        $validated = $request->validate([
            'school_stage_id' => [
                'required',
                Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'grade_name' => ['nullable', 'string', 'max:100'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('school_classrooms', 'name')
                    ->where(fn ($query) => $query
                        ->where('school_id', $schoolId)
                        ->where('school_stage_id', (int) $request->input('school_stage_id'))
                        ->where('grade_name', (string) ($normalizedGradeName ?? 'ØºÙŠØ± Ù…Ø­Ø¯Ø¯')))
                    ->ignore($schoolClassroom->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('school_classrooms', 'code')
                    ->where(fn ($query) => $query->where('school_id', $schoolId))
                    ->ignore($schoolClassroom->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'Ø§Ø³Ù… Ø§Ù„ÙØµÙ„ Ù…ÙˆØ¬ÙˆØ¯ Ù…Ø³Ø¨Ù‚Ù‹Ø§ ÙÙŠ Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠØ©.',
        ]);

        $before = $schoolClassroom->only(['school_stage_id', 'grade_name', 'name', 'code', 'sort_order', 'is_active']);

        try {
            DB::transaction(function () use ($schoolClassroom, $validated, $schoolId, $normalizedGradeName): void {
                $targetGradeName = $normalizedGradeName ?? $this->normalizeGradeInput($schoolClassroom->grade_name);
                $resolvedGradeName = $this->resolveStageGradeNameForClassroom(
                    $schoolId,
                    (int) $validated['school_stage_id'],
                    $targetGradeName
                );

                $providedCode = $this->normalizeCodeInput($validated['code'] ?? null);
                $resolvedCode = $providedCode ?: ($schoolClassroom->code ?: $this->generateScopedCode('school_classrooms', 'code', 'CLS', $schoolId));
                $this->assertScopedCodeAvailable(
                    'school_classrooms',
                    'code',
                    $resolvedCode,
                    $schoolId,
                    (int) $schoolClassroom->id,
                    'code',
                    'Classroom code already exists in this school.'
                );

                $schoolClassroom->update([
                    'school_stage_id' => (int) $validated['school_stage_id'],
                    'grade_name' => $resolvedGradeName,
                    'name' => $validated['name'],
                    'code' => $resolvedCode,
                    'sort_order' => (int) ($validated['sort_order'] ?? 0),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'name' => 'Ø§Ø³Ù… Ø§Ù„ÙØµÙ„ Ù…ÙˆØ¬ÙˆØ¯ Ù…Ø³Ø¨Ù‚Ù‹Ø§ ÙÙŠ Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠØ©.',
                'code' => 'Classroom code already exists in this school.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'student_structure.classroom.updated',
            'school_classroom',
            (int) $schoolClassroom->id,
            [
                'school_id' => $schoolId,
                'before' => $before,
                'after' => $schoolClassroom->only(['school_stage_id', 'grade_name', 'name', 'code', 'sort_order', 'is_active']),
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    public function destroyClassroom(Request $request, SchoolClassroom $schoolClassroom): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureClassroomInSchool($schoolClassroom, $schoolId);
        $actorId = (int) ($request->user()?->id ?? 0);
        $impact = $this->integrityImpactService->checkDeleteImpact(
            'school_classroom',
            (int) $schoolClassroom->id,
            $schoolId
        );

        if (!($impact['allowed'] ?? false)) {
            $this->auditLogger->log(
                'student_structure.classroom.delete_blocked',
                'school_classroom',
                (int) $schoolClassroom->id,
                [
                    'school_id' => $schoolId,
                    'impact' => $this->normalizeImpact($impact),
                ],
                $request,
                $actorId > 0 ? $actorId : null
            );

            throw ValidationException::withMessages([
                'classroom' => (string) ($impact['message'] ?? 'Ù„Ø§ ÙŠÙ…ÙƒÙ† Ø­Ø°Ù Ø§Ù„ÙØµÙ„ Ù„ÙˆØ¬ÙˆØ¯ Ø¨ÙŠØ§Ù†Ø§Øª Ù…Ø±ØªØ¨Ø·Ø©.'),
            ]);
        }

        if (($impact['requires_confirmation'] ?? false) && !$request->boolean('confirm_impact')) {
            throw ValidationException::withMessages([
                'confirm_impact' => (string) ($impact['message'] ?? 'ØªØ£ÙƒÙŠØ¯ Ø§Ù„Ø¹Ù…Ù„ÙŠØ© Ù…Ø·Ù„ÙˆØ¨ Ø¨Ø³Ø¨Ø¨ ÙˆØ¬ÙˆØ¯ Ø¨ÙŠØ§Ù†Ø§Øª Ù…Ø±ØªØ¨Ø·Ø©.'),
            ]);
        }

        $snapshot = $schoolClassroom->only(['school_id', 'school_stage_id', 'grade_name', 'name', 'code', 'sort_order', 'is_active']);

        $schoolClassroom->delete();
        $this->auditLogger->log(
            'student_structure.classroom.deleted',
            'school_classroom',
            (int) $schoolClassroom->id,
            [
                'school_id' => $schoolId,
                'before' => $snapshot,
                'impact' => $this->normalizeImpact($impact),
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    public function storeStudent(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $actorId = (int) ($request->user()?->id ?? 0);
        $normalizedClassroomGradeName = $this->normalizeGradeInput($request->input('classroom_grade_name'));

        $validated = $request->validate([
            'school_stage_id' => [
                'nullable',
                Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_classroom_id' => [
                'required',
                Rule::exists('school_classrooms', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'classroom_grade_name' => ['nullable', 'string', 'max:100'],
            'full_name' => ['required', 'string', 'max:255'],
            'student_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('school_students', 'student_code')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'national_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('school_students', 'national_id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'student_code.unique' => 'كود الطالب موجود مسبقًا في المدرسة.',
            'national_id.unique' => 'الرقم الوطني موجود مسبقًا في المدرسة.',
        ]);

        $request->validate(
            $this->attachmentService->uploadValidationRules(),
            $this->attachmentService->uploadValidationMessages()
        );

        $classroom = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->whereKey((int) $validated['school_classroom_id'])
            ->firstOrFail();

        $stageId = (int) ($validated['school_stage_id'] ?? 0);
        if ($stageId > 0 && (int) $classroom->school_stage_id !== $stageId) {
            throw ValidationException::withMessages([
                'school_classroom_id' => 'الفصل المحدد لا يتبع المرحلة المختارة.',
            ]);
        }

        if ($normalizedClassroomGradeName !== null && $classroom->grade_name !== $normalizedClassroomGradeName) {
            throw ValidationException::withMessages([
                'classroom_grade_name' => 'الصف المحدد لا يتوافق مع الفصل المختار.',
            ]);
        }

        try {
            $student = DB::transaction(function () use ($validated, $schoolId, $request): SchoolStudent {
                $providedCode = $this->normalizeCodeInput($validated['student_code'] ?? null);
                $resolvedStudentCode = $providedCode ?: $this->generateScopedCode('school_students', 'student_code', 'STU', $schoolId);
                $this->assertScopedCodeAvailable(
                    'school_students',
                    'student_code',
                    $resolvedStudentCode,
                    $schoolId,
                    null,
                    'student_code',
                    'Student code already exists in this school.'
                );

                $student = SchoolStudent::create([
                    'school_id' => $schoolId,
                    'school_classroom_id' => (int) $validated['school_classroom_id'],
                    'full_name' => $validated['full_name'],
                    'student_code' => $resolvedStudentCode,
                    'national_id' => $validated['national_id'] ?? null,
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                ]);

                $this->storeStudentAttachments($student, $request, $schoolId);

                return $student;
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'student_code' => 'كود الطالب موجود مسبقًا في المدرسة.',
                'national_id' => 'الرقم الوطني موجود مسبقًا في المدرسة.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'student_structure.student.created',
            'school_student',
            (int) $student->id,
            [
                'school_id' => $schoolId,
                'payload' => $student->only(['school_classroom_id', 'full_name', 'student_code', 'national_id', 'is_active']),
                'resolved_scope' => [
                    'school_stage_id' => (int) $classroom->school_stage_id,
                    'classroom_grade_name' => (string) $classroom->grade_name,
                ],
                'student_code_source' => $this->normalizeCodeInput($validated['student_code'] ?? null) ? 'provided' : 'generated',
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    public function downloadStudentImportTemplate(Request $request, StudentImportService $studentImportService): BinaryFileResponse|RedirectResponse
    {
        $this->resolveSchoolId($request);

        try {
            return $studentImportService->templateResponse();
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function importStudents(Request $request, StudentImportService $studentImportService): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);

        $request->validate([
            'students_file' => ['required', 'file', 'mimes:xlsx', 'max:2048'],
        ], [
            'students_file.required' => 'يرجى اختيار ملف Excel لاستيراد الطلاب.',
            'students_file.file' => 'الملف المرفوع غير صالح.',
            'students_file.mimes' => 'يرجى رفع ملف Excel بصيغة xlsx فقط.',
            'students_file.max' => 'حجم ملف Excel يجب ألا يتجاوز 2 ميجابايت.',
        ]);

        $file = $request->file('students_file');
        if ($file === null) {
            throw ValidationException::withMessages([
                'students_file' => 'يرجى اختيار ملف Excel لاستيراد الطلاب.',
            ]);
        }

        $result = $studentImportService->import($file, $schoolId);
        $summary = $result['summary'];

        if (!$result['ok']) {
            return back()
                ->withErrors([
                    'students_file' => 'لم يتم استيراد الطلاب. يرجى مراجعة الأخطاء وتصحيح ملف Excel ثم إعادة رفعه.',
                ])
                ->with('student_import_summary', $summary)
                ->with('student_import_errors', $result['errors']);
        }

        return back()
            ->with('success', 'تم استيراد ' . $summary['imported_rows'] . ' طالب بنجاح داخل المدرسة الحالية.')
            ->with('student_import_summary', $summary)
            ->with('student_import_errors', []);
    }

    public function updateStudent(Request $request, SchoolStudent $schoolStudent): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureStudentInSchool($schoolStudent, $schoolId);
        $actorId = (int) ($request->user()?->id ?? 0);
        $normalizedClassroomGradeName = $this->normalizeGradeInput($request->input('classroom_grade_name'));

        $validated = $request->validate([
            'school_stage_id' => [
                'nullable',
                Rule::exists('school_stages', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'school_classroom_id' => [
                'required',
                Rule::exists('school_classrooms', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'classroom_grade_name' => ['nullable', 'string', 'max:100'],
            'full_name' => ['required', 'string', 'max:255'],
            'student_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('school_students', 'student_code')
                    ->where(fn ($query) => $query->where('school_id', $schoolId))
                    ->ignore($schoolStudent->id),
            ],
            'national_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('school_students', 'national_id')
                    ->where(fn ($query) => $query->where('school_id', $schoolId))
                    ->ignore($schoolStudent->id),
            ],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'student_code.unique' => 'كود الطالب موجود مسبقًا في المدرسة.',
            'national_id.unique' => 'الرقم الوطني موجود مسبقًا في المدرسة.',
        ]);

        $request->validate(
            $this->attachmentService->uploadValidationRules(),
            $this->attachmentService->uploadValidationMessages()
        );

        $classroom = SchoolClassroom::query()
            ->where('school_id', $schoolId)
            ->whereKey((int) $validated['school_classroom_id'])
            ->firstOrFail();

        $stageId = (int) ($validated['school_stage_id'] ?? 0);
        if ($stageId > 0 && (int) $classroom->school_stage_id !== $stageId) {
            throw ValidationException::withMessages([
                'school_classroom_id' => 'الفصل المحدد لا يتبع المرحلة المختارة.',
            ]);
        }

        if ($normalizedClassroomGradeName !== null && $classroom->grade_name !== $normalizedClassroomGradeName) {
            throw ValidationException::withMessages([
                'classroom_grade_name' => 'الصف المحدد لا يتوافق مع الفصل المختار.',
            ]);
        }

        $before = $schoolStudent->only(['school_classroom_id', 'full_name', 'student_code', 'national_id', 'is_active']);

        try {
            DB::transaction(function () use ($schoolStudent, $validated, $schoolId, $request): void {
                $providedCode = $this->normalizeCodeInput($validated['student_code'] ?? null);
                $resolvedStudentCode = $providedCode ?: ($schoolStudent->student_code ?: $this->generateScopedCode('school_students', 'student_code', 'STU', $schoolId));
                $this->assertScopedCodeAvailable(
                    'school_students',
                    'student_code',
                    $resolvedStudentCode,
                    $schoolId,
                    (int) $schoolStudent->id,
                    'student_code',
                    'Student code already exists in this school.'
                );

                $schoolStudent->update([
                    'school_classroom_id' => (int) $validated['school_classroom_id'],
                    'full_name' => $validated['full_name'],
                    'student_code' => $resolvedStudentCode,
                    'national_id' => $validated['national_id'] ?? null,
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                ]);

                $this->storeStudentAttachments($schoolStudent, $request, $schoolId);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateValidation($exception, [
                'student_code' => 'كود الطالب موجود مسبقًا في المدرسة.',
                'national_id' => 'الرقم الوطني موجود مسبقًا في المدرسة.',
            ]);
            throw $exception;
        }

        $this->auditLogger->log(
            'student_structure.student.updated',
            'school_student',
            (int) $schoolStudent->id,
            [
                'school_id' => $schoolId,
                'before' => $before,
                'after' => $schoolStudent->only(['school_classroom_id', 'full_name', 'student_code', 'national_id', 'is_active']),
                'resolved_scope' => [
                    'school_stage_id' => (int) $classroom->school_stage_id,
                    'classroom_grade_name' => (string) $classroom->grade_name,
                ],
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    public function destroyStudent(Request $request, SchoolStudent $schoolStudent): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureStudentInSchool($schoolStudent, $schoolId);
        $actorId = (int) ($request->user()?->id ?? 0);
        $impact = $this->integrityImpactService->checkDeleteImpact(
            'school_student',
            (int) $schoolStudent->id,
            $schoolId
        );

        if (!($impact['allowed'] ?? false)) {
            $this->auditLogger->log(
                'student_structure.student.delete_blocked',
                'school_student',
                (int) $schoolStudent->id,
                [
                    'school_id' => $schoolId,
                    'impact' => $this->normalizeImpact($impact),
                ],
                $request,
                $actorId > 0 ? $actorId : null
            );

            throw ValidationException::withMessages([
                'student' => (string) ($impact['message'] ?? 'Ù„Ø§ ÙŠÙ…ÙƒÙ† Ø­Ø°Ù Ø§Ù„Ø·Ø§Ù„Ø¨ Ù„ÙˆØ¬ÙˆØ¯ Ø¨ÙŠØ§Ù†Ø§Øª Ù…Ø±ØªØ¨Ø·Ø©.'),
            ]);
        }

        if (($impact['requires_confirmation'] ?? false) && !$request->boolean('confirm_impact')) {
            throw ValidationException::withMessages([
                'confirm_impact' => (string) ($impact['message'] ?? 'ØªØ£ÙƒÙŠØ¯ Ø§Ù„Ø¹Ù…Ù„ÙŠØ© Ù…Ø·Ù„ÙˆØ¨ Ø¨Ø³Ø¨Ø¨ ÙˆØ¬ÙˆØ¯ Ø¨ÙŠØ§Ù†Ø§Øª Ù…Ø±ØªØ¨Ø·Ø©.'),
            ]);
        }

        $snapshot = $schoolStudent->only(['school_id', 'school_classroom_id', 'full_name', 'student_code', 'national_id', 'is_active']);

        DB::transaction(function () use ($schoolStudent, $request, $actorId): void {
            foreach ($schoolStudent->attachments()->get() as $attachment) {
                $this->attachmentService->deleteInstitutionalAttachment(
                    $attachment,
                    $request,
                    $actorId > 0 ? $actorId : null
                );
            }

            $schoolStudent->delete();
        });

        $this->auditLogger->log(
            'student_structure.student.deleted',
            'school_student',
            (int) $schoolStudent->id,
            [
                'school_id' => $schoolId,
                'before' => $snapshot,
                'impact' => $this->normalizeImpact($impact),
            ],
            $request,
            $actorId > 0 ? $actorId : null
        );

        return back();
    }

    private function storeStudentAttachments(SchoolStudent $student, Request $request, int $schoolId): void
    {
        $files = $request->file('attachments', []);
        if (!is_array($files) || $files === []) {
            return;
        }

        $classroom = $student->classroom()->first(['id', 'school_stage_id', 'grade_name']);

        $this->attachmentService->storeManyForAttachable(
            $student,
            $files,
            $request->user(),
            [
                'school_id' => $schoolId,
                'module' => 'student_records',
                'action_type' => 'student_document',
                'metadata' => [
                    'student_id' => (int) $student->id,
                    'school_classroom_id' => (int) $student->school_classroom_id,
                    'school_stage_id' => (int) ($classroom?->school_stage_id ?? 0),
                    'classroom_grade_name' => (string) ($classroom?->grade_name ?? ''),
                ],
                'request' => $request,
            ]
        );
    }

    private function resolveSchoolId(Request $request): int
    {
        $schoolId = (int) $request->attributes->get('school_context_id', (int) ($request->user()?->school_id ?? 0));

        if ($schoolId <= 0) {
            abort(403, 'School context is required.');
        }

        return $schoolId;
    }

    private function ensureStageInSchool(SchoolStage $stage, int $schoolId): void
    {
        if ((int) $stage->school_id !== $schoolId) {
            abort(403, 'You are not allowed to access this school stage.');
        }
    }

    private function ensureStageGradeInSchool(SchoolStageGrade $stageGrade, int $schoolId): void
    {
        if ((int) $stageGrade->school_id !== $schoolId) {
            abort(403, 'You are not allowed to access this school stage grade.');
        }
    }

    private function ensureStageGradeTermInSchool(SchoolStageGradeTerm $stageGradeTerm, int $schoolId): void
    {
        if ((int) $stageGradeTerm->school_id !== $schoolId) {
            abort(403, 'You are not allowed to access this school stage grade term.');
        }
    }

    private function ensureStageTermInSchool(SchoolStageTerm $stageTerm, int $schoolId): void
    {
        if ((int) $stageTerm->school_id !== $schoolId) {
            abort(403, 'You are not allowed to access this school stage term.');
        }
    }

    private function ensureClassroomInSchool(SchoolClassroom $classroom, int $schoolId): void
    {
        if ((int) $classroom->school_id !== $schoolId) {
            abort(403, 'You are not allowed to access this classroom.');
        }
    }

    private function ensureStudentInSchool(SchoolStudent $student, int $schoolId): void
    {
        if ((int) $student->school_id !== $schoolId) {
            abort(403, 'You are not allowed to access this student.');
        }
    }

    private function normalizeCodeInput(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private function assertSchoolStageGradeMatchesStage(int $schoolId, int $stageId, int $gradeId): void
    {
        $matches = SchoolStageGrade::query()
            ->where('school_id', $schoolId)
            ->whereKey($gradeId)
            ->where('school_stage_id', $stageId)
            ->exists();

        if (!$matches) {
            throw ValidationException::withMessages([
                'school_stage_grade_id' => 'Ø§Ù„ØµÙ Ø§Ù„Ù…Ø­Ø¯Ø¯ Ù„Ø§ ÙŠØªØ¨Ø¹ Ø§Ù„Ù…Ø±Ø­Ù„Ø© Ø§Ù„Ù…Ø®ØªØ§Ø±Ø© Ø¯Ø§Ø®Ù„ Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø¯Ø±Ø³Ø©.',
            ]);
        }
    }

    private function resolveStageGradeNameForClassroom(int $schoolId, int $stageId, ?string $normalizedGradeName): string
    {
        $baseQuery = SchoolStageGrade::query()
            ->where('school_id', $schoolId)
            ->where('school_stage_id', $stageId);

        if ($normalizedGradeName !== null) {
            $existing = (clone $baseQuery)
                ->whereRaw('LOWER(name) = ?', [strtolower($normalizedGradeName)])
                ->first();

            if ($existing instanceof SchoolStageGrade) {
                return (string) $existing->name;
            }

            $created = SchoolStageGrade::query()->firstOrCreate(
                [
                    'school_id' => $schoolId,
                    'school_stage_id' => $stageId,
                    'name' => $normalizedGradeName,
                ],
                [
                    'sort_order' => $this->nextStageGradeSortOrder($schoolId, $stageId),
                    'is_active' => true,
                ]
            );

            return (string) $created->name;
        }

        $activeGrade = (clone $baseQuery)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();

        if ($activeGrade instanceof SchoolStageGrade) {
            return (string) $activeGrade->name;
        }

        $anyGrade = (clone $baseQuery)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();

        if ($anyGrade instanceof SchoolStageGrade) {
            return (string) $anyGrade->name;
        }

        $defaultName = 'ØºÙŠØ± Ù…Ø­Ø¯Ø¯';
        $fallback = SchoolStageGrade::query()->firstOrCreate(
            [
                'school_id' => $schoolId,
                'school_stage_id' => $stageId,
                'name' => $defaultName,
            ],
            [
                'sort_order' => 0,
                'is_active' => true,
            ]
        );

        return (string) $fallback->name;
    }

    private function normalizeGradeInput(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private function nextStageGradeSortOrder(int $schoolId, int $stageId): int
    {
        $maxSort = (int) (SchoolStageGrade::query()
            ->where('school_id', $schoolId)
            ->where('school_stage_id', $stageId)
            ->max('sort_order') ?? 0);

        return $maxSort + 1;
    }

    private function normalizeTimeInput(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $normalized) === 1) {
            return $normalized . ':00';
        }

        return $normalized;
    }

    private function normalizeDateInput(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private function assertDateRangeOrder(?string $startDate, ?string $endDate, string $startField, string $message): void
    {
        if ($startDate === null || $endDate === null) {
            return;
        }

        if (strtotime($endDate) < strtotime($startDate)) {
            throw ValidationException::withMessages([
                $startField => $message,
            ]);
        }
    }

    private function generateScopedCode(string $table, string $column, string $prefix, int $schoolId, int $padLength = 3): string
    {
        $this->lockSchoolRowForCodeGeneration($schoolId);

        $codes = DB::table($table)
            ->where('school_id', $schoolId)
            ->whereNotNull($column)
            ->lockForUpdate()
            ->pluck($column)
            ->map(fn ($value): string => (string) $value);

        $max = 0;
        $pattern = '/^' . preg_quote($prefix, '/') . '-(\d+)$/';

        foreach ($codes as $code) {
            if (preg_match($pattern, $code, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $next = $max + 1;
        do {
            $candidate = sprintf('%s-%0' . $padLength . 'd', $prefix, $next);
            $exists = DB::table($table)
                ->where('school_id', $schoolId)
                ->where($column, $candidate)
                ->exists();
            $next++;
        } while ($exists);

        return $candidate;
    }

    private function lockSchoolRowForCodeGeneration(int $schoolId): void
    {
        DB::table('schools')
            ->where('id', $schoolId)
            ->lockForUpdate()
            ->value('id');
    }

    private function assertScopedCodeAvailable(
        string $table,
        string $column,
        ?string $code,
        int $schoolId,
        ?int $ignoreId,
        string $errorField,
        string $message
    ): void {
        if ($code === null || $code === '') {
            return;
        }

        $this->lockSchoolRowForCodeGeneration($schoolId);

        $query = DB::table($table)
            ->where('school_id', $schoolId)
            ->where($column, $code)
            ->lockForUpdate();

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                $errorField => $message,
            ]);
        }
    }

    /**
     * @param array<string, string> $fieldMessages
     */
    private function rethrowDuplicateValidation(QueryException $exception, array $fieldMessages): void
    {
        if (!$this->isUniqueConstraintException($exception)) {
            return;
        }

        $error = strtolower($exception->getMessage());
        $messages = [];

        foreach ($fieldMessages as $field => $message) {
            if (str_contains($error, strtolower($field))) {
                $messages[$field] = $message;
            }
        }

        if (count($messages) === 0) {
            $field = array_key_first($fieldMessages);
            if ($field !== null) {
                $messages[$field] = $fieldMessages[$field];
            }
        }

        throw ValidationException::withMessages($messages);
    }

    private function isUniqueConstraintException(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['1062', '19', '2067'], true);
    }

    /**
     * @param array<string, mixed> $impact
     * @return array<string, mixed>
     */
    private function normalizeImpact(array $impact): array
    {
        return [
            'severity' => (string) ($impact['severity'] ?? ''),
            'message_code' => (string) ($impact['message_code'] ?? ''),
            'affected' => $impact['affected'] ?? [],
            'requires_confirmation' => (bool) ($impact['requires_confirmation'] ?? false),
            'suggested_action' => $impact['suggested_action'] ?? null,
        ];
    }
}
