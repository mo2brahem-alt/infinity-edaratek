<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (int) $this->resource->id, 'title' => (string) $this->resource->title,
            'status' => (string) $this->resource->status, 'due_date' => $this->resource->due_date?->format('Y-m-d')];
    }
}
