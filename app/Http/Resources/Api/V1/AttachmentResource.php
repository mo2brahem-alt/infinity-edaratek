<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $file = $this->resource;
        return ['id' => (int) $file->id, 'name' => (string) $file->file_name,
            'mime_type' => (string) ($file->mime_type ?: 'application/octet-stream'), 'size_bytes' => max(0, (int) $file->file_size),
            'download_url' => '/api/v1/attachments/'.$file->id.'/download'];
    }
}
