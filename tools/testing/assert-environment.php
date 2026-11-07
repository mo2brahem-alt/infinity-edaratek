<?php

// Standalone preflight BEFORE autoload/discovery/migrations; boot mode repeats it.
ini_set('display_errors', '0');
function phaseOneReject(string $category): never
{
    echo json_encode(['kind' => 'guard', 'status' => 'FAIL', 'category' => $category])."\n";
    exit(20);
}
set_exception_handler(static function (Throwable $exception): void {
    phaseOneReject('guard_exception'); // Never print exception messages/DSNs/env.
});
$mode = $argv[1] ?? 'preflight';
if (!in_array($mode, ['preflight', 'boot'], true)) { phaseOneReject('mode'); }
$expected = [
    'EDARATEK_ISOLATED_TEST' => '1', 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
    'APP_URL' => 'http://isolated.invalid', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => 'mysql',
    'DB_PORT' => '3306', 'DB_USERNAME' => 'edaratek_test', 'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
    'MAIL_HOST' => '127.0.0.1', 'MAIL_PORT' => '2525', 'FILESYSTEM_DISK' => 'local',
    'BROADCAST_CONNECTION' => 'null', 'N8N_WHATSAPP_ENABLED' => 'false',
];
foreach ($expected as $key => $value) {
    if (getenv($key) !== $value) { phaseOneReject('profile'); }
}
$database = getenv('DB_DATABASE') ?: '';
$appKey = getenv('APP_KEY') ?: '';
if (getcwd() !== '/app' || file_exists('/app/.env') || glob('/app/bootstrap/cache/*.php')
    || !preg_match('/^edaratek_test_[a-f0-9]{12}$/D', $database)
    || !preg_match('/^[a-f0-9]{64}$/D', getenv('DB_PASSWORD') ?: '')
    || !str_starts_with($appKey, 'base64:')
    || strlen(base64_decode(substr($appKey, 7), true) ?: '') !== 32) {
    phaseOneReject('workspace_or_ephemeral_identity');
}
foreach (['CONFIG', 'EVENTS', 'PACKAGES', 'ROUTES', 'SERVICES'] as $kind) {
    if (getenv('APP_'.$kind.'_CACHE') !== '/app/storage/framework/testing/'.strtolower($kind).'.php') {
        phaseOneReject('cache_path');
    }
}
// Exact env() names verified in config/services.php; no usable send credentials.
$emptyProviderSettings = [
    'N8N_WHATSAPP_WEBHOOK_URL', 'N8N_WHATSAPP_WEBHOOK_SECRET',
    'N8N_WHATSAPP_STATUS_SECRET', 'N8N_WHATSAPP_CLAIM_SECRET', 'META_WHATSAPP_VERIFY_TOKEN',
    'MAIL_URL', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'COMPOSER_AUTH', 'NPM_TOKEN', 'NODE_AUTH_TOKEN',
    'GITHUB_TOKEN', 'GH_TOKEN', 'ACTIONS_RUNTIME_TOKEN', 'SSH_AUTH_SOCK',
    'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_SESSION_TOKEN',
    'AWS_CONTAINER_CREDENTIALS_RELATIVE_URI', 'AWS_CONTAINER_CREDENTIALS_FULL_URI',
    'GOOGLE_APPLICATION_CREDENTIALS', 'FIREBASE_CREDENTIALS',
    'POSTMARK_API_KEY', 'RESEND_API_KEY', 'SLACK_BOT_USER_OAUTH_TOKEN',
];
foreach ($emptyProviderSettings as $key) {
    if ((getenv($key) ?: '') !== '') { phaseOneReject('provider_or_host_credential'); }
}
foreach (getenv() as $key => $value) {
    if (preg_match('/(?:edaratek\.com|infinity-4tech\.com)/i', $value)) {
        phaseOneReject('live_hostname');
    }
    // PHP image download metadata is not an application service endpoint.
    if (!in_array($key, ['PHP_URL', 'PHP_ASC_URL'], true)
        && preg_match('/(?:^|_)(?:URL|ENDPOINT|HOST|DSN|PROXY)$/i', $key)
        && $value !== '' && !in_array($key, ['APP_URL', 'DB_HOST', 'MAIL_HOST'], true)) {
        phaseOneReject('unexpected_service_endpoint');
    }
    if ($key !== 'DB_PASSWORD' && $key !== 'APP_KEY' && $value !== ''
        && preg_match('/(?:TOKEN|SECRET|PASSWORD|API_KEY|PRIVATE_KEY|CREDENTIALS)(?:_|$)/i', $key)) {
        phaseOneReject('unexpected_credential');
    }
}
// Build assets must originate from the isolated frontend Docker stage.
$manifestPath = '/app/public/build/manifest.json';
$manifest = is_file($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;
foreach (['resources/js/app.js', 'resources/css/app.css'] as $entry) {
    $file = $manifest[$entry]['file'] ?? '';
    if (!preg_match('~^assets/[A-Za-z0-9_./-]+$~D', $file) || str_contains($file, '..')
        || !is_file('/app/public/build/'.$file)) { phaseOneReject('vite_build'); }
}
if (!in_array('mysql', PDO::getAvailableDrivers(), true)) { phaseOneReject('pdo_mysql'); }
try {
    $db = new PDO('mysql:host=mysql;port=3306;dbname='.$database, 'edaratek_test', getenv('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $version = $db->query('SELECT VERSION()')->fetchColumn();
} catch (Throwable $exception) { phaseOneReject('isolated_database_unavailable'); }
if ($mode === 'boot') {
    require_once '/app/vendor/autoload.php';
    $app = require '/app/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    foreach (['app.env' => 'testing', 'app.debug' => false, 'database.default' => 'mysql',
        'database.connections.mysql.host' => 'mysql', 'database.connections.mysql.database' => $database,
        'cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync',
        'mail.default' => 'array', 'filesystems.default' => 'local',
        'services.n8n.whatsapp.enabled' => false] as $key => $value) {
        if (config($key) !== $value) { phaseOneReject('effective_application_config'); }
    }
    foreach (['webhook_url', 'webhook_secret', 'status_secret', 'claim_secret'] as $key) {
        if (!in_array(config('services.n8n.whatsapp.'.$key), [null, ''], true)) {
            phaseOneReject('effective_provider_config');
        }
    }
}
// Public reference API defaults stay unchanged for existing URL-matched Http::fake
// fixtures; runtime internal-network isolation prevents their real transport too.
echo json_encode(['kind' => 'guard', 'mode' => $mode, 'status' => 'PASS',
    'database' => $database, 'mysql_version' => $version, 'db_target' => 'ephemeral_internal_service',
    'cache' => 'array', 'queue' => 'sync_without_workers', 'mail' => 'array_and_fakes',
    'provider_credentials' => 'absent', 'vite_manifest' => 'generated_and_verified',
    'laravel_boot' => $mode === 'boot' ? 'PASS' : 'NOT_RUN'])."\n";
