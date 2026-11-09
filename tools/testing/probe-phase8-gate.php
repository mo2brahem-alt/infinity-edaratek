<?php

ini_set('display_errors', '0');
$record = ['kind' => 'infrastructure', 'stage' => 'phase8_test_execution', 'status' => 'FAIL', 'PHASE_8_TEST_EXECUTION' => 'FAIL'];
try {
    $read = static function (string $path): array {
        $source = file_get_contents($path);
        if (! is_string($source) || preg_match('/<!DOCTYPE|<!ENTITY/i', $source)) { throw new RuntimeException('Unsafe JUnit'); }
        $xml = simplexml_load_string($source, SimpleXMLElement::class, LIBXML_NONET);
        if (! $xml) { throw new RuntimeException('Invalid JUnit'); }
        return $xml->xpath('//testcase') ?: [];
    };
    $scope = json_decode(file_get_contents(__DIR__.'/phase8-scope.json'), true, 512, JSON_THROW_ON_ERROR);
    $classes = []; $focused = $read('/tmp/phase8-focused-junit.xml');
    foreach ($focused as $case) {
        if (isset($case->failure) || isset($case->error) || isset($case->skipped)) { throw new RuntimeException('Focused failure'); }
        $classes[(string) $case['class']] = ($classes[(string) $case['class']] ?? 0) + 1;
    }
    foreach ($scope['focused_classes'] as $class) { if (($classes[$class] ?? 0) < 1) { throw new RuntimeException('Missing focused class'); } }
    if (count($classes) !== count($scope['focused_classes'])) { throw new RuntimeException('Unexpected focused class'); }
    $known = 'Tests\\Feature\\StudentExcelImportTest::test_valid_excel_import_creates_students_inside_current_school';
    $lanes = []; $fingerprints = [];
    foreach (['artisan' => '/tmp/phase8-artisan-junit.xml', 'phpunit_mysql' => '/tmp/baseline-junit.xml'] as $lane => $path) {
        $cases = $read($path); $failures = []; $fullClasses = []; $symbols = [];
        foreach ($cases as $case) {
            $class = (string) $case['class'];
            if (isset($case->error) || isset($case->skipped) || $class === 'Tests\\Feature\\WhatsAppIntegrationTest') { throw new RuntimeException('Unexpected execution'); }
            $method = preg_split('/\s+with data set/', (string) $case['name'])[0];
            if (! preg_match('/^[A-Za-z0-9_\\\\]+$/D', $class) || ! preg_match('/^[A-Za-z0-9_]+$/D', $method)) { throw new RuntimeException('Unsafe symbol'); }
            $symbols[] = $class.'::'.$method;
            $fullClasses[$class] = ($fullClasses[$class] ?? 0) + 1;
            if (isset($case->failure)) { $failures[] = $class.'::'.$method; }
        }
        if (count($cases) !== $scope['baseline_total'] + $scope['phase8_additional_cases'] || $failures !== [$known]) { throw new RuntimeException('Full regression mismatch'); }
        foreach ($classes as $class => $count) { if (($fullClasses[$class] ?? 0) !== $count) { throw new RuntimeException('Focused/full mismatch'); } }
        sort($symbols); ksort($fullClasses); $fingerprints[] = hash('sha256', json_encode($symbols, JSON_THROW_ON_ERROR));
        $lanes[$lane] = ['total' => count($cases), 'passed' => count($cases)-1, 'failed' => 1, 'errors' => 0, 'skipped' => 0,
            'known_excel_only' => true, 'class_counts' => $fullClasses, 'case_symbol_sha256' => end($fingerprints)];
    }
    if ($fingerprints[0] !== $fingerprints[1] || (int) ($argv[1] ?? -1) !== 1 || (int) ($argv[2] ?? -1) !== 1) { throw new RuntimeException('Runner/exit mismatch'); }
    if (($classes['Tests\\Feature\\Mobile\\Phase8\\LargePreparedListsTest'] ?? 0) !== $scope['phase8_additional_cases']) { throw new RuntimeException('Additional coverage missing'); }
    $profiles = json_decode(file_get_contents('/tmp/phase8-large-lists.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach (['devices', 'task_attachments'] as $surface) {
        $p = $profiles[$surface];
        if ($p['status'] !== 'PASS' || $p['large']['rows'] <= 100 || $p['large']['returned_rows'] !== 100 || $p['large']['queries'] > $p['query_budget']) { throw new RuntimeException('List scale evidence missing'); }
    }
    $record += ['PHASE_8_FOCUSED_PASS' => count($focused), 'PHASE_8_FOCUSED_TOTAL' => count($focused), 'focused_classes' => $classes,
        'REGRESSION_STATUS' => 'PASS_FOR_CODE_AND_SYNTHETIC_SCOPE', 'baseline_total' => $scope['baseline_total'], 'phase8_additional_cases' => $scope['phase8_additional_cases'],
        'FULL_SUITE_PASS' => $scope['baseline_total']+$scope['phase8_additional_cases']-1, 'FULL_SUITE_TOTAL' => $scope['baseline_total']+$scope['phase8_additional_cases'], 'large_list_profiles' => $profiles,
        'KNOWN_EXCEL_FAILURE' => 'YES', 'RUNTIME_ERRORS' => 0, 'SKIPPED_TESTS' => 0, 'full_lanes' => $lanes, 'runner_case_symbols_match' => true,
        'FULL_SECURITY_REGRESSION_GATE_PASS' => 'BLOCKED', 'phase8_acceptance' => 'BLOCKED',
        'remaining_gate_evidence' => ['H01_ACTUAL_STORAGE_ALIAS_AND_CACHE_PROOF_UNVERIFIED', 'H05_ACTUAL_ENVIRONMENT_SAFETY_PROOF_UNVERIFIED'],
        'actual_aliases_cdn' => 'UNKNOWN', 'WHATSAPP_IN_SCOPE' => 'NO', 'REAL_WHATSAPP_SENDS' => 0, 'REAL_FILES_MIGRATED' => 0, 'PHASE_9_STARTED' => false];
    $record['status'] = 'PASS'; $record['PHASE_8_TEST_EXECUTION'] = 'PASS';
} catch (Throwable) { $record['classification'] = 'PHASE8_EXECUTION_REQUIRES_REVIEW'; }
echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
// This exit certifies evidence/test execution only. T112 remains blocked, never a release PASS.
exit($record['status'] === 'PASS' ? 0 : 29);
