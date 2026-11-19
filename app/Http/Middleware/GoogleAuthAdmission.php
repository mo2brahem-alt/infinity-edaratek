<?php

namespace App\Http\Middleware;

use App\Services\Mobile\NativeRateLimiter;
use App\Support\NativeApiResponse;
use Closure;
use Illuminate\Http\Request;

final class GoogleAuthAdmission
{
    public function __construct(private readonly NativeRateLimiter $limiter) {}

    public function handle(Request $request, Closure $next, string $kind = 'login')
    {
        // Execute before validation/verification so malformed proofs cannot bypass admission.
        if (strlen($request->getContent()) > 20480) {
            return NativeApiResponse::error('PAYLOAD_TOO_LARGE', 413);
        }
        if (! $request->isJson()) {
            return NativeApiResponse::error('BAD_REQUEST', 400);
        }
        $retry = $kind === 'login' ? $this->limiter->consumeGoogleIp($request)
            : $this->limiter->consumeGoogleReauthentication($request, $request->user());
        if ($retry !== null) {
            return NativeApiResponse::error('RATE_LIMITED', 429, null, ['Retry-After' => (string) $retry]);
        }

        return $next($request);
    }
}
