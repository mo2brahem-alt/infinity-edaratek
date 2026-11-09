<?php

namespace App\Support;

use Illuminate\Filesystem\Filesystem;
use Throwable;

class PublicStorageLinkMaintainer
{
    public function __construct(
        private readonly Filesystem $files,
    ) {
    }

    public function ensure(): void
    {
        $targetPath = storage_path('app/public');
        $publicStoragePath = public_path('storage');
        // Never migrate/delete/copy legacy data as an application-boot side effect.
        if ($this->files->exists($publicStoragePath) || is_link($publicStoragePath)) { return; }
        if (! $this->files->isDirectory($targetPath)) { $this->files->makeDirectory($targetPath, 0755, true); }
        try { $this->files->link($targetPath, $publicStoragePath); }
        catch (Throwable $error) { report($error); } // Laravel's public-asset fallback remains available.
    }

    private function isBrokenLink(string $path): bool
    {
        return is_link($path) && ! $this->files->exists($path);
    }
}
