<?php

namespace App\Services\Association;

use App\Models\AssociationRequest;
use App\Models\School;
use App\Models\SchoolSupervisionRequest;
use App\Models\User;
use App\Repositories\SchoolRepository;
use App\Services\Support\AuditLogger;
use App\Services\Support\NotificationService;
use App\Services\Support\StatusHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssociationService
{
    public function __construct(
        private readonly SchoolRepository $schoolRepository,
        private readonly NotificationService $notificationService,
        private readonly AuditLogger $auditLogger,
        private readonly StatusHistoryService $statusHistoryService,
    ) {
    }

    public function createForManager(User $manager, School $school, ?Request $request = null): AssociationRequest
    {
        $supervisorId = $this->schoolRepository->resolveSupervisorIdForSchool($school);

        if (!$supervisorId) {
            abort(422, 'No supervisor assignment found for selected school.');
        }

        $association = AssociationRequest::create([
            'school_id' => $school->id,
            'manager_user_id' => $manager->id,
            'supervisor_user_id' => $supervisorId,
            'title' => 'School association request',
            'status' => AssociationRequest::STATUS_PENDING,
            'notes' => null,
        ]);

        $this->statusHistoryService->record(
            'association_request',
            $association->id,
            null,
            AssociationRequest::STATUS_PENDING,
            $manager->id
        );

        $this->notificationService->notifyUser(
            (int) $manager->id,
            'ASSOCIATION_REQUEST_CREATED',
            'تم إنشاء طلب ارتباط المدرسة',
            'تم تسجيل طلب الارتباط بنجاح وهو بانتظار إجراءات الموافقة.',
            $this->notificationService->withRoute(
                [
                    'association_request_id' => (int) $association->id,
                    'school_id' => (int) $school->id,
                ],
                'manager.requests.page',
                [],
                'manager.requests.page'
            )
        );

        $this->notificationService->notifyUser(
            (int) $supervisorId,
            'ASSOCIATION_REQUEST_CREATED',
            'طلب ارتباط مدرسة جديد',
            'تم إنشاء طلب ارتباط مدرسة ضمن نطاقك.',
            $this->notificationService->withRoute(
                [
                    'association_request_id' => (int) $association->id,
                    'school_id' => (int) $school->id,
                ],
                'supervisor.requests.page',
                [],
                'supervisor.requests.page'
            )
        );

        $this->notificationService->notifySuperAdmins(
            'ASSOCIATION_REQUEST_CREATED',
            'طلب ارتباط مدرسة جديد',
            'تم فتح طلب ارتباط جديد بين مدير مدرسة ومشرف.',
            $this->notificationService->withRoute(
                [
                    'association_request_id' => (int) $association->id,
                    'school_id' => (int) $school->id,
                ],
                'admin.schools.index',
                [],
                'admin.schools.index'
            )
        );

        $this->auditLogger->log(
            'association_request.created',
            'association_request',
            $association->id,
            [
                'school_id' => $school->id,
                'manager_user_id' => $manager->id,
                'supervisor_user_id' => $supervisorId,
            ],
            $request,
            $manager->id
        );

        return $association;
    }

    public function approve(AssociationRequest $association, User $manager, ?Request $request = null): AssociationRequest
    {
        DB::transaction(function () use ($association, $manager, $request): void {
            $association = AssociationRequest::query()
                ->whereKey($association->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($association->status !== AssociationRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending requests can be approved.',
                ]);
            }

            $school = School::query()
                ->whereKey($association->school_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($school->manager_user_id && (int) $school->manager_user_id !== (int) $manager->id) {
                throw ValidationException::withMessages([
                    'school_id' => 'This school is already linked to another manager.',
                ]);
            }

            if ($school->supervisor_id && (int) $school->supervisor_id !== (int) $association->supervisor_user_id) {
                throw ValidationException::withMessages([
                    'school_id' => 'This school is already linked to another supervisor.',
                ]);
            }

            $hasConflictingOpenSupervisionRequest = SchoolSupervisionRequest::query()
                ->where('school_id', $school->id)
                ->whereIn('status', [
                    SchoolSupervisionRequest::STATUS_SUPERVISOR_REQUESTED,
                    SchoolSupervisionRequest::STATUS_MANAGER_APPROVED,
                    SchoolSupervisionRequest::STATUS_ACTIVE_ASSOCIATION,
                ])
                ->where('supervisor_id', '!=', $association->supervisor_user_id)
                ->exists();

            if ($hasConflictingOpenSupervisionRequest) {
                throw ValidationException::withMessages([
                    'school_id' => 'Another supervision workflow is already open for this school.',
                ]);
            }

            $previousAssociationStatus = (string) $association->status;
            $previousSchoolStatus = (string) ($school->status ?: School::STATUS_SUSPENDED);
            $previousSupervisionStatus = (string) ($school->supervision_status ?: School::SUPERVISION_STATUS_SUSPENDED);

            $supervisionRequest = SchoolSupervisionRequest::query()
                ->where('school_id', $school->id)
                ->where('supervisor_id', $association->supervisor_user_id)
                ->whereIn('status', [
                    SchoolSupervisionRequest::STATUS_SUPERVISOR_REQUESTED,
                    SchoolSupervisionRequest::STATUS_MANAGER_APPROVED,
                    SchoolSupervisionRequest::STATUS_ACTIVE_ASSOCIATION,
                ])
                ->orderByDesc('id')
                ->first();

            if (!$supervisionRequest) {
                $supervisionRequest = SchoolSupervisionRequest::create([
                    'school_id' => $school->id,
                    'region_id' => $school->directorate_id,
                    'supervisor_id' => $association->supervisor_user_id,
                    'manager_id' => $manager->id,
                    'status' => SchoolSupervisionRequest::STATUS_MANAGER_APPROVED,
                    'requested_at' => $association->created_at ?: now(),
                    'manager_action_at' => $association->approved_at ?: now(),
                ]);

                $this->statusHistoryService->record(
                    'school_supervision_request',
                    $supervisionRequest->id,
                    null,
                    SchoolSupervisionRequest::STATUS_MANAGER_APPROVED,
                    $manager->id
                );
            } elseif ((string) $supervisionRequest->status === SchoolSupervisionRequest::STATUS_SUPERVISOR_REQUESTED) {
                $supervisionRequest->update([
                    'manager_id' => $manager->id,
                    'status' => SchoolSupervisionRequest::STATUS_MANAGER_APPROVED,
                    'manager_action_at' => now(),
                ]);

                $this->statusHistoryService->record(
                    'school_supervision_request',
                    $supervisionRequest->id,
                    SchoolSupervisionRequest::STATUS_SUPERVISOR_REQUESTED,
                    SchoolSupervisionRequest::STATUS_MANAGER_APPROVED,
                    $manager->id
                );
            } elseif ((int) ($supervisionRequest->manager_id ?? 0) !== (int) $manager->id) {
                $supervisionRequest->update([
                    'manager_id' => $manager->id,
                    'manager_action_at' => now(),
                ]);
            }

            $association->update([
                'status' => AssociationRequest::STATUS_APPROVED,
                'approved_at' => now(),
                'rejected_at' => null,
                'responded_by' => $manager->id,
            ]);

            $isFullyConfirmedAssociation = (string) $supervisionRequest->status === SchoolSupervisionRequest::STATUS_ACTIVE_ASSOCIATION;

            $school->update([
                'status' => $isFullyConfirmedAssociation
                    ? School::STATUS_ACTIVE
                    : School::STATUS_SUSPENDED,
                'supervision_status' => $isFullyConfirmedAssociation
                    ? School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION
                    : School::SUPERVISION_STATUS_WAITING_SUPERVISOR_CONFIRM,
                'supervisor_id' => $association->supervisor_user_id,
                'manager_user_id' => $manager->id,
            ]);

            $this->statusHistoryService->record(
                'association_request',
                $association->id,
                $previousAssociationStatus,
                AssociationRequest::STATUS_APPROVED,
                $manager->id
            );

            $currentSchoolStatus = (string) $school->status;
            if ($previousSchoolStatus !== $currentSchoolStatus) {
                $this->statusHistoryService->record(
                    'school',
                    $association->school_id,
                    $previousSchoolStatus,
                    $currentSchoolStatus,
                    $manager->id
                );
            }

            $currentSupervisionStatus = (string) $school->supervision_status;
            if ($previousSupervisionStatus !== $currentSupervisionStatus) {
                $this->statusHistoryService->record(
                    'school_supervision',
                    $association->school_id,
                    $previousSupervisionStatus,
                    $currentSupervisionStatus,
                    $manager->id
                );
            }

            $this->notificationService->notifyUser(
                (int) $association->supervisor_user_id,
                'ASSOCIATION_REQUEST_APPROVED',
                'موافقة مدير المدرسة على طلب الارتباط',
                'تمت الموافقة الأولية وبانتظار التأكيد النهائي من المشرف.',
                $this->notificationService->withRoute(
                    [
                        'association_request_id' => (int) $association->id,
                        'school_id' => (int) $association->school_id,
                    ],
                    'supervisor.requests.page',
                    [],
                    'supervisor.requests.page'
                )
            );

            $this->notificationService->notifyUser(
                (int) $manager->id,
                'ASSOCIATION_REQUEST_APPROVED',
                'تم اعتماد طلب الارتباط مبدئياً',
                'تمت موافقتك بنجاح وبانتظار تأكيد المشرف.',
                $this->notificationService->withRoute(
                    [
                        'association_request_id' => (int) $association->id,
                        'school_id' => (int) $association->school_id,
                    ],
                    'manager.requests.page',
                    [],
                    'manager.requests.page'
                )
            );

            $this->notificationService->notifySuperAdmins(
                'ASSOCIATION_REQUEST_APPROVED',
                'اعتماد مبدئي لطلب ارتباط',
                'تم اعتماد طلب ارتباط مدرسة من مدير المدرسة.',
                $this->notificationService->withRoute(
                    [
                        'association_request_id' => (int) $association->id,
                        'school_id' => (int) $association->school_id,
                    ],
                    'admin.schools.index',
                    [],
                    'admin.schools.index'
                )
            );

            $this->auditLogger->log(
                'association_request.approved',
                'association_request',
                $association->id,
                ['school_id' => $association->school_id],
                $request,
                $manager->id
            );
        });

        return $association->refresh();
    }

    public function reject(AssociationRequest $association, User $manager, ?string $notes = null, ?Request $request = null): AssociationRequest
    {
        DB::transaction(function () use ($association, $manager, $notes, $request): void {
            $association = AssociationRequest::query()
                ->whereKey($association->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($association->status !== AssociationRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending requests can be rejected.',
                ]);
            }

            $previousStatus = $association->status;

            $association->update([
                'status' => AssociationRequest::STATUS_REJECTED,
                'approved_at' => null,
                'rejected_at' => now(),
                'responded_by' => $manager->id,
                'notes' => $notes,
            ]);

            $this->statusHistoryService->record(
                'association_request',
                $association->id,
                $previousStatus,
                AssociationRequest::STATUS_REJECTED,
                $manager->id,
                ['notes' => $notes]
            );

            $this->notificationService->notifyUser(
                (int) $association->supervisor_user_id,
                'ASSOCIATION_REQUEST_REJECTED',
                'تم رفض طلب الارتباط',
                $notes,
                $this->notificationService->withRoute(
                    [
                        'association_request_id' => (int) $association->id,
                        'school_id' => (int) $association->school_id,
                    ],
                    'supervisor.requests.page',
                    [],
                    'supervisor.requests.page'
                )
            );

            $this->notificationService->notifyUser(
                (int) $manager->id,
                'ASSOCIATION_REQUEST_REJECTED',
                'تم رفض طلب الارتباط',
                $notes,
                $this->notificationService->withRoute(
                    [
                        'association_request_id' => (int) $association->id,
                        'school_id' => (int) $association->school_id,
                    ],
                    'manager.requests.page',
                    [],
                    'manager.requests.page'
                )
            );

            $this->notificationService->notifySuperAdmins(
                'ASSOCIATION_REQUEST_REJECTED',
                'رفض طلب ارتباط',
                'تم رفض طلب ارتباط مدرسة.',
                $this->notificationService->withRoute(
                    [
                        'association_request_id' => (int) $association->id,
                        'school_id' => (int) $association->school_id,
                    ],
                    'admin.schools.index',
                    [],
                    'admin.schools.index'
                )
            );

            $this->auditLogger->log(
                'association_request.rejected',
                'association_request',
                $association->id,
                ['school_id' => $association->school_id, 'notes' => $notes],
                $request,
                $manager->id
            );
        });

        return $association->refresh();
    }
}
