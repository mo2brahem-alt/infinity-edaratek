<?php

namespace Tests\Feature;

use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolClassroom;
use App\Models\SchoolLeaveType;
use App\Models\SchoolStage;
use App\Models\SchoolStudent;
use App\Models\SchoolStudentAttendance;
use App\Models\SchoolStudentLeaveAttachment;
use App\Models\SchoolStudentLeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentLeaveManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_and_approve_preapproved_leave_and_absence_is_saved_as_leave(): void
    {
        Carbon::setTestNow('2026-02-22 08:00:00');
        $this->seedBaseRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-960001');
        [$stage, $classroom, $student] = $this->createStudentStructure((int) $manager->school_id, 'ST-960001');
        $leaveType = $this->createLeaveType((int) $manager->school_id, 'Annual Leave', false);

        $storeResponse = $this->actingAs($manager)->postJson(route('api.school.leaves.store'), [
            'school_student_id' => $student->id,
            'school_leave_type_id' => $leaveType->id,
            'source' => SchoolStudentLeaveRequest::SOURCE_PRE_APPROVED,
            'start_date' => '2026-02-22',
            'end_date' => '2026-02-24',
            'reason' => 'Travel permit',
        ]);

        $storeResponse
            ->assertCreated()
            ->assertJsonPath('data.status', SchoolStudentLeaveRequest::STATUS_PENDING);

        $leaveId = (int) $storeResponse->json('data.id');

        $approveResponse = $this->actingAs($manager)->postJson(route('api.school.leaves.approve', $leaveId), []);
        $approveResponse
            ->assertOk()
            ->assertJsonPath('data.status', SchoolStudentLeaveRequest::STATUS_APPROVED);

        $this
            ->from(route('school.student_attendance.index'))
            ->actingAs($manager)
            ->post(route('school.student_attendance.records.upsert'), [
                'attendance_date' => '2026-02-23',
                'school_stage_id' => $stage->id,
                'school_classroom_id' => $classroom->id,
                'records' => [
                    [
                        'school_student_id' => $student->id,
                        'status' => SchoolStudentAttendance::STATUS_ABSENT,
                    ],
                ],
            ])
            ->assertStatus(302);

        $this->assertDatabaseHas('school_student_attendances', [
            'school_id' => (int) $manager->school_id,
            'school_student_id' => $student->id,
            'attendance_date' => '2026-02-23',
            'status' => SchoolStudentAttendance::STATUS_LEAVE,
            'school_student_leave_request_id' => $leaveId,
        ]);
    }

    public function test_approving_retroactive_leave_converts_absence_and_records_audit_and_history(): void
    {
        Carbon::setTestNow('2026-02-22 08:00:00');
        $this->seedBaseRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-960002');
        [$stage, $classroom, $student] = $this->createStudentStructure((int) $manager->school_id, 'ST-960002');
        $leaveType = $this->createLeaveType((int) $manager->school_id, 'Medical Leave', true);

        $attendance = SchoolStudentAttendance::query()->create([
            'school_id' => (int) $manager->school_id,
            'school_student_id' => $student->id,
            'school_classroom_id' => $classroom->id,
            'attendance_date' => '2026-01-30',
            'status' => SchoolStudentAttendance::STATUS_ABSENT,
            'recorded_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $storeResponse = $this->actingAs($manager)->postJson(route('api.school.leaves.store'), [
            'school_student_id' => $student->id,
            'school_leave_type_id' => $leaveType->id,
            'source' => SchoolStudentLeaveRequest::SOURCE_RETROACTIVE,
            'start_date' => '2026-01-30',
            'end_date' => '2026-01-30',
            'reason' => 'Medical certificate delivered later',
        ]);

        $leaveId = (int) $storeResponse->json('data.id');

        $this->actingAs($manager)
            ->postJson(route('api.school.leaves.attachments.store', $leaveId), [
                'file' => 'medical-proof.pdf',
            ])
            ->assertStatus(422);

        $this->actingAs($manager)
            ->postJson(route('api.school.leaves.approve', $leaveId), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('attachments');

        $this->assertDatabaseHas('school_student_attendances', [
            'id' => $attendance->id,
            'status' => SchoolStudentAttendance::STATUS_ABSENT,
        ]);

        // Make attachment optional for this leave type then approve.
        $leaveType->update(['requires_attachment' => false]);

        $this->actingAs($manager)
            ->postJson(route('api.school.leaves.approve', $leaveId), [])
            ->assertOk()
            ->assertJsonPath('data.status', SchoolStudentLeaveRequest::STATUS_APPROVED);

        $this->assertDatabaseHas('school_student_attendances', [
            'id' => $attendance->id,
            'status' => SchoolStudentAttendance::STATUS_LEAVE,
            'school_student_leave_request_id' => $leaveId,
        ]);

        $this->assertDatabaseHas('status_history', [
            'entity_type' => 'school_student_attendance',
            'entity_id' => $attendance->id,
            'from_status' => SchoolStudentAttendance::STATUS_ABSENT,
            'to_status' => SchoolStudentAttendance::STATUS_LEAVE,
            'changed_by' => $manager->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student_leave.retroactive_attendance_converted',
            'entity_type' => 'school_student_attendance',
            'entity_id' => $attendance->id,
            'user_id' => $manager->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student_leave.approved',
            'entity_type' => 'school_student_leave_request',
            'entity_id' => $leaveId,
            'user_id' => $manager->id,
        ]);
    }

    public function test_leave_api_is_tenant_scoped_for_listing_and_actions(): void
    {
        Carbon::setTestNow('2026-02-22 08:00:00');
        $this->seedBaseRoles();

        $managerA = $this->createSchoolManagerWithSchool('SCH-960003');
        $managerB = $this->createSchoolManagerWithSchool('SCH-960004');

        [, , $studentA] = $this->createStudentStructure((int) $managerA->school_id, 'ST-960003');
        [, , $studentB] = $this->createStudentStructure((int) $managerB->school_id, 'ST-960004');

        $leaveTypeA = $this->createLeaveType((int) $managerA->school_id, 'Annual Leave A', false);
        $leaveTypeB = $this->createLeaveType((int) $managerB->school_id, 'Annual Leave B', false);

        $leaveA = SchoolStudentLeaveRequest::query()->create([
            'school_id' => (int) $managerA->school_id,
            'school_student_id' => $studentA->id,
            'school_leave_type_id' => $leaveTypeA->id,
            'source' => SchoolStudentLeaveRequest::SOURCE_PRE_APPROVED,
            'status' => SchoolStudentLeaveRequest::STATUS_PENDING,
            'start_date' => '2026-02-22',
            'end_date' => '2026-02-22',
            'created_by' => $managerA->id,
            'updated_by' => $managerA->id,
        ]);

        $leaveB = SchoolStudentLeaveRequest::query()->create([
            'school_id' => (int) $managerB->school_id,
            'school_student_id' => $studentB->id,
            'school_leave_type_id' => $leaveTypeB->id,
            'source' => SchoolStudentLeaveRequest::SOURCE_PRE_APPROVED,
            'status' => SchoolStudentLeaveRequest::STATUS_PENDING,
            'start_date' => '2026-02-22',
            'end_date' => '2026-02-22',
            'created_by' => $managerB->id,
            'updated_by' => $managerB->id,
        ]);

        $response = $this->actingAs($managerA)
            ->getJson(route('api.school.leaves.index'))
            ->assertOk();

        $returnedIds = collect($response->json('data'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $this->assertContains((int) $leaveA->id, $returnedIds);
        $this->assertNotContains((int) $leaveB->id, $returnedIds);

        $this->actingAs($managerA)
            ->postJson(route('api.school.leaves.approve', $leaveB->id), [])
            ->assertForbidden();
    }

    public function test_staff_leave_access_depends_on_permission_flag(): void
    {
        Carbon::setTestNow('2026-02-22 08:00:00');
        $this->seedBaseRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-960005');

        $staffWithoutPermission = User::factory()->create([
            'role' => 'staff',
            'school_id' => (int) $manager->school_id,
            'is_active' => true,
            'can_manage_student_leaves' => false,
        ]);
        $staffWithoutPermission->assignRole('staff');

        $staffWithPermission = User::factory()->create([
            'role' => 'staff',
            'school_id' => (int) $manager->school_id,
            'is_active' => true,
            'can_manage_student_leaves' => true,
        ]);
        $staffWithPermission->assignRole('staff');

        $this->actingAs($staffWithoutPermission)
            ->get(route('school.student_leaves.index'))
            ->assertForbidden();

        $this->actingAs($staffWithPermission)
            ->get(route('school.student_leaves.index'))
            ->assertOk();
    }

    public function test_manager_is_redirected_when_student_leaves_feature_is_disabled(): void
    {
        Carbon::setTestNow('2026-02-22 08:00:00');
        $this->seedBaseRoles();
        config()->set('features.student_leaves.enabled', false);

        $manager = $this->createSchoolManagerWithSchool('SCH-960007');

        $this->actingAs($manager)
            ->get(route('school.student_leaves.index'))
            ->assertRedirect(route('manager.dashboard'));

        $this->actingAs($manager)
            ->getJson(route('api.school.leaves.index'))
            ->assertNotFound();
    }

    public function test_overlapping_approved_leaves_are_rejected(): void
    {
        Carbon::setTestNow('2026-02-22 08:00:00');
        $this->seedBaseRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-960006');
        [, , $student] = $this->createStudentStructure((int) $manager->school_id, 'ST-960006');
        $leaveType = $this->createLeaveType((int) $manager->school_id, 'Annual Leave', false);

        $leaveOne = SchoolStudentLeaveRequest::query()->create([
            'school_id' => (int) $manager->school_id,
            'school_student_id' => $student->id,
            'school_leave_type_id' => $leaveType->id,
            'source' => SchoolStudentLeaveRequest::SOURCE_PRE_APPROVED,
            'status' => SchoolStudentLeaveRequest::STATUS_PENDING,
            'start_date' => '2026-02-22',
            'end_date' => '2026-02-24',
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->postJson(route('api.school.leaves.approve', $leaveOne->id), [])
            ->assertOk();

        $storeResponse = $this->actingAs($manager)->postJson(route('api.school.leaves.store'), [
            'school_student_id' => $student->id,
            'school_leave_type_id' => $leaveType->id,
            'source' => SchoolStudentLeaveRequest::SOURCE_PRE_APPROVED,
            'start_date' => '2026-02-23',
            'end_date' => '2026-02-25',
            'reason' => 'Overlap check',
        ]);

        $storeResponse->assertCreated();
        $leaveTwoId = (int) $storeResponse->json('data.id');

        $this->actingAs($manager)
            ->postJson(route('api.school.leaves.approve', $leaveTwoId), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('start_date');
    }

    public function test_leave_attachment_upload_is_tenant_scoped_and_download_url_is_exposed_without_raw_storage_path(): void
    {
        Carbon::setTestNow('2026-02-22 08:00:00');
        Storage::fake('local');
        Storage::fake('public');
        $this->seedBaseRoles();

        $managerA = $this->createSchoolManagerWithSchool('SCH-960008');
        $managerB = $this->createSchoolManagerWithSchool('SCH-960009');

        [, , $studentA] = $this->createStudentStructure((int) $managerA->school_id, 'ST-960008');
        [, , $studentB] = $this->createStudentStructure((int) $managerB->school_id, 'ST-960009');

        $leaveTypeA = $this->createLeaveType((int) $managerA->school_id, 'Annual Leave A', false);
        $leaveTypeB = $this->createLeaveType((int) $managerB->school_id, 'Annual Leave B', false);

        $leaveA = SchoolStudentLeaveRequest::query()->create([
            'school_id' => (int) $managerA->school_id,
            'school_student_id' => $studentA->id,
            'school_leave_type_id' => $leaveTypeA->id,
            'source' => SchoolStudentLeaveRequest::SOURCE_PRE_APPROVED,
            'status' => SchoolStudentLeaveRequest::STATUS_PENDING,
            'start_date' => '2026-02-22',
            'end_date' => '2026-02-22',
            'created_by' => $managerA->id,
            'updated_by' => $managerA->id,
        ]);

        $leaveB = SchoolStudentLeaveRequest::query()->create([
            'school_id' => (int) $managerB->school_id,
            'school_student_id' => $studentB->id,
            'school_leave_type_id' => $leaveTypeB->id,
            'source' => SchoolStudentLeaveRequest::SOURCE_PRE_APPROVED,
            'status' => SchoolStudentLeaveRequest::STATUS_PENDING,
            'start_date' => '2026-02-22',
            'end_date' => '2026-02-22',
            'created_by' => $managerB->id,
            'updated_by' => $managerB->id,
        ]);

        $this->actingAs($managerA)
            ->postJson(route('api.school.leaves.attachments.store', $leaveB->id), [
                'file' => UploadedFile::fake()->create('cross-tenant.pdf', 20, 'application/pdf'),
            ])
            ->assertForbidden();

        $uploadResponse = $this->actingAs($managerA)
            ->postJson(route('api.school.leaves.attachments.store', $leaveA->id), [
                'file' => UploadedFile::fake()->create('leave-proof.pdf', 20, 'application/pdf'),
            ])
            ->assertCreated();

        $filePathAlias = (string) $uploadResponse->json('data.file_path');
        $downloadUrl = (string) $uploadResponse->json('data.download_url');
        $this->assertSame($downloadUrl, $filePathAlias);
        $this->assertStringNotContainsString('schools/', $filePathAlias);
        $this->assertStringContainsString((string) $leaveA->id, $downloadUrl);

        $attachment = SchoolStudentLeaveAttachment::query()
            ->where('school_id', (int) $managerA->school_id)
            ->where('school_student_leave_request_id', $leaveA->id)
            ->where('file_name', 'leave-proof.pdf')
            ->firstOrFail();

        $this->assertStringContainsString("schools/{$managerA->school_id}/student-leaves/{$leaveA->id}/attachments/", (string) $attachment->file_path);
        Storage::disk('local')->assertExists((string) $attachment->file_path);
        Storage::disk('public')->assertMissing((string) $attachment->file_path);

        $this->actingAs($managerA)
            ->get($downloadUrl)
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($managerB)
            ->get($downloadUrl)
            ->assertForbidden();

        $this->assertDatabaseHas('school_student_leave_attachments', [
            'school_id' => (int) $managerA->school_id,
            'school_student_leave_request_id' => $leaveA->id,
            'file_name' => 'leave-proof.pdf',
        ]);

        $this->assertDatabaseMissing('school_student_leave_attachments', [
            'school_student_leave_request_id' => $leaveB->id,
            'file_name' => 'cross-tenant.pdf',
        ]);
    }

    public function test_leave_attachment_download_supports_legacy_public_storage_for_backward_compatibility(): void
    {
        Carbon::setTestNow('2026-02-22 08:00:00');
        Storage::fake('local');
        Storage::fake('public');
        $this->seedBaseRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-960011');
        [, , $student] = $this->createStudentStructure((int) $manager->school_id, 'ST-960011');
        $leaveType = $this->createLeaveType((int) $manager->school_id, 'Medical Leave', true);

        $leave = SchoolStudentLeaveRequest::query()->create([
            'school_id' => (int) $manager->school_id,
            'school_student_id' => $student->id,
            'school_leave_type_id' => $leaveType->id,
            'source' => SchoolStudentLeaveRequest::SOURCE_PRE_APPROVED,
            'status' => SchoolStudentLeaveRequest::STATUS_PENDING,
            'start_date' => '2026-02-22',
            'end_date' => '2026-02-22',
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $legacyPath = sprintf(
            'schools/%d/student-leaves/%d/attachments/legacy-proof.pdf',
            (int) $manager->school_id,
            (int) $leave->id
        );
        Storage::disk('public')->put($legacyPath, 'legacy-binary-content');

        $attachment = SchoolStudentLeaveAttachment::query()->create([
            'school_id' => (int) $manager->school_id,
            'school_student_leave_request_id' => (int) $leave->id,
            'file_name' => 'legacy-proof.pdf',
            'file_path' => $legacyPath,
            'mime_type' => 'application/pdf',
            'file_size' => 21,
            'uploaded_by' => $manager->id,
            'uploaded_at' => now(),
        ]);

        $this->actingAs($manager)
            ->get(route('api.school.leaves.attachments.download', [
                'schoolStudentLeaveRequest' => $leave->id,
                'schoolStudentLeaveAttachment' => $attachment->id,
            ]))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_leave_attachment_upload_rejects_disallowed_mime_types_by_default(): void
    {
        Carbon::setTestNow('2026-02-22 08:00:00');
        Storage::fake('local');
        Storage::fake('public');
        $this->seedBaseRoles();

        config()->set('features.uploads.strict_validation_enabled', false);
        config()->set('features.uploads.strict_student_leave_attachment_validation', true);

        $manager = $this->createSchoolManagerWithSchool('SCH-960010');
        [, , $student] = $this->createStudentStructure((int) $manager->school_id, 'ST-960010');
        $leaveType = $this->createLeaveType((int) $manager->school_id, 'Medical Leave', true);

        $leave = SchoolStudentLeaveRequest::query()->create([
            'school_id' => (int) $manager->school_id,
            'school_student_id' => $student->id,
            'school_leave_type_id' => $leaveType->id,
            'source' => SchoolStudentLeaveRequest::SOURCE_PRE_APPROVED,
            'status' => SchoolStudentLeaveRequest::STATUS_PENDING,
            'start_date' => '2026-02-22',
            'end_date' => '2026-02-22',
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->postJson(route('api.school.leaves.attachments.store', $leave->id), [
                'file' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        config()->set('features.uploads.strict_student_leave_attachment_validation', false);

        $this->actingAs($manager)
            ->postJson(route('api.school.leaves.attachments.store', $leave->id), [
                'file' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
            ])
            ->assertCreated();
    }

    public function test_leave_listing_supports_grade_filter_within_stage_scope(): void
    {
        Carbon::setTestNow('2026-02-22 08:00:00');
        $this->seedBaseRoles();

        $manager = $this->createSchoolManagerWithSchool('SCH-960012');
        $schoolId = (int) $manager->school_id;

        $stage = SchoolStage::query()->create([
            'school_id' => $schoolId,
            'name' => 'Primary',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $gradeOneClassroom = SchoolClassroom::query()->create([
            'school_id' => $schoolId,
            'school_stage_id' => $stage->id,
            'grade_name' => 'الصف الأول',
            'name' => 'أ',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $gradeTwoClassroom = SchoolClassroom::query()->create([
            'school_id' => $schoolId,
            'school_stage_id' => $stage->id,
            'grade_name' => 'الصف الثاني',
            'name' => 'أ',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $studentOne = SchoolStudent::query()->create([
            'school_id' => $schoolId,
            'school_classroom_id' => $gradeOneClassroom->id,
            'full_name' => 'Grade One Student',
            'student_code' => 'ST-960012-1',
            'is_active' => true,
        ]);

        $studentTwo = SchoolStudent::query()->create([
            'school_id' => $schoolId,
            'school_classroom_id' => $gradeTwoClassroom->id,
            'full_name' => 'Grade Two Student',
            'student_code' => 'ST-960012-2',
            'is_active' => true,
        ]);

        $leaveType = $this->createLeaveType($schoolId, 'Annual Leave', false);

        $gradeOneLeave = SchoolStudentLeaveRequest::query()->create([
            'school_id' => $schoolId,
            'school_student_id' => $studentOne->id,
            'school_leave_type_id' => $leaveType->id,
            'source' => SchoolStudentLeaveRequest::SOURCE_PRE_APPROVED,
            'status' => SchoolStudentLeaveRequest::STATUS_PENDING,
            'start_date' => '2026-02-22',
            'end_date' => '2026-02-22',
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $gradeTwoLeave = SchoolStudentLeaveRequest::query()->create([
            'school_id' => $schoolId,
            'school_student_id' => $studentTwo->id,
            'school_leave_type_id' => $leaveType->id,
            'source' => SchoolStudentLeaveRequest::SOURCE_PRE_APPROVED,
            'status' => SchoolStudentLeaveRequest::STATUS_PENDING,
            'start_date' => '2026-02-22',
            'end_date' => '2026-02-22',
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $response = $this->actingAs($manager)
            ->getJson(route('api.school.leaves.index', [
                'school_stage_id' => $stage->id,
                'classroom_grade_name' => 'الصف الأول',
            ]))
            ->assertOk();

        $returnedIds = collect($response->json('data'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $this->assertCount(1, $returnedIds);
        $this->assertSame([(int) $gradeOneLeave->id], $returnedIds);
        $this->assertNotContains((int) $gradeTwoLeave->id, $returnedIds);
    }

    private function seedBaseRoles(): void
    {
        Role::query()->firstOrCreate([
            'name' => 'school_manager',
            'guard_name' => 'web',
        ]);

        Role::query()->firstOrCreate([
            'name' => 'staff',
            'guard_name' => 'web',
        ]);
    }

    private function createSchoolManagerWithSchool(string $schoolCode): User
    {
        $digits = preg_replace('/\D+/', '', $schoolCode) ?: '0';
        $schoolPhone = '05' . str_pad(substr($digits, -8), 8, '0', STR_PAD_LEFT);

        $region = EducationalDirectorate::query()->create([
            'name' => 'Region ' . $schoolCode,
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create([
            'role' => 'school_manager',
            'is_active' => true,
        ]);
        $manager->assignRole('school_manager');

        $school = School::query()->create([
            'directorate_id' => $region->id,
            'name' => 'School ' . $schoolCode,
            'school_id' => $schoolCode,
            'phone' => $schoolPhone,
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        return $manager->fresh();
    }

    /**
     * @return array{0: SchoolStage, 1: SchoolClassroom, 2: SchoolStudent}
     */
    private function createStudentStructure(int $schoolId, string $studentCode): array
    {
        $stage = SchoolStage::query()->create([
            'school_id' => $schoolId,
            'name' => 'Primary ' . $studentCode,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::query()->create([
            'school_id' => $schoolId,
            'school_stage_id' => $stage->id,
            'name' => 'Class ' . $studentCode,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $student = SchoolStudent::query()->create([
            'school_id' => $schoolId,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Student ' . $studentCode,
            'student_code' => $studentCode,
            'is_active' => true,
        ]);

        return [$stage, $classroom, $student];
    }

    private function createLeaveType(int $schoolId, string $name, bool $requiresAttachment): SchoolLeaveType
    {
        return SchoolLeaveType::query()->create([
            'school_id' => $schoolId,
            'name' => $name,
            'requires_attachment' => $requiresAttachment,
            'is_active' => true,
        ]);
    }
}

