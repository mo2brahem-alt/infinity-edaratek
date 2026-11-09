<?php
ini_set('display_errors', '0');
$baselineContext = [];
function evidenceFail(string $category): never {
    global $baselineContext;
    $classifications = [
        'junit_missing' => 'JUNIT_MISSING', 'junit_empty' => 'JUNIT_EMPTY',
        'junit_invalid_utf8' => 'JUNIT_INVALID_UTF8', 'unsafe_junit' => 'JUNIT_UNSAFE_DTD',
        'invalid_junit' => 'JUNIT_XML_PARSE_FAILED', 'zero_tests' => 'JUNIT_ZERO_TESTS',
        'incomplete_test_execution' => 'TEST_EXECUTION_INCOMPLETE',
        'incomplete_event_report' => 'EVENT_REPORT_INCOMPLETE',
        'inconsistent_test_exit' => 'TEST_EXIT_INCONSISTENT',
    ];
    emitEvidence(array_merge($baselineContext, ['kind' => 'infrastructure', 'status' => 'FAIL',
        'stage' => $category, 'classification' => $classifications[$category] ?? 'TEST_INFRASTRUCTURE',
        'BASELINE_TEST_RESULTS_RECORDED' => 'FAIL']));
    exit(25);
}
set_exception_handler(static function (Throwable $error): void { evidenceFail('evidence_exception'); });
function emitEvidence(array $record): void {
    // The existing PowerShell transport recognizes JSON by its first key.
    if (isset($record['kind'])) { $record = ['kind' => $record['kind']] + $record; }
    echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
}
function memoryEvents(): array {
    $path = '/sys/fs/cgroup/memory.events';
    $counts = [];
    if (is_readable($path)) {
        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^(oom|oom_kill|oom_group_kill) ([0-9]+)$/D', $line, $match)) {
                $counts[$match[1]] = (int) $match[2];
            }
        }
    }
    return $counts;
}
require '/app/vendor/autoload.php';
$mode = $argv[1] ?? '';
if ($mode === 'process-profile') {
    $limit = ini_get('memory_limit');
    if (!preg_match('/^(?:-1|[0-9]+[KMG]?)$/iD', $limit)) { evidenceFail('memory_limit_format'); }
    $profile = ['PHP_MEMORY_LIMIT' => $limit, 'PHP_MAX_EXECUTION_TIME' => (int) ini_get('max_execution_time'),
        'cgroup_memory_events_before' => memoryEvents()];
    if (file_put_contents('/tmp/baseline-process-profile.json', json_encode($profile, JSON_THROW_ON_ERROR)) === false) {
        evidenceFail('process_profile_write');
    }
    emitEvidence(['kind' => 'infrastructure', 'status' => 'INFO', 'stage' => 'phpunit_process_profile'] + $profile);
    exit(0);
}
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
$runKind = match ($argv[3] ?? '') {
    'diagnostic' => 'diagnostic_subset',
    'phase3' => 'phase3_focused',
    'phase4' => 'phase4_focused',
    'phase5' => 'phase5_focused',
    'native' => 'phase2_regression',
    default => 'authoritative_full',
};
$baselineContext = ['TEST_EXIT_CODE' => $testExit, 'run_kind' => $runKind];
if (is_file('/tmp/baseline-process-profile.json')) {
    $profile = json_decode(file_get_contents('/tmp/baseline-process-profile.json'), true, 512, JSON_THROW_ON_ERROR);
    $baselineContext += $profile;
    $after = memoryEvents();
    $baselineContext['cgroup_memory_events_after'] = $after;
    $before = $profile['cgroup_memory_events_before']['oom_kill'] ?? null;
    $baselineContext['cgroup_oom_kill_increased'] = $before !== null && isset($after['oom_kill'])
        ? $after['oom_kill'] > $before : null;
}
// Record the actual process exit BEFORE report I/O or XML parsing can fail.
emitEvidence(['kind' => 'infrastructure', 'status' => 'INFO', 'stage' => 'baseline_process_exit',
    'TEST_EXIT_CODE' => $testExit]);
