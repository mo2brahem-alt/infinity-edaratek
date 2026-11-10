#!/usr/bin/env bash
# Paste this entire file into the existing trusted SSH session.
# Reads only the test application and explicitly named test vhost files.
# No Laravel boot, DB/cache connection, writes, redirects or deployment.
set -eu
export LC_ALL=C
APP_DIR=/home/test/public_html
TARGET=test.edaratek.com
printf 'T112_READONLY_BEGIN\n'
date -u +%Y-%m-%dT%H:%M:%SZ
printf 'SSH_USER='; id -un
if [ ! -d "$APP_DIR" ]; then printf 'BLOCKED=TEST_APP_PATH_MISSING\n'; exit 41; fi
cd "$APP_DIR"
if [ "$(pwd -P)" != "$APP_DIR" ]; then printf 'BLOCKED=CANONICAL_PATH_REQUIRES_REVIEW\n'; exit 42; fi
if [ ! -f artisan ] || [ ! -f composer.json ]; then printf 'BLOCKED=NOT_EXPECTED_LARAVEL_ROOT\n'; exit 43; fi
printf 'APP_CANONICAL_PATH=%s\n' "$(pwd -P)"
printf 'GIT_HEAD='; git rev-parse HEAD 2>/dev/null || printf 'UNAVAILABLE_NO_CONFIG_CHANGED\n'
printf 'PHP_CLI='; php -r 'echo PHP_VERSION, PHP_EOL;'
printf 'PATH_METADATA_BEGIN\n'
for item in . public public/storage public/media-files storage/app/private storage/app/public bootstrap/cache/config.php; do
  if [ -e "$item" ] || [ -L "$item" ]; then
    stat -c '%F %a %U:%G %n' -- "$item"
    if [ -L "$item" ]; then printf 'LINK_TARGET='; readlink -- "$item"; fi
  else printf 'ABSENT=%s\n' "$item"; fi
done
printf 'PATH_METADATA_END\n'

php -d display_errors=0 -d log_errors=0 -d auto_prepend_file= -d auto_append_file= <<'T112_PHP'
<?php
error_reporting(0);
set_error_handler(static function () { throw new RuntimeException('PROBE_READ_ERROR'); });
$root = '/home/test/public_html';
if (getcwd() !== $root || realpath($root) !== $root) { echo "BLOCKED=PATH_GUARD\n"; exit(44); }
$result = ['probe' => 'T112_allowlisted_config_no_Laravel_boot', 'timestamp_utc' => gmdate('c')];
$inScopeFile = static function ($file) use ($root) {
    $resolved = realpath($file);
    return is_string($resolved) && str_starts_with($resolved, $root . '/') && is_file($resolved);
};
$url = static function ($value) {
    if (!is_string($value) || $value === '') { return null; }
    $parts = parse_url($value);
    if (!is_array($parts) || !isset($parts['host'])) { return 'NON_ABSOLUTE_URL_REQUIRES_REVIEW'; }
    return ['scheme' => $parts['scheme'] ?? null, 'host' => $parts['host'],
        'port' => $parts['port'] ?? null, 'credentials_or_query_suppressed' => isset($parts['user']) || isset($parts['pass']) || isset($parts['query'])];
};
$safeName = static fn ($value) => is_string($value) && preg_match('/^[a-zA-Z0-9_.:-]{1,100}$/D', $value) ? $value : 'UNAVAILABLE_OR_SUPPRESSED';
$config = null;
try {
    $result['config_cache_present'] = $inScopeFile('bootstrap/cache/config.php');
    $result['config_cache_external_or_missing'] = !$result['config_cache_present'];
    if ($result['config_cache_present']) {
        // Generated config array only; never initialize/bootstrap the application.
        $config = require 'bootstrap/cache/config.php';
        if (!is_array($config)) { throw new RuntimeException('NOT_ARRAY'); }
        $result['cached_config'] = ['app_env' => $safeName($config['app']['env'] ?? null),
            'app_debug' => is_bool($config['app']['debug'] ?? null) ? $config['app']['debug'] : 'NOT_BOOLEAN',
            'app_url' => $url($config['app']['url'] ?? null),
            'app_key_present' => !empty($config['app']['key']),
            'filesystem_default' => $safeName($config['filesystems']['default'] ?? null),
            'cache_default' => $safeName($config['cache']['default'] ?? null),
            'cache_prefix' => $safeName($config['cache']['prefix'] ?? null),
            'queue_default' => $safeName($config['queue']['default'] ?? null),
            'mail_default' => $safeName($config['mail']['default'] ?? null),
            'database_default' => $safeName($config['database']['default'] ?? null)];
        $cacheName = $config['cache']['default'] ?? '';
        $result['cached_config']['cache_driver'] = $safeName($config['cache']['stores'][$cacheName]['driver'] ?? null);
        $result['cached_config']['cache_connection'] = $safeName($config['cache']['stores'][$cacheName]['connection'] ?? null);
        foreach (['local', 'public', 'school_attachments'] as $name) {
            $disk = $config['filesystems']['disks'][$name] ?? null;
            if (!is_array($disk)) { $result['disks'][$name] = ['configured' => false]; continue; }
            $diskRoot = $disk['root'] ?? null;
            $inside = is_string($diskRoot) && str_starts_with($diskRoot, $root . '/');
            $entry = ['configured' => true, 'driver' => $safeName($disk['driver'] ?? null),
                'root' => $inside ? $diskRoot : 'OUTSIDE_TEST_APP_REQUIRES_OPERATOR_REVIEW',
                'url' => $url($disk['url'] ?? null), 'visibility' => $safeName($disk['visibility'] ?? null)];
            // Never inspect a root configured outside this test application's path.
            if ($inside) {
                $resolved = realpath($diskRoot);
                $resolvedInside = is_string($resolved) && str_starts_with($resolved, $root . '/');
                $entry['resolved_root'] = $resolvedInside ? $resolved : 'MISSING_OR_EXTERNAL_TARGET_REQUIRES_REVIEW';
                if ($resolvedInside) { $entry['readable_for_probe_user'] = is_readable($resolved); $entry['directory'] = is_dir($resolved); }
            }
            $result['disks'][$name] = $entry;
        }
    }
} catch (Throwable $e) { $result['cached_config_probe'] = 'UNAVAILABLE_RAW_ERROR_SUPPRESSED'; }
try {
    $result['source_env_present'] = $inScopeFile('.env');
    $result['source_env_external_or_missing'] = !$result['source_env_present'];
    if ($result['source_env_present'] && $inScopeFile('vendor/autoload.php')) {
        // Composer autoload/Dotenv parser only. No Laravel app bootstrap/provider boot.
        require_once 'vendor/autoload.php';
        $env = Dotenv\Dotenv::parse(file_get_contents('.env'));
        foreach (['APP_ENV', 'APP_DEBUG', 'FILESYSTEM_DISK', 'CACHE_STORE', 'CACHE_DRIVER', 'CACHE_PREFIX', 'QUEUE_CONNECTION', 'MAIL_MAILER'] as $key) {
            $result['source_env_allowlist'][$key] = array_key_exists($key, $env) ? $safeName($env[$key]) : 'UNSET';
        }
        $result['source_env_allowlist']['APP_URL'] = $url($env['APP_URL'] ?? null);
        $result['source_env_allowlist']['APP_KEY_PRESENT'] = !empty($env['APP_KEY']);
        unset($env);
    }
} catch (Throwable $e) { $result['source_env_probe'] = 'UNAVAILABLE_RAW_ERROR_SUPPRESSED'; }
foreach (['routes/api.php', 'bootstrap/app.php', 'config/filesystems.php', 'config/cache.php', 'public/.htaccess'] as $file) {
    $result['source_sha256'][$file] = $inScopeFile($file) ? hash_file('sha256', $file) : 'ABSENT_OR_EXTERNAL_REQUIRES_REVIEW';
}
$result['limits'] = ['Cached/source values are not proof of FPM effective runtime or production isolation.',
    'No backend connection, cache key, QA file, authenticated download or forced error was executed.',
    'Probe-user readability does not establish application/FPM-user readability.'];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), PHP_EOL;
