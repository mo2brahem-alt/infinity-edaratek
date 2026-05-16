<?php

namespace App\Services\Exports;

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SchoolExportDocumentService
{
    public function schoolForExport(int $schoolId): School
    {
        return School::query()
            ->whereKey($schoolId)
            ->firstOrFail([
                'id',
                'name',
                'school_id',
                'phone',
                'email',
                'address',
                'logo_path',
            ]);
    }

    /**
     * @param array<string, scalar|null> $details
     */
    public function writeCsvPreamble($stream, School $school, string $title, ?User $exportedBy = null, array $details = [], string $delimiter = ','): void
    {
        $this->writeUtf8Bom($stream);
        $this->putCsvRow($stream, ['منصة إدارتك', $title], $delimiter);
        $this->putCsvRow($stream, ['المدرسة', (string) $school->name], $delimiter);
        $this->putCsvRow($stream, ['كود المدرسة', (string) ($school->school_id ?? '')], $delimiter);

        if ($exportedBy) {
            $this->putCsvRow($stream, ['تم التصدير بواسطة', (string) $exportedBy->name], $delimiter);
        }

        $this->putCsvRow($stream, ['تاريخ التصدير', now()->format('Y-m-d H:i')], $delimiter);

        foreach ($details as $label => $value) {
            $this->putCsvRow($stream, [(string) $label, (string) ($value ?? '')], $delimiter);
        }

        $this->putCsvRow($stream, [], $delimiter);
    }

    public function writeCsvFooter($stream, School $school, ?User $exportedBy = null, string $delimiter = ','): void
    {
        $this->putCsvRow($stream, [], $delimiter);
        $this->putCsvRow($stream, ['اسم المدرسة', (string) $school->name], $delimiter);
        $this->putCsvRow($stream, ['وقت إنشاء الملف', now()->format('Y-m-d H:i')], $delimiter);

        if ($exportedBy) {
            $this->putCsvRow($stream, ['المصدر', (string) $exportedBy->name], $delimiter);
        }

        $this->putCsvRow($stream, ['تم إنشاء هذا المستند بواسطة منصة إدارتك.'], $delimiter);
    }

    /**
     * @param array<int, scalar|null> $row
     */
    public function putCsvRow($stream, array $row, string $delimiter = ','): void
    {
        fputcsv($stream, array_map(
            fn ($value): string => is_scalar($value) || $value === null ? (string) $value : '',
            $row
        ), $delimiter);
    }

    public function writeUtf8Bom($stream): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
    }

    /**
     * @param array<int, scalar|null> $parts
     */
    public function safeFileName(string $prefix, School $school, string $extension, array $parts = []): string
    {
        $segments = collect([
            $prefix,
            $school->school_id ?: 'school-' . (int) $school->id,
            ...$parts,
            now()->format('Ymd-His'),
        ])
            ->map(fn ($part): string => Str::slug((string) $part, '-'))
            ->filter()
            ->values()
            ->all();

        return implode('-', $segments) . '.' . ltrim($extension, '.');
    }

    public function csvHeaders(string $contentType = 'text/csv; charset=UTF-8'): array
    {
        return [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    public function wordHeaders(string $filename): array
    {
        return [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    public function downloadPdfFromHtml(string $html, string $filename, string $orientation = 'portrait')
    {
        $orientation = in_array($orientation, ['portrait', 'landscape'], true) ? $orientation : 'portrait';

        if (app()->bound('dompdf.wrapper')) {
            $pdf = app('dompdf.wrapper');
            $pdf->loadHTML($html, 'UTF-8');
            $pdf->setPaper('a4', $orientation);

            return $pdf->download($filename);
        }

        $browserBinary = $this->resolveHeadlessBrowserBinary();
        if ($browserBinary === null) {
            abort(500, 'تعذر تصدير PDF لعدم توفر محرك توليد PDF على هذه البيئة.');
        }

        $directory = storage_path('app/temp/report-exports');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            abort(500, 'تعذر تجهيز مجلد التصدير المؤقت.');
        }

        $token = uniqid('report-export-', true);
        $htmlPath = $directory . DIRECTORY_SEPARATOR . $token . '.html';
        $pdfPath = $directory . DIRECTORY_SEPARATOR . $token . '.pdf';

        file_put_contents($htmlPath, $html);

        try {
            $htmlUrl = 'file:///' . str_replace('\\', '/', $htmlPath);
            $result = Process::timeout(90)->run([
                $browserBinary,
                '--headless=new',
                '--disable-gpu',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--no-first-run',
                '--no-default-browser-check',
                '--allow-file-access-from-files',
                '--print-to-pdf=' . $pdfPath,
                $htmlUrl,
            ]);

            if (! $result->successful() || ! is_file($pdfPath)) {
                @unlink($pdfPath);
                abort(500, 'تعذر إنشاء ملف PDF. يرجى التأكد من توفر محرك PDF على الخادم.');
            }

            return response()
                ->download($pdfPath, $filename, [
                    'Content-Type' => 'application/pdf',
                    'X-Content-Type-Options' => 'nosniff',
                ])
                ->deleteFileAfterSend(true);
        } finally {
            @unlink($htmlPath);
        }
    }

    public function schoolLogoDataUri(?School $school): ?string
    {
        if (! $school) {
            return null;
        }

        $path = $this->normalizePublicStoragePath((string) ($school->logo_path ?? ''));
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

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

        return 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($path));
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

    private function resolveHeadlessBrowserBinary(): ?string
    {
        $candidates = [
            env('HEADLESS_BROWSER_BINARY'),
            env('CHROME_BIN'),
            env('CHROMIUM_PATH'),
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/snap/bin/chromium',
        ];

        foreach ($candidates as $candidate) {
            $path = trim((string) $candidate);
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
