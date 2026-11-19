<?php

return [
    'enabled' => (bool) env('FIREBASE_GOOGLE_ENABLED', false),
    'project_id' => 'my-project-79313-1716224806958',
    // Absolute, operator-managed file outside this checkout and document root.
    'credentials_file' => env('FIREBASE_GOOGLE_CREDENTIALS_FILE'),
    'max_auth_age_seconds' => 300,
];
