<?php

namespace App\Services\Support;

use App\Models\{Attachment, PrivateFileMigration, SchoolAttendanceAttachment, SchoolStudentLeaveAttachment, SchoolStudentLeaveRequest, TicketMessage};
use App\Support\PublicAssetPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB, Storage};

class PrivateAttachmentMigrator
{
    public const TYPES = [Attachment::class, SchoolStudentLeaveAttachment::class, SchoolAttendanceAttachment::class];
    private const QUARANTINE = ['AMBIGUOUS_REFERENCE', 'ORPHAN_REQUIRES_REVIEW', 'ORPHAN_OR_DELETED_PARENT', 'UNSAFE_SOURCE_PATH', 'MISSING_SOURCE'];

    public function run(bool $apply = false, int $limit = 100, int $offset = 0, bool $inventoryOnly = false, bool $resumeOnly = false, string $surface = 'public'): array
    {
        if ($limit < 1 || $limit > 500 || $offset < 0 || ($inventoryOnly && $resumeOnly) || ! in_array($surface, ['public', 'storage', 'media-files'], true)) { throw new PrivateFileMigratorException('INVALID_BATCH_LIMIT'); }
        if ($apply && ! config('private-files.migration_enabled', false)) { throw new PrivateFileMigratorException('APPLY_SCOPE_NOT_APPROVED'); }
        $counts = ['observed' => 0, 'public_assets' => 0, 'private_required' => 0, 'quarantined' => 0, 'verified' => 0, 'failed' => 0, 'pending' => 0,
            'inventory_complete' => $resumeOnly ? null : true, 'next_offset' => null, 'metadata_only' => 0, 'inventory_surface' => $surface,
            'mode' => $apply ? ($inventoryOnly ? 'inventory_apply' : ($resumeOnly ? 'resume_apply' : 'apply')) : 'dry_run'];
        $seen = 0;
        $source = $this->inventoryDisk($surface);
        $batchPaths = [];
        foreach ($resumeOnly || ! $source ? [] : $source->getDriver()->listContents('', true) as $entry) {
            if (! $entry->isFile()) { continue; }
            if ($seen++ < $offset) { continue; }
            if ($counts['observed'] >= $limit) { $counts['inventory_complete'] = false; $counts['next_offset'] = $offset + $limit; break; }
            $path = $entry->path(); $counts['observed']++; $batchPaths[$path] = true;
            $result = $this->inventoryPath($path, $apply);
            $counts[$result['kind']]++;
            if ($result['quarantined']) { $counts['quarantined']++; }
        }
        // Reconcile source pointers even when canonical bytes exist only in a legacy physical alias.
        $missing = $resumeOnly ? collect() : Attachment::withTrashed()->where('disk', 'public')->orderBy('id')->offset($offset)->limit($limit + 1)->get();
        if ($missing->count() > $limit) { $counts['inventory_complete'] = false; $counts['next_offset'] = $offset + $limit; $missing = $missing->take($limit); }
        foreach ($missing as $record) {
            if (isset($batchPaths[$record->file_path]) || Storage::disk('public')->exists($record->file_path)) { continue; }
            $batchPaths[$record->file_path] = true; $counts['metadata_only']++;
            $result = $this->inventoryPath($record->file_path, $apply);
            $counts[$result['kind']]++;
            if ($result['quarantined']) { $counts['quarantined']++; }
        }
        if ($apply) {
            foreach ($inventoryOnly ? [] : PrivateFileMigration::query()->whereNotNull('source_hash')->where(fn ($q) => $q->whereNull('safe_failure_code')->orWhereNotIn('safe_failure_code', self::QUARANTINE))
                ->orderByRaw("state = 'verified'")->orderBy('id')->limit($limit)->get() as $manifest) {
                $this->migrate($manifest);
            }
            $counts['verified'] = PrivateFileMigration::where('state', 'verified')->whereNull('safe_failure_code')->count();
            $counts['failed'] = PrivateFileMigration::whereNotNull('safe_failure_code')->count();
            $counts['pending'] = PrivateFileMigration::where('state', '!=', 'verified')->count();
        }
        return $counts;
    }

