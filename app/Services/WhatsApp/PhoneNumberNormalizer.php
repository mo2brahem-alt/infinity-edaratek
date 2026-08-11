<?php

namespace App\Services\WhatsApp;

class PhoneNumberNormalizer
{
    public function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '9665') && strlen($digits) === 12) {
            return $digits;
        }

        if (str_starts_with($digits, '05') && strlen($digits) === 10) {
            return '966' . substr($digits, 1);
        }

        if (str_starts_with($digits, '5') && strlen($digits) === 9) {
            return '966' . $digits;
        }

        if (
            strlen($digits) === 12
            && preg_match('/^201[0125]\d{8}$/', $digits) === 1
        ) {
            return $digits;
        }

        if (
            strlen($digits) === 11
            && preg_match('/^01[0125]\d{8}$/', $digits) === 1
        ) {
            return '2' . $digits;
        }

        return null;
    }
}
