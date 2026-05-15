<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Media;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\Subtask;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UploadValidationFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_media_upload_keeps_legacy_behavior_when_strict_validation_is_disabled(): void
    {
        config()->set('features.uploads.strict_validation_enabled', false);
        Storage::fake('public');

        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)->post(route('admin.media.store'), [
            'file' => UploadedFile::fake()->create('legacy-script.php', 5, 'application/x-php'),
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('media', [
            'file_name' => 'legacy-script.php',
            'mime_type' => 'application/x-php',
        ]);
    }

    public function test_admin_media_upload_rejects_disallowed_mime_when_strict_validation_is_enabled(): void
    {
        config()->set('features.uploads.strict_validation_enabled', true);
        Storage::fake('public');

        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)->post(route('admin.media.store'), [
            'file' => UploadedFile::fake()->create('blocked-script.php', 5, 'application/x-php'),
        ]);

        $response
            ->assertStatus(302)
            ->assertSessionHasErrors('file');

        $this->assertDatabaseMissing('media', [
            'file_name' => 'blocked-script.php',
        ]);
    }

    public function test_admin_media_upload_accepts_safe_svg_when_strict_validation_is_enabled(): void
    {
        config()->set('features.uploads.strict_validation_enabled', true);
        Storage::fake('public');

        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)->post(route('admin.media.store'), [
            'file' => $this->fakeSvgUpload('site-logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 24"><defs><clipPath id="logo-clip"><path d="M0 0h100v24H0z"/></clipPath></defs><g clip-path="url(#logo-clip)"><path fill="#ffffff" d="M0 0h100v24H0z"/></g></svg>'),
        ]);

        $response
            ->assertOk()
            ->assertJsonFragment([
                'file_name' => 'site-logo.svg',
                'file_type' => 'image',
                'mime_type' => 'image/svg+xml',
            ]);

        $media = Media::query()->where('file_name', 'site-logo.svg')->firstOrFail();

        Storage::disk('public')->assertExists($media->file_path);
        $this->assertSame('/admin/media/' . $media->id . '/preview', $media->url);
    }

    public function test_admin_media_upload_rejects_unsafe_svg_content(): void
    {
        config()->set('features.uploads.strict_validation_enabled', true);
        Storage::fake('public');

        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)->post(route('admin.media.store'), [
            'file' => $this->fakeSvgUpload('unsafe-logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ]);

        $response
            ->assertStatus(302)
            ->assertSessionHasErrors('file');

        $this->assertDatabaseMissing('media', [
            'file_name' => 'unsafe-logo.svg',
        ]);
    }

    public function test_staff_subtask_reply_attachment_enforces_strict_mime_validation_when_enabled(): void
    {
        config()->set('features.uploads.strict_validation_enabled', true);
        Storage::fake('public');

        [, $staff, $subtask] = $this->createSchoolWorkflow();

        $blocked = $this->actingAs($staff)->post(route('staff.subtasks.reply', $subtask), [
            'message' => 'Please find attachment.',
            'attachment' => UploadedFile::fake()->create('blocked-script.php', 5, 'application/x-php'),
        ]);

        $blocked
            ->assertStatus(302)
            ->assertSessionHasErrors('attachment');

        $allowed = $this->actingAs($staff)->post(route('staff.subtasks.reply', $subtask), [
            'message' => 'Valid document attachment.',
            'attachment' => UploadedFile::fake()->create('evidence.pdf', 50, 'application/pdf'),
        ]);

        $allowed->assertOk();

        $this->assertDatabaseHas('attachments', [
            'file_name' => 'evidence.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->assertSame(1, Attachment::query()->count());
    }

    private function createSuperAdmin(): User
    {
        Role::query()->firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $superAdmin->assignRole('super_admin');

        return $superAdmin;
    }

    private function fakeSvgUpload(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'edaratek-svg-');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, 'image/svg+xml', null, true);
    }

    /**
     * @return array{0: User, 1: User, 2: Subtask}
     */
    private function createSchoolWorkflow(): array
    {
        foreach (['school_manager', 'staff'] as $roleName) {
            Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }

        $manager = User::factory()->create([
            'role' => 'school_manager',
            'is_active' => true,
        ]);
        $manager->assignRole('school_manager');

        $staff = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);
        $staff->assignRole('staff');

        $directorate = EducationalDirectorate::query()->create([
            'name' => 'Upload Test Directorate',
            'governorate' => 'Riyadh',
        ]);

        $school = School::query()->create([
            'directorate_id' => $directorate->id,
            'name' => 'Upload Test School',
            'school_id' => 'SCH-995001',
            'phone' => '0500099501',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);
        $staff->update(['school_id' => $school->id]);

        $ticket = Ticket::query()->create([
            'title' => 'Upload Validation Ticket',
            'description' => 'Testing attachment rules',
            'priority' => 'MEDIUM',
            'school_id' => $school->id,
            'created_by' => $manager->id,
            'assigned_to' => $manager->id,
            'status' => Ticket::STATUS_IN_PROGRESS,
        ]);

        $subtask = Subtask::query()->create([
            'ticket_id' => $ticket->id,
            'school_id' => $school->id,
            'created_by' => $manager->id,
            'assigned_to' => $staff->id,
            'title' => 'Upload Files',
            'description' => 'Attach test files',
            'status' => Subtask::STATUS_OPEN,
        ]);

        return [$manager, $staff, $subtask];
    }
}

