<?php

namespace App\Services\Support;

use App\Models\{Attachment, PrivateFileMigration, SchoolAttendanceAttachment, SchoolStudentLeaveAttachment, SchoolStudentLeaveRequest, TicketMessage};
use App\Support\PublicAssetPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB, Storage};

class PrivateAttachmentMigrator
{
    public const TYPES = [Attachment::class, SchoolStudentLeaveAttachment::class, SchoolAttendanceAttachment::class];

    public function run(bool $apply = false, int $limit = 100): array
    {
        if ($limit < 1 || $limit > 500) { throw new PrivateFileMigratorException('INVALID_BATCH_LIMIT'); }
        if ($apply && ! config('private-files.migration_enabled', false)) { throw new PrivateFileMigratorException('APPLY_SCOPE_NOT_APPROVED'); }
        $counts = ['observed' => 0, 'public_assets' => 0, 'private_required' => 0, 'quarantined' => 0, 'verified' => 0, 'failed' => 0, 'pending' => 0];
        // Lazy filesystem iteration; no unbounded allFiles() materialization.
        foreach (Storage::disk('public')->getDriver()->listContents('', true) as $entry) {
            if (! $entry->isFile()) { continue; }
            if ($counts['observed'] >= $limit) { break; }
            $path = $entry->path(); $counts['observed']++;
            $key = hash('sha256', 'public'."\0".$path."\0".'1');
            $existing = PrivateFileMigration::query()->where('inventory_key', $key)->first();
            if ($existing) { continue; } // Pending manifests are resumed below, even after source revocation.
            $refs = collect();
            foreach (self::TYPES as $type) {
                $query = $type === Attachment::class ? $type::withTrashed() : $type::query();
                $refs = $refs->merge($query->where('file_path', $path)->get());
            }
            if ($refs->isEmpty() && PublicAssetPath::allows($path)) { $counts['public_assets']++; continue; }
            $counts['private_required']++;
            $record = $refs->count() === 1 ? $refs->first() : null;
            $reason = $refs->count() > 1 ? 'AMBIGUOUS_REFERENCE' : ($record ? null : 'ORPHAN_REQUIRES_REVIEW');
            if ($record && ! $this->validParent($record)) { $reason = 'ORPHAN_OR_DELETED_PARENT'; }
            try { $source = $this->publicPhysicalPath($path); }
            catch (\Throwable) { $source = null; $reason = 'UNSAFE_SOURCE_PATH'; }
            if ($reason) { $counts['quarantined']++; }
            if (! $apply) { continue; } // Dry-run has no manifest, file or metadata mutations.
            $targetDisk = $record instanceof Attachment ? 'school_attachments' : 'local';
            PrivateFileMigration::query()->firstOrCreate(['inventory_key' => $key], [
                'schema_version' => 1, 'attachment_type' => $record ? $record::class : null, 'attachment_id' => $record?->id,
                'source_disk' => 'public', 'source_path' => $path, 'destination_disk' => $targetDisk,
                'destination_path' => 'private-migrations/'.$key, 'source_size' => $source ? filesize($source) : null,
                'source_hash' => $source ? hash_file('sha256', $source) : null,
                'observed_source_metadata' => $record ? $record->getRawOriginal() : null,
                'safe_failure_code' => $reason,
            ]);
        }
        // Also reconcile metadata whose public source is missing, without declaring success.
        $missing = Attachment::withTrashed()->where('disk', 'public')->orderBy('id')->limit($limit)->get();
        foreach ($missing as $record) {
            if (Storage::disk('public')->exists($record->file_path)) { continue; }
            $counts['quarantined']++;
            if ($apply) {
                $key = hash('sha256', 'public'."\0".$record->file_path."\0".'1');
                PrivateFileMigration::firstOrCreate(['inventory_key' => $key], ['schema_version' => 1, 'attachment_type' => Attachment::class,
                    'attachment_id' => $record->id, 'source_disk' => 'public', 'source_path' => $record->file_path,
                    'destination_disk' => 'school_attachments', 'destination_path' => 'private-migrations/'.$key,
                    'observed_source_metadata' => $record->getRawOriginal(), 'safe_failure_code' => 'MISSING_SOURCE']);
            }
        }
        if ($apply) {
            foreach (PrivateFileMigration::query()->where('state', '!=', 'verified')->orderBy('id')->limit($limit)->get() as $manifest) {
                if ($manifest->safe_failure_code && in_array($manifest->safe_failure_code, ['AMBIGUOUS_REFERENCE', 'ORPHAN_REQUIRES_REVIEW', 'ORPHAN_OR_DELETED_PARENT', 'UNSAFE_SOURCE_PATH', 'MISSING_SOURCE'], true)) { continue; }
                $this->migrate($manifest);
            }
            $counts['verified'] = PrivateFileMigration::where('state', 'verified')->count();
            $counts['failed'] = PrivateFileMigration::whereNotNull('safe_failure_code')->count();
            $counts['pending'] = PrivateFileMigration::where('state', '!=', 'verified')->count();
        }
        return $counts;
    }