    private function inventoryPath(string $path, bool $apply): array
    {
        $key = hash('sha256', 'public'."\0".$path."\0".'1');
        $existing = PrivateFileMigration::query()->where('inventory_key', $key)->first();
        if ($existing) { return ['kind' => 'private_required', 'quarantined' => in_array($existing->safe_failure_code, self::QUARANTINE, true)]; }
        $refs = collect();
        foreach (self::TYPES as $type) {
            $query = $type === Attachment::class ? $type::withTrashed() : $type::query();
            $refs = $refs->merge($query->where('file_path', $path)->limit(2)->get());
            if ($refs->count() > 1) { break; }
        }
        if ($refs->isEmpty() && PublicAssetPath::allows($path)) { return ['kind' => 'public_assets', 'quarantined' => false]; }
        $record = $refs->count() === 1 ? $refs->first() : null;
        $reason = $refs->count() > 1 ? 'AMBIGUOUS_REFERENCE' : ($record ? null : 'ORPHAN_REQUIRES_REVIEW');
        if ($record && ! $this->validParent($record)) { $reason = 'ORPHAN_OR_DELETED_PARENT'; }
        try { $source = $this->publicPhysicalPath($path); }
        catch (\Throwable $error) { $source = null; $reason = $reason ?: ($error instanceof PrivateFileMigratorException ? $error->getMessage() : 'UNSAFE_SOURCE_PATH'); }
        if ($apply) {
            PrivateFileMigration::firstOrCreate(['inventory_key' => $key], ['schema_version' => 1,
                'attachment_type' => $record ? $record::class : null, 'attachment_id' => $record?->id, 'source_disk' => 'public', 'source_path' => $path,
                'destination_disk' => $record instanceof Attachment ? 'school_attachments' : 'local', 'destination_path' => 'private-migrations/'.$key,
                'source_size' => $source ? filesize($source) : null, 'source_hash' => $source ? hash_file('sha256', $source) : null,
                'observed_source_metadata' => $record ? $record->getRawOriginal() : null, 'safe_failure_code' => $reason]);
        }
        return ['kind' => 'private_required', 'quarantined' => $reason !== null];
    }

    private function inventoryDisk(string $surface): ?\Illuminate\Contracts\Filesystem\Filesystem
    {
        if ($surface === 'public') { return Storage::disk('public'); }
        $root = realpath(public_path($surface)); if (! $root) { return null; }
        $public = realpath(public_path()); $canonical = realpath(Storage::disk('public')->path(''));
        if (! is_dir($root) || ($root !== $canonical && (! $public || ! str_starts_with($root, $public.DIRECTORY_SEPARATOR)))) { throw new PrivateFileMigratorException('UNSAFE_ALIAS_ROOT'); }
        return Storage::build(['driver' => 'local', 'root' => $root, 'throw' => true]);
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
            if (! in_array($manifest->state, ['inventoried', 'copied_verified', 'metadata_switched', 'public_revoked', 'verified'], true)) { throw new PrivateFileMigratorException('INVALID_MANIFEST_STATE'); }
            if ($manifest->state === 'verified') {
                $this->assertFingerprint(app(PrivateFileReader::class)->physicalPath($manifest->destination_disk, $manifest->destination_path), $manifest);
                if ($this->anonymousCopies($manifest->source_path) !== []) { throw new PrivateFileMigratorException('PUBLIC_REEXPOSURE'); }
                return;
            }
            if (! in_array($manifest->attachment_type, self::TYPES, true) || ! $manifest->attachment_id) { throw new PrivateFileMigratorException('ORPHAN_REQUIRES_REVIEW'); }
            if ($manifest->source_disk !== 'public' || ! in_array($manifest->destination_disk, ['local', 'school_attachments'], true)
                || $manifest->destination_disk !== ($manifest->attachment_type === Attachment::class ? 'school_attachments' : 'local')
                || $manifest->inventory_key !== hash('sha256', 'public'."\0".$manifest->source_path."\0".'1')
                || (int) $manifest->schema_version !== 1 || $manifest->destination_path !== 'private-migrations/'.$manifest->inventory_key
                || PublicAssetPath::normalize($manifest->destination_path) === null || ! $manifest->source_hash) { throw new PrivateFileMigratorException('INVALID_MANIFEST'); }
            if (in_array($manifest->state, ['inventoried', 'copied_verified'], true)) {
                $source = $this->publicPhysicalPath($manifest->source_path);
                $this->assertFingerprint($source, $manifest);
                app(PrivateFileReader::class)->assertPrivateDisk($manifest->destination_disk);
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
        $copies = $this->anonymousCopies($path);
        if ($copies === []) { throw new PrivateFileMigratorException('MISSING_SOURCE'); }
        $hash = hash_file('sha256', $copies[0]);
        foreach ($copies as $copy) { if (hash_file('sha256', $copy) !== $hash) { throw new PrivateFileMigratorException('CHECKSUM_CONFLICT'); } }
        return $copies[0];
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
                if (! in_array((string) $record->module, ['', 'tickets'], true)
                    || (($record->attachable_type || $record->attachable_id) && ($record->attachable_type !== TicketMessage::class || (int) $record->attachable_id !== (int) $record->ticket_message_id))
                    || ($task && $m->ticket_id && (int) $m->ticket_id !== (int) $task->ticket_id)) { return false; }
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
