<?php

namespace Tests\Feature;

use App\Models\CertificateTemplate;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolCertificateSignature;
use App\Models\SchoolClassroom;
use App\Models\SchoolStage;
use App\Models\SchoolStudent;
use App\Models\StudentCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tenant isolation tests for features that previously lacked explicit cross-school
 * access attempt coverage: Reports filter injection and Certificate CRUD operations.
 *
 * Pattern: create two independent school contexts (A and B), then attempt to
 * access or mutate school A's resources while authenticated as school B's manager.
 * All attempts must be rejected (403 Forbidden or validation error).
 */
class SchoolTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────
    // Reports — cross-school filter injection
    // ─────────────────────────────────────────────────────────────────────

    public function test_reports_rejects_stage_id_belonging_to_another_school(): void
    {
        ['managerA' => $managerA, 'schoolB' => $schoolB] = $this->createTwoSchoolContexts('SCH-TI-RPT-1', 'SCH-TI-RPT-2');

        $foreignStage = SchoolStage::create([
            'school_id' => $schoolB->id,
            'name'       => 'Foreign Stage',
            'sort_order' => 1,
            'is_active'  => true,
        ]);

        $this->actingAs($managerA)
            ->from(route('school.reports.index'))
            ->get(route('school.reports.index', ['entity' => 'stages', 'stage_id' => $foreignStage->id]))
            ->assertSessionHasErrors('stage_id');
    }

    public function test_reports_rejects_classroom_id_belonging_to_another_school(): void
    {
        ['managerA' => $managerA, 'schoolB' => $schoolB] = $this->createTwoSchoolContexts('SCH-TI-RPT-3', 'SCH-TI-RPT-4');

        $foreignStage = SchoolStage::create([
            'school_id' => $schoolB->id,
            'name'       => 'Foreign Stage B',
            'sort_order' => 1,
            'is_active'  => true,
        ]);
        $foreignClassroom = SchoolClassroom::create([
            'school_id'       => $schoolB->id,
            'school_stage_id' => $foreignStage->id,
            'name'            => 'Foreign Classroom',
            'sort_order'      => 1,
            'is_active'       => true,
        ]);

        $this->actingAs($managerA)
            ->from(route('school.reports.index'))
            ->get(route('school.reports.index', ['entity' => 'students', 'classroom_id' => $foreignClassroom->id]))
            ->assertSessionHasErrors('classroom_id');
    }

    public function test_reports_rejects_student_id_belonging_to_another_school(): void
    {
        ['managerA' => $managerA, 'schoolB' => $schoolB] = $this->createTwoSchoolContexts('SCH-TI-RPT-5', 'SCH-TI-RPT-6');

        $foreignStage = SchoolStage::create([
            'school_id' => $schoolB->id,
            'name'       => 'Stage B',
            'sort_order' => 1,
            'is_active'  => true,
        ]);
        $foreignClassroom = SchoolClassroom::create([
            'school_id'       => $schoolB->id,
            'school_stage_id' => $foreignStage->id,
            'name'            => 'Class B',
            'sort_order'      => 1,
            'is_active'       => true,
        ]);
        $foreignStudent = SchoolStudent::create([
            'school_id'          => $schoolB->id,
            'school_classroom_id'=> $foreignClassroom->id,
            'full_name'          => 'Foreign Student',
            'student_code'       => 'SCH-TI-RPT-6-S1',
            'is_active'          => true,
        ]);

        $this->actingAs($managerA)
            ->from(route('school.reports.index'))
            ->get(route('school.reports.index', ['entity' => 'attendance', 'student_id' => $foreignStudent->id]))
            ->assertSessionHasErrors('student_id');
    }

    public function test_reports_rejects_teacher_id_belonging_to_another_school(): void
    {
        ['managerA' => $managerA, 'schoolB' => $schoolB] = $this->createTwoSchoolContexts('SCH-TI-RPT-7', 'SCH-TI-RPT-8');

        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $foreignTeacher = User::factory()->create([
            'role'      => 'teacher',
            'school_id' => $schoolB->id,
            'is_active' => true,
        ]);
        $foreignTeacher->assignRole('teacher');

        $this->actingAs($managerA)
            ->from(route('school.reports.index'))
            ->get(route('school.reports.index', ['entity' => 'teachers', 'teacher_id' => $foreignTeacher->id]))
            ->assertSessionHasErrors('teacher_id');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Certificates — cross-school CRUD isolation
    // ─────────────────────────────────────────────────────────────────────

    public function test_manager_cannot_update_certificate_template_belonging_to_another_school(): void
    {
        ['managerA' => $managerA, 'schoolB' => $schoolB] = $this->createTwoSchoolContexts('SCH-TI-CERT-1', 'SCH-TI-CERT-2');

        $foreignTemplate = CertificateTemplate::create([
            'school_id'    => $schoolB->id,
            'name'         => 'Foreign Template',
            'type'         => CertificateTemplate::TYPE_APPRECIATION,
            'title_text'   => 'شهادة أجنبية',
            'default_body' => 'نص القالب الأجنبي.',
            'is_active'    => true,
        ]);

        $this->actingAs($managerA)
            ->from(route('school.certificates.index'))
            ->put(route('school.certificates.templates.update', $foreignTemplate->id), [
                'name'         => 'Hijacked Template',
                'type'         => CertificateTemplate::TYPE_APPRECIATION,
                'title_text'   => 'محاولة اختراق',
                'default_body' => 'نص مخترق.',
                'is_active'    => true,
            ])
            ->assertSessionHasErrors('certificate_template_id');

        // Verify the template was NOT modified
        $this->assertDatabaseHas('certificate_templates', [
            'id'   => $foreignTemplate->id,
            'name' => 'Foreign Template',
        ]);
    }

    public function test_manager_cannot_delete_certificate_template_belonging_to_another_school(): void
    {
        ['managerA' => $managerA, 'schoolB' => $schoolB] = $this->createTwoSchoolContexts('SCH-TI-CERT-3', 'SCH-TI-CERT-4');

        $foreignTemplate = CertificateTemplate::create([
            'school_id'    => $schoolB->id,
            'name'         => 'Template To Protect',
            'type'         => CertificateTemplate::TYPE_EXCELLENCE,
            'title_text'   => 'شهادة محمية',
            'default_body' => 'نص.',
            'is_active'    => true,
        ]);

        $this->actingAs($managerA)
            ->from(route('school.certificates.index'))
            ->delete(route('school.certificates.templates.destroy', $foreignTemplate->id))
            ->assertSessionHasErrors('certificate_template_id');

        // Verify the template was NOT deleted
        $this->assertDatabaseHas('certificate_templates', ['id' => $foreignTemplate->id]);
    }

    public function test_manager_cannot_delete_certificate_signature_belonging_to_another_school(): void
    {
        ['managerA' => $managerA, 'schoolB' => $schoolB] = $this->createTwoSchoolContexts('SCH-TI-CERT-5', 'SCH-TI-CERT-6');

        $foreignSignature = SchoolCertificateSignature::create([
            'school_id' => $schoolB->id,
            'name'      => 'Foreign Signer',
            'title'     => 'مدير',
            'is_active' => true,
            'is_default'=> false,
        ]);

        $this->actingAs($managerA)
            ->from(route('school.certificates.index'))
            ->delete(route('school.certificates.signatures.destroy', $foreignSignature->id))
            ->assertSessionHasErrors('signature');

        // Verify the signature was NOT deleted
        $this->assertDatabaseHas('school_certificate_signatures', ['id' => $foreignSignature->id]);
    }

    public function test_manager_cannot_cancel_certificate_belonging_to_another_school(): void
    {
        ['managerA' => $managerA, 'schoolB' => $schoolB] = $this->createTwoSchoolContexts('SCH-TI-CERT-7', 'SCH-TI-CERT-8');

        $foreignStage = SchoolStage::create([
            'school_id' => $schoolB->id, 'name' => 'Stage', 'sort_order' => 1, 'is_active' => true,
        ]);
        $foreignClassroom = SchoolClassroom::create([
            'school_id' => $schoolB->id, 'school_stage_id' => $foreignStage->id,
            'name' => 'Class', 'sort_order' => 1, 'is_active' => true,
        ]);
        $foreignStudent = SchoolStudent::create([
            'school_id' => $schoolB->id, 'school_classroom_id' => $foreignClassroom->id,
            'full_name' => 'Cert Student B', 'student_code' => 'SCH-TI-CERT-8-S1', 'is_active' => true,
        ]);
        $foreignCertificate = StudentCertificate::create([
            'school_id'          => $schoolB->id,
            'school_student_id'  => $foreignStudent->id,
            'recipient_type'     => StudentCertificate::RECIPIENT_STUDENT,
            'recipient_id'       => $foreignStudent->id,
            'recipient_name'     => $foreignStudent->full_name,
            'type'               => CertificateTemplate::TYPE_APPRECIATION,
            'title'              => 'شهادة أجنبية',
            'body'               => 'نص.',
            'certificate_number' => 'TI-CERT-7-001',
            'status'             => 'issued',
            'verification_token' => 'ti-cert-7-tok-001',
        ]);

        $this->actingAs($managerA)
            ->from(route('school.certificates.index'))
            ->post(route('school.certificates.cancel', $foreignCertificate->id), [
                'cancel_reason' => 'محاولة إلغاء غير مصرح.',
            ])
            ->assertSessionHasErrors('certificate');

        // Verify the certificate was NOT cancelled
        $this->assertDatabaseHas('student_certificates', [
            'id'     => $foreignCertificate->id,
            'status' => 'issued',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Creates two fully-active school contexts sharing the same directorate.
     * Returns ['managerA', 'schoolA', 'managerB', 'schoolB'].
     *
     * @return array{managerA: User, schoolA: School, managerB: User, schoolB: School}
     */
    private function createTwoSchoolContexts(string $codeA, string $codeB): array
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::create([
            'name'       => 'TI-Region-' . $codeA,
            'governorate' => 'Riyadh',
        ]);

        $managerA = User::factory()->create(['role' => 'school_manager']);
        $managerA->assignRole('school_manager');

        $managerB = User::factory()->create(['role' => 'school_manager']);
        $managerB->assignRole('school_manager');

        $schoolA = School::create([
            'directorate_id'    => $region->id,
            'name'              => $codeA . ' School',
            'school_id'         => $codeA,
            'phone'             => '050' . substr(md5($codeA), 0, 7),
            'status'            => School::STATUS_ACTIVE,
            'supervision_status'=> School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id'   => $managerA->id,
        ]);

        $schoolB = School::create([
            'directorate_id'    => $region->id,
            'name'              => $codeB . ' School',
            'school_id'         => $codeB,
            'phone'             => '050' . substr(md5($codeB), 0, 7),
            'status'            => School::STATUS_ACTIVE,
            'supervision_status'=> School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id'   => $managerB->id,
        ]);

        $managerA->update(['school_id' => $schoolA->id]);
        $managerB->update(['school_id' => $schoolB->id]);

        return compact('managerA', 'schoolA', 'managerB', 'schoolB');
    }
}
