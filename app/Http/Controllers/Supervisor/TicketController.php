<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Ticket;
use App\Services\Ticketing\TicketingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(private readonly TicketingService $ticketingService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Ticket::query()
            ->with(['school:id,name,school_id,status', 'manager:id,name,email'])
            ->where('created_by', $user->id)
            ->latest('id');

        $perPage = (int) ($validated['per_page'] ?? 0);
        if ($perPage > 0) {
            $paginator = $query->paginate($perPage)->appends($request->query());

            return response()->json([
                'data' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:10000',
            'priority' => 'nullable|string|max:50',
            'due_date' => 'nullable|date',
            'school_id' => 'required|exists:schools,id',
            'assigned_to' => 'required|exists:users,id',
        ]);

        $school = School::query()->findOrFail($validated['school_id']);

        if ($school->status !== School::STATUS_ACTIVE) {
            return response()->json(['message' => 'يمكن إنشاء المهام فقط للمدارس النشطة.'], 422);
        }

        if ((int) $school->supervisor_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'هذه المدرسة غير مسندة إلى هذا المشرف.'], 403);
        }

        if ((int) $school->manager_user_id !== (int) $validated['assigned_to']) {
            return response()->json(['message' => 'يجب إسناد المهمة إلى مدير هذه المدرسة فقط.'], 422);
        }

        $ticket = Ticket::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'priority' => $validated['priority'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'school_id' => $school->id,
            'created_by' => $request->user()->id,
            'assigned_to' => $validated['assigned_to'],
            'status' => Ticket::STATUS_OPEN,
        ]);

        $this->ticketingService->markTicketStatus($ticket, Ticket::STATUS_OPEN, $request->user()->id, $request);
        $this->ticketingService->notifyTicketCreated($ticket);

        return response()->json($ticket->load(['school:id,name', 'manager:id,name,email']), 201);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        return response()->json($ticket->load([
            'school',
            'manager',
            'subtasks.assignee',
            'subtasks.messages.user',
            'subtasks.messages.attachments',
            'messages.user',
        ]));
    }

    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string|max:10000',
            'priority' => 'nullable|string|max:50',
            'due_date' => 'nullable|date',
            'status' => 'nullable|in:OPEN,IN_PROGRESS,WAITING_MANAGER_REVIEW,WAITING_SUPERVISOR_REVIEW,CLOSED',
        ]);

        $ticket->update(collect($validated)->except('status')->all());

        if (!empty($validated['status']) && $validated['status'] !== $ticket->status) {
            $this->ticketingService->markTicketStatus($ticket, $validated['status'], $request->user()->id, $request);
        }

        return response()->json($ticket->refresh());
    }

    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('close', $ticket);

        $this->ticketingService->markTicketStatus(
            $ticket,
            Ticket::STATUS_CLOSED,
            $request->user()->id,
            $request,
            ['reason' => 'closed_by_supervisor']
        );
        $this->ticketingService->notifyTicketClosed($ticket->refresh(), (int) $request->user()->id);

        $ticket->update(['closed_at' => now()]);

        return response()->json($ticket->refresh());
    }
}
