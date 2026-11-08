<?php

return [
    // Operator-only first provisioning. Never expose these inputs via HTTP.
    'super_admin' => [
        'enabled' => env('EDARATEK_ADMIN_PROVISIONING_ENABLED', false),
        'email' => env('EDARATEK_ADMIN_PROVISIONING_EMAIL'),
        'name' => env('EDARATEK_ADMIN_PROVISIONING_NAME'),
        'password' => env('EDARATEK_ADMIN_PROVISIONING_PASSWORD'),
    ],
];
