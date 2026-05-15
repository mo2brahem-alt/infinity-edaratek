<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Subtask;
use App\Models\TicketMessage;
use App\Services\Ticketing\TicketingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubtaskController extends Controller
{
    public function __construct(private readonly TicketingService $ticketingService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Subtask::query()
            ->with([
                'ticket:id,title,status',
                'school:id,name,school_id',
                'messages:id,subtask_id,user_id,message,message_type,created_at',
                'messages.attachments:id,ticket_message_id,file_name,file_path,mime_type,file_size',
                'messages.user:id,name',
            ])
            ->where('assigned_to', $request->user()->id)
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

    public function show(Subtask $subtask): JsonResponse
    {
        $this->authorize('view', $subtask);

        return response()->json($subtask->load([
            'ticket:id,title,status',
            'school:id,name,school_id',
            'messages:id,subtask_id,user_id,message,message_type,created_at',
            'messages.attachments:id,ticket_message_id,file_name,file_path,mime_type,file_size',
            'messages.user:id,name',
        ]));
    }

    public function reply(Request $request, Subtask $subtask): JsonResponse
    {
        $this->authorize('reply', $subtask);

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'attachment' => $this->attachmentValidationRules(),
        ], [
            'attachment.mimetypes' => 'صيغة الملف المرفوع غير مدعومة.',
        ]);

        $message = TicketMessage::create([
            'subtask_id' => $subtask->id,
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'message_type' => 'reply',
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('ticketing/attachments', 'public');

            Attachment::create([
                'school_id' => (int) ($subtask->school_id ?? 0) ?: null,
                'ticket_message_id' => $message->id,
                'attachable_type' => TicketMessage::class,
                'attachable_id' => (int) $message->id,
                'module' => 'tickets',
                'action_type' => 'subtask_reply_attachment',
                'uploaded_by' => $request->user()->id,
                'file_name' => $file->getClientOriginalName(),
                'stored_name' => basename($path),
                'disk' => 'public',
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'extension' => $file->clientExtension(),
                'file_size' => $file->getSize(),
                'is_private' => false,
            ]);
        }

        $this->ticketingService->notifySubtaskReplied($subtask, (int) $request->user()->id);

        return response()->json($message->load('attachments'));
    }

    public function submit(Request $request, Subtask $subtask): JsonResponse
    {
        $this->authorize('submit', $subtask);

        $this->ticketingService->markSubtaskStatus(
            $subtask,
            Subtask::STATUS_SUBMITTED,
            $request->user()->id,
            $request,
            ['action' => 'submitted_by_staff']
        );
        $this->ticketingService->notifySubtaskSubmitted($subtask->refresh(), (int) $request->user()->id);

        return response()->json($subtask->refresh());
    }

    /**
     * @return array<int, string>
     */
    private function attachmentValidationRules(): array
    {
        $rules = ['nullable', 'file', 'max:10240'];

        if ($this->strictUploadValidationEnabled()) {
            $mimeTypes = $this->allowedAttachmentMimeTypes();
            if (count($mimeTypes) > 0) {
                $rules[] = 'mimetypes:' . implode(',', $mimeTypes);
            }
        }

        return $rules;
    }

    private function strictUploadValidationEnabled(): bool
    {
        return (bool) config('features.uploads.strict_validation_enabled', false);
    }

    /**
     * @return array<int, string>
     */
    private function allowedAttachmentMimeTypes(): array
    {
        return collect(config('features.uploads.ticket_attachment_mime_types', []))
            ->map(fn ($mime) => trim((string) $mime))
            ->filter()
            ->values()
            ->all();
    }
}
