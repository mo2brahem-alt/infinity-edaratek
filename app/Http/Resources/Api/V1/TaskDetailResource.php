<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Subtask;
use App\Support\NativeCollection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class TaskDetailResource extends TaskSummaryResource
{
    public function __construct(Subtask $task, private readonly LengthAwarePaginator $attachments)
    {
        parent::__construct($task);
    }

    public function toArray(Request $request): array
    {
        $page = NativeCollection::envelope($this->attachments,
            AttachmentResource::collection($this->attachments->getCollection())->resolve($request), '/api/v1/staff/tasks/'.$this->resource->id);
        return parent::toArray($request) + ['description' => $this->resource->description === null ? null : (string) $this->resource->description,
            'attachments' => $page['data'], 'attachments_meta' => $page['meta'], 'attachments_links' => $page['links']];
    }
}
