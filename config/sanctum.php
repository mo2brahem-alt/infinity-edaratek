<?php

use Laravel\Sanctum\Sanctum;

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
    ))),

    // Kept for existing Sanctum users. Native API routes never consult this
    // session-capable guard and inspect a Bearer token themselves.
    'guard' => ['web'],

    // Do not impose a global expiry on future non-native Sanctum consumers.
    'expiration' => null,

    // A separate non-null native policy is consumed when a native PAT is
    // created and again when it is authenticated.
    'native_expiration' => max(1, (int) env('MOBILE_NATIVE_TOKEN_EXPIRATION_MINUTES', 1440)),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
