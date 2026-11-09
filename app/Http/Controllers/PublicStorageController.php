<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicStorageController extends Controller
{
    public function show(Request $request, string $path)
    {
        $normalizedPath = $this->normalizePublicDiskPath($path);

        if ($normalizedPath === null || ! \App\Support\PublicAssetPath::allows($normalizedPath)
            || \App\Services\Support\PrivateFileClassification::hasSensitiveReference($normalizedPath) || ! Storage::disk('public')->exists($normalizedPath)) {
            abort(404, '', ['Cache-Control' => 'private, no-store']);
        }

        $absolutePath = Storage::disk('public')->path($normalizedPath);

        $root = realpath(Storage::disk('public')->path(''));
        $real = realpath($absolutePath);
        if (! $root || ! $real || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR) || ! is_file($absolutePath)) {
            abort(404);
        }

        return response()->file($absolutePath, [
            'Cache-Control' => 'public, max-age=604800',
            'Content-Type' => Storage::disk('public')->mimeType($normalizedPath) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function normalizePublicDiskPath(string $path): ?string
    {
        $path = str_replace('\\', '/', rawurldecode($path));
        $path = preg_replace('#/{2,}#', '/', $path) ?? $path;
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, "\0")) {
            return null;
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (str_starts_with($path, 'media-files/')) {
            $path = substr($path, strlen('media-files/'));
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $path;
    }
}
