<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $appends = ['url', 'download_url'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_private' => 'boolean',
        ];
    }

    public function message()
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }

    public function attachable()
    {
        return $this->morphTo();
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    public function scopeUploadedBy(Builder $query, int $userId): Builder
    {
        return $query->where('uploaded_by', $userId);
    }

    public function isLegacyTicketAttachment(): bool
    {
        return (int) ($this->ticket_message_id ?? 0) > 0;
    }

    public function isInstitutionalAttachment(): bool
    {
        return (int) ($this->school_id ?? 0) > 0
            && !empty($this->attachable_type)
            && (int) ($this->attachable_id ?? 0) > 0;
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if ($this->isInstitutionalAttachment()) {
            return route('school.attachments.download', ['attachment' => (int) $this->id], false);
        }

        return $this->isLegacyTicketAttachment() ? $this->getLegacyUrl() : null;
    }

    public function getUrlAttribute(): string
    {
        if ($this->isInstitutionalAttachment()) {
            return (string) $this->getDownloadUrlAttribute();
        }

        return $this->getLegacyUrl();
    }

    private function getLegacyUrl(): string
    {
        $disk = trim((string) ($this->disk ?: 'public')) ?: 'public';

        return Storage::disk($disk)->url((string) $this->file_path);
    }
}
