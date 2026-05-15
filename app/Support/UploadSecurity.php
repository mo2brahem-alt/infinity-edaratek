<?php

namespace App\Support;

class UploadSecurity
{
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
