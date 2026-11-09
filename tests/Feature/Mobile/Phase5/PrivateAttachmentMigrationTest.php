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
        $a = $this->legacy(); $before = $a->getRawOriginal();
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
        $this->assertSame(1, app(PrivateAttachmentMigrator::class)->run(false, 1)['observed']);
    }
}
