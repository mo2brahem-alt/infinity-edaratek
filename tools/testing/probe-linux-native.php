<?php

// Standalone prerequisites only. No Laravel, database mutation or HTTP request.
ini_set('display_errors', '0');
$record = ['kind' => 'infrastructure', 'stage' => 'linux_native_prerequisites', 'status' => 'FAIL'];
$process = null;
$input = null;
try {
    if (PHP_OS_FAMILY !== 'Linux' || getenv('EDARATEK_ISOLATED_TEST') !== '1' || getenv('APP_ENV') !== 'testing') {
        throw new RuntimeException('Native isolated Linux required');
    }
    if (!is_readable('/dev/null') || filetype('/dev/null') !== 'char') { throw new RuntimeException('Null device unavailable'); }
    $input = fopen('/dev/null', 'rb');
    if (!$input || fread($input, 1) !== '') { throw new RuntimeException('Unexpected null device behavior'); }
    fclose($input); $input = null;
    foreach (['curl', 'zip', 'pdo_mysql', 'openssl', 'pcntl'] as $extension) {
        if (!extension_loaded($extension)) { throw new RuntimeException('Required native test extension missing'); }
    }
    if (!function_exists('symlink') || !function_exists('proc_open')) { throw new RuntimeException('Native process/filesystem functions missing'); }
    $process = proc_open([PHP_BINARY, '-r', 'exit(fread(STDIN, 1) === "" ? 0 : 3);'],
        [['file', '/dev/null', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Native process unavailable'); }
    $out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process); $process = null;
    if ($exit !== 0 || $out !== '' || $error !== '') { throw new RuntimeException('Null subprocess descriptor failed'); }
    $record += ['dev_null_character_device' => true, 'native_stdin_eof' => true, 'extensions_present' => true];
    $record['status'] = 'PASS';
} catch (Throwable) { $record['classification'] = 'NATIVE_LINUX_PREREQUISITES_NOT_VERIFIED'; }
finally {
    if (is_resource($input)) { fclose($input); }
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
}
echo json_encode($record, JSON_THROW_ON_ERROR).PHP_EOL;
exit($record['status'] === 'PASS' ? 0 : 29);
