<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Requests\Api\V1\{NativePaginationRequest, StaffTaskListRequest};
use App\Http\Resources\Api\V1\{TaskDetailResource, TaskSummaryResource};
use App\Services\Support\AttachmentAccess;
use App\Services\Ticketing\SubtaskAccessQuery;
use App\Support\{NativeApiResponse, NativeCollection, NativeId};
use Illuminate\Http\JsonResponse;

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
        $p = $query->paginate($request->perPage(), ['id', 'title', 'status', 'due_date'], 'page', $request->page());
        return response()->json(NativeCollection::envelope($p, TaskSummaryResource::collection($p->getCollection())->resolve($request), '/api/v1/staff/tasks', $filters));
    }

    public function show(NativePaginationRequest $request, string $subtask): JsonResponse
    {
        $task = $this->tasks->forStaff($request->user())->whereKey(NativeId::parse($subtask))->firstOrFail();
        $files = app(AttachmentAccess::class)->forSubtask($request->user(), $task)
            ->orderByDesc('id')->paginate($request->perPage(), ['*'], 'page', $request->page());
        return NativeApiResponse::data((new TaskDetailResource($task, $files))->resolve($request));
    }
}