    public function migrate(PrivateFileMigration $manifest): void
    {
        if (! config('private-files.migration_enabled', false)) { throw new PrivateFileMigratorException('APPLY_SCOPE_NOT_APPROVED'); }
        // Shared DB mutex serializes duplicate workers through copy/CAS/revocation.
        $lock = 'private_file_'.substr($manifest->inventory_key, 0, 40);
        $acquired = false;
        try {
            $acquired = (int) (DB::selectOne('SELECT GET_LOCK(?, 2) AS acquired', [$lock])->acquired ?? 0) === 1;
            if (! $acquired) { throw new PrivateFileMigratorException('LOCK_TIMEOUT'); }
            $manifest->refresh();
            if ($manifest->state === 'verified') { return; }
            if (! in_array($manifest->attachment_type, self::TYPES, true) || ! $manifest->attachment_id) { throw new PrivateFileMigratorException('ORPHAN_REQUIRES_REVIEW'); }
            if ($manifest->source_disk !== 'public' || ! in_array($manifest->destination_disk, ['local', 'school_attachments'], true)
                || PublicAssetPath::normalize($manifest->destination_path) === null || ! $manifest->source_hash) { throw new PrivateFileMigratorException('INVALID_MANIFEST'); }
            if (in_array($manifest->state, ['inventoried', 'copied_verified'], true)) {
                $source = $this->publicPhysicalPath($manifest->source_path);
                $this->assertFingerprint($source, $manifest);
                $disk = Storage::disk($manifest->destination_disk);
                if (! $disk->exists($manifest->destination_path)) {
                    $stream = fopen($source, 'rb');
                    try { if (! $disk->put($manifest->destination_path, $stream)) { throw new PrivateFileMigratorException('COPY_FAILED'); } }
                    finally { if (is_resource($stream)) { fclose($stream); } }
                }
                $dest = app(PrivateFileReader::class)->physicalPath($manifest->destination_disk, $manifest->destination_path);
                $this->assertFingerprint($dest, $manifest);
                $manifest->update(['state' => 'copied_verified', 'safe_failure_code' => null]); $this->checkpoint($manifest);
                DB::transaction(function () use ($manifest): void {
                    $type = $manifest->attachment_type;
                    $record = ($type === Attachment::class ? $type::withTrashed() : $type::query())->lockForUpdate()->find($manifest->attachment_id);
                    if (! $record || ! $this->validParent($record) || $record->getRawOriginal() != $manifest->observed_source_metadata) { throw new PrivateFileMigratorException('METADATA_CONFLICT'); }
                    $this->assertFingerprint($this->publicPhysicalPath($manifest->source_path), $manifest);
                    $payload = ['file_path' => $manifest->destination_path];
                    if ($record instanceof Attachment) { $payload += ['disk' => $manifest->destination_disk, 'is_private' => true]; }
                    $record->update($payload);
                    $manifest->update(['state' => 'metadata_switched', 'safe_failure_code' => null]);
                });
                $this->checkpoint($manifest);
            }
            if ($manifest->state === 'metadata_switched') {
                $dest = app(PrivateFileReader::class)->physicalPath($manifest->destination_disk, $manifest->destination_path);
                $this->assertFingerprint($dest, $manifest);
                $aliases = $this->anonymousCopies($manifest->source_path);
                foreach ($aliases as $alias) { $this->assertFingerprint($alias, $manifest); }
                foreach ($aliases as $alias) {
                    if (is_file($alias) && ! unlink($alias)) { throw new PrivateFileMigratorException('PUBLIC_REVOCATION_FAILED'); }
                }
                if ($this->anonymousCopies($manifest->source_path) !== []) { throw new PrivateFileMigratorException('PUBLIC_REVOCATION_FAILED'); }
                $manifest->update(['state' => 'public_revoked', 'safe_failure_code' => null]); $this->checkpoint($manifest);
            }
            if ($manifest->state === 'public_revoked') {
                $this->assertFingerprint(app(PrivateFileReader::class)->physicalPath($manifest->destination_disk, $manifest->destination_path), $manifest);
                if ($this->anonymousCopies($manifest->source_path) !== []) { throw new PrivateFileMigratorException('PUBLIC_REEXPOSURE'); }
                $manifest->update(['state' => 'verified', 'safe_failure_code' => null]);
            }
        } catch (\Throwable $error) {
            $manifest->refresh();
            $manifest->update(['safe_failure_code' => $error instanceof PrivateFileMigratorException ? $error->getMessage() : 'RETRYABLE_IO_FAILURE']);
        } finally {
            if ($acquired) { DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]); }
        }
    }

    protected function checkpoint(PrivateFileMigration $manifest): void {}

    private function assertFingerprint(string $path, PrivateFileMigration $manifest): void
    {
        clearstatcache(true, $path);
        if (! is_file($path) || filesize($path) !== $manifest->source_size || hash_file('sha256', $path) !== $manifest->source_hash) { throw new PrivateFileMigratorException('CHECKSUM_CONFLICT'); }
    }

    private function publicPhysicalPath(string $path): string
    {
        if (PublicAssetPath::normalize($path) === null) { throw new PrivateFileMigratorException('UNSAFE_SOURCE_PATH'); }
        $root = realpath(Storage::disk('public')->path('')); $real = realpath(Storage::disk('public')->path($path));
        if (! $root || ! $real || ! is_file($real) || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR)) { throw new PrivateFileMigratorException('MISSING_SOURCE'); }
        return $real;
    }

    private function anonymousCopies(string $path): array
    {
        if (PublicAssetPath::normalize($path) === null) { throw new PrivateFileMigratorException('UNSAFE_SOURCE_PATH'); }
        $copies = []; $publicRoot = realpath(public_path()); $sourceRoot = realpath(Storage::disk('public')->path(''));
        foreach ([Storage::disk('public')->path(''), public_path('storage'), public_path('media-files')] as $base) {
            $root = realpath($base); if (! $root) { continue; }
            $real = realpath($base.DIRECTORY_SEPARATOR.$path); if (! $real) { continue; }
            if ($root !== $sourceRoot && (! $publicRoot || ! str_starts_with($root, $publicRoot.DIRECTORY_SEPARATOR))) { throw new PrivateFileMigratorException('UNSAFE_ALIAS_ROOT'); }
            if (! is_file($real) || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR)) { throw new PrivateFileMigratorException('UNSAFE_ALIAS_PATH'); }
            $copies[$real] = $real;
        }
        return array_values($copies);
    }

    private function validParent(Model $record): bool
    {
        if ($record instanceof Attachment) {
            if ($record->trashed()) { return false; }
            if ($record->ticket_message_id) {
                $m = $record->message; $task = $m?->subtask; $parent = $task ?: $m?->ticket;
                return $parent && (! $record->school_id || (int) $record->school_id === (int) $parent->school_id)
                    && (! $task || ($task->ticket && (int) $task->ticket->school_id === (int) $task->school_id));
            }
            $types = ['exams' => \App\Models\SchoolExam::class, 'schedules' => \App\Models\SchoolTimetableVersion::class,
                'teacher_preparations' => \App\Models\SchoolTeachingAssignment::class, 'student_records' => \App\Models\SchoolStudent::class, 'staff_documents' => \App\Models\User::class];
            $type = $types[$record->module] ?? null;
            $parent = $type && $record->attachable_type === $type ? $type::find($record->attachable_id) : null;
            return $parent && (int) $parent->school_id === (int) $record->school_id;
        }
        $parent = $record instanceof SchoolStudentLeaveAttachment ? $record->leaveRequest : $record->classroom;
        return $parent && (int) $parent->school_id === (int) $record->school_id;
    }
}
