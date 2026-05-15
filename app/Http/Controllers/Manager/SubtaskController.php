<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Subtask;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ticketing\TicketingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubtaskController extends Controller
{
    public function __construct(private readonly TicketingService $ticketingService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Subtask::class);

        $validated = $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'due_date' => 'nullable|date',
            'assigned_to' => 'required|exists:users,id',
        ]);

        $ticket = Ticket::query()->findOrFail($validated['ticket_id']);
        $this->authorize('view', $ticket);

        $staff = User::query()->findOrFail($validated['assigned_to']);

        if (!$staff->hasSystemRole('staff')) {
            return response()->json(['message' => 'يمكن إسناد المهام الفرعية فقط لمستخدمي هيكل المدرسة.'], 422);
        }

        if ((int) $staff->school_id !== (int) $ticket->school_id) {
            return response()->json(['message' => 'يجب أن ينتمي المستخدم إلى نفس المدرسة.'], 422);
        }

        if (!(bool) $staff->is_active) {
            return response()->json(['message' => 'حساب المستخدم المُسند إليه غير نشط.'], 422);
        }

        $subtask = Subtask::create([
            'ticket_id' => $ticket->id,
            'school_id' => $ticket->school_id,
            'created_by' => $request->user()->id,
            'assigned_to' => $staff->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'status' => Subtask::STATUS_OPEN,
        ]);

        $this->ticketingService->markSubtaskStatus($subtask, Subtask::STATUS_OPEN, $request->user()->id, $request);
        $this->ticketingService->markTicketStatus($ticket, Ticket::STATUS_IN_PROGRESS, $request->user()->id, $request, ['action' => 'subtask_created']);
        $this->ticketingService->notifySubtaskAssigned($subtask, (int) $request->user()->id);

        return response()->json($subtask->load('assignee:id,name,email'), 201);
    }

    public function update(Request $request, Subtask $subtask): JsonResponse
    {
        $this->authorize('update', $subtask);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'due_date' => 'nullable|date',
            'status' => 'nullable|in:OPEN,IN_PROGRESS,SUBMITTED,APPROVED',
            'assigned_to' => 'sometimes|required|exists:users,id',
        ]);

        $payload = collect($validated)->except(['status', 'assigned_to'])->all();
        $previousAssigneeId = (int) $subtask->assigned_to;

        if (array_key_exists('assigned_to', $validated)) {
            $staff = User::query()->findOrFail((int) $validated['assigned_to']);

            if (!$staff->hasSystemRole('staff')) {
                return response()->json(['message' => 'يمكن إسناد المهام الفرعية فقط لمستخدمي هيكل المدرسة.'], 422);
            }

            if ((int) $staff->school_id !== (int) $subtask->school_id) {
                return response()->json(['message' => 'يجب أن يكون المستخدم المُسند إليه من نفس المدرسة.'], 422);
            }

            if (!(bool) $staff->is_active) {
                return response()->json(['message' => 'حساب المستخدم المُسند إليه غير نشط.'], 422);
            }

            $payload['assigned_to'] = $staff->id;
        }

        $subtask->update($payload);

        if (array_key_exists('assigned_to', $validated) && (int) $validated['assigned_to'] !== $previousAssigneeId) {
            $this->ticketingService->notifySubtaskAssigned($subtask->refresh(), (int) $request->user()->id);
        }

        if (!empty($validated['status']) && $validated['status'] !== $subtask->status) {
            $this->ticketingService->markSubtaskStatus($subtask, $validated['status'], $request->user()->id, $request);

            if ($validated['status'] === Subtask::STATUS_SUBMITTED) {
                $this->ticketingService->notifySubtaskSubmitted($subtask->refresh(), (int) $request->user()->id);
            }

            if ($validated['status'] === Subtask::STATUS_APPROVED) {
                $this->ticketingService->notifySubtaskApproved($subtask->refresh(), (int) $request->user()->id);
            }
        }

        return response()->json($subtask->refresh());
    }

    public function approve(Request $request, Subtask $subtask): JsonResponse
    {
        $this->authorize('approve', $subtask);

        $this->ticketingService->markSubtaskStatus(
            $subtask,
            Subtask::STATUS_APPROVED,
            $request->user()->id,
            $request,
            ['action' => 'approved_by_manager']
        );
        $this->ticketingService->notifySubtaskApproved($subtask->refresh(), (int) $request->user()->id);

        return response()->json($subtask->refresh());
    }
}
