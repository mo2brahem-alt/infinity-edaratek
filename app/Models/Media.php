<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $guarded = [];

    // Accessor للحصول على الرابط الكامل
    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        $rawPath = trim((string) $this->file_path);

        if ($rawPath === '') {
            return null;
        }

        if (str_starts_with($rawPath, 'http://') || str_starts_with($rawPath, 'https://')) {
            return $rawPath;
        }

        if ($this->exists && $this->getKey()) {
            return '/admin/media/' . $this->getKey() . '/preview';
        }

        $path = $this->normalizePublicPath($rawPath);

        return $path ? '/media-files/' . $path : null;
    }

    private function normalizePublicPath(string $path): ?string
    {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^/?public/storage/#i', '', $path) ?? $path;
        $path = preg_replace('#^/?storage/#i', '', $path) ?? $path;
        $path = preg_replace('#^/?media-files/#i', '', $path) ?? $path;

        return ltrim($path, '/');
    }
}