if (Composer\InstalledVersions::getVersion('phpunit/phpunit') !== '11.5.51.0') {
    evidenceFail('unsupported_warning_event_format');
}
$xmlFile = '/tmp/baseline-junit.xml'; $eventFile = '/tmp/baseline-events.log';
$warnings = ['user' => 0, 'php' => 0, 'phpunit' => 0, 'runner' => 0];
$prefixes = ['Test Triggered Warning (' => 'user', 'Test Triggered PHP Warning (' => 'php',
    'Test Triggered PHPUnit Warning (' => 'phpunit', 'Test Runner Triggered Warning (' => 'runner'];
$finishedCodes = [];
$lastTest = null;
$eventTail = [];
$warningSources = [];
$knownEventTypes = ['PHPUnit Started', 'PHPUnit Finished', 'Test Suite Started', 'Test Suite Finished',
    'Test Preparation Started', 'Test Prepared', 'Test Passed', 'Test Failed', 'Test Errored',
    'Test Finished', 'Test Considered Risky', 'Test Skipped', 'Test Marked Incomplete',
    'Assertion Succeeded', 'Assertion Failed', 'Before Test Method Called', 'After Test Method Called',
    'Before Test Method Finished', 'After Test Method Finished', 'Test Triggered Warning',
    'Test Triggered PHP Warning', 'Test Triggered PHPUnit Warning', 'Test Runner Triggered Warning',
    'Test Triggered Deprecation', 'Test Triggered PHP Deprecation', 'Test Triggered Notice',
    'Test Triggered PHP Notice', 'Test Triggered Error', 'Test Runner Execution Started',
    'Test Runner Execution Finished'];
$eventPresent = is_file($eventFile);
$eventBytes = $eventPresent ? filesize($eventFile) : null;
$stream = $eventPresent ? fopen($eventFile, 'rb') : false;
while ($stream !== false && ($line = fgets($stream)) !== false) {
    // PHPUnit 11.5.51 prefixes only event first lines; continuations are indented.
    if (!preg_match('/^\[[^\]\r\n]+\] \[\d+ bytes\] (.*)$/', rtrim($line, "\r\n"), $event)) { continue; }
    $type = strstr($event[1], ' (', true);
    $eventTail[] = in_array($type, $knownEventTypes, true) ? $type : 'OTHER_PHPUNIT_EVENT';
    if (count($eventTail) > 20) { array_shift($eventTail); }
    // Source symbols only: the match stops BEFORE dataset names/values.
    if (in_array($type, $knownEventTypes, true)
        && preg_match('/^Test [A-Za-z ]+ \((Tests[\\\\A-Za-z0-9_]+)::([A-Za-z_][A-Za-z0-9_]*)(?=[\s,)])/', $event[1], $identity)) {
        $lastTest = ['class' => substr($identity[1], 0, 200), 'method' => substr($identity[2], 0, 200)];
    }
    foreach ($prefixes as $prefix => $key) {
        if (str_starts_with($event[1], $prefix)) { $warnings[$key]++; }
    }
    // Source location only, never the warning continuation/message. A path is
    // emitted only if it resolves to a static existing file inside the image.
    if (str_starts_with($event[1], 'Test Triggered PHP Warning (')
        && preg_match('~ in (/app/(?:vendor|app|tests|database|bootstrap|config|tools)/[A-Za-z0-9_/.-]+\.php):([0-9]+)$~D', $event[1], $location)) {
        $resolved = realpath($location[1]);
        if ($resolved !== false && str_starts_with($resolved, '/app/') && is_file($resolved)) {
            $relative = substr($resolved, 5);
            $signature = hash('sha256', 'PHP_WARNING:'.$relative.':'.$location[2]);
            if (isset($warningSources[$signature])) { $warningSources[$signature]['count']++; }
            elseif (count($warningSources) < 40) {
                $warningSources[$signature] = ['category' => 'PHP_WARNING', 'signature_sha256' => $signature,
                    'source_file' => $relative, 'source_line' => (int) $location[2], 'count' => 1];
            }
        }
    }
    if (preg_match('/^PHPUnit Finished \(Shell Exit Code: ([0-9]{1,3})\)$/D', $event[1], $finish)) {
        $finishedCodes[] = (int) $finish[1];
    }
}
if ($stream !== false) { fclose($stream); }
$baselineContext += ['event_file_present' => $eventPresent, 'event_file_bytes' => $eventBytes,
    'phpunit_finished_event' => count($finishedCodes) > 0,
    'finished_shell_exit_code' => count($finishedCodes) === 1 ? $finishedCodes[0] : null,
    'finished_event_count' => count($finishedCodes),
    'last_test_identity' => $lastTest, 'last_phpunit_event_types' => $eventTail,
    'php_warning_sources' => array_values($warningSources),
    'execution_classification' => count($finishedCodes) === 1 ? 'COMPLETED_REQUIRES_EXIT_CHECK' : 'INCOMPLETE_TEST_EXECUTION'];

