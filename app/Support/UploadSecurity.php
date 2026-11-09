<?php

namespace App\Support;

class UploadSecurity
{
    public static function validateProtected(\Illuminate\Http\UploadedFile $file, string $mimeConfig, string $field = 'attachments'): void
    {
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $extension = strtolower($file->clientExtension());
        if (! $file->isValid() || (int) $file->getSize() > 10240 * 1024 || ! in_array($mime, (array) config($mimeConfig, []), true)
            || in_array($extension, ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'js', 'html', 'htm', 'exe', 'sh', 'bat', 'svg'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([$field => ['نوع الملف أو حجمه غير مسموح.']]);
        }
    }
    public static function strictValidationEnabled(?string $specificFlag = null): bool
    {
        $specificStrictMode = $specificFlag !== null
            ? (bool) config($specificFlag, true)
            : false;

        $globalStrictMode = (bool) config('features.uploads.strict_validation_enabled', true);

        if ($specificStrictMode || $globalStrictMode) {
            return true;
        }

        return !self::legacyUnsafeModeAllowed();
    }

    public static function legacyUnsafeModeAllowed(): bool
    {
        $environment = strtolower((string) config('app.env', 'production'));

        return in_array($environment, ['local', 'testing'], true);
    }
}
