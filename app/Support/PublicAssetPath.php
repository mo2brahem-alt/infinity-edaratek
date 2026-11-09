<?php

namespace App\Support;

final class PublicAssetPath
{
    // Purpose-specific public writers: CMS, branding and chosen profile images.
    public const PREFIXES = ['uploads/', 'settings/', 'schools/logos/', 'profile-photos/'];

    public static function normalize(string $path): ?string
    {
        if ($path === '' || preg_match('/[\x00-\x1f\x7f\\\\:]/', $path) || str_starts_with($path, '/')) { return null; }
        if (rawurldecode($path) !== $path || str_contains($path, '%')) { return null; }
        foreach (explode('/', $path) as $part) { if (in_array($part, ['', '.', '..'], true)) { return null; } }
        return $path;
    }

    public static function allows(string $path): bool
    {
        if (self::normalize($path) === null) { return false; }
        foreach (self::PREFIXES as $prefix) { if (str_starts_with($path, $prefix)) { return true; } }
        return false;
    }
}
