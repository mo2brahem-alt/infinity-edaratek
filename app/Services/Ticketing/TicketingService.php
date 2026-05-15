<?php

namespace App\Services\Ticketing;

use App\Models\Subtask;
use App\Models\Ticket;
use App\Services\Support\AuditLogger;
use App\Services\Support\NotificationService;
use App\Services\Support\StatusHistoryService;
use Illuminate\Http\Request;

class TicketingService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly AuditLogger $auditLogger,
        private readonly StatusHistoryService $statusHistoryService,
    ) {
    }

    public function markTicketStatus(Ticket $ticket, string $toStatus, int $changedBy, ?Request $request = null, ?array $meta = null): Ticket
    {
        $fromStatus = $ticket->status;
        $ticket->update(['status' => $toStatus]);

        $this->statusHistoryService->record('ticket', $ticket->id, $fromStatus, $toStatus, $changedBy, $meta);

        $this->auditLogger->log('ticket.status_changed', 'ticket', $ticket->id, [
            'from' => $fromStatus,
            'to' => $toStatus,
            'meta' => $meta,
        ], $request, $changedBy);

        return $ticket->refresh();
    }

    public function markSubtaskStatus(Subtask $subtask, string $toStatus, int $changedBy, ?Request $request = null, ?array $meta = null): Subtask
    {
        $fromStatus = $subtask->status;
        $subtask->update(['status' => $toStatus]);

        $this->statusHistoryService->record('subtask', $subtask->id, $fromStatus, $toStatus, $changedBy, $meta);

        $this->auditLogger->log('subtask.status_changed', 'subtask', $subtask->id, [
            'from' => $fromStatus,
            'to' => $toStatus,
            'meta' => $meta,
        ], $request, $changedBy);

        return $subtask->refresh();
    }

    public function notifyTicketCreated(Ticket $ticket): void
    {
        if ((int) $ticket->assigned_to <= 0) {
            return;
        }

        $this->notificationService->notifyUser(
            (int) $ticket->assigned_to,
            'TICKET_CREATED',
            'مهمة جديدة',
            'تم إنشاء مهمة جديدة وتعيينها لك.',
            $this->notificationService->withRoute(
                [
                    'ticket_id' => (int) $ticket->id,
                    'school_id' => (int) $ticket->school_id,
                ],
                'manager.tickets.show',
                ['ticket' => (int) $ticket->id],
                'manager.tickets.show',
                ['ticket' => (int) $ticket->id]
            )
        );
    }

    public function notifySubtaskAssigned(Subtask $subtask, ?int $triggeredByUserId = null): void
    {
        if ((int) $subtask->assigned_to <= 0) {
            return;
        }

        if ($triggeredByUserId !== null && (int) $triggeredByUserId === (int) $subtask->assigned_to) {
            return;
        }

        $this->notificationService->notifyUser(
            (int) $subtask->assigned_to,
            'SUBTASK_ASSIGNED',
            'تم تعيين مهمة جديدة لك',
            'يوجد إجراء جديد يحتاج تنفيذك داخل المدرسة.',
            $this->notificationService->withRoute(
                [
                    'ticket_id' => (int) $subtask->ticket_id,
                    'subtask_id' => (int) $subtask->id,
                    'school_id' => (int) $subtask->school_id,
                ],
                'staff.subtasks.show',
                ['subtask' => (int) $subtask->id],
                'staff.subtasks.show',
                ['subtask' => (int) $subtask->id]
            )
        );
    }

    public function notifySubtaskSubmitted(Subtask $subtask, int $submittedByUserId): void
    {
        $ticket = $subtask->ticket()->first(['id', 'assigned_to', 'school_id']);

        if (!$ticket || (int) $ticket->assigned_to <= 0) {
            return;
        }

        if ((int) $ticket->assigned_to === $submittedByUserId) {
            return;
        }

        $this->notificationService->notifyUser(
            (int) $ticket->assigned_to,
            'SUBTASK_SUBMITTED',
            'تم تسليم مهمة فرعية',
            'قام أحد أعضاء الهيكل المدرسي بتسليم مهمة فرعية للمراجعة.',
            $this->notificationService->withRoute(
                [
                    'ticket_id' => (int) $ticket->id,
                    'subtask_id' => (int) $subtask->id,
                    'school_id' => (int) $ticket->school_id,
                ],
                'manager.tickets.show',
                ['ticket' => (int) $ticket->id],
                'manager.tickets.show',
                ['ticket' => (int) $ticket->id]
            )
        );
    }

    public function notifySubtaskApproved(Subtask $subtask, int $approvedByUserId): void
    {
        if ((int) $subtask->assigned_to <= 0) {
            return;
        }

        if ((int) $subtask->assigned_to === $approvedByUserId) {
            return;
        }

        $this->notificationService->notifyUser(
            (int) $subtask->assigned_to,
            'SUBTASK_APPROVED',
            'تم اعتماد المهمة الفرعية',
            'قام مدير المدرسة باعتماد المهمة الفرعية.',
            $this->notificationService->withRoute(
                [
                    'ticket_id' => (int) $subtask->ticket_id,
                    'subtask_id' => (int) $subtask->id,
                    'school_id' => (int) $subtask->school_id,
                ],
                'staff.subtasks.show',
                ['subtask' => (int) $subtask->id],
                'staff.subtasks.show',
                ['subtask' => (int) $subtask->id]
            )
        );
    }

    public function notifySubtaskReplied(Subtask $subtask, int $authorUserId): void
    {
        $ticket = $subtask->ticket()->first(['id', 'assigned_to', 'school_id']);
        if (!$ticket || (int) $ticket->assigned_to <= 0) {
            return;
        }

        if ($authorUserId === (int) $ticket->assigned_to) {
            if ((int) $subtask->assigned_to <= 0 || (int) $subtask->assigned_to === $authorUserId) {
                return;
            }

            $this->notificationService->notifyUser(
                (int) $subtask->assigned_to,
                'SUBTASK_MANAGER_REPLIED',
                'تعليق جديد من مدير المدرسة',
                'قام مدير المدرسة بإضافة تعليق على المهمة الفرعية.',
                $this->notificationService->withRoute(
                    [
                        'ticket_id' => (int) $ticket->id,
                        'subtask_id' => (int) $subtask->id,
                        'school_id' => (int) $ticket->school_id,
                    ],
                    'staff.subtasks.show',
                    ['subtask' => (int) $subtask->id],
                    'staff.subtasks.show',
                    ['subtask' => (int) $subtask->id]
                )
            );

            return;
        }

        if ((int) $ticket->assigned_to === $authorUserId) {
            return;
        }

        $this->notificationService->notifyUser(
            (int) $ticket->assigned_to,
            'SUBTASK_STAFF_REPLIED',
            'تعليق جديد من الموظف',
            'تمت إضافة رد جديد على المهمة الفرعية.',
            $this->notificationService->withRoute(
                [
                    'ticket_id' => (int) $ticket->id,
                    'subtask_id' => (int) $subtask->id,
                    'school_id' => (int) $ticket->school_id,
                ],
                'manager.tickets.show',
                ['ticket' => (int) $ticket->id],
                'manager.tickets.show',
                ['ticket' => (int) $ticket->id]
            )
        );
    }

    public function notifyManagerFinalReportSubmitted(Ticket $ticket, int $submittedByUserId): void
    {
        if ((int) $ticket->created_by <= 0 || (int) $ticket->created_by === $submittedByUserId) {
            return;
        }

        $this->notificationService->notifyUser(
            (int) $ticket->created_by,
            'TICKET_FINAL_REPORT_SUBMITTED',
            'تم إرسال التقرير النهائي',
            'قام مدير المدرسة بإرسال التقرير النهائي للمراجعة.',
            $this->notificationService->withRoute(
                [
                    'ticket_id' => (int) $ticket->id,
                    'school_id' => (int) $ticket->school_id,
                ],
                'supervisor.tickets.show',
                ['ticket' => (int) $ticket->id],
                'supervisor.tickets.show',
                ['ticket' => (int) $ticket->id]
            )
        );
    }

    public function notifyTicketClosed(Ticket $ticket, int $closedByUserId): void
    {
        if ((int) $ticket->created_by === $closedByUserId) {
            if ((int) $ticket->assigned_to <= 0 || (int) $ticket->assigned_to === $closedByUserId) {
                return;
            }

            $this->notificationService->notifyUser(
                (int) $ticket->assigned_to,
                'TICKET_CLOSED',
                'تم إغلاق المهمة',
                'قام المشرف بإغلاق المهمة بعد المراجعة.',
                $this->notificationService->withRoute(
                    [
                        'ticket_id' => (int) $ticket->id,
                        'school_id' => (int) $ticket->school_id,
                    ],
                    'manager.tickets.show',
                    ['ticket' => (int) $ticket->id],
                    'manager.tickets.show',
                    ['ticket' => (int) $ticket->id]
                )
            );

            return;
        }

        if ((int) $ticket->created_by <= 0 || (int) $ticket->created_by === $closedByUserId) {
            return;
        }

        $this->notificationService->notifyUser(
            (int) $ticket->created_by,
            'TICKET_CLOSED',
            'تم إغلاق المهمة',
            'تم إغلاق المهمة بعد اكتمال المعالجة.',
            $this->notificationService->withRoute(
                [
                    'ticket_id' => (int) $ticket->id,
                    'school_id' => (int) $ticket->school_id,
                ],
                'supervisor.tickets.show',
                ['ticket' => (int) $ticket->id],
                'supervisor.tickets.show',
                ['ticket' => (int) $ticket->id]
            )
        );
    }
}
