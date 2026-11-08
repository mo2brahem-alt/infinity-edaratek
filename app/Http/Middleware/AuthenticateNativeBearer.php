<?php

namespace App\Http\Middleware;

use App\Services\Mobile\NativeSanctumAuthenticator;
use App\Support\NativeApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateNativeBearer
{
    public function __construct(private readonly NativeSanctumAuthenticator $authenticator)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->authenticator->authenticate($request);

        if ($user === null) {
            return NativeApiResponse::error('UNAUTHENTICATED', 401);
        }

        // This resolver is intentionally request-local. It never asks the web
        // guard to authenticate a native request and a web cookie cannot win.
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}
