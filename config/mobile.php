<?php

return [
    // Frozen v1 values: atomically reserve identifier/account AND IP budgets.
    // Shared stores must support locks; no unlocked/fail-open fallback is allowed.
    'password_reset_identifier_per_hour' => 3,
    'password_reset_ip_per_hour' => 10,
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
