<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Subtask;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ticketing\TicketingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function __construct(private readonly TicketingService $ticketingService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $schoolId = (int) ($request->user()?->school_id ?? 0);

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Ticket::query()
            ->with([
                'school:id,name,school_id,status',
                'creator:id,name,email',
                'subtasks.assignee:id,name,email,department_id,school_staff_type',
            ])
            ->where('assigned_to', $request->user()->id)
            ->where('school_id', $schoolId)
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

    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        return response()->json($ticket->load([
            'school:id,name,school_id,status',
            'creator:id,name,email',
            'subtasks.assignee:id,name,email,department_id,school_staff_type',
            'subtasks.messages:id,subtask_id,user_id,message,message_type,created_at',
            'subtasks.messages.attachments:id,ticket_message_id,file_name,file_path,mime_type,file_size',
            'subtasks.messages.user:id,name',
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'priority' => 'nullable|in:LOW,MEDIUM,HIGH',
            'due_date' => 'nullable|date',
            'assigned_to' => 'required|exists:users,id',
        ]);

        $schoolId = (int) ($user->school_id ?? 0);
        if ($schoolId <= 0) {
            return response()->json(['message' => 'يجب ربط حساب مدير المدرسة بمدرسة أولًا.'], 422);
        }

        $assignee = User::query()->findOrFail((int) $validated['assigned_to']);

        if (!$assignee->hasSystemRole('staff')) {
            return response()->json(['message' => 'يمكن إسناد المهام فقط لمستخدمي هيكل المدرسة.'], 422);
        }

        if ((int) $assignee->school_id !== $schoolId) {
            return response()->json(['message' => 'يجب أن يكون المستخدم المُسند إليه من نفس المدرسة.'], 422);
        }

        if (!(bool) $assignee->is_active) {
            return response()->json(['message' => 'حساب المستخدم المُسند إليه غير نشط.'], 422);
        }

        $ticket = DB::transaction(function () use ($validated, $user, $schoolId, $assignee, $request) {
            $ticketDescription = trim((string) ($validated['description'] ?? ''));

            $ticket = Ticket::query()->create([
                'title' => $validated['title'],
                'description' => $ticketDescription !== '' ? $ticketDescription : $validated['title'],
                'priority' => $validated['priority'] ?? 'MEDIUM',
                'due_date' => $validated['due_date'] ?? null,
                'school_id' => $schoolId,
                'created_by' => $user->id,
                'assigned_to' => $user->id,
                'status' => Ticket::STATUS_OPEN,
            ]);

            $subtask = Subtask::query()->create([
                'ticket_id' => $ticket->id,
                'school_id' => $schoolId,
                'created_by' => $user->id,
                'assigned_to' => $assignee->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'status' => Subtask::STATUS_OPEN,
            ]);

            $this->ticketingService->markSubtaskStatus($subtask, Subtask::STATUS_OPEN, $user->id, $request, [
                'action' => 'created_by_manager',
            ]);
            $this->ticketingService->markTicketStatus($ticket, Ticket::STATUS_IN_PROGRESS, $user->id, $request, [
                'action' => 'internal_task_created',
            ]);
            $this->ticketingService->notifySubtaskAssigned($subtask, (int) $user->id);

            return $ticket->load([
                'school:id,name,school_id,status',
                'creator:id,name,email',
                'subtasks.assignee:id,name,email,department_id,school_staff_type',
            ]);
        });

        return response()->json($ticket, 201);
    }

    public function finalReport(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('addFinalReport', $ticket);

        if ((int) $ticket->created_by === (int) $request->user()->id) {
            return response()->json(['message' => 'المهام الداخلية لا تتطلب تقريرًا نهائيًا للمشرف.'], 422);
        }

        $validated = $request->validate([
            'manager_final_report' => 'required|string|max:5000',
        ]);

        $ticket->update([
            'manager_final_report' => $validated['manager_final_report'],
        ]);

        $this->ticketingService->markTicketStatus(
            $ticket,
            Ticket::STATUS_WAITING_SUPERVISOR_REVIEW,
            $request->user()->id,
            $request,
            ['action' => 'manager_final_report_submitted']
        );
        $this->ticketingService->notifyManagerFinalReportSubmitted($ticket->refresh(), (int) $request->user()->id);

        return response()->json($ticket->refresh());
    }

    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        if ((int) $ticket->created_by !== (int) $request->user()->id) {
            return response()->json(['message' => 'يمكنك إغلاق المهام الداخلية التي أنشأتها أنت فقط من هنا.'], 422);
        }

        $this->ticketingService->markTicketStatus(
            $ticket,
            Ticket::STATUS_CLOSED,
            $request->user()->id,
            $request,
            ['reason' => 'closed_by_manager']
        );
        $this->ticketingService->notifyTicketClosed($ticket->refresh(), (int) $request->user()->id);

        $ticket->update(['closed_at' => now()]);

        return response()->json($ticket->refresh());
    }
}
