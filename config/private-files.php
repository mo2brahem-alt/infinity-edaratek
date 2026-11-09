<?php

return [
    // Default dry-run; an operator must approve environment/data scope separately.
    'migration_enabled' => env('PRIVATE_FILE_MIGRATION_ENABLED', false),
];
