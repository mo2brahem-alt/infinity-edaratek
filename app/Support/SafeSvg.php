<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class SafeSvg
{
    /**
     * @var array<int, string>
     */
    private const BLOCKED_PATTERNS = [
        '/<\s*script\b/i',
        '/<\s*(?:iframe|object|embed|link|meta|base|foreignobject)\b/i',
        '/<\?\s*xml-stylesheet\b/i',
        '/\son[a-z0-9_-]+\s*=/i',
        '/(?:href|src|xlink:href)\s*=\s*["\']?\s*(?:javascript:|data:)/i',
        '/@import\b/i',
    ];

    public static function isSvg(UploadedFile $file): bool
    {
        $mimeType = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType()));
        $extension = strtolower((string) $file->getClientOriginalExtension());

        return $mimeType === 'image/svg+xml' || $extension === 'svg';
    }

    public static function assertUploadIsSafe(UploadedFile $file, string $field = 'file'): void
    {
        $contents = @file_get_contents((string) $file->getRealPath());

        if (! is_string($contents) || trim($contents) === '' || stripos($contents, '<svg') === false) {
            self::fail($field);
        }

        foreach (self::BLOCKED_PATTERNS as $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                self::fail($field);
            }
        }

        if (self::containsUnsafeCssUrl($contents)) {
            self::fail($field);
        }
    }

    private static function containsUnsafeCssUrl(string $contents): bool
    {
        if (preg_match_all('/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/i', $contents, $matches) !== false) {
            foreach ($matches[2] ?? [] as $rawUrl) {
                $url = strtolower(trim(html_entity_decode((string) $rawUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

                if ($url !== '' && str_starts_with($url, '#')) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    private static function fail(string $field): void
    {
        throw ValidationException::withMessages([
            $field => 'ملف SVG غير آمن. يرجى رفع شعار SVG بسيط بدون سكربتات أو روابط تنفيذية.',
        ]);
    }
}
