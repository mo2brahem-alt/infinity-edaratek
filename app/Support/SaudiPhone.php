<?php

namespace App\Support;

class SaudiPhone
{
    /**
     * Normalize supported Saudi mobile formats to canonical "05XXXXXXXX".
     *
     * Accepted examples:
     * - 05XXXXXXXX
     * - 5XXXXXXXX
     * - +9665XXXXXXXX
     * - 009665XXXXXXXX
     */
    public static function normalizeMobile(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $raw = self::toLatinDigits($raw);
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (preg_match('/^05\d{8}$/', $digits) === 1) {
            return $digits;
        }

        if (preg_match('/^5\d{8}$/', $digits) === 1) {
            return '0'.$digits;
        }

        if (preg_match('/^9665\d{8}$/', $digits) === 1) {
            return '0'.substr($digits, 3);
        }

        if (preg_match('/^009665\d{8}$/', $digits) === 1) {
            return '0'.substr($digits, 5);
        }

        return null;
    }

    public static function isValidMobile(mixed $value): bool
    {
        return self::normalizeMobile($value) !== null;
    }

    private static function toLatinDigits(string $value): string
    {
        $arabicIndic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $easternArabicIndic = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $latinDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace(
            [...$arabicIndic, ...$easternArabicIndic],
            [...$latinDigits, ...$latinDigits],
            $value
        );
    }
}

