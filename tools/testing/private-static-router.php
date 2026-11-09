<?php

// Representative HTTP serving policy: aliases reach Laravel BEFORE static-file lookup.
// Never run this test fixture server on shared/real data or use it as a production config.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_HOST') !== 'mysql') { http_response_code(503); exit; }
$root = realpath((string) getenv('EDARATEK_STATIC_ROOT'));
if (! $root || dirname($root) !== sys_get_temp_dir() || ! str_starts_with(basename($root), 'edaratek_phase5_')) { http_response_code(503); exit; }
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if (str_starts_with($uri, '/public/')) { http_response_code(404); header('Cache-Control: private, no-store'); exit; }
if (! preg_match('#^/(storage|media-files)/#', $uri)) { return false; }
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->usePublicPath($root);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
config(['filesystems.disks.public.root' => (string) getenv('EDARATEK_STATIC_PUBLIC_ROOT')]);
Illuminate\Support\Facades\Storage::forgetDisk('public');
// Match a front-controller rewrite even when a physical file already exists.
$_SERVER['SCRIPT_FILENAME'] = $root.'/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['PHP_SELF'] = '/index.php';
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request); $response->send(); $kernel->terminate($request, $response);
