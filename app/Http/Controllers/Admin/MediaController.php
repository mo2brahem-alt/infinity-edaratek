<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Support\SafeSvg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    // جلب جميع الوسائط (JSON) للمودال
    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 0);
        $query = Media::query()->latest('id');

        if ($perPage > 0) {
            $paginator = $query->paginate($perPage)->appends($request->query());

            return response()->json([
                'data' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        }

        return response()->json($query->get());
    }

    // رفع ملف جديد
    public function store(Request $request)
    {
        $request->validate([
            'file' => $this->fileValidationRules(),
        ], [
            'file.mimetypes' => 'صيغة الملف غير مدعومة. الصيغ المسموحة تشمل JPG وPNG وWebP وGIF وSVG وملفات الفيديو المدعومة.',
            'file.max' => 'حجم الملف يتجاوز الحد المسموح.',
        ]);

        $file = $request->file('file');
        $mimeType = (string) ($file->getMimeType() ?: 'application/octet-stream');

        if (SafeSvg::isSvg($file)) {
            SafeSvg::assertUploadIsSafe($file);
            $mimeType = 'image/svg+xml';
        }

        $path = $file->store('uploads', 'public');
        $type = str_starts_with($mimeType, 'video/') ? 'video' : 'image';

        $media = Media::create([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $type,
            'mime_type' => $mimeType,
            'file_size' => $file->getSize(),
        ]);

        return response()->json($media);
    }

    public function preview(Media $media)
    {
        $path = $this->normalizePublicDiskPath((string) $media->file_path);

        if ($path === null || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $absolutePath = Storage::disk('public')->path($path);

        if (! is_file($absolutePath)) {
            abort(404);
        }

        return response()->file($absolutePath, [
            'Cache-Control' => 'private, max-age=300',
            'Content-Type' => Storage::disk('public')->mimeType($path) ?: $media->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // حذف ملف
    public function destroy($id)
    {
        $media = Media::findOrFail($id);
        Storage::disk('public')->delete($media->file_path);
        $media->delete();
        return response()->json(['message' => 'Deleted']);
    }

    /**
     * @return array<int, string>
     */
    private function fileValidationRules(): array
    {
        $rules = ['required', 'file', 'max:100000'];

        if ($this->strictUploadValidationEnabled()) {
            $mimeTypes = $this->allowedMediaMimeTypes();
            if (count($mimeTypes) > 0) {
                $rules[] = 'mimetypes:' . implode(',', $mimeTypes);
            }
        }

        return $rules;
    }

    private function strictUploadValidationEnabled(): bool
    {
        return (bool) config('features.uploads.strict_validation_enabled', false);
    }

    /**
     * @return array<int, string>
     */
    private function allowedMediaMimeTypes(): array
    {
        return collect(config('features.uploads.media_mime_types', []))
            ->map(fn ($mime) => trim((string) $mime))
            ->filter()
            ->values()
            ->all();
    }

    private function normalizePublicDiskPath(string $path): ?string
    {
        $path = str_replace('\\', '/', rawurldecode($path));
        $path = preg_replace('#/{2,}#', '/', $path) ?? $path;
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, "\0")) {
            return null;
        }

        foreach (['public/storage/', 'storage/', 'media-files/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $path;
    }
}
