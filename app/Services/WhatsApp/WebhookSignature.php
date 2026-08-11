<?php

namespace App\Services\WhatsApp;

class WebhookSignature
{
    public function sign(string $body, string $timestamp, string $secret): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    public function verify(string $body, ?string $timestamp, ?string $signature, ?string $secret, int $toleranceSeconds = 300): bool
    {
        if (! $timestamp || ! $signature || ! $secret) {
            return false;
        }

        if (! ctype_digit($timestamp)) {
            return false;
        }

        $issuedAt = (int) $timestamp;
        $now = time();

        if ($issuedAt > $now || ($now - $issuedAt) > $toleranceSeconds) {
            return false;
        }

        $expected = $this->sign($body, $timestamp, $secret);
        $provided = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;

        return hash_equals($expected, $provided);
    }
}
