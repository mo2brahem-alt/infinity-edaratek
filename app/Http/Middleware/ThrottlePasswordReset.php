<?php

namespace App\Http\Middleware;

use App\Services\Mobile\NativeRateLimiter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class ThrottlePasswordReset
{
    public function handle(Request $request, Closure $next)
    {
        if (($retry = app(NativeRateLimiter::class)->consumeReset($request)) !== null) {
            throw new TooManyRequestsHttpException($retry, 'Too many requests.');
        }
        return $next($request);
    }
}
