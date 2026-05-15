<?php

namespace App\Support;

class PublicContentNormalizer
{
    public function __construct(
        private readonly PublicHtmlSanitizer $htmlSanitizer,
    ) {
    }

    public function sanitizeHtml(?string $content): string
    {
        return $this->htmlSanitizer->sanitize($content);
    }

    public function sanitizeComponentContent(?string $content): string
    {
        $value = trim((string) $content);

        if ($value === '') {
            return '';
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return $this->sanitizeHtml($value);
        }

        return (string) json_encode(
            $this->sanitizeArrayPayload($decoded),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    public function safeComponentHtmlForRender(?string $content): string
    {
        $value = trim((string) $content);

        if ($value === '') {
            return '';
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return '';
        }

        return $this->sanitizeHtml($value);
    }

    public function normalizeComponentContentForRender(?string $content, ?string $siteLogo = null): string
    {
        $value = trim((string) $content);

        if ($value === '') {
            return '';
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded) || array_is_list($decoded)) {
            return $value;
        }

        return (string) json_encode(
            $this->normalizeComponentPayload($decoded, $siteLogo),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * @param array<int, string> $shortcodes
     */
    public function stripShortcodes(?string $content, array $shortcodes): string
    {
        $value = (string) $content;

        if ($value === '' || $shortcodes === []) {
            return $value;
        }

        $normalizedShortcodes = array_values(array_filter(array_map(
            static fn (mixed $shortcode): string => is_string($shortcode) ? trim($shortcode) : '',
            $shortcodes
        )));

        if ($normalizedShortcodes === []) {
            return $value;
        }

        return str_replace($normalizedShortcodes, '', $value);
    }

    /**
     * @param  array<int|string, mixed>  $payload
     * @return array<int|string, mixed>
     */
    private function sanitizeArrayPayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sanitizeArrayPayload($value);
                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            if ($this->looksLikeUrlKey((string) $key)) {
                $payload[$key] = SafePublicUrl::normalize($value) ?? '';
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeComponentPayload(array $payload, ?string $siteLogo = null): array
    {
        $design = is_array($payload['design'] ?? null) ? $payload['design'] : [];

        $legacyBackgroundType = $this->normalizeLegacyBackgroundType($payload['bgType'] ?? null);
        if (($design['backgroundType'] ?? null) === null || ($design['backgroundType'] ?? null) === 'none') {
            if ($legacyBackgroundType !== null) {
                $design['backgroundType'] = $legacyBackgroundType;
            }
        }

        if (empty($design['backgroundColor']) && isset($payload['bgColor'])) {
            $design['backgroundColor'] = (string) $payload['bgColor'];
        }

        if (empty($design['backgroundGradient']) && isset($payload['bgGradient'])) {
            $design['backgroundGradient'] = (string) $payload['bgGradient'];
        }

        if (empty($design['backgroundImage']) && isset($payload['bgImage'])) {
            $design['backgroundImage'] = (string) $payload['bgImage'];
        }

        if (! isset($design['backgroundOpacity']) && isset($payload['bgImageOpacity'])) {
            $design['backgroundOpacity'] = (int) $payload['bgImageOpacity'];
        }

        if (empty($design['textAlign']) && isset($payload['alignment'])) {
            $design['textAlign'] = (string) $payload['alignment'];
        }

        if (! isset($design['titleSize']) && isset($payload['titleSize'])) {
            $design['titleSize'] = (int) $payload['titleSize'];
        }

        if (! isset($design['subtitleSize']) && isset($payload['subtitleSize'])) {
            $design['subtitleSize'] = (int) $payload['subtitleSize'];
        }

        if (! isset($design['imageFit']) && isset($payload['imageFit'])) {
            $design['imageFit'] = (string) $payload['imageFit'];
        }

        if (! isset($design['imageHeight']) && isset($payload['imageHeight'])) {
            $design['imageHeight'] = (int) $payload['imageHeight'];
        }

        if (! isset($design['imageWidth']) && isset($payload['imageWidth'])) {
            $design['imageWidth'] = (int) $payload['imageWidth'];
        }

        if (($payload['type'] ?? null) === 'section_title' && $siteLogo !== null && trim($siteLogo) !== '') {
            $normalizedLogo = $this->normalizeMediaPath($siteLogo);

            if ($this->isSameMediaPath($payload['image'] ?? null, $normalizedLogo)) {
                $payload['image'] = '';
            }

            if ($this->isSameMediaPath($payload['bgImage'] ?? null, $normalizedLogo)) {
                $payload['bgImage'] = '';
                $payload['bgType'] = 'none';
                if (($design['backgroundImage'] ?? null) !== null) {
                    $design['backgroundImage'] = '';
                }
                if (($design['backgroundType'] ?? null) !== null) {
                    $design['backgroundType'] = 'none';
                }
            }

            if ($this->isSameMediaPath($design['backgroundImage'] ?? null, $normalizedLogo)) {
                $design['backgroundImage'] = '';
                $design['backgroundType'] = 'none';
            }
        }

        $payload['design'] = $design;

        return $payload;
    }

    private function normalizeLegacyBackgroundType(mixed $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'color', 'gradient', 'image', 'none' => $normalized,
            default => null,
        };
    }

    private function normalizeMediaPath(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $normalized = trim($path);
        $normalized = preg_replace('#^https?://[^/]+#i', '', $normalized) ?? $normalized;
        $normalized = ltrim($normalized, '/');
        $normalized = preg_replace('#^storage/#i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('#^public/storage/#i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('#^media-files/#i', '', $normalized) ?? $normalized;

        return preg_replace('#^uploads/#i', 'uploads/', $normalized) ?? $normalized;
    }

    private function isSameMediaPath(?string $path, ?string $expected): bool
    {
        $normalizedPath = $this->normalizeMediaPath($path);

        return $normalizedPath !== null && $expected !== null && $normalizedPath === $expected;
    }

    private function looksLikeUrlKey(string $key): bool
    {
        $normalized = strtolower(trim($key));

        return in_array($normalized, ['url', 'href', 'btnurl', 'buttonurl', 'contacturl'], true)
            || str_ends_with($normalized, '_url')
            || str_ends_with($normalized, 'url');
    }
}
