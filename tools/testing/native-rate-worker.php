<?php

// Isolated concurrency fixture only. No provider, queue, real data or PHPUnit execution.
ini_set('display_errors', '0');
ob_start();
require __DIR__.'/assert-environment.php'; // argv[1]=boot; rejects before unsafe bootstrap.
$kind = $argv[2] ?? '';
$root = $argv[3] ?? '';
$worker = $argv[4] ?? '';
if (! in_array($kind, ['login', 'read', 'session', 'reset'], true)
    || realpath(dirname($root)) !== realpath(storage_path('framework/testing'))
    || ! preg_match('/^p7-rate-[a-f0-9-]{36}$/D', basename($root)) || ! preg_match('/^[1-6]$/D', $worker)) { exit(21); }
config(['cache.limiter' => 'phase7_rate', 'cache.stores.phase7_rate' => ['driver' => 'file', 'path' => $root, 'lock_path' => $root]]);
file_put_contents($root.'/ready-'.$worker, 'ready');
$deadline = microtime(true) + 15;
while (! is_file($root.'/start')) { if (microtime(true) > $deadline) { exit(22); } usleep(10000); }
$request = \Illuminate\Http\Request::create('/', 'POST', ['email' => 'synthetic-limit@example.test'], [], [], ['REMOTE_ADDR' => '192.0.2.'.$worker]);
$user = (new \App\Models\User)->forceFill(['id' => 123]);
$limiter = app(\App\Services\Mobile\NativeRateLimiter::class);
$retry = match ($kind) { 'login' => $limiter->consumeLogin($request, 'synthetic-limit@example.test'),
    'reset' => $limiter->consumeReset($request), default => $limiter->consumeRequest($request, $user, $kind) };
ob_end_clean();
echo json_encode(['fixture' => 'phase7_atomic_budget', 'admitted' => $retry === null]).PHP_EOL;
exit($retry === null ? 0 : 10);
