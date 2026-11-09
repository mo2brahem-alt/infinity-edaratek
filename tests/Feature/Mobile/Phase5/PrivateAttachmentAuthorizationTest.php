<?php

namespace Tests\Feature\Mobile\Phase5;

use App\Models\{Attachment, School, SchoolLeaveType, SchoolStage, SchoolClassroom, SchoolStudent, SchoolStudentLeaveRequest, SchoolStudentLeaveAttachment, TicketMessage, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MobileSchoolFixtures;
use Tests\TestCase;

class PrivateAttachmentAuthorizationTest extends TestCase
{
    use RefreshDatabase, MobileSchoolFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['public', 'local', 'school_attachments'] as $disk) { Storage::fake($disk); }
    }

    private function ticketFile(array $s, array $attributes = []): Attachment
    {
        $task = $this->task($s);
        $message = TicketMessage::create(['subtask_id' => $task->id, 'user_id' => $s['staff']->id, 'message' => 'Synthetic', 'message_type' => 'reply']);
        $file = Attachment::create(array_merge(['school_id' => $s['school']->id, 'ticket_message_id' => $message->id,
            'attachable_type' => TicketMessage::class, 'attachable_id' => $message->id, 'module' => 'tickets',
            'file_name' => 'proof.txt', 'file_path' => 'synthetic/'.$task->id.'.txt', 'disk' => 'school_attachments', 'mime_type' => 'text/html', 'file_size' => 16, 'is_private' => true], $attributes));
        Storage::disk($file->disk)->put($file->file_path, 'synthetic-bytes');
        return $file;
    }

    private function leave(array $s): SchoolStudentLeaveRequest
    {
        $stage = SchoolStage::create(['school_id' => $s['school']->id, 'name' => 'Synthetic', 'is_active' => true]);
        $class = SchoolClassroom::create(['school_id' => $s['school']->id, 'school_stage_id' => $stage->id, 'name' => 'A', 'is_active' => true]);
        $student = SchoolStudent::create(['school_id' => $s['school']->id, 'school_classroom_id' => $class->id, 'full_name' => 'Synthetic Student', 'student_code' => 'SYNTHETIC', 'is_active' => true]);
        $type = SchoolLeaveType::create(['school_id' => $s['school']->id, 'name' => 'Synthetic', 'is_active' => true, 'requires_attachment' => false]);
        return SchoolStudentLeaveRequest::create(['school_id' => $s['school']->id, 'school_student_id' => $student->id,
            'school_leave_type_id' => $type->id, 'source' => SchoolStudentLeaveRequest::SOURCE_PRE_APPROVED,
            'status' => SchoolStudentLeaveRequest::STATUS_PENDING, 'start_date' => '2026-11-09', 'end_date' => '2026-11-09', 'created_by' => $s['manager']->id]);
    }

    public function test_ticket_bytes_headers_metadata_and_native_bearer_only(): void
    {
        $s = $this->schools(); $a = $this->ticketFile($s['A']);
        $url = '/api/v1/attachments/'.$a->id.'/download';
        $this->actingAs($s['A']['staff'])->getJson($url)->assertUnauthorized();
        $r = $this->withToken($this->nativeToken($s['A']['staff']))->get($url);
        $r->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertSame('synthetic-bytes', $r->streamedContent());
        $this->getJson('/api/v1/staff/tasks/'.$a->message->subtask_id)->assertOk()
            ->assertJsonPath('data.attachments.0.id', $a->id)->assertJsonMissingPath('data.attachments.0.file_path')->assertJsonMissingPath('data.attachments.0.disk');
        $this->assertArrayNotHasKey('file_path', $a->toArray());
        $this->assertStringStartsWith('/school/attachments/', $a->url);
    }

    public function test_ticket_other_assignee_foreign_ids_nested_school_and_transfer_are_hidden(): void
    {
        $s = $this->schools(); $a = $this->ticketFile($s['A']); $b = $this->ticketFile($s['B']);
        $other = User::factory()->create(['role' => 'staff', 'school_id' => $s['A']['school']->id]);
        $this->withToken($this->nativeToken($other))->getJson('/api/v1/attachments/'.$a->id.'/download')->assertNotFound();
        $this->withToken($this->nativeToken($s['A']['staff']))->getJson('/api/v1/attachments/'.$b->id.'/download')->assertNotFound();
        $this->getJson('/api/v1/attachments/'.$a->id.'/download?school_id='.$s['B']['school']->id)->assertUnprocessable();
        $s['A']['staff']->update(['school_id' => $s['B']['school']->id]);
        $this->getJson('/api/v1/attachments/'.$a->id.'/download')->assertNotFound();
    }

    public function test_private_reader_rejects_paths_public_fallback_and_missing_or_deleted_records(): void
    {
        $s = $this->schools(); $a = $this->ticketFile($s['A']);
        $this->withToken($this->nativeToken($s['A']['staff']));
        foreach (['../outside.txt', '/etc/passwd', 'nested/../../outside', 'C:\\private\\proof', '%2e%2e/outside', 'nested\\..\\outside'] as $path) {
            $a->update(['file_path' => $path]);
            $this->getJson('/api/v1/attachments/'.$a->id.'/download')->assertNotFound();
        }
        $a->update(['file_path' => 'ticketing/attachments/legacy.txt', 'disk' => 'public']);
        Storage::disk('public')->put($a->file_path, 'synthetic-bytes');
        $this->getJson('/api/v1/attachments/'.$a->id.'/download')->assertNotFound();
        $a->update(['disk' => 'school_attachments']);
        $this->getJson('/api/v1/attachments/'.$a->id.'/download')->assertNotFound();
        $a->delete(); $this->getJson('/api/v1/attachments/'.$a->id.'/download')->assertNotFound();
    }

    public function test_upload_is_private_named_by_server_and_cannot_override_storage_authority(): void
    {
        $s = $this->schools(); $task = $this->task($s['A']);
        $this->actingAs($s['A']['staff'])->postJson(route('staff.subtasks.reply', $task), [
            'message' => 'Allowed', 'attachment' => UploadedFile::fake()->createWithContent('original.txt', 'safe document')])->assertOk();
        $a = Attachment::latest('id')->firstOrFail();
        $this->assertSame('school_attachments', $a->disk); $this->assertTrue($a->is_private);
        $this->assertStringNotContainsString('original', $a->file_path);
        Storage::disk('school_attachments')->assertExists($a->file_path); Storage::disk('public')->assertMissing($a->file_path);
        $this->postJson(route('staff.subtasks.reply', $task), ['message' => 'Denied', 'disk' => 'public', 'path' => '../other'])->assertUnprocessable();
        $this->postJson(route('staff.subtasks.reply', $task), ['message' => 'Denied', 'attachment' => UploadedFile::fake()->createWithContent('proof.php', '<?php echo 1;')])->assertUnprocessable();
    }

    public function test_leave_requires_operation_permission_and_matching_parent_for_web_and_native(): void
    {
        $s = $this->schools(); $leave = $this->leave($s['A']);
        $a = SchoolStudentLeaveAttachment::create(['school_id' => $s['A']['school']->id, 'school_student_leave_request_id' => $leave->id,
            'file_name' => 'leave.txt', 'file_path' => 'synthetic/leave.txt', 'mime_type' => 'text/plain', 'file_size' => 4, 'uploaded_by' => $s['A']['manager']->id]);
        Storage::disk('local')->put($a->file_path, 'leave-bytes');
        $url = '/api/v1/student-leaves/'.$leave->id.'/attachments/'.$a->id.'/download';
        $staff = $s['A']['staff']; $staff->update(['can_manage_school_calendar' => true, 'can_manage_student_leaves' => false]);
        $this->withToken($this->nativeToken($staff))->getJson($url)->assertForbidden();
        $this->actingAs($staff)->getJson(route('api.school.leaves.attachments.download', [$leave, $a]))->assertForbidden();
        $staff->update(['can_manage_student_leaves' => true]);
        $this->withToken($this->nativeToken($staff))->get($url)->assertOk();
        $this->withToken($this->nativeToken($s['B']['manager']))->getJson($url)->assertNotFound();
        $otherLeave = $this->leave($s['B']);
        $this->withToken($this->nativeToken($s['A']['manager']))->getJson('/api/v1/student-leaves/'.$otherLeave->id.'/attachments/'.$a->id.'/download')->assertNotFound();
        $leave->update(['status' => SchoolStudentLeaveRequest::STATUS_REJECTED]);
        $this->get($url)->assertOk(); // Existing authorized review of rejected evidence remains valid.
        $leave->delete(); $this->getJson($url)->assertNotFound();
    }

    public function test_current_account_role_and_school_revocation_apply_to_existing_token(): void
    {
        $s = $this->schools(); $a = $this->ticketFile($s['A']); $user = $s['A']['staff'];
        $this->withToken($this->nativeToken($user)); $url = '/api/v1/attachments/'.$a->id.'/download';
        $this->get($url)->assertOk(); $user->update(['is_active' => false]); $this->getJson($url)->assertForbidden();
        $user->update(['is_active' => true, 'role' => 'teacher']); $user->syncRoles(['teacher']); $this->getJson($url)->assertNotFound();
        $user->update(['role' => 'staff']); $user->syncRoles(['staff']);
        $s['A']['school']->update(['status' => School::STATUS_SUSPENDED]); $this->getJson($url)->assertForbidden();
    }

    public function test_institutional_documents_require_capability_and_cannot_delete_by_viewing_ticket(): void
    {
        $s = $this->schools();
        $a = Attachment::create(['school_id' => $s['A']['school']->id, 'attachable_type' => User::class, 'attachable_id' => $s['A']['staff']->id,
            'module' => 'staff_documents', 'disk' => 'school_attachments', 'file_path' => 'synthetic/id.txt', 'file_name' => 'id.txt', 'file_size' => 2]);
        Storage::disk('school_attachments')->put($a->file_path, 'identity-bytes');
        $url = '/api/v1/attachments/'.$a->id.'/download';
        $this->withToken($this->nativeToken($s['A']['manager']))->get($url)->assertOk();
        $this->withToken($this->nativeToken($s['A']['staff']))->getJson($url)->assertForbidden();
        $this->withToken($this->nativeToken($s['B']['manager']))->getJson($url)->assertNotFound();
        $ticket = $this->ticketFile($s['A']);
        $this->actingAs($s['A']['staff'])->deleteJson(route('school.attachments.destroy', $ticket))->assertForbidden();
        $this->assertFalse($ticket->fresh()->trashed());
    }
}
