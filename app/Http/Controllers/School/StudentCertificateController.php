<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\CancelStudentCertificateRequest;
use App\Http\Requests\School\IssueStudentCertificateRequest;
use App\Http\Requests\School\StoreCertificateSignatureRequest;
use App\Http\Requests\School\StoreCertificateTemplateRequest;
use App\Http\Requests\School\UpdateCertificateTemplateRequest;
use App\Models\CertificateTemplate;
use App\Models\School;
use App\Models\SchoolAcademicYear;
use App\Models\SchoolCertificateSignature;
use App\Models\SchoolClassroom;
use App\Models\SchoolStage;
use App\Models\SchoolStudent;
use App\Models\SchoolTerm;
use App\Models\StudentCertificate;
use App\Models\User;
use App\Services\Certificates\CertificateIssuingService;
use App\Services\Certificates\CertificateRenderingService;
use App\Services\Certificates\CertificateTemplateService;
use App\Services\Exports\SchoolExportDocumentService;
use App\Support\CertificateOptionLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class StudentCertificateController extends Controller
{
    public function __construct(
        private readonly CertificateTemplateService $templateService,
        private readonly CertificateIssuingService $issuingService,
        private readonly CertificateRenderingService $renderingService,
        private readonly SchoolExportDocumentService $exportDocuments,
    ) {
    }

    public function index(Request $request): Response
    {
        $schoolId = $this->resolveSchoolId($request);
        $user = $request->user();
        $permissions = $this->permissions($user);

        $templates = CertificateTemplate::query()
            ->forSchool($schoolId)
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (CertificateTemplate $template): array => $this->serializeTemplate($template))
            ->values()
            ->all();

        $signatures = SchoolCertificateSignature::query()
            ->forSchool($schoolId)
            ->latest('is_default')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (SchoolCertificateSignature $signature): array => $this->serializeSignature($signature))
            ->values()
            ->all();

        $certificates = StudentCertificate::query()
            ->forSchool($schoolId)
            ->with(['student:id,school_id,school_classroom_id,full_name,student_code', 'template:id,name', 'issuer:id,name'])
            ->latest('id')
            ->limit(150)
            ->get()
            ->map(fn (StudentCertificate $certificate): array => $this->serializeCertificate($certificate, $permissions))
            ->values()
            ->all();

        return Inertia::render('School/Certificates/Index', [
            'school' => School::query()->whereKey($schoolId)->first(['id', 'name', 'school_id']),
            'templates' => $templates,
            'signatures' => $signatures,
            'certificates' => $certificates,
            'students' => $this->students($schoolId),
            'recipients' => $this->recipients($schoolId),
            'filterOptions' => [
                'types' => CertificateOptionLibrary::types(),
                'frames' => CertificateOptionLibrary::frames(),
                'fonts' => CertificateOptionLibrary::fonts(),
                'phrases' => CertificateOptionLibrary::phrases(),
                'variables' => CertificateOptionLibrary::variables(),
                'stages' => $this->stages($schoolId),
                'classrooms' => $this->classrooms($schoolId),
                'academicYears' => $this->academicYears($schoolId),
                'terms' => $this->terms($schoolId),
            ],
            'permissions' => $permissions,
            'isManager' => $user?->hasSystemRole('school_manager') ?? false,
        ]);
    }

    public function storeTemplate(StoreCertificateTemplateRequest $request): RedirectResponse
    {
        $this->abortUnless($request->user()?->canCreateCertificateTemplates(), 'ليست لديك صلاحية لإنشاء قوالب الشهادات.');
        $schoolId = $this->resolveSchoolId($request);

        $this->templateService->create($schoolId, (int) $request->user()->id, $request->validated());

        return back()->with('success', 'تم حفظ قالب الشهادة بنجاح.');
    }

    public function updateTemplate(UpdateCertificateTemplateRequest $request, CertificateTemplate $certificateTemplate): RedirectResponse
    {
        $this->abortUnless($request->user()?->canUpdateCertificateTemplates(), 'ليست لديك صلاحية لتعديل قوالب الشهادات.');
        $schoolId = $this->resolveSchoolId($request);

        $this->templateService->update($certificateTemplate, $schoolId, (int) $request->user()->id, $request->validated());

        return back()->with('success', 'تم تحديث قالب الشهادة بنجاح.');
    }

    public function destroyTemplate(Request $request, CertificateTemplate $certificateTemplate): RedirectResponse
    {
        $this->abortUnless($request->user()?->canDeleteCertificateTemplates(), 'ليست لديك صلاحية لحذف قوالب الشهادات.');
        $schoolId = $this->resolveSchoolId($request);
        $this->templateService->assertTemplateBelongsToSchool($certificateTemplate, $schoolId);
        $certificateTemplate->delete();

        return back()->with('success', 'تم حذف قالب الشهادة بنجاح.');
    }

    public function storeSignature(StoreCertificateSignatureRequest $request): RedirectResponse
    {
        $this->abortUnless($request->user()?->canManageCertificateSignatures(), 'ليست لديك صلاحية لإدارة توقيعات الشهادات.');
        $schoolId = $this->resolveSchoolId($request);
        $payload = $request->validated();
        $disk = CertificateRenderingService::DISK;

        $signaturePath = $request->file('signature')
            ? $request->file('signature')->storeAs(
                sprintf('certificate-signatures/school-%d', $schoolId),
                Str::uuid() . '.' . $request->file('signature')->extension(),
                $disk
            )
            : null;

        $stampPath = $request->file('stamp')
            ? $request->file('stamp')->storeAs(
                sprintf('certificate-signatures/school-%d', $schoolId),
                Str::uuid() . '.' . $request->file('stamp')->extension(),
                $disk
            )
            : null;

        if ((bool) ($payload['is_default'] ?? false)) {
            SchoolCertificateSignature::query()->forSchool($schoolId)->update(['is_default' => false]);
        }

        SchoolCertificateSignature::query()->create([
            'school_id' => $schoolId,
            'name' => $payload['name'],
            'title' => $payload['title'] ?? null,
            'signature_disk' => $signaturePath ? $disk : null,
            'signature_path' => $signaturePath,
            'stamp_disk' => $stampPath ? $disk : null,
            'stamp_path' => $stampPath,
            'is_default' => (bool) ($payload['is_default'] ?? false),
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'created_by' => (int) $request->user()->id,
        ]);

        return back()->with('success', 'تم حفظ التوقيع والختم بنجاح.');
    }

    public function destroySignature(Request $request, SchoolCertificateSignature $schoolCertificateSignature): RedirectResponse
    {
        $this->abortUnless($request->user()?->canManageCertificateSignatures(), 'ليست لديك صلاحية لإدارة توقيعات الشهادات.');
        $schoolId = $this->resolveSchoolId($request);
        $this->assertSignatureBelongsToSchool($schoolCertificateSignature, $schoolId);
        $schoolCertificateSignature->forceFill(['is_active' => false, 'is_default' => false])->save();
        $schoolCertificateSignature->delete();

        return back()->with('success', 'تم تعطيل التوقيع بنجاح.');
    }

    public function issue(IssueStudentCertificateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $this->abortUnless($user?->canIssueCertificates(), 'ليست لديك صلاحية لإصدار الشهادات.');
        $recipientCount = count((array) ($request->validated('recipient_ids') ?: $request->validated('student_ids', [])));
        if ($recipientCount > 1) {
            $this->abortUnless($user?->canBulkIssueCertificates(), 'ليست لديك صلاحية لإصدار شهادات جماعية.');
        }

        $issued = $this->issuingService->issueForRecipients(
            $this->resolveSchoolId($request),
            $user,
            $request->validated()
        );

        return back()->with('success', $issued->count() > 1 ? 'تم إصدار الشهادات بنجاح.' : 'تم إصدار الشهادة بنجاح.');
    }

    public function print(Request $request, StudentCertificate $studentCertificate): SymfonyResponse
    {
        $this->abortUnless($request->user()?->canPrintCertificates(), 'ليست لديك صلاحية لطباعة الشهادات.');
        $schoolId = $this->resolveSchoolId($request);
        $this->assertCertificateBelongsToSchool($studentCertificate, $schoolId);

        return response($this->renderingService->renderHtml($studentCertificate));
    }

    public function download(Request $request, StudentCertificate $studentCertificate): SymfonyResponse
    {
        $this->abortUnless($request->user()?->canPrintCertificates(), 'ليست لديك صلاحية لتحميل الشهادات.');
        $schoolId = $this->resolveSchoolId($request);
        $this->assertCertificateBelongsToSchool($studentCertificate, $schoolId);

        $format = (string) $request->query('format', 'pdf');
        if (! in_array($format, ['pdf', 'word'], true)) {
            $format = 'pdf';
        }

        $studentCertificate->loadMissing('template');
        $html = $this->renderingService->renderHtml($studentCertificate);
        $filenameBase = Str::slug((string) $studentCertificate->certificate_number, '-');
        if ($filenameBase === '') {
            $filenameBase = 'certificate-' . (int) $studentCertificate->id;
        }

        if ($format === 'word') {
            return response($html, 200, $this->exportDocuments->wordHeaders($filenameBase . '.doc'));
        }

        $orientation = (string) ($studentCertificate->template?->orientation ?? 'landscape');

        return $this->exportDocuments->downloadPdfFromHtml($html, $filenameBase . '.pdf', $orientation);
    }

    public function cancel(CancelStudentCertificateRequest $request, StudentCertificate $studentCertificate): RedirectResponse
    {
        $this->abortUnless($request->user()?->canCancelCertificates(), 'ليست لديك صلاحية لإلغاء الشهادات.');

        $this->issuingService->cancel(
            $studentCertificate,
            $this->resolveSchoolId($request),
            $request->user(),
            (string) $request->validated('cancel_reason')
        );

        return back()->with('success', 'تم إلغاء الشهادة بنجاح.');
    }

    private function resolveSchoolId(Request $request): int
    {
        $schoolId = (int) $request->attributes->get('school_context_id', (int) ($request->user()?->school_id ?? 0));
        if ($schoolId <= 0) {
            abort(403, 'لا يمكن تحديد المدرسة الحالية.');
        }

        return $schoolId;
    }

    private function abortUnless(bool $condition, string $message): void
    {
        if (!$condition) {
            abort(403, $message);
        }
    }

    private function assertCertificateBelongsToSchool(StudentCertificate $certificate, int $schoolId): void
    {
        if ((int) $certificate->school_id !== $schoolId) {
            abort(403, 'لا يمكنك الوصول إلى شهادة خارج نطاق مدرستك.');
        }
    }

    private function assertSignatureBelongsToSchool(SchoolCertificateSignature $signature, int $schoolId): void
    {
        if ((int) $signature->school_id !== $schoolId) {
            throw ValidationException::withMessages([
                'signature' => 'لا يمكنك استخدام توقيع أو ختم خارج نطاق مدرستك.',
            ]);
        }
    }

    private function permissions(?User $user): array
    {
        return [
            'can_access_certificates' => $user?->canAccessCertificates() ?? false,
            'can_view_certificate_templates' => $user?->canViewCertificateTemplates() ?? false,
            'can_create_certificate_templates' => $user?->canCreateCertificateTemplates() ?? false,
            'can_update_certificate_templates' => $user?->canUpdateCertificateTemplates() ?? false,
            'can_delete_certificate_templates' => $user?->canDeleteCertificateTemplates() ?? false,
            'can_issue_certificates' => $user?->canIssueCertificates() ?? false,
            'can_bulk_issue_certificates' => $user?->canBulkIssueCertificates() ?? false,
            'can_print_certificates' => $user?->canPrintCertificates() ?? false,
            'can_cancel_certificates' => $user?->canCancelCertificates() ?? false,
            'can_manage_certificate_signatures' => $user?->canManageCertificateSignatures() ?? false,
        ];
    }

    private function students(int $schoolId): array
    {
        return SchoolStudent::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->with(['classroom:id,school_id,school_stage_id,name,grade_name', 'classroom.stage:id,name'])
            ->orderBy('full_name')
            ->limit(1500)
            ->get(['id', 'school_id', 'school_classroom_id', 'full_name', 'student_code', 'is_active'])
            ->map(fn (SchoolStudent $student): array => [
                'id' => (int) $student->id,
                'full_name' => (string) $student->full_name,
                'student_code' => (string) ($student->student_code ?? ''),
                'school_classroom_id' => (int) ($student->school_classroom_id ?? 0),
                'classroom_name' => (string) ($student->classroom?->name ?? ''),
                'grade_name' => (string) ($student->classroom?->grade_name ?? ''),
                'stage_name' => (string) ($student->classroom?->stage?->name ?? ''),
            ])
            ->values()
            ->all();
    }

    private function recipients(int $schoolId): array
    {
        $students = collect($this->students($schoolId))
            ->map(fn (array $student): array => [
                'key' => 'student:' . $student['id'],
                'type' => StudentCertificate::RECIPIENT_STUDENT,
                'id' => (int) $student['id'],
                'name' => (string) $student['full_name'],
                'label' => 'طالب',
                'group' => 'الطلاب',
                'school_classroom_id' => (int) ($student['school_classroom_id'] ?? 0),
                'description' => trim(implode(' / ', array_filter([
                    $student['stage_name'] ?? '',
                    $student['grade_name'] ?? '',
                    $student['classroom_name'] ?? '',
                ]))),
            ]);

        $users = User::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(1000)
            ->get(['id', 'name', 'email', 'role', 'school_staff_type'])
            ->map(fn (User $user): array => [
                'key' => 'user:' . $user->id,
                'type' => StudentCertificate::RECIPIENT_USER,
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'label' => $this->userRecipientLabel($user),
                'group' => 'منسوبو المدرسة',
                'school_classroom_id' => 0,
                'description' => (string) ($user->email ?? ''),
            ]);

        return $students->merge($users)->values()->all();
    }

    private function userRecipientLabel(User $user): string
    {
        if ((string) $user->role === 'school_manager') {
            return 'مدير المدرسة';
        }

        return match ((string) ($user->school_staff_type ?? '')) {
            User::SCHOOL_STAFF_EDUCATIONAL => 'كادر تعليمي',
            User::SCHOOL_STAFF_ADMINISTRATIVE => 'كادر إداري',
            default => 'منسوب المدرسة',
        };
    }

    private function stages(int $schoolId): array
    {
        return SchoolStage::query()->where('school_id', $schoolId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])->toArray();
    }

    private function classrooms(int $schoolId): array
    {
        return SchoolClassroom::query()->where('school_id', $schoolId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'school_stage_id', 'name', 'grade_name'])->toArray();
    }

    private function academicYears(int $schoolId): array
    {
        return SchoolAcademicYear::query()->where('school_id', $schoolId)->orderByDesc('starts_on')->get(['id', 'name', 'is_active'])->toArray();
    }

    private function terms(int $schoolId): array
    {
        return SchoolTerm::query()->where('school_id', $schoolId)->orderByDesc('start_date')->get(['id', 'name', 'school_academic_year_id', 'is_active'])->toArray();
    }

    private function serializeTemplate(CertificateTemplate $template): array
    {
        return [
            'id' => (int) $template->id,
            'name' => (string) $template->name,
            'type' => (string) $template->type,
            'type_label' => CertificateOptionLibrary::labelForType((string) $template->type),
            'orientation' => (string) $template->orientation,
            'paper_size' => (string) $template->paper_size,
            'frame_key' => (string) ($template->frame_key ?? ''),
            'title_text' => (string) ($template->title_text ?? ''),
            'default_body' => (string) ($template->default_body ?? ''),
            'title_style_json' => (array) ($template->title_style_json ?? []),
            'student_name_style_json' => (array) ($template->student_name_style_json ?? []),
            'body_style_json' => (array) ($template->body_style_json ?? []),
            'date_style_json' => (array) ($template->date_style_json ?? []),
            'signature_style_json' => (array) ($template->signature_style_json ?? []),
            'is_active' => (bool) $template->is_active,
        ];
    }

    private function serializeSignature(SchoolCertificateSignature $signature): array
    {
        return [
            'id' => (int) $signature->id,
            'name' => (string) $signature->name,
            'title' => (string) ($signature->title ?? ''),
            'is_default' => (bool) $signature->is_default,
            'is_active' => (bool) $signature->is_active,
        ];
    }

    private function serializeCertificate(StudentCertificate $certificate, array $permissions): array
    {
        return [
            'id' => (int) $certificate->id,
            'certificate_number' => (string) $certificate->certificate_number,
            'title' => (string) $certificate->title,
            'type' => (string) $certificate->type,
            'type_label' => CertificateOptionLibrary::labelForType((string) $certificate->type),
            'status' => (string) $certificate->status,
            'status_label' => (string) $certificate->status === StudentCertificate::STATUS_CANCELLED ? 'ملغاة' : 'صالحة',
            'recipient_name' => (string) ($certificate->recipient_name ?: ($certificate->student?->full_name ?? '')),
            'recipient_label' => (string) ($certificate->recipient_label ?: 'مستفيد'),
            'student_name' => (string) ($certificate->recipient_name ?: ($certificate->student?->full_name ?? '')),
            'template_name' => (string) ($certificate->template?->name ?? ''),
            'issued_by' => (string) ($certificate->issuer?->name ?? ''),
            'issued_at' => optional($certificate->issued_at)->format('Y-m-d H:i'),
            'print_url' => ($permissions['can_print_certificates'] ?? false) ? route('school.certificates.print', $certificate, false) : null,
            'download_url' => ($permissions['can_print_certificates'] ?? false) ? route('school.certificates.download', ['studentCertificate' => $certificate, 'format' => 'pdf'], false) : null,
            'download_word_url' => ($permissions['can_print_certificates'] ?? false) ? route('school.certificates.download', ['studentCertificate' => $certificate, 'format' => 'word'], false) : null,
            'verify_url' => route('certificates.verify', ['token' => (string) $certificate->verification_token], false),
        ];
    }
}
