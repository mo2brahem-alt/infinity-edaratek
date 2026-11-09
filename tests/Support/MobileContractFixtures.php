<?php

namespace Tests\Support;

use App\Models\{Attachment, SchoolClassroom, SchoolLeaveType, SchoolStage, SchoolStudent, SchoolStudentLeaveAttachment, SchoolStudentLeaveRequest, TicketMessage, User};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait MobileContractFixtures
{
    use MobileSchoolFixtures;

    protected function bearer(User $user): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->nativeToken($user));
    }

    protected function credentials(User $user, array $extra = []): array
    {
        return array_merge(['email' => $user->email, 'password' => 'password', 'device_uuid' => (string) Str::uuid(), 'device_name' => 'Synthetic device'], $extra);
    }

    protected function contractFile(array $school, ?\App\Models\Subtask $task = null, array $extra = []): Attachment
    {
        $task ??= $this->task($school);
        $message = TicketMessage::create(['subtask_id' => $task->id, 'user_id' => $school['staff']->id, 'message' => 'Hidden transcript', 'message_type' => 'reply']);
        $file = Attachment::create(array_merge(['school_id' => $school['school']->id, 'ticket_message_id' => $message->id,
            'module' => 'tickets', 'attachable_type' => TicketMessage::class, 'attachable_id' => $message->id,
            'uploaded_by' => $school['staff']->id, 'file_name' => 'إثبات.txt', 'file_path' => 'synthetic/'.Str::uuid().'.txt',
            'disk' => 'school_attachments', 'file_size' => 15, 'mime_type' => 'text/plain', 'is_private' => true], $extra));
        Storage::disk('school_attachments')->put($file->file_path, 'synthetic-bytes');
        return $file;
    }

    protected function contractLeave(array $school): array
    {
        $stage = SchoolStage::create(['school_id' => $school['school']->id, 'name' => 'Synthetic', 'is_active' => true]);
        $class = SchoolClassroom::create(['school_id' => $school['school']->id, 'school_stage_id' => $stage->id, 'name' => 'A', 'is_active' => true]);
        $student = SchoolStudent::create(['school_id' => $school['school']->id, 'school_classroom_id' => $class->id, 'full_name' => 'Synthetic', 'student_code' => (string) Str::uuid(), 'is_active' => true]);
        $type = SchoolLeaveType::create(['school_id' => $school['school']->id, 'name' => 'Synthetic', 'is_active' => true, 'requires_attachment' => false]);
        $leave = SchoolStudentLeaveRequest::create(['school_id' => $school['school']->id, 'school_student_id' => $student->id,
            'school_leave_type_id' => $type->id, 'source' => SchoolStudentLeaveRequest::SOURCE_PRE_APPROVED, 'status' => SchoolStudentLeaveRequest::STATUS_PENDING,
            'start_date' => '2026-11-09', 'end_date' => '2026-11-09', 'created_by' => $school['manager']->id]);
        $file = SchoolStudentLeaveAttachment::create(['school_id' => $school['school']->id, 'school_student_leave_request_id' => $leave->id,
            'uploaded_by' => $school['manager']->id, 'file_name' => 'leave.txt', 'file_path' => 'synthetic/'.Str::uuid().'.txt', 'mime_type' => 'text/plain', 'file_size' => 15]);
        Storage::disk('local')->put($file->file_path, 'synthetic-bytes');
        return [$leave, $file];
    }

    protected function exactKeys(array $expected, array $actual): void
    {
        sort($expected); $keys = array_keys($actual); sort($keys); $this->assertSame($expected, $keys);
    }

    protected function assertSafeError($response, int $status, string $code): void
    {
        $response->assertStatus($status)->assertJsonPath('code', $code)->assertHeader('Content-Type', 'application/json');
        $this->assertTrue(Str::isUuid($response->json('request_id')));
        $this->assertIsString($response->json('message'));
        foreach (['data', 'trace', 'exception', 'file', 'token', 'password'] as $field) { $response->assertJsonMissingPath($field); }
    }
}