T112_PHP

printf 'TEST_VHOST_ALLOWLIST_BEGIN\n'
for cfg in /etc/apache2/sites-available/test.edaratek.com.conf /etc/httpd/conf.d/test.edaratek.com.conf /etc/nginx/sites-available/test.edaratek.com /etc/nginx/sites-available/test.edaratek.com.conf; do
  if [ ! -r "$cfg" ]; then printf 'VHOST_CANDIDATE_UNAVAILABLE=%s\n' "$cfg"; continue; fi
  # Do not inspect a symlink whose destination might be another domain's config.
  if [ -L "$cfg" ]; then printf 'VHOST_SYMLINK_REQUIRES_OPERATOR_REVIEW=%s\n' "$cfg"; continue; fi
  # Verify an actual server-name directive before selecting this exact file.
  if ! grep -Eq '^[[:space:]]*(ServerName[[:space:]]+test\.edaratek\.com(:[0-9]+)?[[:space:]]*$|server_name[[:space:]]+test\.edaratek\.com[[:space:]]*;)' "$cfg"; then
    printf 'VHOST_NAME_GUARD_REJECTED=%s\n' "$cfg"; continue
  fi
  printf 'VHOST_CANDIDATE=%s\n' "$cfg"
  # Never dump SSL/private-key, password, proxy URL, arbitrary header or entire vhost.
  awk '
    /^[[:space:]]*#/ {next}
    /^[[:space:]]*(DocumentRoot|ServerName|root)[[:space:]]/ {print;next}
    /^[[:space:]]*Alias[[:space:]]+"?\/(storage|media-files)(\/|"|[[:space:]])/ {print;next}
    /storage|media-files/ {print "STORAGE_RULE_PRESENT_REQUIRES_SANITIZED_OPERATOR_REVIEW line=" NR;next}
    /^[[:space:]]*(Include|IncludeOptional|ProxyPass|ProxyPassMatch|proxy_cache|fastcgi_cache)[[:space:]]/ {print "INDIRECT_OR_CACHE_DIRECTIVE_PRESENT_REQUIRES_REVIEW line=" NR}
  ' "$cfg"
done
printf 'TEST_VHOST_ALLOWLIST_END\n'
printf 'HTTP_TEST_HOST_ONLY_BEGIN\n'
for route in / /up /api/v1/me; do
  printf 'PATH=%s\n' "$route"
  curl -q --silent --show-error --max-time 15 --max-redirs 0 --no-location --noproxy '*' --proto '=https' --proto-redir '=https' \
    --header 'Accept: application/json' --output /dev/null \
    --write-out 'HTTP_STATUS=%{http_code} CONTENT_TYPE=%{content_type} TLS_VERIFY=%{ssl_verify_result}\n' \
    "https://$TARGET$route" || printf 'HTTP_PROBE_FAILED_NO_REDIRECT_FOLLOWED\n'
done
printf 'HTTP_TEST_HOST_ONLY_END\n'
printf 'T112_STATUS=BLOCKED_PENDING_EVIDENCE_REVIEW\nNO_APPLICATION_BOOT=YES\nNO_DB_OR_CACHE_OPERATIONS=YES\nNO_CONFIG_CHANGES=YES\nREAL_FILES_MIGRATED=0\nPHASE_9_STARTED=NO\nT112_READONLY_END\n'
