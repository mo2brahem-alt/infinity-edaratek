<?php

namespace App\Services\Support;

use App\Models\StatusHistory;

class StatusHistoryService
{
    public function record(string $entityType, int $entityId, ?string $fromStatus, string $toStatus, ?int $changedBy = null, ?array $meta = null): StatusHistory
    {
        return StatusHistory::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $changedBy,
            'meta' => $meta,
        ]);
    }
}
