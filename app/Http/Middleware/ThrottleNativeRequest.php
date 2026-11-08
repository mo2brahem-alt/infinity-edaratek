<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Mobile\NativeRateLimiter;
use App\Support\NativeApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ThrottleNativeRequest
{
    public function __construct(private readonly NativeRateLimiter $limiter)
    {
    }

    public function handle(Request $request, Closure $next, string $kind): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return NativeApiResponse::error('UNAUTHENTICATED', 401);
        }

        $retryAfter = $this->limiter->requestAllowed($request, $user, $kind);

        if ($retryAfter !== null) {
            return NativeApiResponse::error('RATE_LIMITED', 429, null, [
                'Retry-After' => (string) $retryAfter,
            ]);
        }

        $this->limiter->recordRequest($request, $user, $kind);

        return $next($request);
    }
}
