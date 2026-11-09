<?php

namespace App\Services\Mobile;

use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class NativeRateLimiter
{
    public function consumeLogin(Request $request, string $email): ?int
    {
        // All attempts count, including unknown identities and rotating IPs.
        return $this->consume([
            [$this->key('login-identity', mb_strtolower(trim($email))), (int) config('mobile.login_attempts_per_minute'), 60],
            [$this->key('login-ip', (string) $request->ip()), (int) config('mobile.login_ip_attempts_per_minute'), 60],
        ]);
    }

    public function consumeRequest(Request $request, User $user, string $kind): ?int
    {
        if (! in_array($kind, ['read', 'session'], true)) { throw new \InvalidArgumentException('Unknown limiter'); }
        return $this->consume([
            [$this->key($kind.'-account', (string) $user->getKey()), (int) config("mobile.native_{$kind}_attempts_per_minute"), 60],
            [$this->key($kind.'-ip', (string) $request->ip()), (int) config("mobile.native_{$kind}_ip_attempts_per_minute"), 60],
        ]);
    }

    public function consumeReset(Request $request): ?int
    {
        $email = $request->input('email');
        $identity = is_string($email) ? mb_strtolower(trim($email)) : 'invalid';
        return $this->consume([
            [$this->key('reset-identity', $identity), (int) config('mobile.password_reset_identifier_per_hour'), 3600],
            [$this->key('reset-ip', (string) $request->ip()), (int) config('mobile.password_reset_ip_per_hour'), 3600],
        ]);
    }

    /** @param list<array{0:string,1:int,2:int}> $limits */
    private function consume(array $limits): ?int
    {
        $cache = Cache::store(config('cache.limiter') ?: config('cache.default'));
        $limiter = new RateLimiter($cache);
        $locks = [];
        usort($limits, static fn ($a, $b) => strcmp($a[0], $b[0]));
        try {
            // Lock each shared identity/IP key in deterministic order. Never fall back
            // to unlocked admission on timeout, unavailable cache or missing lock support.
            foreach ($limits as [$key]) {
                $lock = $cache->lock('admission:'.$key, 10);
                $lock->block(2);
                $locks[] = $lock;
            }
            foreach ($limits as [$key, $maximum]) {
                if ($limiter->tooManyAttempts($key, max(1, $maximum))) {
                    return max(1, $limiter->availableIn($key));
                }
            }
            foreach ($limits as [$key, , $window]) { $limiter->hit($key, $window); }
            return null;
        } catch (LockTimeoutException) {
            return 1;
        } finally {
            foreach (array_reverse($locks) as $lock) { $lock->release(); }
        }
    }

    private function key(string $scope, string $subject): string
    {
        return 'mobile:'.hash_hmac('sha256', $scope.'|'.$subject, (string) config('app.key'));
    }
}
