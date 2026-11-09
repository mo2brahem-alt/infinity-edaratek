<?php

namespace App\Services\Support;

use App\Models\{Attachment, SchoolAttendanceAttachment, SchoolClassroom, SchoolExam, SchoolStudent, SchoolStudentLeaveAttachment, SchoolStudentLeaveRequest, SchoolTeachingAssignment, SchoolTimetableVersion, Subtask, Ticket, TicketMessage, User};
use App\Services\School\SchoolContextResolver;
use Illuminate\Support\Facades\Gate;

final class AttachmentAccess
{
    public function __construct(private readonly SchoolContextResolver $schools) {}

    public function forSubtask(User $actor, Subtask $task): \Illuminate\Database\Eloquent\Builder
    {
        Gate::forUser($actor)->authorize('view', $task);
        // Apply the same ticket-parent/metadata consistency checks before counting or paging.
        return Attachment::query()->whereIn('ticket_message_id', $task->messages()
            ->where(fn ($q) => $q->whereNull('ticket_id')->orWhere('ticket_id', $task->ticket_id))->select('id'))
            ->where(fn ($q) => $q->whereNull('school_id')->orWhere('school_id', $task->school_id))
            ->where(fn ($q) => $q->whereNull('module')->orWhereIn('module', ['', 'tickets']))
            ->where(function ($q) {
                $q->where(fn ($legacy) => $legacy->where(fn ($t) => $t->whereNull('attachable_type')->orWhere('attachable_type', ''))
                    ->where(fn ($id) => $id->whereNull('attachable_id')->orWhere('attachable_id', 0)))
                    ->orWhere(fn ($typed) => $typed->where('attachable_type', TicketMessage::class)->whereColumn('attachable_id', 'ticket_message_id'));
            });
    }

    public function authorize(User $actor, Attachment $attachment, string $action = 'download'): void
    {
        $actor = User::query()->findOrFail($actor->id);
        abort_unless($actor->hasApprovedAccountAccess(), 403);
        abort_if($attachment->trashed(), 404);
        if ($attachment->isLegacyTicketAttachment() || $attachment->module === 'tickets') {
            $message = $attachment->message;
            if (! $message && $attachment->attachable_type === TicketMessage::class) { $message = TicketMessage::find($attachment->attachable_id); }
            abort_unless($message && in_array((string) $attachment->module, ['', 'tickets'], true), 404);
            if ($attachment->attachable_type || $attachment->attachable_id) {
                abort_unless($attachment->attachable_type === TicketMessage::class && (int) $attachment->attachable_id === (int) $message->id, 404);
            }
            $parent = $message->subtask_id ? Subtask::find($message->subtask_id) : Ticket::find($message->ticket_id);
            abort_unless($parent, 404);
            if ($parent instanceof Subtask) {
                abort_unless($parent->ticket && (int) $parent->ticket->school_id === (int) $parent->school_id, 404);
                abort_if($message->ticket_id && (int) $message->ticket_id !== (int) $parent->ticket_id, 404);
            }
            abort_if($attachment->school_id && (int) $attachment->school_id !== (int) $parent->school_id, 404);
            // Surface suspension consistently while keeping invisible records hidden.
            if ((int) $actor->school_id === (int) $parent->school_id && $actor->hasSystemRole('staff')) { $this->schools->current($actor, purpose: 'staff'); }
            if ((int) $actor->school_id === (int) $parent->school_id && $actor->hasSystemRole('school_manager') && ! $actor->hasSystemRole('staff')) { $this->schools->current($actor, purpose: 'manager'); }
            $ticket = $parent instanceof Subtask ? $parent->ticket : $parent;
            // Existing supervisor ticket detail includes its subtask messages/attachments.
            $supervisedParent = $actor->hasSystemRole('supervisor') && Gate::forUser($actor)->allows('view', $ticket);
            abort_unless(Gate::forUser($actor)->allows('view', $parent) || $supervisedParent, 404);
            abort_unless($action === 'download', 403); // No new ticket-attachment delete product action.
            return;
        }
        $school = $this->schools->current($actor);
        abort_unless((int) $attachment->school_id === (int) $school->id, 404);
        $types = ['exams' => SchoolExam::class, 'schedules' => SchoolTimetableVersion::class,
            'teacher_preparations' => SchoolTeachingAssignment::class, 'student_records' => SchoolStudent::class, 'staff_documents' => User::class];
        $type = $types[$attachment->module] ?? null;
        abort_unless($type && $attachment->attachable_type === $type, 404);
        $parent = $type::query()->find($attachment->attachable_id);
        abort_unless($parent && (int) $parent->school_id === (int) $school->id, 404);
        $allowed = match ($attachment->module) {
            'exams' => $actor->canManageSchoolExams(),
            'schedules', 'teacher_preparations' => $actor->canManageAcademicPlanning(),
            'student_records' => $actor->canManageStudentStructure(),
            'staff_documents' => ($actor->hasSystemRole('super_admin') || $actor->hasSystemRole('school_manager') || $actor->can('manage-school-users')) && $parent->hasSystemRole('staff'),
            default => false,
        };
        abort_unless($allowed, 403);
        if ($parent instanceof SchoolExam && ! $actor->hasSystemRole('super_admin') && ! $actor->hasSystemRole('school_manager') && ! $actor->canManageAcademicPlanning()) {
            abort_unless((int) $parent->teacher_user_id === (int) $actor->id, 403);
        }
    }

    public function leave(User $actor, SchoolStudentLeaveRequest $leave, SchoolStudentLeaveAttachment $attachment): void
    {
        $actor = User::query()->findOrFail($actor->id);
        abort_unless(config('features.student_leaves.enabled', true), 404);
        $school = $this->schools->current($actor);
        abort_unless($actor->canManageStudentLeaves(), 403);
        abort_unless((int) $leave->school_id === (int) $school->id && (int) $attachment->school_id === (int) $school->id
            && (int) $attachment->school_student_leave_request_id === (int) $leave->id, 404);
        abort_unless($leave->student && (int) $leave->student->school_id === (int) $school->id
            && $leave->leaveType && (int) $leave->leaveType->school_id === (int) $school->id, 404);
    }

    public function attendance(User $actor, SchoolAttendanceAttachment $attachment): void
    {
        $actor = User::query()->findOrFail($actor->id);
        $school = $this->schools->current($actor);
        abort_unless($actor->canManageStudentAttendance(), 403);
        abort_unless((int) $attachment->school_id === (int) $school->id && $attachment->classroom
            && (int) $attachment->classroom->school_id === (int) $school->id, 404);
    }
}
