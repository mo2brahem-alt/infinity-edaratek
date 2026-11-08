<?php

return [
    // Native access tokens are deliberately finite. This does not change the
    // lifetime of any existing Sanctum consumer.
    'native_token_expiration_minutes' => max(1, (int) env('MOBILE_NATIVE_TOKEN_EXPIRATION_MINUTES', 1440)),
    'login_attempts_per_minute' => 5,
    'login_ip_attempts_per_minute' => 30,
    'native_read_attempts_per_minute' => 120,
    'native_read_ip_attempts_per_minute' => 300,
    'native_session_attempts_per_minute' => 10,
    'native_session_ip_attempts_per_minute' => 30,
];
