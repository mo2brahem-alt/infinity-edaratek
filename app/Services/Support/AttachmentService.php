<?php

namespace App\Services\Support;

use App\Models\Attachment;
use App\Models\User;
use App\Support\UploadSecurity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AttachmentService
{
    public const DEFAULT_DISK = 'school_attachments';
    public const MAX_UPLOAD_FILES = 10;
    public const MAX_FILE_SIZE_KB = 10240;

    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadValidationRules(
        string $strictFlag = 'features.uploads.strict_school_attachment_validation',
        string $mimeTypesConfig = 'features.uploads.school_attachment_mime_types'
    ): array {
        $rules = [
            'attachments' => ['nullable', 'array', 'max:' . self::MAX_UPLOAD_FILES],
            'attachments.*' => ['file', 'max:' . self::MAX_FILE_SIZE_KB],
        ];

        if ($this->strictValidationEnabled($strictFlag)) {
            $mimeTypes = $this->allowedMimeTypes($mimeTypesConfig);
            if ($mimeTypes !== []) {
                $rules['attachments.*'][] = 'mimetypes:' . implode(',', $mimeTypes);
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function uploadValidationMessages(): array
    {
        return [
            'attachments.array' => 'تنسيق المرفقات غير صالح.',
            'attachments.max' => 'لا يمكن رفع أكثر من 10 مرفقات في العملية الواحدة.',
            'attachments.*.file' => 'الملف المرفوع غير صالح.',
            'attachments.*.max' => 'حجم الملف يتجاوز الحد الأقصى المسموح وهو 10 ميجابايت.',
            'attachments.*.mimetypes' => 'نوع الملف غير مسموح.',
        ];
    }

    public function strictValidationEnabled(string $specificFlag): bool
    {
        return UploadSecurity::strictValidationEnabled($specificFlag);
    }

    /**
     * @return array<int, string>
     */
    public function allowedMimeTypes(string $configKey): array
    {
        return collect(config($configKey, []))
            ->map(fn ($mime) => trim((string) $mime))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param array<int, UploadedFile|null> $files
     * @param array<string, mixed> $context
     * @return Collection<int, Attachment>
     */
    public function storeManyForAttachable(Model $attachable, array $files, User $uploader, array $context = []): Collection
    {
        $normalizedFiles = collect($files)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->values();

        if ($normalizedFiles->isEmpty()) {
            return collect();
        }

        $schoolId = $this->resolveSchoolId($attachable, $context);
        $module = trim((string) ($context['module'] ?? ''));
        $actionType = trim((string) ($context['action_type'] ?? 'attachment'));
        $description = $this->nullIfEmpty($context['description'] ?? null);
        $metadata = $this->normalizeMetadata($context['metadata'] ?? null);
        $disk = trim((string) ($context['disk'] ?? self::DEFAULT_DISK));
        $isPrivate = array_key_exists('is_private', $context) ? (bool) $context['is_private'] : true;
        $baseDirectory = $this->defaultBaseDirectory($schoolId, $module);
        $storedFiles = [];
        $created = collect();

        if ($module === '') {
            throw new RuntimeException('لا يمكن حفظ المرفقات بدون تحديد الوحدة المرتبطة بها.');
        }

        try {
            foreach ($normalizedFiles as $file) {
                $originalName = trim((string) $file->getClientOriginalName());
                $extension = strtolower((string) ($file->guessExtension() ?: $file->clientExtension() ?: 'bin'));
                $storedName = Str::uuid()->toString() . ($extension !== '' ? '.' . $extension : '');
                $path = Storage::disk($disk)->putFileAs($baseDirectory, $file, $storedName);

                if (!is_string($path) || trim($path) === '') {
                    throw new RuntimeException('تعذر تخزين المرفق في المسار المحدد.');
                }

                $storedFiles[] = [
                    'disk' => $disk,
                    'path' => $path,
                ];

                $attachment = Attachment::query()->create([
                    'school_id' => $schoolId,
                    'ticket_message_id' => $context['ticket_message_id'] ?? null,
                    'attachable_type' => $attachable::class,
                    'attachable_id' => (int) $attachable->getKey(),
                    'module' => $module,
                    'action_type' => $actionType,
                    'uploaded_by' => (int) $uploader->id,
                    'file_name' => $originalName !== '' ? $originalName : $storedName,
                    'stored_name' => $storedName,
                    'disk' => $disk,
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'extension' => $extension !== '' ? $extension : null,
                    'file_size' => (int) ($file->getSize() ?: 0),
                    'description' => $description,
                    'metadata' => $metadata,
                    'is_private' => $isPrivate,
                ]);

                $created->push($attachment);

                $this->auditLogger->log(
                    'attachments.uploaded',
                    'attachment',
                    (int) $attachment->id,
                    [
                        'school_id' => $schoolId,
                        'module' => $module,
                        'action_type' => $actionType,
                        'attachable_type' => $attachable::class,
                        'attachable_id' => (int) $attachable->getKey(),
                        'file_name' => $attachment->file_name,
                        'file_size' => (int) $attachment->file_size,
                        'mime_type' => (string) $attachment->mime_type,
                    ],
                    $context['request'] instanceof Request ? $context['request'] : null,
                    (int) $uploader->id
                );
            }
        } catch (Throwable $exception) {
            foreach ($storedFiles as $storedFile) {
                Storage::disk((string) $storedFile['disk'])->delete((string) $storedFile['path']);
            }

            throw $exception;
        }

        return $created;
    }

    public function deleteInstitutionalAttachment(Attachment $attachment, ?Request $request = null, ?int $userId = null): void
    {
        $disk = $this->attachmentDisk($attachment);
        $path = (string) ($attachment->file_path ?? '');

        if ($path !== '') {
            Storage::disk($disk)->delete($path);
        }

        $attachment->delete();

        $this->auditLogger->log(
            'attachments.deleted',
            'attachment',
            (int) $attachment->id,
            [
                'school_id' => (int) ($attachment->school_id ?? 0),
                'module' => (string) ($attachment->module ?? ''),
                'action_type' => (string) ($attachment->action_type ?? ''),
                'attachable_type' => (string) ($attachment->attachable_type ?? ''),
                'attachable_id' => (int) ($attachment->attachable_id ?? 0),
                'file_name' => (string) ($attachment->file_name ?? ''),
            ],
            $request,
            $userId
        );
    }

    public function downloadInstitutionalAttachment(Attachment $attachment): BinaryFileResponse|StreamedResponse
    {
        $disk = $this->attachmentDisk($attachment);
        $path = (string) ($attachment->file_path ?? '');

        if ($path === '' || !Storage::disk($disk)->exists($path)) {
            abort(404, 'المرفق المطلوب غير موجود.');
        }

        return Storage::disk($disk)->download(
            $path,
            (string) ($attachment->file_name ?: $attachment->stored_name ?: basename($path))
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeForUi(Attachment $attachment): array
    {
        return [
            'id' => (int) $attachment->id,
            'file_name' => (string) ($attachment->file_name ?? ''),
            'file_size' => (int) ($attachment->file_size ?? 0),
            'mime_type' => (string) ($attachment->mime_type ?? ''),
            'description' => $attachment->description,
            'module' => (string) ($attachment->module ?? ''),
            'action_type' => (string) ($attachment->action_type ?? ''),
            'uploaded_at' => optional($attachment->created_at)->toISOString(),
            'uploaded_by' => $attachment->uploader?->name,
            'download_url' => route('school.attachments.download', ['attachment' => (int) $attachment->id], false),
        ];
    }

    public function attachmentDisk(Attachment $attachment): string
    {
        return trim((string) ($attachment->disk ?: self::DEFAULT_DISK)) ?: self::DEFAULT_DISK;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function resolveSchoolId(Model $attachable, array $context): int
    {
        $explicitSchoolId = (int) ($context['school_id'] ?? 0);
        if ($explicitSchoolId > 0) {
            return $explicitSchoolId;
        }

        $schoolId = (int) data_get($attachable, 'school_id', 0);
        if ($schoolId <= 0) {
            throw new RuntimeException('لا يمكن تحديد المدرسة المرتبطة بهذا المرفق.');
        }

        return $schoolId;
    }

    /**
     * @param mixed $metadata
     * @return array<string, mixed>|null
     */
    private function normalizeMetadata(mixed $metadata): ?array
    {
        if (!is_array($metadata) || $metadata === []) {
            return null;
        }

        return $metadata;
    }

    private function defaultBaseDirectory(int $schoolId, string $module): string
    {
        return trim(implode('/', [
            $schoolId,
            trim($module, '/'),
            now()->format('Y'),
            now()->format('m'),
        ]), '/');
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }
}
