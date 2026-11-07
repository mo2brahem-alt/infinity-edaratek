<?php
ini_set('display_errors', '0');
function evidenceFail(string $category): never {
    echo json_encode(['kind' => 'infrastructure', 'status' => 'FAIL', 'stage' => $category])."\n";
    exit(25);
}
set_exception_handler(static function (Throwable $error): void { evidenceFail('evidence_exception'); });
function emitEvidence(array $record): void {
    echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
}
require '/app/vendor/autoload.php';
$mode = $argv[1] ?? '';
if ($mode === 'platform') {
    $strict = filter_var($argv[2] ?? null, FILTER_VALIDATE_INT);
    $platformExit = filter_var($argv[3] ?? null, FILTER_VALIDATE_INT);
    if ($strict === false || $platformExit === false) { evidenceFail('platform_arguments'); }
    $requirements = json_decode(file_get_contents('/tmp/platform.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($requirements) || $requirements === []) { evidenceFail('platform_report'); }
    $safeRequirements = [];
    foreach ($requirements as $entry) {
        if (!isset($entry['name'], $entry['status'])
            || !preg_match('/^[a-zA-Z0-9_.-]+$/D', $entry['name'])
            || !in_array($entry['status'], ['success', 'failed'], true)) { evidenceFail('platform_record'); }
        $safeRequirements[] = ['name' => $entry['name'], 'status' => $entry['status'],
            'version' => preg_replace('/[^a-zA-Z0-9.+_-]/', '', (string) ($entry['version'] ?? ''))];
        if ($entry['status'] !== 'success' && $platformExit === 0) { evidenceFail('platform_inconsistent'); }
    }
    if (!preg_match('/Composer version ([0-9]+\.[0-9]+\.[0-9]+)/', file_get_contents('/tmp/composer-version.txt'), $composer)) {
        evidenceFail('composer_version');
    }
    $node = trim(file_get_contents('/opt/frontend-node-version.txt'));
    if (!preg_match('/^\d+\.\d+\.\d+$/D', $node)) { evidenceFail('node_version'); }
    $extensions = get_loaded_extensions(); sort($extensions, SORT_STRING);
    $metadata = json_decode(file_get_contents('/app/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    emitEvidence(['kind' => 'platform', 'php_version' => PHP_VERSION, 'extensions' => $extensions,
        'pdo_drivers' => PDO::getAvailableDrivers(), 'composer_version' => $composer[1],
        'php_requirement' => $metadata['require']['php'],
        'phpunit_version' => Composer\InstalledVersions::getPrettyVersion('phpunit/phpunit'),
        'laravel_version' => Composer\InstalledVersions::getPrettyVersion('laravel/framework'),
        'frontend_node_version' => $node, 'composer_validate_exit' => 0,
        'composer_strict_exit' => $strict, 'composer_strict_warning_present' => $strict !== 0,
        'composer_platform_exit' => $platformExit, 'platform_requirements' => $safeRequirements,
        'runtime_network' => 'internal_only', 'dependency_network' => 'build_only']);
    exit(0);
}
if ($mode !== 'baseline') { evidenceFail('evidence_mode'); }
$testExit = filter_var($argv[2] ?? null, FILTER_VALIDATE_INT);
if ($testExit === false || $testExit < 0 || $testExit > 255) { evidenceFail('test_exit'); }
if (Composer\InstalledVersions::getVersion('phpunit/phpunit') !== '11.5.51.0') {
    evidenceFail('unsupported_warning_event_format');
}
$xmlFile = '/tmp/baseline-junit.xml'; $eventFile = '/tmp/baseline-events.log';
if (!is_file($xmlFile) || !is_file($eventFile)) { evidenceFail('missing_baseline_reports'); }
$source = file_get_contents($xmlFile);
if (preg_match('/<!DOCTYPE|<!ENTITY/i', $source)) { evidenceFail('unsafe_junit'); }
libxml_use_internal_errors(true);
$xml = simplexml_load_string($source, SimpleXMLElement::class, LIBXML_NONET);
if ($xml === false) { evidenceFail('invalid_junit'); }
$cases = $xml->xpath('//testcase') ?: [];
if (count($cases) === 0) { evidenceFail('zero_tests'); }
$warnings = ['user' => 0, 'php' => 0, 'phpunit' => 0, 'runner' => 0];
$prefixes = ['Test Triggered Warning (' => 'user', 'Test Triggered PHP Warning (' => 'php',
    'Test Triggered PHPUnit Warning (' => 'phpunit', 'Test Runner Triggered Warning (' => 'runner'];
$finished = false;
$stream = fopen($eventFile, 'rb');
while (($line = fgets($stream)) !== false) {
    // PHPUnit 11.5.51 prefixes only event first lines; continuations are indented.
    if (!preg_match('/^\[[^\]\r\n]+\] \[\d+ bytes\] (.*)$/', rtrim($line, "\r\n"), $event)) { continue; }
    foreach ($prefixes as $prefix => $key) {
        if (str_starts_with($event[1], $prefix)) { $warnings[$key]++; }
    }
    if ($event[1] === 'PHPUnit Finished (Shell Exit Code: '.$testExit.')') { $finished = true; }
}
fclose($stream);
if (!$finished) { evidenceFail('incomplete_event_report'); }
$failed = 0; $errors = 0; $skipped = 0; $diagnostics = [];
foreach ($cases as $case) {
    if (isset($case->error)) { $errors++; $node = $case->error; }
    elseif (isset($case->failure)) { $failed++; $node = $case->failure; }
    elseif (isset($case->skipped)) { $skipped++; continue; }
    else { continue; }
    if (count($diagnostics) >= 128) { continue; }
    // No dataset labels, expected/actual assertion values or raw exception bodies.
    $method = preg_split('/\s+with data set/', (string) $case['name'])[0];
    $clean = static fn (string $value): string => substr(preg_replace('/[^A-Za-z0-9_\\\\]/', '', $value), 0, 200);
    $body = (string) $node;
    $classification = 'UNKNOWN_REQUIRES_REVIEW';
    if (preg_match('/SQLSTATE|Connection refused|could not find driver/i', $body)) { $classification = 'ENVIRONMENTAL'; }
    elseif (preg_match('/Vite manifest|Unable to locate file in Vite manifest/i', $body)) { $classification = 'TEST_INFRASTRUCTURE'; }
    elseif (isset($case->failure)) { $classification = 'ASSERTION_FAILURE_REQUIRES_REVIEW'; }
    $diagnostics[] = ['class' => $clean((string) $case['class']), 'method' => $clean($method),
        'type' => $clean((string) $node['type']), 'classification' => $classification,
        'sanitized_message' => $classification, 'raw_message_exported' => false];
}
if (($failed + $errors) > 0 && $testExit === 0) { evidenceFail('inconsistent_test_exit'); }
emitEvidence(['kind' => 'baseline', 'TOTAL' => count($cases),
    'PASSED' => count($cases) - $failed - $errors - $skipped, 'FAILED' => $failed,
    'ERRORS' => $errors, 'SKIPPED' => $skipped, 'WARNINGS' => array_sum($warnings),
    'warning_events' => $warnings, 'warning_basis' => 'PHPUnit 11.5.51 verbose warning events; events, not affected tests',
    'TEST_EXIT_CODE' => $testExit, 'baseline_gate' => $testExit === 0 ? 'PASS' : 'FAIL',
    'diagnostics' => $diagnostics, 'diagnostics_truncated' => ($failed + $errors) > count($diagnostics)]);
