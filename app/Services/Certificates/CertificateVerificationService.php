<?php

namespace App\Services\Certificates;

use App\Models\StudentCertificate;

class CertificateVerificationService
{
    /**
     * @return array<string, mixed>|null
     */
    public function publicPayload(string $token): ?array
    {
        $certificate = StudentCertificate::query()
            ->where('verification_token', $token)
            ->with(['school:id,name', 'student:id,full_name', 'template:id,name'])
            ->first();

        if (!$certificate) {
            return null;
        }

        return [
            'certificate_number' => (string) $certificate->certificate_number,
            'status' => (string) $certificate->status,
            'status_label' => (string) $certificate->status === StudentCertificate::STATUS_CANCELLED ? 'ملغاة' : 'صالحة',
            'recipient_name' => (string) ($certificate->recipient_name ?: ($certificate->student?->full_name ?? '')),
            'recipient_label' => (string) ($certificate->recipient_label ?: 'مستفيد'),
            'student_name' => (string) ($certificate->recipient_name ?: ($certificate->student?->full_name ?? '')),
            'school_name' => (string) ($certificate->school?->name ?? ''),
            'type' => (string) $certificate->type,
            'title' => (string) $certificate->title,
            'issued_at' => optional($certificate->issued_at)->format('Y-m-d'),
        ];
    }
}
