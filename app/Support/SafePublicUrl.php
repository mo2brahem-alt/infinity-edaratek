<?php

namespace App\Support;

class SafePublicUrl
{
    /**
     * @param  array<int, string>  $allowedSchemes
     */
    public static function normalize(?string $value, array $allowedSchemes = ['http', 'https', 'mailto', 'tel']): ?string
    {
        $decoded = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $trimmed = trim($decoded);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F]/u', $trimmed) === 1) {
            return null;
        }

        $collapsed = strtolower((string) preg_replace('/\s+/', '', $trimmed));
        foreach (['javascript:', 'vbscript:', 'data:'] as $blockedScheme) {
            if (str_starts_with($collapsed, $blockedScheme)) {
                return null;
            }
        }

        if (str_starts_with($trimmed, '//')) {
            return null;
        }

        if (
            str_starts_with($trimmed, '#')
            || str_starts_with($trimmed, '/')
            || str_starts_with($trimmed, './')
            || str_starts_with($trimmed, '../')
            || str_starts_with($trimmed, '?')
        ) {
            return $trimmed;
        }

        $scheme = parse_url($trimmed, PHP_URL_SCHEME);
        if ($scheme === null) {
            return $trimmed;
        }

        return in_array(strtolower((string) $scheme), $allowedSchemes, true) ? $trimmed : null;
    }

    public static function isAllowed(?string $value): bool
    {
        return self::normalize($value) !== null;
    }
}
