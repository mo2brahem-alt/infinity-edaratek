<?php

namespace App\Services\Mobile;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class NativeRateLimiter
{
    public function loginAllowed(Request $request, ?User $user = null): ?int
    {
        $keys = [
            [$this->key('login-ip', $request->ip()), (int) config('mobile.login_ip_attempts_per_minute')],
        ];

        if ($user instanceof User) {
            $keys[] = [$this->key('login-account', (string) $user->getKey()), (int) config('mobile.login_attempts_per_minute')];
        }

        return $this->availableFor($keys);
    }

    public function recordLogin(Request $request, ?User $user = null): void
    {
        RateLimiter::hit($this->key('login-ip', $request->ip()), 60);

        if ($user instanceof User) {
            RateLimiter::hit($this->key('login-account', (string) $user->getKey()), 60);
        }
    }

    public function invalidCredentialsAllowed(Request $request, string $email): ?int
    {
        return $this->availableFor([[
            $this->key('login-failed', mb_strtolower($email).'|'.$request->ip()),
            (int) config('mobile.login_attempts_per_minute'),
        ]]);
    }

    public function recordInvalidCredentials(Request $request, string $email): void
    {
        RateLimiter::hit($this->key('login-failed', mb_strtolower($email).'|'.$request->ip()), 60);
        RateLimiter::hit($this->key('login-ip', $request->ip()), 60);
    }

    public function clearInvalidCredentials(Request $request, string $email): void
    {
        RateLimiter::clear($this->key('login-failed', mb_strtolower($email).'|'.$request->ip()));
    }

    public function requestAllowed(Request $request, User $user, string $kind): ?int
    {
        $accountLimit = (int) config("mobile.native_{$kind}_attempts_per_minute");
        $ipLimit = (int) config("mobile.native_{$kind}_ip_attempts_per_minute");

        return $this->availableFor([
            [$this->key("{$kind}-account", (string) $user->getKey()), $accountLimit],
            [$this->key("{$kind}-ip", $request->ip()), $ipLimit],
        ]);
    }

    public function recordRequest(Request $request, User $user, string $kind): void
    {
        RateLimiter::hit($this->key("{$kind}-account", (string) $user->getKey()), 60);
        RateLimiter::hit($this->key("{$kind}-ip", $request->ip()), 60);
    }

    /** @param list<array{0:string,1:int}> $limits */
    private function availableFor(array $limits): ?int
    {
        foreach ($limits as [$key, $limit]) {
            if (RateLimiter::tooManyAttempts($key, max(1, $limit))) {
                return max(1, RateLimiter::availableIn($key));
            }
        }

        return null;
    }

    private function key(string $scope, string $subject): string
    {
        return 'mobile:'.hash_hmac('sha256', $scope.'|'.$subject, (string) config('app.key'));
    }
}
