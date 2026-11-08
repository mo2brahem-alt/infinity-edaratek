<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Mobile\AccountAccessEvaluator;
use App\Support\NativeApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureNativeAccountAccess
{
    public function __construct(private readonly AccountAccessEvaluator $accounts)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return NativeApiResponse::error('UNAUTHENTICATED', 401);
        }

        $denialCode = $this->accounts->denialCode($user);

        if ($denialCode !== null) {
            \App\Support\AuthSecurityLog::event('NATIVE_ACCOUNT_DENIED', (int) $user->id);
            return NativeApiResponse::error($denialCode, 403);
        }

        return $next($request);
    }
}
