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

        if (! $this->files->isDirectory($targetPath)) {
            $this->files->makeDirectory($targetPath, 0755, true);
        }

        if ($this->isBrokenLink($publicStoragePath)) {
            $this->files->delete($publicStoragePath);
        }

        if ($this->files->exists($publicStoragePath)) {
            if (is_link($publicStoragePath)) {
                return;
            }

            if (! $this->files->isDirectory($publicStoragePath)) {
                return;
            }

            $this->files->copyDirectory($publicStoragePath, $targetPath);
            $this->files->deleteDirectory($publicStoragePath);
        }

        try {
            $this->files->link($targetPath, $publicStoragePath);
        } catch (Throwable $throwable) {
            report($throwable);

            if (! $this->files->exists($publicStoragePath)) {
                $this->files->copyDirectory($targetPath, $publicStoragePath);
            }
        }
    }

    private function isBrokenLink(string $path): bool
    {
        return is_link($path) && ! $this->files->exists($path);
    }
}
