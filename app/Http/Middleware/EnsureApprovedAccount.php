<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $user = app(\App\Services\Mobile\AccountAccessEvaluator::class)->freshUser((int) $user->getKey());
            if (! $user) {
                Auth::guard('web')->logout();
                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
                return redirect()->route('login');
            }
            Auth::guard('web')->setUser($user);
        }

        if (! $user || $user->hasApprovedAccountAccess()) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        \App\Support\AuthSecurityLog::event('ACCOUNT_ACCESS_DENIED', (int) $user->id);

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()
            ->route('login')
            ->with('warning', $user->approvalBlockedMessage());
    }
}
