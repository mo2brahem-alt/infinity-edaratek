<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class AuthSecurityLog
{
    public static function event(string $event, ?int $userId = null): void
    {
        if (! in_array($event, ['ACCOUNT_ACCESS_DENIED', 'APPROVAL_APPROVED', 'APPROVAL_REJECTED', 'NATIVE_AUTH_DENIED', 'NATIVE_ACCOUNT_DENIED'], true)) {
            return;
        }

        $key = 'auth-security:'.hash_hmac('sha256', $event.'|'.($userId ?? 'guest'), (string) config('app.key'));
        if (Cache::add($key, true, 60)) {
            Log::notice('auth.security', ['event' => $event, 'user_id' => $userId]);
        }
    }
}
