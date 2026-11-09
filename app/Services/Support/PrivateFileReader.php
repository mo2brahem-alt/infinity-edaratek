<?php

namespace App\Services\Support;

use App\Support\PublicAssetPath;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PrivateFileReader
{
    public function physicalPath(string $disk, string $path): string
    {
        abort_unless(in_array($disk, ['local', 'school_attachments'], true) && PublicAssetPath::normalize($path) !== null, 404);
        $root = realpath(Storage::disk($disk)->path(''));
        $file = realpath(Storage::disk($disk)->path($path));
        abort_unless($root && $file && is_file($file) && str_starts_with($file, $root.DIRECTORY_SEPARATOR), 404);
        return $file;
    }

    public function download(string $disk, string $path, string $name): StreamedResponse
    {
        $file = $this->physicalPath($disk, $path);
        $name = basename(str_replace('\\', '/', $name));
        $name = trim(preg_replace('/[\x00-\x1f\x7f]/u', '', $name) ?? '');
        if ($name === '' || in_array($name, ['.', '..'], true)) { $name = 'attachment'; }
        return Storage::disk($disk)->download($path, $name, [
            'Content-Type' => (new \finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
