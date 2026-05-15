<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->buildContentSecurityPolicy());
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        return $response;
    }

    private function buildContentSecurityPolicy(): string
    {
        $scriptSources = ["'self'", "'unsafe-inline'"];
        $styleSources = ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'];
        $connectSources = ["'self'", 'https:'];
        $imgSources = ["'self'", 'data:', 'blob:', 'https:'];
        $fontSources = ["'self'", 'data:', 'https://fonts.gstatic.com'];

        if ($this->isLocalLikeEnvironment()) {
            $scriptSources[] = "'unsafe-eval'";

            foreach ($this->localDevelopmentOrigins() as $origin) {
                $scriptSources[] = $origin;
                $styleSources[] = $origin;
                $connectSources[] = $origin;

                if (str_starts_with($origin, 'http://')) {
                    $connectSources[] = 'ws://' . substr($origin, 7);
                }

                if (str_starts_with($origin, 'https://')) {
                    $connectSources[] = 'wss://' . substr($origin, 8);
                }
            }
        }

        $directives = [
            "default-src 'self'",
            'base-uri \'self\'',
            'form-action \'self\'',
            'frame-ancestors \'self\'',
            'object-src \'none\'',
            'script-src ' . implode(' ', array_unique($scriptSources)),
            'style-src ' . implode(' ', array_unique($styleSources)),
            'img-src ' . implode(' ', array_unique($imgSources)),
            'font-src ' . implode(' ', array_unique($fontSources)),
            'connect-src ' . implode(' ', array_unique($connectSources)),
        ];

        return implode('; ', $directives);
    }

    private function isLocalLikeEnvironment(): bool
    {
        $environment = strtolower((string) config('app.env', 'production'));

        return in_array($environment, ['local', 'testing'], true);
    }

    /**
     * @return array<int, string>
     */
    private function localDevelopmentOrigins(): array
    {
        $origins = [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
        ];

        $hotFile = public_path('hot');

        if (is_file($hotFile)) {
            $hotOrigin = trim((string) file_get_contents($hotFile));

            if ($hotOrigin !== '' && preg_match('/^https?:\\/\\//', $hotOrigin) === 1) {
                $origins[] = rtrim($hotOrigin, '/');
            }
        }

        return array_values(array_unique($origins));
    }
}
