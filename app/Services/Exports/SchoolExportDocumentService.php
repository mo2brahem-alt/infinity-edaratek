<?php

namespace App\Services\Exports;

use App\Models\School;
use App\Models\User;
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
}
