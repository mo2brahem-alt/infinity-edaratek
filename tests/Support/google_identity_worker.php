<?php

// Only spawned by GoogleIdentityConcurrencyTest against an owned disposable DB.
if (getenv('APP_ENV') !== 'testing'
    || ! in_array(getenv('DB_HOST'), ['127.0.0.1', 'mysql'], true)
    || ! preg_match('/^edaratek_(?:google|test)_[a-f0-9]{12}$/D', (string) getenv('DB_DATABASE'))) {
    fwrite(STDERR, 'ISOLATION_PROFILE_REJECTED');
    exit(3);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.google-concurrency-absent');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
[$script, $action, $userId, $barrier, $marker] = $argv;
$allowed = realpath(storage_path('framework/testing'));
if (! $allowed || dirname($barrier) !== $allowed || dirname($marker) !== $allowed) {
    exit(3);
}
touch($marker);
$deadline = microtime(true) + 10;
while (! is_file($barrier) && microtime(true) < $deadline) {
    usleep(10000);
}
if (! is_file($barrier)) {
    exit(4);
}
try {
    if ($action === 'budget') {
        $root = getenv('GOOGLE_TEST_CACHE_DIR');
        if (! is_string($root) || realpath(dirname($root)) !== $allowed || ! preg_match('/^google-budget-[a-f0-9-]{36}$/D', basename($root))) {
            exit(3);
        }
        config(['cache.limiter' => 'google_race_budget', 'cache.stores.google_race_budget' => ['driver' => 'file', 'path' => $root, 'lock_path' => $root]]);
        $retry = app(App\Services\Mobile\NativeRateLimiter::class)->consumeGoogleIdentity(config('firebase_google.project_id'), 'concurrency-firebase', (int) $userId);
        echo $retry === null ? 'OK' : 'RATE_LIMITED';
        exit(0);
    }
    $service = app(App\Services\Mobile\Google\GoogleIdentityService::class);
    $proof = new App\Services\Mobile\Google\VerifiedGoogleIdentity(config('firebase_google.project_id'), 'concurrency-firebase', 'concurrency-google');
    match ($action) {
        'link' => $service->link((int) $userId, 'Synthetic concurrency-only password', $proof),
        'unlink' => $service->unlink((int) $userId, 'Synthetic concurrency-only password'),
        'login' => $service->login($proof, (string) Illuminate\Support\Str::uuid(), 'Synthetic concurrent client'),
        default => throw new RuntimeException('Unexpected worker operation'),
    };
    echo 'OK';
} catch (App\Services\Mobile\Google\GoogleAuthFailure $failure) {
    echo $failure->errorCode;
} catch (Throwable) {
    echo 'WORKER_FAILURE';
    exit(2);
}
