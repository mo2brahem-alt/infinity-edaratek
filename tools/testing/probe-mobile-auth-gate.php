<?php

// Consume the private full-suite JUnit after summarize-junit has already
// validated its structure. Emit only fixed test symbols and aggregate counts.
ini_set('display_errors', '0');
ob_start();

$record = [
    'kind' => 'infrastructure',
    'stage' => 'mobile_auth_foundation_gate',
    'status' => 'FAIL',
    'MOBILE_AUTH_FOUNDATION_PASS' => 'FAIL',
];

try {
    $testExit = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT);
    if ($testExit === false || ! is_file('/tmp/baseline-junit.xml')) {
        throw new RuntimeException('Full-suite evidence is unavailable');
    }

    $source = file_get_contents('/tmp/baseline-junit.xml');
    if (! is_string($source) || preg_match('/<!DOCTYPE|<!ENTITY/i', $source)) {
        throw new RuntimeException('Unsafe full-suite evidence');
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($source, SimpleXMLElement::class, LIBXML_NONET);
    if ($xml === false) {
        throw new RuntimeException('Invalid full-suite evidence');
    }

    $mobileTests = 0;
    $failures = [];
    foreach ($xml->xpath('//testcase') ?: [] as $case) {
        $class = (string) $case['class'];
        $method = preg_split('/\s+with data set/', (string) $case['name'])[0];
        if (str_starts_with($class, 'Tests\\Feature\\Mobile\\') && ! str_starts_with($class, 'Tests\\Feature\\Mobile\\Phase3\\')
            && ! str_starts_with($class, 'Tests\\Feature\\Mobile\\Phase4\\') && ! str_starts_with($class, 'Tests\\Feature\\Mobile\\Phase5\\')) {
            $mobileTests++;
            if (isset($case->failure) || isset($case->error)) {
                $failures[] = 'MOBILE_TEST_FAILURE';
            }
        }
        if (isset($case->failure) || isset($case->error)) {
            $failures[] = $class.'::'.$method;
        }
    }

    $expectedRegression = 'Tests\\Feature\\StudentExcelImportTest::test_valid_excel_import_creates_students_inside_current_school';
    $record['TEST_EXIT_CODE'] = $testExit;
    $record['mobile_test_cases'] = $mobileTests;
    $record['mobile_test_failures'] = count(array_filter($failures, static fn (string $failure): bool => $failure === 'MOBILE_TEST_FAILURE'));
    $record['full_suite_failure_count'] = count(array_filter($failures, static fn (string $failure): bool => $failure !== 'MOBILE_TEST_FAILURE'));
    $record['known_preexisting_regression_only'] = $record['full_suite_failure_count'] === 1
        && in_array($expectedRegression, $failures, true);

    if ($testExit !== 1 || $mobileTests !== 16 || $record['mobile_test_failures'] !== 0
        || ! $record['known_preexisting_regression_only']) {
        throw new RuntimeException('Phase 2 gate conditions not met');
    }

    $record['status'] = 'PASS';
    $record['MOBILE_AUTH_FOUNDATION_PASS'] = 'PASS';
} catch (\Throwable $error) {
    $record['classification'] = 'TEST_INFRASTRUCTURE';
    $record['exception_class'] = get_class($error);
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($record['MOBILE_AUTH_FOUNDATION_PASS'] === 'PASS' ? 0 : 29);
