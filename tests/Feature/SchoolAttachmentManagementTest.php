<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolClassroom;
use App\Models\SchoolCourseOffering;
use App\Models\SchoolExam;
use App\Models\SchoolStage;
use App\Models\SchoolStudent;
use App\Models\SchoolSubject;
use App\Models\SchoolSubjectTeacherAssignment;
use App\Models\SchoolTeachingAssignment;
use App\Models\SchoolTerm;
use App\Models\SchoolTimetableVersion;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolAttachmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_exam_with_private_attachment_inside_same_school(): void
    {
        Storage::fake('school_attachments');

        $context = $this->createExamContext('SCH-ATT001');

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['manager'])
            ->post(route('school.exams.store'), $this->examPayload($context, [
                'attachments' => [
                    UploadedFile::fake()->create('exam-guide.pdf', 128, 'application/pdf'),
                ],
            ]));

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasNoErrors();

        $exam = SchoolExam::query()->latest('id')->firstOrFail();
        $attachment = Attachment::query()->where('attachable_type', SchoolExam::class)->latest('id')->firstOrFail();

        $this->assertSame($context['school']->id, (int) $attachment->school_id);
        $this->assertSame((int) $exam->id, (int) $attachment->attachable_id);
        $this->assertSame('exams', (string) $attachment->module);
        $this->assertSame('exam_attachment', (string) $attachment->action_type);
        $this->assertTrue((bool) $attachment->is_private);
        $this->assertSame((int) $exam->id, (int) data_get($attachment->metadata, 'exam_id'));
        $this->assertSame((int) $context['manager']->id, (int) $attachment->uploaded_by);
        Storage::disk('school_attachments')->assertExists((string) $attachment->file_path);
    }

    public function test_authorized_manager_can_download_and_delete_exam_attachment(): void
    {
        Storage::fake('school_attachments');

        $context = $this->createExamContext('SCH-ATT002');
        $attachment = $this->createExamAttachment($context);

        $download = $this->actingAs($context['manager'])
            ->get(route('school.attachments.download', $attachment));

        $download->assertOk();
        $download->assertHeader('content-disposition');

        $delete = $this->from(route('school.exams.index'))
            ->actingAs($context['manager'])
            ->delete(route('school.attachments.destroy', $attachment));

        $delete->assertRedirect(route('school.exams.index', absolute: false));
        Storage::disk('school_attachments')->assertMissing((string) $attachment->file_path);
        $this->assertSoftDeleted('attachments', [
            'id' => $attachment->id,
        ]);
    }

    public function test_same_school_staff_without_exam_permission_cannot_download_exam_attachment(): void
    {
        Storage::fake('school_attachments');

        $context = $this->createExamContext('SCH-ATT003');
        $attachment = $this->createExamAttachment($context);

        $department = Department::query()->create([
            'name' => 'قسم إداري - مرفقات',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'school_id' => $context['school']->id,
            'department_id' => $department->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('school.attachments.download', $attachment))
            ->assertForbidden();
    }

    public function test_other_school_manager_cannot_access_exam_attachment_or_attach_to_foreign_exam(): void
    {
        Storage::fake('school_attachments');

        $contextA = $this->createExamContext('SCH-ATT004A');
        $contextB = $this->createExamContext('SCH-ATT004B');
        $attachment = $this->createExamAttachment($contextA);

        $this->actingAs($contextB['manager'])
            ->get(route('school.attachments.download', $attachment))
            ->assertNotFound();

        $this->from(route('school.exams.index'))
            ->actingAs($contextB['manager'])
            ->put(route('school.exams.update', $contextA['exam']), [
                'title' => 'اختبار خارج المدرسة',
                'attachments' => [
                    UploadedFile::fake()->create('forbidden.pdf', 32, 'application/pdf'),
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('attachments', 1);
    }

    public function test_disallowed_file_type_is_rejected_for_exam_attachment_uploads(): void
    {
        Storage::fake('school_attachments');
        config()->set('features.uploads.strict_school_attachment_validation', true);
        config()->set('features.uploads.school_attachment_mime_types', ['application/pdf']);

        $context = $this->createExamContext('SCH-ATT005');

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['manager'])
            ->post(route('school.exams.store'), $this->examPayload($context, [
                'attachments' => [
                    UploadedFile::fake()->create('malicious.html', 8, 'text/html'),
                ],
            ]));

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasErrors('attachments.0');
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_manager_can_create_timetable_version_with_attachment(): void
    {
        Storage::fake('school_attachments');

        $context = $this->createPlanningContext('SCH-ATT006');

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['manager'])
            ->post(route('school.academic_planning.versions.store'), [
                'school_term_id' => $context['term']->id,
                'name' => 'نسخة توزيع شهر سبتمبر',
                'attachments' => [
                    UploadedFile::fake()->create('timetable.pdf', 96, 'application/pdf'),
                ],
            ]);

        $response->assertRedirect();

        $version = SchoolTimetableVersion::query()->firstOrFail();
        $attachment = Attachment::query()->where('attachable_type', SchoolTimetableVersion::class)->firstOrFail();

        $this->assertSame((int) $context['school']->id, (int) $attachment->school_id);
        $this->assertSame((int) $version->id, (int) $attachment->attachable_id);
        $this->assertSame('schedules', (string) $attachment->module);
        $this->assertSame('schedule_document', (string) $attachment->action_type);
        $this->assertSame((int) $version->id, (int) data_get($attachment->metadata, 'school_timetable_version_id'));
        Storage::disk('school_attachments')->assertExists((string) $attachment->file_path);
    }

    public function test_manager_can_attach_teacher_preparation_to_course_offering_assignment(): void
    {
        Storage::fake('school_attachments');

        $context = $this->createExamContext('SCH-ATT-TP1');

        $response = $this->from(route('school.academic_planning.index'))
            ->actingAs($context['manager'])
            ->post(route('school.academic_planning.offerings.assignment.sync', $context['courseOffering']->id), [
                'teacher_user_id' => $context['teacher']->id,
                'school_classroom_ids' => [$context['classroom']->id],
                'can_create_exam' => true,
                'can_update_exam' => true,
                'can_delete_exam' => true,
                'can_approve_exam' => false,
                'can_enter_exam_scores' => true,
                'can_edit_exam_scores' => true,
                'can_use_question_bank' => true,
                'attachments' => [
                    UploadedFile::fake()->create('lesson-plan.pdf', 72, 'application/pdf'),
                ],
            ]);

        $response->assertRedirect(route('school.academic_planning.index', absolute: false));
        $response->assertSessionHasNoErrors();

        $assignment = SchoolTeachingAssignment::query()
            ->where('school_id', $context['school']->id)
            ->where('school_course_offering_id', $context['courseOffering']->id)
            ->firstOrFail();

        $attachment = Attachment::query()
            ->where('attachable_type', SchoolTeachingAssignment::class)
            ->where('attachable_id', $assignment->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame((int) $context['school']->id, (int) $attachment->school_id);
        $this->assertSame('teacher_preparations', (string) $attachment->module);
        $this->assertSame('course_preparation_attachment', (string) $attachment->action_type);
        $this->assertSame((int) $assignment->id, (int) $attachment->attachable_id);
        $this->assertSame((int) $context['courseOffering']->id, (int) data_get($attachment->metadata, 'school_course_offering_id'));
        $this->assertSame((int) $context['teacher']->id, (int) data_get($attachment->metadata, 'teacher_user_id'));
        Storage::disk('school_attachments')->assertExists((string) $attachment->file_path);

        $this->actingAs($context['manager'])
            ->get(route('school.attachments.download', $attachment))
            ->assertOk();
    }

    public function test_other_school_manager_cannot_access_teacher_preparation_attachment(): void
    {
        Storage::fake('school_attachments');

        $contextA = $this->createExamContext('SCH-ATT-TP2A');
        $contextB = $this->createExamContext('SCH-ATT-TP2B');

        $this->from(route('school.academic_planning.index'))
            ->actingAs($contextA['manager'])
            ->post(route('school.academic_planning.offerings.assignment.sync', $contextA['courseOffering']->id), [
                'teacher_user_id' => $contextA['teacher']->id,
                'school_classroom_ids' => [$contextA['classroom']->id],
                'attachments' => [
                    UploadedFile::fake()->create('teacher-preparation.pdf', 48, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $attachment = Attachment::query()
            ->where('attachable_type', SchoolTeachingAssignment::class)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($contextB['manager'])
            ->get(route('school.attachments.download', $attachment))
            ->assertNotFound();
    }

    public function test_syncing_course_offering_assignment_preserves_the_same_assignment_record_for_attachments(): void
    {
        Storage::fake('school_attachments');

        $context = $this->createExamContext('SCH-ATT-TP3');
        $originalAssignmentId = (int) $context['teachingAssignment']->id;

        $this->from(route('school.academic_planning.index'))
            ->actingAs($context['manager'])
            ->post(route('school.academic_planning.offerings.assignment.sync', $context['courseOffering']->id), [
                'teacher_user_id' => $context['teacher']->id,
                'school_classroom_ids' => [$context['classroom']->id],
                'can_create_exam' => true,
                'can_update_exam' => true,
                'can_delete_exam' => true,
                'can_approve_exam' => true,
                'can_enter_exam_scores' => true,
                'can_edit_exam_scores' => true,
                'can_use_question_bank' => true,
                'attachments' => [
                    UploadedFile::fake()->create('preparation-v1.pdf', 60, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $assignmentAfterFirstSync = SchoolTeachingAssignment::query()
            ->where('school_id', $context['school']->id)
            ->where('school_course_offering_id', $context['courseOffering']->id)
            ->firstOrFail();

        $this->assertSame($originalAssignmentId, (int) $assignmentAfterFirstSync->id);

        $this->from(route('school.academic_planning.index'))
            ->actingAs($context['manager'])
            ->post(route('school.academic_planning.offerings.assignment.sync', $context['courseOffering']->id), [
                'teacher_user_id' => $context['teacher']->id,
                'school_classroom_ids' => [$context['classroom']->id],
                'can_create_exam' => true,
                'can_update_exam' => true,
                'can_delete_exam' => true,
                'can_approve_exam' => false,
                'can_enter_exam_scores' => true,
                'can_edit_exam_scores' => true,
                'can_use_question_bank' => true,
            ])
            ->assertRedirect(route('school.academic_planning.index', absolute: false));

        $assignmentAfterSecondSync = SchoolTeachingAssignment::query()
            ->where('school_id', $context['school']->id)
            ->where('school_course_offering_id', $context['courseOffering']->id)
            ->firstOrFail();

        $this->assertSame($originalAssignmentId, (int) $assignmentAfterSecondSync->id);
        $this->assertDatabaseCount('attachments', 1);
    }

    public function test_manager_can_create_student_with_private_attachment_inside_same_school(): void
    {
        Storage::fake('school_attachments');

        $context = $this->createManagerContext('SCH-ATT-STU1');
        $stage = SchoolStage::query()->create([
            'school_id' => $context['school']->id,
            'name' => 'Primary Stage',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $classroom = SchoolClassroom::query()->create([
            'school_id' => $context['school']->id,
            'school_stage_id' => $stage->id,
            'grade_name' => 'Grade 1',
            'name' => 'A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->from(route('school.student_structure.index'))
            ->actingAs($context['manager'])
            ->post(route('school.student_structure.students.store'), [
                'school_stage_id' => $stage->id,
                'classroom_grade_name' => 'Grade 1',
                'school_classroom_id' => $classroom->id,
                'full_name' => 'Student Attachment One',
                'student_code' => 'ST-ATT-001',
                'national_id' => '1234567890',
                'is_active' => true,
                'attachments' => [
                    UploadedFile::fake()->image('birth-certificate.jpg'),
                ],
            ]);

        $response->assertRedirect(route('school.student_structure.index', absolute: false));
        $response->assertSessionHasNoErrors();

        $student = SchoolStudent::query()->where('student_code', 'ST-ATT-001')->firstOrFail();
        $attachment = Attachment::query()->where('attachable_type', SchoolStudent::class)->latest('id')->firstOrFail();

        $this->assertSame((int) $context['school']->id, (int) $attachment->school_id);
        $this->assertSame((int) $student->id, (int) $attachment->attachable_id);
        $this->assertSame('student_records', (string) $attachment->module);
        $this->assertSame('student_document', (string) $attachment->action_type);
        $this->assertSame((int) $student->id, (int) data_get($attachment->metadata, 'student_id'));
        $this->assertSame((int) $classroom->id, (int) data_get($attachment->metadata, 'school_classroom_id'));
        Storage::disk('school_attachments')->assertExists((string) $attachment->file_path);

        $this->actingAs($context['manager'])
            ->get(route('school.attachments.download', $attachment))
            ->assertOk();
    }

    public function test_other_school_manager_cannot_access_student_attachment(): void
    {
        Storage::fake('school_attachments');

        $contextA = $this->createManagerContext('SCH-ATT-STU2A');
        $contextB = $this->createManagerContext('SCH-ATT-STU2B');

        $stage = SchoolStage::query()->create([
            'school_id' => $contextA['school']->id,
            'name' => 'Intermediate Stage',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $classroom = SchoolClassroom::query()->create([
            'school_id' => $contextA['school']->id,
            'school_stage_id' => $stage->id,
            'grade_name' => 'Grade 2',
            'name' => 'B',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $student = SchoolStudent::query()->create([
            'school_id' => $contextA['school']->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'Protected Student',
            'student_code' => 'ST-ATT-002',
            'is_active' => true,
        ]);

        $attachment = Attachment::query()->create([
            'school_id' => $contextA['school']->id,
            'attachable_type' => SchoolStudent::class,
            'attachable_id' => $student->id,
            'module' => 'student_records',
            'action_type' => 'student_document',
            'uploaded_by' => $contextA['manager']->id,
            'file_name' => 'protected.pdf',
            'stored_name' => 'protected.pdf',
            'disk' => 'school_attachments',
            'file_path' => $contextA['school']->id . '/student_records/2026/05/protected.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'file_size' => 1024,
            'is_private' => true,
            'metadata' => ['student_id' => $student->id],
        ]);

        Storage::disk('school_attachments')->put((string) $attachment->file_path, 'protected-content');

        $this->actingAs($contextB['manager'])
            ->get(route('school.attachments.download', $attachment))
            ->assertNotFound();
    }

    public function test_manager_can_create_school_user_with_private_attachment_inside_same_school(): void
    {
        Storage::fake('school_attachments');

        $this->seedSystemRoles();
        $context = $this->createManagerContext('SCH-ATT-USR1', true);
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $response = $this->actingAs($context['manager'])
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('api.school.users.store'), [
                'name' => 'Staff Attachment One',
                'email' => 'staff.attachment.one@example.com',
                'mobile' => '0500009151',
                'department_id' => $department->id,
                'department_role_id' => $departmentRole->id,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role_names' => ['teacher'],
                'attachments' => [
                    UploadedFile::fake()->image('national-id.jpg'),
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.email', 'staff.attachment.one@example.com');

        $user = User::query()->where('email', 'staff.attachment.one@example.com')->firstOrFail();
        $attachment = Attachment::query()
            ->where('attachable_type', User::class)
            ->where('attachable_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame((int) $context['school']->id, (int) $attachment->school_id);
        $this->assertSame('staff_documents', (string) $attachment->module);
        $this->assertSame('identity_document', (string) $attachment->action_type);
        $this->assertSame((int) $user->id, (int) data_get($attachment->metadata, 'user_id'));
        Storage::disk('school_attachments')->assertExists((string) $attachment->file_path);

        $this->actingAs($context['manager'])
            ->get(route('school.attachments.download', $attachment))
            ->assertOk();
    }

    public function test_other_school_manager_cannot_access_school_user_attachment(): void
    {
        Storage::fake('school_attachments');

        $this->seedSystemRoles();
        $contextA = $this->createManagerContext('SCH-ATT-USR2A', true);
        $contextB = $this->createManagerContext('SCH-ATT-USR2B', true);
        [$department, $departmentRole] = $this->createGlobalDepartmentAndRole();

        $staffUser = User::factory()->create([
            'name' => 'Protected Staff',
            'email' => 'protected.staff@example.com',
            'mobile' => '0500009152',
            'role' => 'staff',
            'school_id' => $contextA['school']->id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $staffUser->syncRoles(['staff']);

        $attachment = Attachment::query()->create([
            'school_id' => $contextA['school']->id,
            'attachable_type' => User::class,
            'attachable_id' => $staffUser->id,
            'module' => 'staff_documents',
            'action_type' => 'identity_document',
            'uploaded_by' => $contextA['manager']->id,
            'file_name' => 'identity.pdf',
            'stored_name' => 'identity.pdf',
            'disk' => 'school_attachments',
            'file_path' => $contextA['school']->id . '/staff_documents/2026/05/identity.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'file_size' => 2048,
            'is_private' => true,
            'metadata' => ['user_id' => $staffUser->id],
        ]);

        Storage::disk('school_attachments')->put((string) $attachment->file_path, 'protected-staff-content');

        $this->actingAs($contextB['manager'])
            ->get(route('school.attachments.download', $attachment))
            ->assertNotFound();
    }

    private function seedSystemRoles(): void
    {
        $roles = [
            ['name' => 'super_admin', 'is_system' => true, 'assignable_by_school_admin' => false],
            ['name' => 'school_manager', 'is_system' => true, 'assignable_by_school_admin' => false],
            ['name' => 'staff', 'is_system' => false, 'assignable_by_school_admin' => true],
            ['name' => 'teacher', 'is_system' => false, 'assignable_by_school_admin' => true],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['name' => $role['name'], 'guard_name' => 'web'],
                [
                    'is_system' => $role['is_system'],
                    'assignable_by_school_admin' => $role['assignable_by_school_admin'],
                ]
            );
        }
    }

    /**
     * @return array{0: Department, 1: DepartmentRole}
     */
    private function createGlobalDepartmentAndRole(): array
    {
        $department = Department::query()->create([
            'name' => 'Administrative Affairs',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::query()->create([
            'department_id' => $department->id,
            'name' => 'Registrar',
            'is_active' => true,
        ]);

        return [$department, $departmentRole];
    }

    private function createManagerContext(string $schoolCode, bool $withSubscription = false): array
    {
        $this->ensureBaseRoles();

        if ($withSubscription) {
            $this->seedSystemRoles();
        }

        $region = EducationalDirectorate::query()->create([
            'name' => 'Region ' . $schoolCode,
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create([
            'role' => 'school_manager',
            'is_active' => true,
        ]);
        $manager->assignRole('school_manager');

        $digits = preg_replace('/\D+/', '', $schoolCode) ?: '0';
        $schoolPhone = '05' . str_pad(substr($digits, -8), 8, '0', STR_PAD_LEFT);

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

        if ($withSubscription) {
            $plan = Plan::query()->create([
                'name' => 'Manager Plan ' . $schoolCode,
                'role_type' => Plan::ROLE_SCHOOL_MANAGER,
                'price' => 1000,
                'monthly_price' => 1000,
                'yearly_price' => 11000,
                'included_users_count' => 50,
                'extra_user_monthly_price' => 60,
                'billing_cycle' => Plan::BILLING_MONTHLY,
                'is_active' => true,
            ]);

            Subscription::query()->create([
                'user_id' => $manager->id,
                'plan_id' => $plan->id,
                'school_id' => $school->id,
                'status' => Subscription::STATUS_ACTIVE,
                'billing_cycle' => Plan::BILLING_YEARLY,
                'base_price' => 11000,
                'included_users_count' => 50,
                'extra_user_monthly_price' => 60,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addYear(),
            ]);
        }

        return [
            'school' => $school,
            'manager' => $manager->fresh(),
        ];
    }

    private function createPlanningContext(string $schoolCode): array
    {
        $this->ensureBaseRoles();

        $region = EducationalDirectorate::query()->create([
            'name' => 'منطقة ' . $schoolCode,
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create([
            'role' => 'school_manager',
        ]);
        $manager->assignRole('school_manager');

        $school = School::query()->create([
            'directorate_id' => $region->id,
            'name' => 'مدرسة ' . $schoolCode,
            'school_id' => $schoolCode,
            'phone' => '05' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $term = SchoolTerm::query()->create([
            'school_id' => $school->id,
            'name' => 'الترم الأول',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        return [
            'school' => $school,
            'manager' => $manager,
            'term' => $term,
        ];
    }

    private function createExamContext(string $schoolCode): array
    {
        $context = $this->createPlanningContext($schoolCode);

        $department = Department::query()->create([
            'name' => 'قسم المعلمين ' . $schoolCode,
            'staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
            'school_id' => null,
        ]);

        $teacher = User::factory()->create([
            'role' => 'staff',
            'school_id' => $context['school']->id,
            'department_id' => $department->id,
            'school_staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
            'is_active' => true,
        ]);
        $teacher->assignRole('staff');

        $stage = SchoolStage::query()->create([
            'school_id' => $context['school']->id,
            'name' => 'المرحلة الابتدائية',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::query()->create([
            'school_id' => $context['school']->id,
            'school_stage_id' => $stage->id,
            'grade_name' => 'الصف الأول',
            'name' => 'أ',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subject = SchoolSubject::query()->create([
            'school_id' => $context['school']->id,
            'name' => 'الرياضيات',
            'code' => 'MTH-' . substr($schoolCode, -3),
            'is_active' => true,
        ]);

        SchoolSubjectTeacherAssignment::query()->create([
            'school_id' => $context['school']->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
        ]);

        $courseOffering = SchoolCourseOffering::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $stage->id,
            'school_classroom_id' => $classroom->id,
            'school_subject_id' => $subject->id,
            'is_active' => true,
        ]);

        $teachingAssignment = SchoolTeachingAssignment::query()->create([
            'school_id' => $context['school']->id,
            'school_course_offering_id' => $courseOffering->id,
            'teacher_user_id' => $teacher->id,
            'is_active' => true,
            'can_create_exam' => true,
            'can_update_exam' => true,
            'can_delete_exam' => true,
            'can_approve_exam' => false,
            'can_enter_exam_scores' => true,
            'can_edit_exam_scores' => true,
            'can_use_question_bank' => true,
        ]);

        $exam = SchoolExam::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $stage->id,
            'school_classroom_id' => $classroom->id,
            'school_subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
            'title' => 'اختبار محفوظ',
            'exam_date' => '2026-09-14',
            'starts_at' => '09:00:00',
            'ends_at' => '09:45:00',
            'max_score' => 20,
            'passing_score' => 10,
            'status' => SchoolExam::STATUS_DRAFT,
            'is_active' => true,
        ]);

        return array_merge($context, [
            'teacher' => $teacher,
            'stage' => $stage,
            'classroom' => $classroom,
            'subject' => $subject,
            'courseOffering' => $courseOffering,
            'teachingAssignment' => $teachingAssignment,
            'exam' => $exam,
        ]);
    }

    private function createExamAttachment(array $context): Attachment
    {
        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['manager'])
            ->post(route('school.exams.store'), $this->examPayload($context, [
                'title' => 'اختبار مع مرفق',
                'attachments' => [
                    UploadedFile::fake()->create('attachment.pdf', 64, 'application/pdf'),
                ],
            ]));

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasNoErrors();

        return Attachment::query()->where('attachable_type', SchoolExam::class)->latest('id')->firstOrFail();
    }

    private function examPayload(array $context, array $overrides = []): array
    {
        return array_merge([
            'school_exam_template_id' => null,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroom']->id,
            'school_subject_id' => $context['subject']->id,
            'teacher_user_id' => $context['teacher']->id,
            'title' => 'اختبار أسبوعي 1',
            'exam_date' => '2026-09-15',
            'starts_at' => '09:00',
            'ends_at' => '09:45',
            'max_score' => 20,
            'passing_score' => 10,
            'allow_subject_schedule_overlap' => false,
            'is_active' => true,
        ], $overrides);
    }

    private function ensureBaseRoles(): void
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    }
}
