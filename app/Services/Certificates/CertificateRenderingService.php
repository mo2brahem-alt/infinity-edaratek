<?php

namespace App\Services\Certificates;

use App\Models\SchoolCertificateSignature;
use App\Models\StudentCertificate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class CertificateRenderingService
{
    public const DISK = 'school_attachments';

    public function renderHtml(StudentCertificate $certificate): string
    {
        $certificate->loadMissing([
            'school:id,name,logo_path,manager_user_id',
            'student.classroom.stage',
            'template',
            'signature',
            'issuer:id,name',
        ]);

        $template = $certificate->template;
        $signature = $certificate->signature;
        $data = (array) ($certificate->rendered_data_json ?? []);
        $styles = [
            'title' => (array) ($template?->title_style_json ?? []),
            'student' => (array) ($template?->student_name_style_json ?? []),
            'body' => (array) ($template?->body_style_json ?? []),
            'date' => (array) ($template?->date_style_json ?? []),
            'signature' => (array) ($template?->signature_style_json ?? []),
        ];

        return view('certificates.print', [
            'certificate' => $certificate,
            'template' => $template,
            'school' => $certificate->school,
            'student' => $certificate->student,
            'recipientName' => (string) ($certificate->recipient_name ?: $certificate->student?->full_name ?: ''),
            'recipientLabel' => (string) ($certificate->recipient_label ?: 'مستفيد'),
            'signature' => $signature,
            'styles' => $styles,
            'frameKey' => (string) ($template?->frame_key ?: 'formal-simple'),
            'title' => (string) ($certificate->title ?: $template?->title_text ?: 'شهادة'),
            'body' => new HtmlString(nl2br(e((string) $certificate->body))),
            'issuedDate' => optional($certificate->issued_at)->format('Y-m-d') ?: now()->toDateString(),
            'verificationUrl' => route('certificates.verify', ['token' => (string) $certificate->verification_token]),
            'schoolLogoImage' => $this->schoolLogoDataUri((string) ($certificate->school?->logo_path ?? '')),
            'signatureImage' => $this->imageDataUri($signature, 'signature'),
            'stampImage' => $this->imageDataUri($signature, 'stamp'),
            'renderedData' => $data,
        ])->render();
    }

    public function storePrintableHtml(StudentCertificate $certificate): string
    {
        $html = $this->renderHtml($certificate);
        $path = sprintf(
            'certificates/school-%d/%s.html',
            (int) $certificate->school_id,
            (string) $certificate->certificate_number
        );

        Storage::disk(self::DISK)->put($path, $html);

        return $path;
    }

    private function schoolLogoDataUri(string $path): ?string
    {
        $path = $this->normalizePublicStoragePath($path);
        if ($path === '' || !Storage::disk('public')->exists($path)) {
            return null;
        }

        $content = Storage::disk('public')->get($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => null,
        };

        if ($mime === null) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    private function normalizePublicStoragePath(string $path): string
    {
        $path = ltrim(trim($path), '/');

        foreach (['storage/', 'media-files/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return substr($path, strlen($prefix));
            }
        }

        return $path;
    }

    private function imageDataUri(?SchoolCertificateSignature $signature, string $type): ?string
    {
        if (!$signature) {
            return null;
        }

        $disk = (string) ($type === 'stamp' ? $signature->stamp_disk : $signature->signature_disk);
        $path = (string) ($type === 'stamp' ? $signature->stamp_path : $signature->signature_path);
        if ($disk === '' || $path === '' || !Storage::disk($disk)->exists($path)) {
            return null;
        }

        $content = Storage::disk($disk)->get($path);
        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }
}
