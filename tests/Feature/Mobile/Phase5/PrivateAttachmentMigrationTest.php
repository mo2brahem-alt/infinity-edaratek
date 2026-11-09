<?php

namespace Tests\Feature\Mobile\Phase5;

use App\Models\{Attachment, PrivateFileMigration, TicketMessage};
use App\Services\Support\PrivateAttachmentMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MobileSchoolFixtures;
use Tests\TestCase;

class PrivateAttachmentMigrationTest extends TestCase
{
    use RefreshDatabase, MobileSchoolFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['public', 'local', 'school_attachments'] as $disk) { Storage::fake($disk); }
        config(['private-files.migration_enabled' => true]);
    }

    private function legacy(): Attachment
    {
        $s = $this->schools(); $task = $this->task($s['A']);
        $message = TicketMessage::create(['subtask_id' => $task->id, 'user_id' => $s['A']['staff']->id, 'message' => 'Synthetic', 'message_type' => 'reply']);
        $a = Attachment::create(['school_id' => $s['A']['school']->id, 'ticket_message_id' => $message->id,
            'module' => 'tickets', 'disk' => 'public', 'file_path' => 'ticketing/attachments/legacy.txt',
            'file_name' => 'legacy.txt', 'file_size' => 15, 'mime_type' => 'text/plain', 'is_private' => false, 'uploaded_by' => $s['A']['staff']->id]);
        Storage::disk('public')->put($a->file_path, 'synthetic-proof');
        return $a;
    }

    public function test_dry_run_never_mutates_files_manifest_or_metadata(): void
    {
        $a = $this->legacy(); $before = $a->fresh()->getRawOriginal();
        $counts = app(PrivateAttachmentMigrator::class)->run();
        $this->assertSame(1, $counts['private_required']); $this->assertSame(0, PrivateFileMigration::count());
        $this->assertSame($before, $a->fresh()->getRawOriginal());
        Storage::disk('public')->assertExists($a->file_path);
        $this->assertSame([], Storage::disk('school_attachments')->allFiles());
    }

    public function test_copy_verify_switch_revoke_and_repeat_are_idempotent(): void
    {
        $a = $this->legacy(); $old = $a->file_path;
        $service = app(PrivateAttachmentMigrator::class); $result = $service->run(true);
        $this->assertSame(1, $result['verified']); $this->assertSame(0, $result['failed']);
        $a->refresh(); $manifest = PrivateFileMigration::firstOrFail();
        $this->assertSame('verified', $manifest->state); $this->assertSame('school_attachments', $a->disk);
        $this->assertSame(hash('sha256', 'synthetic-proof'), hash('sha256', Storage::disk($a->disk)->get($a->file_path)));
        Storage::disk('public')->assertMissing($old);
        $service->run(true); $this->assertSame(1, PrivateFileMigration::count());
        $this->assertCount(1, Storage::disk('school_attachments')->allFiles());
        $this->get('/storage/'.$old)->assertNotFound(); $this->get('/media-files/'.$old)->assertNotFound();
        $staff = $a->message->subtask->assignee;
        $response = $this->actingAs($staff)->get(route('school.attachments.download', $a));
        $response->assertOk(); $this->assertSame('synthetic-proof', $response->streamedContent());
    }

    public function test_crash_after_copy_resumes_without_duplicate_or_source_loss(): void
    {
        $a = $this->legacy();
        $interrupted = new class extends PrivateAttachmentMigrator {
            protected function checkpoint(PrivateFileMigration $m): void { if ($m->state === 'copied_verified') { throw new \RuntimeException('Synthetic interruption'); } }
        };
        $interrupted->run(true); $m = PrivateFileMigration::firstOrFail();
        $this->assertSame('copied_verified', $m->state); $this->assertSame('public', $a->fresh()->disk);
        Storage::disk('public')->assertExists($a->file_path);
        app(PrivateAttachmentMigrator::class)->run(true);
        $this->assertSame('verified', $m->fresh()->state); $this->assertCount(1, Storage::disk('school_attachments')->allFiles());
    }

    public function test_crash_after_metadata_switch_preserves_private_bytes_and_resumes_revocation(): void
    {
        $a = $this->legacy(); $old = $a->file_path;
        $interrupted = new class extends PrivateAttachmentMigrator {
            protected function checkpoint(PrivateFileMigration $m): void { if ($m->state === 'metadata_switched') { throw new \RuntimeException('Synthetic interruption'); } }
        };
        $interrupted->run(true); $m = PrivateFileMigration::firstOrFail();
        $this->assertSame('metadata_switched', $m->state); $this->assertSame('school_attachments', $a->fresh()->disk);
        Storage::disk('public')->assertExists($old);
        app(PrivateAttachmentMigrator::class)->run(true);
        $this->assertSame('verified', $m->fresh()->state); Storage::disk('public')->assertMissing($old);
    }

    public function test_changed_source_after_inventory_never_switches_metadata_or_removes_source(): void
    {
        $a = $this->legacy();
        $interrupted = new class extends PrivateAttachmentMigrator { protected function checkpoint(PrivateFileMigration $m): void { throw new \RuntimeException('Synthetic interruption'); } };
        $interrupted->run(true);
        Storage::disk('public')->put($a->file_path, 'changed-source');
        app(PrivateAttachmentMigrator::class)->run(true);
        $m = PrivateFileMigration::firstOrFail();
        $this->assertSame('CHECKSUM_CONFLICT', $m->safe_failure_code); $this->assertSame('public', $a->fresh()->disk);
        $this->assertSame('changed-source', Storage::disk('public')->get($a->file_path));
    }

    public function test_destination_checksum_failure_and_concurrent_metadata_change_fail_closed(): void
    {
        $a = $this->legacy();
        $interrupted = new class extends PrivateAttachmentMigrator { protected function checkpoint(PrivateFileMigration $m): void { throw new \RuntimeException('Synthetic interruption'); } };
        $interrupted->run(true); $m = PrivateFileMigration::firstOrFail();
        Storage::disk($m->destination_disk)->put($m->destination_path, 'corrupt');
        app(PrivateAttachmentMigrator::class)->run(true);
        $this->assertSame('CHECKSUM_CONFLICT', $m->fresh()->safe_failure_code); Storage::disk('public')->assertExists($a->file_path);
        Storage::disk($m->destination_disk)->put($m->destination_path, 'synthetic-proof');
        $a->update(['file_name' => 'concurrently-replaced.txt']);
        app(PrivateAttachmentMigrator::class)->run(true);
        $this->assertSame('METADATA_CONFLICT', $m->fresh()->safe_failure_code); $this->assertSame('public', $a->fresh()->disk);
    }

    public function test_orphan_and_soft_deleted_decisions_are_quarantined_until_review(): void
    {
        $a = $this->legacy(); $a->delete(); Storage::disk('public')->put('unknown/orphan.bin', 'orphan');
        app(PrivateAttachmentMigrator::class)->run(true);
        $this->assertSame(0, PrivateFileMigration::where('state', 'verified')->count());
        $this->assertSame(2, PrivateFileMigration::whereNotNull('safe_failure_code')->count());
        Storage::disk('public')->assertExists($a->file_path);
        $a->restore(); $duplicate = $a->replicate(); $duplicate->save();
        // Inventory keeps quarantined decisions immutable until reviewed; no automatic owner guess.
        app(PrivateAttachmentMigrator::class)->run(true);
        $this->assertSame(0, PrivateFileMigration::where('state', 'verified')->count());
    }

    public function test_missing_and_ambiguous_source_references_never_become_verified(): void
    {
        $a = $this->legacy(); $duplicate = $a->replicate(); $duplicate->save();
        $missing = $a->replicate(); $missing->file_path = 'ticketing/attachments/missing.txt'; $missing->save();
        app(PrivateAttachmentMigrator::class)->run(true);
        $this->assertSame(0, PrivateFileMigration::where('state', 'verified')->count());
        $this->assertTrue(PrivateFileMigration::where('safe_failure_code', 'AMBIGUOUS_REFERENCE')->exists());
        $this->assertTrue(PrivateFileMigration::where('safe_failure_code', 'MISSING_SOURCE')->exists());
        Storage::disk('public')->assertExists($a->file_path);
    }

    public function test_manifest_foreign_record_injection_cannot_switch_other_school_metadata(): void
    {
        $a = $this->legacy();
        $schoolB = \App\Models\School::where('school_id', 'SYNTHETIC-B')->firstOrFail();
        $staffB = \App\Models\User::where('school_id', $schoolB->id)->where('role', 'staff')->firstOrFail();
        $b = Attachment::create(['school_id' => $schoolB->id, 'attachable_type' => \App\Models\User::class,
            'attachable_id' => $staffB->id, 'module' => 'staff_documents', 'disk' => 'public', 'file_path' => 'staff/other.txt', 'file_name' => 'other.txt', 'mime_type' => 'text/plain', 'uploaded_by' => $staffB->id]);
        Storage::disk('public')->put($b->file_path, 'other-school');
        $interrupted = new class extends PrivateAttachmentMigrator { protected function checkpoint(PrivateFileMigration $m): void { throw new \RuntimeException('Synthetic interruption'); } };
        $interrupted->run(true);
        $m = PrivateFileMigration::where('attachment_id', $a->id)->firstOrFail(); $m->update(['attachment_id' => $b->id]);
        app(PrivateAttachmentMigrator::class)->migrate($m);
        $this->assertSame('METADATA_CONFLICT', $m->fresh()->safe_failure_code);
        $this->assertSame('public', $b->fresh()->disk); Storage::disk('public')->assertExists($b->file_path);
    }

    public function test_apply_requires_operator_scope_and_batch_is_bounded(): void
    {
        $this->legacy(); config(['private-files.migration_enabled' => false]);
        $this->artisan('attachments:migrate-private', ['--apply' => true])->assertExitCode(1);
        $this->assertSame(0, PrivateFileMigration::count());
        Storage::disk('public')->put('uploads/logo.png', 'branding');
        \App\Models\Media::create(['file_name' => 'logo.png', 'file_path' => 'uploads/logo.png', 'file_type' => 'image', 'mime_type' => 'text/plain', 'file_size' => 8]);
        $this->assertSame(1, app(PrivateAttachmentMigrator::class)->run(false, 1)['observed']);
    }

    public function test_inventory_continuation_does_not_mutate_sources_until_explicit_resume(): void
    {
        $a = $this->legacy(); Storage::disk('public')->put('uploads/logo.png', 'logo');
        \App\Models\Media::create(['file_name' => 'logo.png', 'file_path' => 'uploads/logo.png', 'file_type' => 'image', 'mime_type' => 'text/plain', 'file_size' => 4]);
        $service = app(PrivateAttachmentMigrator::class);
        $first = $service->run(true, 1, 0, true);
        $this->assertFalse($first['inventory_complete']); $this->assertSame(1, $first['next_offset']);
        $second = $service->run(true, 1, $first['next_offset'], true);
        $this->assertTrue($second['inventory_complete']); $this->assertSame('public', $a->fresh()->disk);
        Storage::disk('public')->assertExists($a->file_path);
        $last = $service->run(true, 1, 0, false, true);
        $this->assertSame(1, $last['verified']); $this->assertNull($last['inventory_complete']);
        Storage::disk('public')->assertExists('uploads/logo.png');
    }

    public function test_independent_connection_lock_blocks_a_duplicate_worker_without_copy_or_switch(): void
    {
        $a = $this->legacy(); app(PrivateAttachmentMigrator::class)->run(true, 100, 0, true);
        $m = PrivateFileMigration::firstOrFail();
        config(['database.connections.phase5_peer' => config('database.connections.mysql')]);
        $peer = \Illuminate\Support\Facades\DB::connection('phase5_peer'); $lock = 'private_file_'.substr($m->inventory_key, 0, 40);
        try {
            $this->assertSame(1, (int) $peer->selectOne('SELECT GET_LOCK(?, 2) AS acquired', [$lock])->acquired);
            app(PrivateAttachmentMigrator::class)->migrate($m);
            $this->assertSame('LOCK_TIMEOUT', $m->fresh()->safe_failure_code); $this->assertSame('public', $a->fresh()->disk);
            $this->assertSame([], Storage::disk('school_attachments')->allFiles());
        } finally { $peer->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]); \Illuminate\Support\Facades\DB::disconnect('phase5_peer'); }
    }

    public function test_verified_record_audit_detects_later_corruption_without_republishing_source(): void
    {
        $a = $this->legacy(); $service = app(PrivateAttachmentMigrator::class); $service->run(true);
        $m = PrivateFileMigration::firstOrFail(); Storage::disk($m->destination_disk)->put($m->destination_path, 'corrupt-later');
        $counts = $service->run(true, 100, 0, false, true);
        $this->assertSame(0, $counts['verified']); $this->assertSame(1, $counts['failed']);
        $this->assertSame('CHECKSUM_CONFLICT', $m->fresh()->safe_failure_code); Storage::disk('public')->assertMissing('ticketing/attachments/legacy.txt');
        $migration = require database_path('migrations/2026_11_09_000000_create_private_file_migrations_table.php'); $migration->down();
        $this->assertSame(1, PrivateFileMigration::count()); $this->assertSame('school_attachments', $a->fresh()->disk);
        Storage::disk($m->destination_disk)->assertExists($m->destination_path);
    }

    public function test_large_inventory_counts_reconcile_across_bounded_read_only_batches(): void
    {
        $a = $this->legacy();
        $assets = [];
        for ($i = 0; $i < 503; $i++) {
            $path = 'uploads/logo-'.$i.'.txt'; Storage::disk('public')->put($path, 'branding');
            $assets[] = ['file_name' => basename($path), 'file_path' => $path, 'file_type' => 'image', 'mime_type' => 'text/plain', 'file_size' => 8];
        }
        \App\Models\Media::insert($assets);
        $observed = 0; $offset = 0; $service = app(PrivateAttachmentMigrator::class);
        do {
            $batch = $service->run(true, 100, $offset, true); $observed += $batch['observed'];
            $this->assertLessThanOrEqual(100, $batch['observed']);
            $offset = $batch['next_offset'];
        } while ($offset !== null);
        $this->assertSame(504, $observed); $this->assertTrue($batch['inventory_complete']);
        $this->assertSame(1, PrivateFileMigration::count()); $this->assertSame('public', $a->fresh()->disk);
        $this->assertSame(1, $service->run(true, 100, 0, false, true)['verified']);
        $this->assertCount(503, Storage::disk('public')->allFiles());
    }

    public function test_alias_only_legacy_copy_migrates_without_requiring_a_canonical_public_copy(): void
    {
        $a = $this->legacy(); $old = $a->file_path;
        $root = sys_get_temp_dir().'/edaratek_phase5_'.bin2hex(random_bytes(10)); mkdir($root);
        $originalPublic = public_path(); $this->app->usePublicPath($root);
        try {
            $path = $root.'/storage/'.$old; mkdir(dirname($path), 0755, true); file_put_contents($path, 'conflicting-proof');
            $conflict = app(PrivateAttachmentMigrator::class)->run(false, 100, 0, false, false, 'storage');
            $this->assertSame(1, $conflict['quarantined']); $this->assertSame(0, PrivateFileMigration::count());
            $this->assertSame('synthetic-proof', Storage::disk('public')->get($old));
            file_put_contents($path, 'synthetic-proof');
            Storage::disk('public')->delete($old);
            $counts = app(PrivateAttachmentMigrator::class)->run(true, 100, 0, false, false, 'storage');
            $this->assertSame(1, $counts['verified']); $this->assertFileDoesNotExist($path);
            $this->assertSame('synthetic-proof', Storage::disk('school_attachments')->get($a->fresh()->file_path));
        } finally {
            $this->app->usePublicPath($originalPublic);
            if (dirname($root) === sys_get_temp_dir() && str_starts_with(basename($root), 'edaratek_phase5_')) { (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($root); }
        }
    }

    public function test_retained_manifest_blocks_public_reappearance_even_after_attachment_record_deletion(): void
    {
        $a = $this->legacy(); $old = 'uploads/misplaced.txt';
        Storage::disk('public')->move($a->file_path, $old); $a->update(['file_path' => $old]);
        \App\Models\Media::create(['file_name' => 'misplaced.txt', 'file_path' => $old, 'file_type' => 'image', 'mime_type' => 'text/plain', 'file_size' => 15]);
        $service = app(PrivateAttachmentMigrator::class); $this->assertSame(1, $service->run(true)['verified']);
        $a->forceDelete(); Storage::disk('public')->put($old, 'synthetic-proof');
        $this->get('/storage/'.$old)->assertNotFound(); $this->get('/media-files/'.$old)->assertNotFound();
        $counts = $service->run(true, 100, 0, false, true);
        $this->assertSame(0, $counts['verified']);
        $this->assertSame('PUBLIC_REEXPOSURE', PrivateFileMigration::firstOrFail()->safe_failure_code);
    }
}