// Metadata only; raw bytes are neither emitted nor repaired. Counts below are
// diagnostic tokens, NEVER a substitute for parsed testcase result records.
$present = is_file($xmlFile);
$source = $present ? file_get_contents($xmlFile) : false;
$readable = is_string($source);
$baselineContext += ['junit_file_present' => $present,
    'junit_bytes' => $present ? filesize($xmlFile) : null,
    'junit_sha256' => $readable ? hash('sha256', $source) : null,
    'utf8_valid' => $readable ? preg_match('//u', $source) === 1 : null,
    'xml_declaration_present' => $readable ? preg_match('/^(?:\xEF\xBB\xBF)?<\?xml\s/', $source) === 1 : null,
    'contains_doctype_or_entity' => $readable ? preg_match('/<!DOCTYPE|<!ENTITY/i', $source) === 1 : null,
    'starts_with_expected_xml_structure' => $readable ? preg_match('/^(?:\xEF\xBB\xBF)?\s*(?:<\?xml[^?]*\?>\s*)?<testsuites?(?:\s|>)/', $source) === 1 : null,
    'ends_with_plausible_xml_structure' => $readable ? preg_match('~</testsuites?>\s*$~D', $source) === 1 : null,
    'testcase_token_count' => $readable ? preg_match_all('/<testcase(?=\s|\/?>)/', $source) : null,
    'testcase_token_count_is_diagnostic_only' => true,
    'libxml_errors' => [], 'libxml_error_count' => 0];

// Only complete, ASCII, literal/numeric PHPUnit summary lines are allowed.
// Never export test progress, assertion values, dataset labels or exceptions.
$summary = [];
$fatalCategories = [];
$fatalPatterns = [
    'PHP_MEMORY_EXHAUSTED' => '/(?:PHP\s+)?Fatal error:.*Allowed memory size of [0-9]+ bytes exhausted/i',
    'PHP_EXECUTION_TIME_EXCEEDED' => '/(?:PHP\s+)?Fatal error:.*Maximum execution time of [0-9]+ seconds exceeded/i',
    'SEGMENTATION_FAULT' => '/^(?:Segmentation fault|.*\bSegmentation fault\s*\(core dumped\))/i',
    'ZEND_HEAP_CORRUPTION' => '/^.*zend_mm_heap corrupted/i',
    'BUS_ERROR' => '/^Bus error(?:\s|$)/i',
    'PROCESS_KILLED' => '/^(?:Killed|.*\bKilled\s+.*phpunit)(?:\s|$)/i',
    'PROCESS_OUT_OF_MEMORY' => '/^(?:Out of memory:|Memory cgroup out of memory:)/i',
    'PHP_PARSE_ERROR' => '/^(?:PHP\s+)?Parse error:/i',
    'PHP_REDECLARE_FATAL' => '/(?:PHP\s+)?Fatal error:.*Cannot (?:redeclare|declare class)/i',
    'PHP_UNDEFINED_CALL' => '/(?:PHP\s+)?Fatal error:.*(?:Call to undefined|Call to a member function)/i',
    'PHP_CLASS_NOT_FOUND' => '/(?:PHP\s+)?Fatal error:.*Class .* not found/i',
    'PHP_UNCAUGHT_FATAL' => '/(?:PHP\s+)?Fatal error:.*Uncaught /i',
    'PHP_FATAL_ERROR' => '/(?:PHP\s+)?Fatal error:/i',
];
$logPresent = is_file('/tmp/baseline.log');
$baselineContext['baseline_log_present'] = $logPresent;
$baselineContext['baseline_log_bytes'] = $logPresent ? filesize('/tmp/baseline.log') : null;
$log = $logPresent ? fopen('/tmp/baseline.log', 'rb') : false;
while ($log !== false && ($line = fgets($log)) !== false) {
    $line = rtrim($line, "\r\n");
    foreach ($fatalPatterns as $category => $pattern) {
        if (preg_match($pattern, $line)) { $fatalCategories[$category] = true; }
    }
    if (strlen($line) > 256) { continue; }
    if (preg_match('/^(?:FAILURES!|ERRORS!|OK, but there were issues!|No tests executed!)$/D', $line)
        || preg_match('/^OK \([0-9]+ tests?, [0-9]+ assertions?\)$/D', $line)
        || preg_match('/^Time: [0-9]+(?::[0-9]{2}){0,2}(?:\.[0-9]+)?, Memory: [0-9]+(?:\.[0-9]+)? (?:KB|MB|GB)$/D', $line)
        || preg_match('/^Tests: [0-9]+(?:, (?:Assertions|Errors|Failures|Warnings|Skipped|Incomplete|Risky|Deprecations|PHPUnit Deprecations|PHPUnit Warnings|Notices): [0-9]+)*\.$/D', $line)) {
        $summary[] = $line;
        if (count($summary) > 8) { array_shift($summary); }
    }
}
if ($log !== false) { fclose($log); }
$baselineContext['safe_phpunit_summary_lines'] = $summary;
$baselineContext['fatal_signature_categories'] = array_keys($fatalCategories);
$baselineContext['fatal_signatures_are_observations_only'] = true;
emitEvidence(array_merge($baselineContext, ['kind' => 'infrastructure', 'status' => 'INFO',
    'stage' => 'baseline_report_metadata']));

