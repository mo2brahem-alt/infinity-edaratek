<?php

namespace Tests\Feature;

use App\Models\CertificateTemplate;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolClassroom;
use App\Models\SchoolStage;
use App\Models\SchoolStudent;
use App\Models\StudentCertificate;
use App\Models\User;
use App\Services\Certificates\CertificateRenderingService;
use App\Support\SchoolPermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentCertificateFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_certificate_template_inside_school_scope(): void
    {
        $context = $this->createCertificateContext('SCH-CERT-001');

        $this->actingAs($context['manager'])
            ->from(route('school.certificates.index'))
            ->post(route('school.certificates.templates.store'), [
                'name' => 'قالب التفوق',
                'type' => CertificateTemplate::TYPE_EXCELLENCE,
                'frame_key' => 'classic-gold',
                'title_text' => 'شهادة تفوق',
                'default_body' => 'تمنح {school_name} هذه الشهادة إلى {student_name}.',
                'is_active' => true,
            ])
            ->assertRedirect(route('school.certificates.index', absolute: false));

        $this->assertDatabaseHas('certificate_templates', [
            'school_id' => $context['school']->id,
            'name' => 'قالب التفوق',
            'type' => CertificateTemplate::TYPE_EXCELLENCE,
        ]);
    }

    public function test_staff_without_certificate_permission_cannot_access_certificates(): void
    {
        $context = $this->createCertificateContext('SCH-CERT-002');
        $staff = User::factory()->create([
            'role' => 'staff',
            'school_id' => $context['school']->id,
            'is_active' => true,
        ]);
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('school.certificates.index'))
            ->assertForbidden();
    }

    public function test_authorized_staff_can_issue_certificate_for_same_school_student_only(): void
    {
        $contextA = $this->createCertificateContext('SCH-CERT-003');
        $contextB = $this->createCertificateContext('SCH-CERT-004');
        Permission::findOrCreate('certificates.view', 'web');
        Permission::findOrCreate('certificates.issue', 'web');
        Permission::findOrCreate('certificates.print', 'web');

        $staff = User::factory()->create([
            'role' => 'staff',
            'school_id' => $contextA['school']->id,
            'is_active' => true,
        ]);
        $staff->assignRole('staff');
        $staff->givePermissionTo(['certificates.view', 'certificates.issue', 'certificates.print']);

        $template = CertificateTemplate::query()->create([
            'school_id' => $contextA['school']->id,
            'name' => 'قالب شكر',
            'type' => CertificateTemplate::TYPE_APPRECIATION,
            'title_text' => 'شهادة شكر',
            'default_body' => 'تشكر {school_name} الطالب/ة {student_name} رقم {certificate_number}.',
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->from(route('school.certificates.index'))
            ->post(route('school.certificates.issue'), [
                'certificate_template_id' => $template->id,
                'student_ids' => [$contextA['student']->id],
                'type' => CertificateTemplate::TYPE_APPRECIATION,
                'title' => 'شهادة شكر',
                'body' => 'تشكر {school_name} الطالب/ة {student_name}.',
            ])
            ->assertRedirect(route('school.certificates.index', absolute: false));

        $certificate = StudentCertificate::query()->where('school_id', $contextA['school']->id)->first();
        $this->assertNotNull($certificate);
        $this->assertNotEmpty($certificate->certificate_number);
        $this->assertNotEmpty($certificate->verification_token);

        $this->actingAs($staff)
            ->from(route('school.certificates.index'))
            ->post(route('school.certificates.issue'), [
                'certificate_template_id' => $template->id,
                'student_ids' => [$contextB['student']->id],
                'type' => CertificateTemplate::TYPE_APPRECIATION,
                'title' => 'شهادة مرفوضة',
                'body' => 'نص',
            ])
            ->assertSessionHasErrors('student_ids');
    }

    public function test_authorized_staff_can_issue_certificate_for_school_user_only(): void
    {
        $contextA = $this->createCertificateContext('SCH-CERT-005');
        $contextB = $this->createCertificateContext('SCH-CERT-006');
        Permission::findOrCreate('certificates.view', 'web');
        Permission::findOrCreate('certificates.issue', 'web');
        Permission::findOrCreate('certificates.print', 'web');

        $staff = User::factory()->create([
            'role' => 'staff',
            'school_id' => $contextA['school']->id,
            'is_active' => true,
            'school_staff_type' => User::SCHOOL_STAFF_ADMINISTRATIVE,
        ]);
        $staff->assignRole('staff');
        $staff->givePermissionTo(['certificates.view', 'certificates.issue', 'certificates.print']);

        $recipient = User::factory()->create([
            'role' => 'teacher',
            'school_id' => $contextA['school']->id,
            'is_active' => true,
            'school_staff_type' => User::SCHOOL_STAFF_EDUCATIONAL,
        ]);

        $this->actingAs($staff)
            ->from(route('school.certificates.index'))
            ->post(route('school.certificates.issue'), [
                'recipient_type' => 'user',
                'recipient_ids' => [$recipient->id],
                'type' => CertificateTemplate::TYPE_APPRECIATION,
                'title' => 'شهادة شكر',
                'body' => 'تشكر {school_name} {recipient_type_label} {recipient_name}.',
            ])
            ->assertRedirect(route('school.certificates.index', absolute: false));

        $this->assertDatabaseHas('student_certificates', [
            'school_id' => $contextA['school']->id,
            'school_student_id' => null,
            'recipient_type' => 'user',
            'recipient_id' => $recipient->id,
            'recipient_name' => $recipient->name,
        ]);

        $this->actingAs($staff)
            ->from(route('school.certificates.index'))
            ->post(route('school.certificates.issue'), [
                'recipient_type' => 'user',
                'recipient_ids' => [$contextB['manager']->id],
                'type' => CertificateTemplate::TYPE_APPRECIATION,
                'title' => 'شهادة مرفوضة',
                'body' => 'نص',
            ])
            ->assertSessionHasErrors('recipient_ids');
    }

    public function test_certificate_delegation_template_and_catalog_are_available(): void
    {
        $metadata = SchoolPermissionCatalog::permissionMetadata();
        $managerAssignable = SchoolPermissionCatalog::managerAssignablePermissionNames();
        $templates = collect(SchoolPermissionCatalog::delegationTemplates());

        $this->assertArrayHasKey('certificates.issue', $metadata);
        $this->assertContains('certificates.bulk_issue', $managerAssignable);
        $this->assertNotContains('certificates.signatures.manage', $managerAssignable);
        $this->assertTrue($templates->contains(fn (array $template): bool => $template['key'] === 'certificate_officer'));
    }

    public function test_certificate_print_html_embeds_school_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put(
            'schools/logos/certificate-school-logo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
        );

        $context = $this->createCertificateContext('SCH-CERT-007');
        $context['school']->update(['logo_path' => 'schools/logos/certificate-school-logo.png']);

        $certificate = StudentCertificate::query()->create([
            'school_id' => $context['school']->id,
            'school_student_id' => $context['student']->id,
            'recipient_type' => StudentCertificate::RECIPIENT_STUDENT,
            'recipient_id' => $context['student']->id,
            'recipient_name' => $context['student']->full_name,
            'recipient_label' => 'طالب',
            'certificate_number' => 'CERT-LOGO-001',
            'type' => CertificateTemplate::TYPE_APPRECIATION,
            'title' => 'Certificate With Logo',
            'body' => 'Body',
            'status' => StudentCertificate::STATUS_ISSUED,
            'issued_by' => $context['manager']->id,
            'issued_at' => now(),
            'verification_token' => 'logo-token',
        ]);

        $html = app(CertificateRenderingService::class)->renderHtml($certificate);

        $this->assertStringContainsString('class="school-logo"', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
    }

    /**
     * @return array{school: School, manager: User, stage: SchoolStage, classroom: SchoolClassroom, student: SchoolStudent}
     */
    private function createCertificateContext(string $schoolCode): array
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $directorate = EducationalDirectorate::query()->create([
            'name' => 'Certificate Region ' . $schoolCode,
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create([
            'role' => 'school_manager',
            'is_active' => true,
        ]);
        $manager->assignRole('school_manager');

        $school = School::query()->create([
            'directorate_id' => $directorate->id,
            'name' => 'Certificate School ' . $schoolCode,
            'school_id' => $schoolCode,
            'phone' => '0500000000',
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);
        $manager->update(['school_id' => $school->id]);

        $stage = SchoolStage::query()->create([
            'school_id' => $school->id,
            'name' => 'المرحلة الابتدائية',
            'code' => $schoolCode . '-STG',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::query()->create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => 'أ',
            'grade_name' => 'الصف الأول',
            'code' => $schoolCode . '-CLS',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $student = SchoolStudent::query()->create([
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'طالب الشهادة',
            'student_code' => $schoolCode . '-STD',
            'is_active' => true,
        ]);

        return compact('school', 'manager', 'stage', 'classroom', 'student');
    }
}
