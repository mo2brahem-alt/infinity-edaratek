<?php

namespace App\Services\Supervision;

use App\Models\School;
use App\Models\SchoolSupervisorAssignment;
use App\Models\SchoolSupervisionRequest;
use App\Models\User;
use App\Services\Support\AuditLogger;
use App\Services\Support\NotificationService;
use App\Services\Support\StatusHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SchoolSupervisionRequestService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly AuditLogger $auditLogger,
        private readonly StatusHistoryService $statusHistoryService,
    ) {
    }

    /**
     * @return array{created: array<int, SchoolSupervisionRequest>, skipped_school_ids: array<int, int>}
     */
    public function createBySupervisorSelection(User $supervisor, int $regionId, array $schoolIds, ?Request $request = null): array
    {
        $schoolIds = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $schoolIds)));

        $schoolsCount = School::query()
            ->where('directorate_id', $regionId)
            ->whereIn('id', $schoolIds)
            ->count();

        if ($schoolsCount !== count($schoolIds)) {
            throw ValidationException::withMessages([
                'school_ids' => 'One or more selected schools do not belong to the selected region.',
            ]);
        }

        $created = [];
        $skippedSchoolIds = [];

        DB::transaction(function () use ($schoolIds, $supervisor, $regionId, $request, &$created, &$skippedSchoolIds): void {
            $lockedSchools = School::query()
                ->whereIn('id', $schoolIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($schoolIds as $schoolId) {
                /** @var School|null $school */
                $school = $lockedSchools->get($schoolId);

                if (!$school || (int) $school->directorate_id !== $regionId) {
                    throw ValidationException::withMessages([
                        'school_ids' => 'One or more selected schools do not belong to the selected region.',
                    ]);
                }

                if ($this->isLockedForAnotherSupervisor($school, (int) $supervisor->id)) {
                    $skippedSchoolIds[] = (int) $school->id;
                    continue;
                }

                $existing = SchoolSupervisionRequest::query()
                    ->where('school_id', $school->id)
                    ->where('supervisor_id', $supervisor->id)
                    ->whereIn('status', SchoolSupervisionRequest::OPEN_STATUSES)
                    ->exists();

                if ($existing) {
                    $skippedSchoolIds[] = (int) $school->id;
                    continue;
                }

                $originalManagerId = (int) ($school->manager_user_id ?? 0);
                $validManagerId = $this->resolveValidManagerId($originalManagerId);
                $managerWasSanitized = $originalManagerId > 0 && $validManagerId === null;

                $requestItem = SchoolSupervisionRequest::create([
                    'school_id' => $school->id,
                    'region_id' => $regionId,
                    'supervisor_id' => $supervisor->id,
                    'manager_id' => $validManagerId,
                    'status' => SchoolSupervisionRequest::STATUS_SUPERVISOR_REQUESTED,
                    'requested_at' => now(),
                ]);

                $schoolUpdate = [
                    'supervision_status' => School::SUPERVISION_STATUS_WAITING_MANAGER_APPROVAL,
                ];
                if ($managerWasSanitized) {
                    $schoolUpdate['manager_user_id'] = null;
                }

                $school->update($schoolUpdate);

                $this->statusHistoryService->record(
                    'school_supervision_request',
                    $requestItem->id,
                    null,
                    SchoolSupervisionRequest::STATUS_SUPERVISOR_REQUESTED,
                    $supervisor->id
                );

                $this->statusHistoryService->record(
                    'school_supervision',
                    $school->id,
                    null,
                    School::SUPERVISION_STATUS_WAITING_MANAGER_APPROVAL,
                    $supervisor->id
                );

                if ($validManagerId) {
                    $this->notificationService->notifyUser(
                        $validManagerId,
                        'SUPERVISION_REQUEST_CREATED',
                        'طلب إشراف جديد',
                        'قام مشرف المنطقة بطلب الإشراف على مدرستك.',
                        $this->notificationService->withRoute(
                            [
                                'request_id' => (int) $requestItem->id,
                                'school_id' => (int) $school->id,
                            ],
                            'manager.requests.page',
                            [],
                            'manager.requests.page'
                        )
                    );
                }

                $this->notificationService->notifySuperAdmins(
                    'SUPERVISION_REQUEST_CREATED',
                    'فتح طلب إشراف جديد',
                    'تم فتح طلب إشراف جديد على مدرسة ضمن النظام.',
                    $this->notificationService->withRoute(
                        [
                            'request_id' => (int) $requestItem->id,
                            'school_id' => (int) $school->id,
                        ],
                        'admin.schools.index',
                        [],
                        'admin.schools.index'
                    )
                );

                $this->auditLogger->log(
                    'school_supervision_request.created',
                    'school_supervision_request',
                    $requestItem->id,
                    [
                        'school_id' => $school->id,
                        'region_id' => $regionId,
                        'supervisor_id' => $supervisor->id,
                    ],
                    $request,
                    $supervisor->id
                );

                $created[] = $requestItem;
            }
        });

        return [
            'created' => $created,
            'skipped_school_ids' => $skippedSchoolIds,
        ];
    }

    /**
     * @return array{created: array<int, SchoolSupervisionRequest>, skipped_school_ids: array<int, int>}
     */
    public function createBySupervisorLocationSelection(
        User $supervisor,
        int $countryId,
        int $governorateId,
        array $schoolIds,
        ?Request $request = null
    ): array {
        $schoolIds = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $schoolIds)));

        $selectedSchools = School::query()
            ->with(['directorate:id,country_id,governorate_id'])
            ->whereIn('id', $schoolIds)
            ->get(['id', 'directorate_id']);

        if ($selectedSchools->count() !== count($schoolIds)) {
            throw ValidationException::withMessages([
                'school_ids' => 'واحدة أو أكثر من المدارس المختارة لا تنتمي إلى الدولة والمحافظة المحددتين.',
            ]);
        }

        $invalidSelection = $selectedSchools->first(function (School $school) use ($countryId, $governorateId): bool {
            return (int) ($school->directorate?->country_id ?? 0) !== $countryId
                || (int) ($school->directorate?->governorate_id ?? 0) !== $governorateId;
        });

        if ($invalidSelection instanceof School) {
            throw ValidationException::withMessages([
                'school_ids' => 'واحدة أو أكثر من المدارس المختارة لا تنتمي إلى الدولة والمحافظة المحددتين.',
            ]);
        }

        $created = [];
        $skippedSchoolIds = [];

        $selectedSchools
            ->groupBy(fn (School $school) => (int) $school->directorate_id)
            ->each(function ($schools, $regionId) use ($supervisor, $request, &$created, &$skippedSchoolIds): void {
                $result = $this->createBySupervisorSelection(
                    $supervisor,
                    (int) $regionId,
                    $schools->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    $request
                );

                $created = [...$created, ...$result['created']];
                $skippedSchoolIds = [...$skippedSchoolIds, ...$result['skipped_school_ids']];
            });

        return [
            'created' => $created,
            'skipped_school_ids' => array_values(array_unique(array_map('intval', $skippedSchoolIds))),
        ];
    }

    private function isLockedForAnotherSupervisor(School $school, int $supervisorId): bool
    {
        if ($school->supervisor_id && (int) $school->supervisor_id !== $supervisorId) {
            return true;
        }

        $hasActiveSchoolAssignment = SchoolSupervisorAssignment::query()
            ->where('is_active', true)
            ->where('school_id', $school->id)
            ->where('supervisor_id', '!=', $supervisorId)
            ->exists();

        if ($hasActiveSchoolAssignment) {
            return true;
        }

        $hasActiveDirectorateAssignment = SchoolSupervisorAssignment::query()
            ->where('is_active', true)
            ->whereNull('school_id')
            ->where('directorate_id', $school->directorate_id)
            ->where('supervisor_id', '!=', $supervisorId)
            ->exists();

        if ($hasActiveDirectorateAssignment) {
            return true;
        }

        return SchoolSupervisionRequest::query()
            ->where('school_id', $school->id)
            ->whereIn('status', SchoolSupervisionRequest::OPEN_STATUSES)
            ->where('supervisor_id', '!=', $supervisorId)
            ->exists();
    }

    public function managerApprove(SchoolSupervisionRequest $requestItem, User $manager, ?string $notes = null, ?Request $request = null): SchoolSupervisionRequest
    {
        DB::transaction(function () use ($requestItem, $manager, $notes, $request): void {
            $requestItem = SchoolSupervisionRequest::query()
                ->whereKey($requestItem->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($requestItem->status !== SchoolSupervisionRequest::STATUS_SUPERVISOR_REQUESTED) {
                throw ValidationException::withMessages([
                    'status' => 'Only SUPERVISOR_REQUESTED requests can be approved by manager.',
                ]);
            }

            $school = School::query()
                ->whereKey($requestItem->school_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($school->manager_user_id && (int) $school->manager_user_id !== (int) $manager->id) {
                throw ValidationException::withMessages([
                    'school_id' => 'This school is already linked to another manager.',
                ]);
            }

            if ($school->supervisor_id && (int) $school->supervisor_id !== (int) $requestItem->supervisor_id) {
                throw ValidationException::withMessages([
                    'school_id' => 'This school is already linked to another supervisor.',
                ]);
            }

            $from = $requestItem->status;

            $requestItem->update([
                'manager_id' => $manager->id,
                'status' => SchoolSupervisionRequest::STATUS_MANAGER_APPROVED,
                'manager_action_at' => now(),
                'notes' => $notes,
            ]);

            $school->update([
                'manager_user_id' => $manager->id,
                'supervision_status' => School::SUPERVISION_STATUS_WAITING_SUPERVISOR_CONFIRM,
            ]);

            $this->statusHistoryService->record(
                'school_supervision_request',
                $requestItem->id,
                $from,
                SchoolSupervisionRequest::STATUS_MANAGER_APPROVED,
                $manager->id
            );

            $this->statusHistoryService->record(
                'school_supervision',
                $school->id,
                School::SUPERVISION_STATUS_WAITING_MANAGER_APPROVAL,
                School::SUPERVISION_STATUS_WAITING_SUPERVISOR_CONFIRM,
                $manager->id
            );

            $this->notificationService->notifyUser(
                (int) $requestItem->supervisor_id,
                'SUPERVISION_REQUEST_MANAGER_APPROVED',
                'موافقة مدير المدرسة على طلب الإشراف',
                'وافق مدير المدرسة على الطلب. يلزمك الآن التأكيد النهائي.',
                $this->notificationService->withRoute(
                    [
                        'request_id' => (int) $requestItem->id,
                        'school_id' => (int) $school->id,
                    ],
                    'supervisor.requests.page',
                    [],
                    'supervisor.requests.page'
                )
            );

            $this->notificationService->notifySuperAdmins(
                'SUPERVISION_REQUEST_MANAGER_APPROVED',
                'اعتماد أولي لطلب إشراف',
                'قام مدير المدرسة بالموافقة الأولية على طلب الإشراف.',
                $this->notificationService->withRoute(
                    [
                        'request_id' => (int) $requestItem->id,
                        'school_id' => (int) $school->id,
                    ],
                    'admin.schools.index',
                    [],
                    'admin.schools.index'
                )
            );

            $this->auditLogger->log(
                'school_supervision_request.manager_approved',
                'school_supervision_request',
                $requestItem->id,
                ['school_id' => $school->id, 'manager_id' => $manager->id],
                $request,
                $manager->id
            );
        });

        return $requestItem->refresh();
    }

    public function managerReject(SchoolSupervisionRequest $requestItem, User $manager, ?string $notes = null, ?Request $request = null): SchoolSupervisionRequest
    {
        if (!in_array($requestItem->status, [
            SchoolSupervisionRequest::STATUS_SUPERVISOR_REQUESTED,
            SchoolSupervisionRequest::STATUS_MANAGER_APPROVED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Request cannot be rejected in current state.',
            ]);
        }

        $from = $requestItem->status;

        DB::transaction(function () use ($requestItem, $manager, $notes, $request, $from): void {
            $requestItem->update([
                'manager_id' => $manager->id,
                'status' => SchoolSupervisionRequest::STATUS_MANAGER_REJECTED,
                'manager_action_at' => now(),
                'notes' => $notes,
            ]);

            $school = $requestItem->school()->firstOrFail();
            $this->syncSchoolSupervisionStatus($school);

            $this->statusHistoryService->record(
                'school_supervision_request',
                $requestItem->id,
                $from,
                SchoolSupervisionRequest::STATUS_MANAGER_REJECTED,
                $manager->id,
                ['notes' => $notes]
            );

            $this->notificationService->notifyUser(
                (int) $requestItem->supervisor_id,
                'SUPERVISION_REQUEST_MANAGER_REJECTED',
                'رفض مدير المدرسة طلب الإشراف',
                $notes,
                $this->notificationService->withRoute(
                    [
                        'request_id' => (int) $requestItem->id,
                        'school_id' => (int) $school->id,
                    ],
                    'supervisor.requests.page',
                    [],
                    'supervisor.requests.page'
                )
            );

            $this->notificationService->notifySuperAdmins(
                'SUPERVISION_REQUEST_MANAGER_REJECTED',
                'رفض طلب إشراف',
                'قام مدير المدرسة برفض طلب الإشراف.',
                $this->notificationService->withRoute(
                    [
                        'request_id' => (int) $requestItem->id,
                        'school_id' => (int) $school->id,
                    ],
                    'admin.schools.index',
                    [],
                    'admin.schools.index'
                )
            );

            $this->auditLogger->log(
                'school_supervision_request.manager_rejected',
                'school_supervision_request',
                $requestItem->id,
                ['school_id' => $school->id, 'manager_id' => $manager->id, 'notes' => $notes],
                $request,
                $manager->id
            );
        });

        return $requestItem->refresh();
    }

    public function supervisorConfirm(SchoolSupervisionRequest $requestItem, User $supervisor, ?Request $request = null): SchoolSupervisionRequest
    {
        DB::transaction(function () use ($requestItem, $supervisor, $request): void {
            $requestItem = SchoolSupervisionRequest::query()
                ->whereKey($requestItem->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($requestItem->status !== SchoolSupervisionRequest::STATUS_MANAGER_APPROVED) {
                throw ValidationException::withMessages([
                    'status' => 'Only MANAGER_APPROVED requests can be confirmed by supervisor.',
                ]);
            }

            $school = School::query()
                ->whereKey($requestItem->school_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($school->supervisor_id && (int) $school->supervisor_id !== (int) $supervisor->id) {
                throw ValidationException::withMessages([
                    'school_id' => 'This school is already linked to another supervisor.',
                ]);
            }

            if ($requestItem->manager_id && $school->manager_user_id && (int) $school->manager_user_id !== (int) $requestItem->manager_id) {
                throw ValidationException::withMessages([
                    'school_id' => 'This school is already linked to another manager.',
                ]);
            }

            $from = $requestItem->status;

            $requestItem->update([
                'status' => SchoolSupervisionRequest::STATUS_ACTIVE_ASSOCIATION,
                'supervisor_confirmed_at' => now(),
            ]);

            $school->update([
                'supervisor_id' => $supervisor->id,
                'manager_user_id' => $requestItem->manager_id ?: $school->manager_user_id,
                'status' => School::STATUS_ACTIVE,
                'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            ]);

            $this->statusHistoryService->record(
                'school_supervision_request',
                $requestItem->id,
                $from,
                SchoolSupervisionRequest::STATUS_ACTIVE_ASSOCIATION,
                $supervisor->id
            );

            $this->statusHistoryService->record(
                'school',
                $school->id,
                School::STATUS_SUSPENDED,
                School::STATUS_ACTIVE,
                $supervisor->id
            );

            $this->statusHistoryService->record(
                'school_supervision',
                $school->id,
                School::SUPERVISION_STATUS_WAITING_SUPERVISOR_CONFIRM,
                School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
                $supervisor->id
            );

            if ($requestItem->manager_id) {
                $this->notificationService->notifyUser(
                    (int) $requestItem->manager_id,
                    'SUPERVISION_REQUEST_SUPERVISOR_CONFIRMED',
                    'تأكيد المشرف للارتباط',
                    'تم تأكيد الطلب وأصبحت علاقة الإشراف نشطة.',
                    $this->notificationService->withRoute(
                        [
                            'request_id' => (int) $requestItem->id,
                            'school_id' => (int) $school->id,
                        ],
                        'manager.requests.page',
                        [],
                        'manager.requests.page'
                    )
                );
            }

            $this->notificationService->notifySuperAdmins(
                'SUPERVISION_REQUEST_SUPERVISOR_CONFIRMED',
                'تفعيل ارتباط إشراف',
                'أكد المشرف الطلب النهائي وتم تفعيل الارتباط.',
                $this->notificationService->withRoute(
                    [
                        'request_id' => (int) $requestItem->id,
                        'school_id' => (int) $school->id,
                    ],
                    'admin.schools.index',
                    [],
                    'admin.schools.index'
                )
            );

            $this->auditLogger->log(
                'school_supervision_request.supervisor_confirmed',
                'school_supervision_request',
                $requestItem->id,
                ['school_id' => $school->id, 'supervisor_id' => $supervisor->id],
                $request,
                $supervisor->id
            );
        });

        return $requestItem->refresh();
    }

    public function supervisorCancel(SchoolSupervisionRequest $requestItem, User $supervisor, ?string $notes = null, ?Request $request = null): SchoolSupervisionRequest
    {
        if (!in_array($requestItem->status, [
            SchoolSupervisionRequest::STATUS_SUPERVISOR_REQUESTED,
            SchoolSupervisionRequest::STATUS_MANAGER_APPROVED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Request cannot be canceled in current state.',
            ]);
        }

        $from = $requestItem->status;

        DB::transaction(function () use ($requestItem, $supervisor, $notes, $request, $from): void {
            $requestItem->update([
                'status' => SchoolSupervisionRequest::STATUS_CANCELED,
                'notes' => $notes,
            ]);

            $school = $requestItem->school()->firstOrFail();
            $this->syncSchoolSupervisionStatus($school);

            $this->statusHistoryService->record(
                'school_supervision_request',
                $requestItem->id,
                $from,
                SchoolSupervisionRequest::STATUS_CANCELED,
                $supervisor->id,
                ['notes' => $notes]
            );

            if ($requestItem->manager_id) {
                $this->notificationService->notifyUser(
                    (int) $requestItem->manager_id,
                    'SUPERVISION_REQUEST_SUPERVISOR_CANCELED',
                    'إلغاء المشرف لطلب الإشراف',
                    $notes,
                    $this->notificationService->withRoute(
                        [
                            'request_id' => (int) $requestItem->id,
                            'school_id' => (int) $school->id,
                        ],
                        'manager.requests.page',
                        [],
                        'manager.requests.page'
                    )
                );
            }

            $this->notificationService->notifySuperAdmins(
                'SUPERVISION_REQUEST_SUPERVISOR_CANCELED',
                'إلغاء طلب إشراف',
                'قام المشرف بإلغاء طلب الإشراف.',
                $this->notificationService->withRoute(
                    [
                        'request_id' => (int) $requestItem->id,
                        'school_id' => (int) $school->id,
                    ],
                    'admin.schools.index',
                    [],
                    'admin.schools.index'
                )
            );

            $this->auditLogger->log(
                'school_supervision_request.supervisor_canceled',
                'school_supervision_request',
                $requestItem->id,
                ['school_id' => $school->id, 'supervisor_id' => $supervisor->id, 'notes' => $notes],
                $request,
                $supervisor->id
            );
        });

        return $requestItem->refresh();
    }

    private function syncSchoolSupervisionStatus(School $school): void
    {
        $hasActiveAssociation = SchoolSupervisionRequest::query()
            ->where('school_id', $school->id)
            ->where('status', SchoolSupervisionRequest::STATUS_ACTIVE_ASSOCIATION)
            ->exists();

        if ($hasActiveAssociation) {
            $school->update(['supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION]);

            return;
        }

        $hasManagerApproved = SchoolSupervisionRequest::query()
            ->where('school_id', $school->id)
            ->where('status', SchoolSupervisionRequest::STATUS_MANAGER_APPROVED)
            ->exists();

        if ($hasManagerApproved) {
            $school->update(['supervision_status' => School::SUPERVISION_STATUS_WAITING_SUPERVISOR_CONFIRM]);

            return;
        }

        $hasSupervisorRequested = SchoolSupervisionRequest::query()
            ->where('school_id', $school->id)
            ->where('status', SchoolSupervisionRequest::STATUS_SUPERVISOR_REQUESTED)
            ->exists();

        if ($hasSupervisorRequested) {
            $school->update(['supervision_status' => School::SUPERVISION_STATUS_WAITING_MANAGER_APPROVAL]);

            return;
        }

        $school->update(['supervision_status' => School::SUPERVISION_STATUS_SUSPENDED]);
    }

    private function resolveValidManagerId(int $managerId): ?int
    {
        if ($managerId <= 0) {
            return null;
        }

        return User::query()->whereKey($managerId)->exists()
            ? $managerId
            : null;
    }
}
