<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Requests\Mobile\StaffTaskListRequest;
use App\Models\Subtask;
use App\Services\Ticketing\SubtaskAccessQuery;
use App\Support\NativeApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StaffTaskController
{
    public function __construct(private readonly SubtaskAccessQuery $tasks) {}

    public function index(StaffTaskListRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $query = $this->tasks->forStaff($request->user());
        if (isset($filters['search'])) { $query->where('title', 'like', '%'.addcslashes($filters['search'], '\\%_').'%'); }
        if (isset($filters['status'])) { $query->where('status', $filters['status']); }
        $sort = $filters['sort'] ?? '-id';
        if (str_contains($sort, 'due_date')) { $query->orderByRaw('due_date is null')->orderBy('due_date', str_starts_with($sort, '-') ? 'desc' : 'asc'); }
        $query->orderBy('id', $sort === 'id' ? 'asc' : 'desc');
        $p = $query->paginate((int) ($filters['per_page'] ?? 25), ['id', 'title', 'status', 'due_date'], 'page', (int) ($filters['page'] ?? 1));
        $link = static fn (int $page): string => '/api/v1/staff/tasks?'.http_build_query(array_merge($filters, ['per_page' => $p->perPage(), 'page' => $page]));
        return response()->json([
            'data' => $p->getCollection()->map(fn (Subtask $task): array => $this->serialize($task))->values()->all(),
            'meta' => ['current_page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total(),
                'last_page' => $p->lastPage(), 'from' => $p->firstItem(), 'to' => $p->lastItem()],
            'links' => ['first' => $link(1), 'last' => $link($p->lastPage()), 'prev' => $p->currentPage() > 1 ? $link($p->currentPage() - 1) : null,
                'next' => $p->hasMorePages() ? $link($p->currentPage() + 1) : null],
        ]);
    }

    public function show(Request $request, string $subtask): JsonResponse
    {
        $this->tasks->rejectAuthority($request);
        $id = filter_var($subtask, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);
        $task = $this->tasks->forStaff($request->user())->whereKey($id)->firstOrFail();
        \Illuminate\Support\Facades\Gate::forUser($request->user())->authorize('view', $task);
        $attachments = \App\Models\Attachment::query()->whereIn('ticket_message_id', $task->messages()->select('id'))
            ->where(fn ($q) => $q->whereNull('school_id')->orWhere('school_id', $task->school_id))
            ->get()->filter(function ($attachment) use ($request): bool {
                try { app(\App\Services\Support\AttachmentAccess::class)->authorize($request->user(), $attachment); return true; }
                catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { return false; }
            })->map(fn ($a) => ['id' => (int) $a->id, 'name' => (string) $a->file_name,
                'mime_type' => (string) ($a->mime_type ?: 'application/octet-stream'), 'size_bytes' => max(0, (int) $a->file_size),
                'download_url' => '/api/v1/attachments/'.$a->id.'/download'])->values()->all();
        return NativeApiResponse::data($this->serialize($task) + ['description' => $task->description, 'attachments' => $attachments]);
    }

    private function serialize(Subtask $task): array
    {
        return ['id' => (int) $task->id, 'title' => (string) $task->title, 'status' => (string) $task->status, 'due_date' => $task->due_date?->format('Y-m-d')];
    }
}