// Completion is checked FIRST; no XML result is trusted for a partial suite.
if (!$eventPresent || $eventBytes === 0 || count($finishedCodes) > 1) { evidenceFail('incomplete_event_report'); }
if (count($finishedCodes) === 0) { evidenceFail('incomplete_test_execution'); }
if ($finishedCodes[0] !== $testExit) { evidenceFail('inconsistent_test_exit'); }
$baselineContext['execution_classification'] = 'COMPLETED';
if (!$present) { evidenceFail('junit_missing'); }
if (!$readable) { evidenceFail('junit_unreadable'); }
if ($baselineContext['junit_bytes'] === 0) { evidenceFail('junit_empty'); }
if ($baselineContext['contains_doctype_or_entity']) { evidenceFail('unsafe_junit'); }
if (!$baselineContext['utf8_valid']) { evidenceFail('junit_invalid_utf8'); }
libxml_use_internal_errors(true);
libxml_clear_errors();
$xml = simplexml_load_string($source, SimpleXMLElement::class, LIBXML_NONET);
$libxmlErrors = libxml_get_errors();
$baselineContext['libxml_error_count'] = count($libxmlErrors);
foreach (array_slice($libxmlErrors, 0, 20) as $error) {
    $baselineContext['libxml_errors'][] = ['code' => (int) $error->code, 'level' => (int) $error->level,
        'line' => (int) $error->line, 'column' => (int) $error->column];
}
$baselineContext['libxml_errors_truncated'] = count($libxmlErrors) > 20;
libxml_clear_errors();
if ($xml === false) { evidenceFail('invalid_junit'); }
if (!in_array($xml->getName(), ['testsuite', 'testsuites'], true)) { evidenceFail('junit_root_structure'); }
$cases = $xml->xpath('//testcase') ?: [];
if (count($cases) === 0) { evidenceFail('zero_tests'); }
$failed = 0; $errors = 0; $skipped = 0; $diagnostics = []; $databaseGroups = [];
foreach ($cases as $case) {
    if (isset($case->error)) { $errors++; $node = $case->error; }
    elseif (isset($case->failure)) { $failed++; $node = $case->failure; }
    elseif (isset($case->skipped)) { $skipped++; continue; }
    else { continue; }
    // No dataset labels, arbitrary assertion values or raw exception bodies.
    $method = preg_split('/\s+with data set/', (string) $case['name'])[0];
    $clean = static fn (string $value): string => substr(preg_replace('/[^A-Za-z0-9_\\\\]/', '', $value), 0, 200);
    $body = (string) $node;
    $sqlstate = null; $driverCode = null;
    if (preg_match('/SQLSTATE\[([A-Z0-9]{5})\]/', $body, $sql)) { $sqlstate = $sql[1]; }
    if (preg_match('/SQLSTATE\[[A-Z0-9]{5}\]:[^\r\n]{0,100}?: ([0-9]{2,5})(?:\s|$)/', $body, $driver)) {
        $driverCode = (int) $driver[1];
    }
    if ($sqlstate !== null) {
        $group = $sqlstate.':'.($driverCode ?? 'unknown');
        if (isset($databaseGroups[$group])) { $databaseGroups[$group]['count']++; }
        elseif (count($databaseGroups) < 40) { $databaseGroups[$group] = ['sqlstate' => $sqlstate,
            'sqlstate_class' => substr($sqlstate, 0, 2), 'driver_error_code' => $driverCode, 'count' => 1]; }
    }
    $classification = 'UNKNOWN_REQUIRES_REVIEW';
    if (in_array($driverCode, [1044, 1045, 1049, 2002, 2006, 2013], true)
        || preg_match('/Connection refused|could not find driver/i', $body)) { $classification = 'ENVIRONMENTAL'; }
    elseif (preg_match('/Vite manifest|Unable to locate file in Vite manifest/i', $body)) { $classification = 'TEST_INFRASTRUCTURE'; }
    elseif (isset($case->failure)) { $classification = 'ASSERTION_FAILURE_REQUIRES_REVIEW'; }
    if (count($diagnostics) >= 128) { continue; }
    $sourceLocation = null;
    if (preg_match('~(?:/app/)?(tests/[A-Za-z0-9_/-]+\.php):([0-9]{1,6})(?:\s|$)~', $body, $location)) {
        $sourceLocation = ['file' => $location[1], 'line' => (int) $location[2]];
    }
    $httpStatus = null;
    if (preg_match('/Expected response status code \[([1-5][0-9]{2})\] but received ([1-5][0-9]{2})/', $body, $http)) {
        $httpStatus = ['expected' => (int) $http[1], 'actual' => (int) $http[2]];
    }
    if (preg_match('/Failed asserting that ([1-5][0-9]{2}) is identical to ([1-5][0-9]{2})/', $body, $http)) {
        $httpStatus = ['expected' => (int) $http[2], 'actual' => (int) $http[1]];
    }
    $diagnostics[] = ['class' => $clean((string) $case['class']), 'method' => $clean($method),
        'type' => $clean((string) $node['type']), 'classification' => $classification,
        'sqlstate' => $sqlstate, 'sqlstate_class' => $sqlstate ? substr($sqlstate, 0, 2) : null,
        'driver_error_code' => $driverCode,
        'test_source' => $sourceLocation, 'http_status' => $httpStatus,
        'sanitized_message' => $classification, 'raw_message_exported' => false];
}
if (($failed + $errors) > 0 && $testExit === 0) { evidenceFail('inconsistent_test_exit'); }
emitEvidence(['kind' => 'baseline', 'TOTAL' => count($cases),
    'run_kind' => $runKind, 'database_error_groups' => array_values($databaseGroups),
    'php_warning_sources' => array_values($warningSources),
    'PASSED' => count($cases) - $failed - $errors - $skipped, 'FAILED' => $failed,
    'ERRORS' => $errors, 'SKIPPED' => $skipped, 'WARNINGS' => array_sum($warnings),
    'warning_events' => $warnings, 'warning_basis' => 'PHPUnit 11.5.51 verbose warning events; events, not affected tests',
    'TEST_EXIT_CODE' => $testExit, 'BASELINE_TEST_RESULTS_RECORDED' => 'PASS',
    'phpunit_completed' => true, 'test_suite_outcome' => $testExit === 0 ? 'PASS' : 'NONZERO_EXIT',
    'diagnostics' => $diagnostics, 'diagnostics_truncated' => ($failed + $errors) > count($diagnostics)]);
