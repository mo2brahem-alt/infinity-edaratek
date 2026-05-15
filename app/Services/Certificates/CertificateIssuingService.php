<?php

namespace App\Services\Certificates;

use App\Models\CertificateTemplate;
use App\Models\School;
use App\Models\SchoolAcademicYear;
use App\Models\SchoolCertificateSignature;
use App\Models\SchoolStudent;
use App\Models\SchoolTerm;
use App\Models\StudentCertificate;
use App\Models\User;
use App\Support\CertificateOptionLibrary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CertificateIssuingService
{
    public function __construct(
        private readonly CertificateTemplateService $templateService,
        private readonly CertificateRenderingService $renderingService
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return Collection<int, StudentCertificate>
     */
    public function issueForStudents(int $schoolId, User $actor, array $payload): Collection
    {
        return $this->issueForRecipients($schoolId, $actor, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return Collection<int, StudentCertificate>
     */
    public function issueForRecipients(int $schoolId, User $actor, array $payload): Collection
    {
        $template = $this->resolveTemplate($schoolId, (int) ($payload['certificate_template_id'] ?? 0));
        $signature = $this->resolveSignature($schoolId, (int) ($payload['school_certificate_signature_id'] ?? 0));
        $recipients = $this->resolveRecipients($schoolId, $payload);
        $school = School::query()
            ->whereKey($schoolId)
            ->with('manager:id,name')
            ->firstOrFail(['id', 'name', 'manager_user_id', 'logo_path']);
        $academicYear = $this->resolveAcademicYear($schoolId, (int) ($payload['school_academic_year_id'] ?? 0));
        $term = $this->resolveTerm($schoolId, (int) ($payload['school_term_id'] ?? 0));
        $storedPaths = [];

        try {
            return DB::transaction(function () use (
                $payload,
                $schoolId,
                $actor,
                $template,
                $signature,
                $recipients,
                $school,
                $academicYear,
                $term,
                &$storedPaths
            ): Collection {
                $issued = collect();

                foreach ($recipients as $recipient) {
                    $number = $this->generateCertificateNumber($schoolId);
                    $bodyTemplate = trim((string) ($payload['body'] ?? ''))
                        ?: (string) ($template?->default_body ?? '')
                        ?: (CertificateOptionLibrary::phrases()[(string) ($payload['type'] ?? $template?->type)] ?? '');
                    $renderedData = $this->renderData($school, $recipient, $academicYear, $term, $number, $payload);
                    $body = $this->replaceVariables($bodyTemplate, $renderedData);
                    $type = (string) ($payload['type'] ?? $template?->type ?? CertificateTemplate::TYPE_APPRECIATION);
                    $student = $recipient['student'];
                    $context = (array) $recipient['context'];

                    $certificate = StudentCertificate::query()->create([
                        'school_id' => $schoolId,
                        'school_student_id' => $student ? (int) $student->id : null,
                        'recipient_type' => (string) $recipient['type'],
                        'recipient_id' => (int) $recipient['id'],
                        'recipient_name' => (string) $recipient['name'],
                        'recipient_label' => (string) $recipient['label'],
                        'recipient_context_json' => $context,
                        'certificate_template_id' => $template?->id,
                        'school_certificate_signature_id' => $signature?->id,
                        'certificate_number' => $number,
                        'type' => $type,
                        'title' => trim((string) ($payload['title'] ?? '')) ?: CertificateOptionLibrary::labelForType($type),
                        'body' => $body,
                        'rendered_data_json' => $renderedData,
                        'status' => StudentCertificate::STATUS_ISSUED,
                        'issued_by' => (int) $actor->id,
                        'issued_at' => now(),
                        'verification_token' => Str::random(56),
                        'school_academic_year_id' => $academicYear?->id,
                        'school_term_id' => $term?->id,
                        'school_stage_id' => $student?->classroom?->school_stage_id,
                        'school_classroom_id' => $student?->school_classroom_id,
                        'metadata' => [
                            'activity_name' => $payload['activity_name'] ?? null,
                            'achievement_detail' => $payload['achievement_detail'] ?? null,
                            'rendering_mode' => 'printable_html',
                        ],
                    ]);

                    $path = $this->renderingService->storePrintableHtml($certificate);
                    $storedPaths[] = $path;
                    $certificate->forceFill([
                        'pdf_disk' => CertificateRenderingService::DISK,
                        'pdf_path' => $path,
                    ])->save();

                    $issued->push($certificate->refresh());
                }

                return $issued;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk(CertificateRenderingService::DISK)->delete($path);
            }

            throw $exception;
        }
    }

    public function cancel(StudentCertificate $certificate, int $schoolId, User $actor, string $reason): StudentCertificate
    {
        if ((int) $certificate->school_id !== $schoolId) {
            throw ValidationException::withMessages([
                'certificate' => 'لا يمكنك إلغاء شهادة خارج نطاق مدرستك.',
            ]);
        }

        if ((string) $certificate->status === StudentCertificate::STATUS_CANCELLED) {
            return $certificate;
        }

        $certificate->forceFill([
            'status' => StudentCertificate::STATUS_CANCELLED,
            'cancelled_by' => (int) $actor->id,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ])->save();

        return $certificate->refresh();
    }

    private function resolveTemplate(int $schoolId, int $templateId): ?CertificateTemplate
    {
        if ($templateId <= 0) {
            return null;
        }

        $template = CertificateTemplate::query()->forSchool($schoolId)->whereKey($templateId)->first();
        $this->templateService->assertTemplateBelongsToSchool($template, $schoolId);

        return $template;
    }

    private function resolveSignature(int $schoolId, int $signatureId): ?SchoolCertificateSignature
    {
        if ($signatureId <= 0) {
            return null;
        }

        $signature = SchoolCertificateSignature::query()->forSchool($schoolId)->active()->whereKey($signatureId)->first();
        if (!$signature) {
            throw ValidationException::withMessages([
                'school_certificate_signature_id' => 'لا يمكنك استخدام توقيع أو ختم خارج نطاق مدرستك.',
            ]);
        }

        return $signature;
    }

    /**
     * @param array<int, mixed> $studentIds
     * @return Collection<int, SchoolStudent>
     */
    private function resolveRecipients(int $schoolId, array $payload): Collection
    {
        $recipientType = (string) ($payload['recipient_type'] ?? StudentCertificate::RECIPIENT_STUDENT);
        $recipientIds = (array) ($payload['recipient_ids'] ?? []);

        if ($recipientIds === [] && isset($payload['student_ids'])) {
            $recipientType = StudentCertificate::RECIPIENT_STUDENT;
            $recipientIds = (array) $payload['student_ids'];
        }

        return match ($recipientType) {
            StudentCertificate::RECIPIENT_USER => $this->resolveSchoolUsers($schoolId, $recipientIds),
            default => $this->resolveStudents($schoolId, $recipientIds),
        };
    }

    /**
     * @param array<int, mixed> $studentIds
     * @return Collection<int, array<string, mixed>>
     */
    private function resolveStudents(int $schoolId, array $studentIds): Collection
    {
        $ids = collect($studentIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'recipient_ids' => 'اختر طالبًا واحدًا على الأقل لإصدار الشهادة.',
                'student_ids' => 'اختر طالبًا واحدًا على الأقل لإصدار الشهادة.',
            ]);
        }

        $students = SchoolStudent::query()
            ->where('school_id', $schoolId)
            ->whereIn('id', $ids->all())
            ->with(['classroom.stage'])
            ->get();

        if ($students->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'recipient_ids' => 'لا يمكنك إصدار شهادة لطالب خارج نطاق مدرستك.',
                'student_ids' => 'لا يمكنك إصدار شهادة لطالب خارج نطاق مدرستك.',
            ]);
        }

        return $students
            ->map(fn (SchoolStudent $student): array => [
                'type' => StudentCertificate::RECIPIENT_STUDENT,
                'id' => (int) $student->id,
                'name' => (string) $student->full_name,
                'label' => 'طالب',
                'student' => $student,
                'user' => null,
                'context' => [
                    'student_code' => (string) ($student->student_code ?? ''),
                    'stage_name' => (string) ($student->classroom?->stage?->name ?? ''),
                    'grade_name' => (string) ($student->classroom?->grade_name ?? ''),
                    'classroom_name' => (string) ($student->classroom?->name ?? ''),
                ],
            ])
            ->values();
    }

    /**
     * @param array<int, mixed> $userIds
     * @return Collection<int, array<string, mixed>>
     */
    private function resolveSchoolUsers(int $schoolId, array $userIds): Collection
    {
        $ids = collect($userIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            throw ValidationException::withMessages(['recipient_ids' => 'اختر مستفيدًا واحدًا على الأقل لإصدار الشهادة.']);
        }

        $users = User::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->whereIn('id', $ids->all())
            ->get(['id', 'school_id', 'name', 'email', 'role', 'school_staff_type']);

        if ($users->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'recipient_ids' => 'لا يمكنك إصدار شهادة لشخص خارج نطاق مدرستك.',
            ]);
        }

        return $users
            ->map(fn (User $user): array => [
                'type' => StudentCertificate::RECIPIENT_USER,
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'label' => $this->userRecipientLabel($user),
                'student' => null,
                'user' => $user,
                'context' => [
                    'email' => (string) ($user->email ?? ''),
                    'role' => (string) ($user->role ?? ''),
                    'school_staff_type' => (string) ($user->school_staff_type ?? ''),
                ],
            ])
            ->values();
    }

    private function resolveAcademicYear(int $schoolId, int $academicYearId): ?SchoolAcademicYear
    {
        if ($academicYearId <= 0) {
            return null;
        }

        return SchoolAcademicYear::query()->where('school_id', $schoolId)->whereKey($academicYearId)->firstOrFail();
    }

    private function resolveTerm(int $schoolId, int $termId): ?SchoolTerm
    {
        if ($termId <= 0) {
            return null;
        }

        return SchoolTerm::query()->where('school_id', $schoolId)->whereKey($termId)->firstOrFail();
    }

    private function generateCertificateNumber(int $schoolId): string
    {
        do {
            $number = sprintf('CERT-%d-%s-%s', $schoolId, now()->format('Ymd'), strtoupper(Str::random(6)));
        } while (StudentCertificate::query()->where('certificate_number', $number)->exists());

        return $number;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    /**
     * @param array<string, mixed> $recipient
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function renderData(School $school, array $recipient, ?SchoolAcademicYear $academicYear, ?SchoolTerm $term, string $number, array $payload): array
    {
        $date = Carbon::parse($payload['certificate_date'] ?? now());
        $student = $recipient['student'];
        $context = (array) ($recipient['context'] ?? []);

        return [
            '{student_name}' => (string) $recipient['name'],
            '{recipient_name}' => (string) $recipient['name'],
            '{recipient_type_label}' => (string) $recipient['label'],
            '{student_gender_label}' => $student ? 'الطالب/ة' : 'المستفيد/ة',
            '{school_name}' => (string) $school->name,
            '{grade_name}' => (string) ($context['grade_name'] ?? ''),
            '{stage_name}' => (string) ($context['stage_name'] ?? ''),
            '{classroom_name}' => (string) ($context['classroom_name'] ?? ''),
            '{academic_year}' => (string) ($academicYear?->name ?? ''),
            '{term_name}' => (string) ($term?->name ?? ''),
            '{certificate_date}' => $date->format('Y-m-d'),
            '{hijri_date}' => (string) ($payload['hijri_date'] ?? ''),
            '{gregorian_date}' => $date->format('Y-m-d'),
            '{manager_name}' => (string) data_get($school, 'manager.name', ''),
            '{teacher_name}' => (string) ($payload['teacher_name'] ?? ''),
            '{activity_name}' => (string) ($payload['activity_name'] ?? ''),
            '{achievement_detail}' => (string) ($payload['achievement_detail'] ?? ''),
            '{certificate_number}' => $number,
        ];
    }

    private function userRecipientLabel(User $user): string
    {
        if ($user->hasSystemRole('school_manager') || (string) $user->role === 'school_manager') {
            return 'مدير المدرسة';
        }

        return match ((string) ($user->school_staff_type ?? '')) {
            User::SCHOOL_STAFF_EDUCATIONAL => 'كادر تعليمي',
            User::SCHOOL_STAFF_ADMINISTRATIVE => 'كادر إداري',
            default => 'منسوب المدرسة',
        };
    }

    /**
     * @param array<string, string> $renderedData
     */
    private function replaceVariables(string $body, array $renderedData): string
    {
        return str_replace(array_keys($renderedData), array_values($renderedData), $body);
    }
}
