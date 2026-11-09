<?php

ini_set('display_errors', '0');
$record = ['kind' => 'infrastructure', 'stage' => 'phase7_acceptance_gate', 'status' => 'FAIL', 'MOBILE_API_CONTRACT_PASS' => 'FAIL'];
try {
    $read = static function (string $path): array {
        $source = file_get_contents($path);
        if (! is_string($source) || preg_match('/<!DOCTYPE|<!ENTITY/i', $source)) { throw new RuntimeException('Unsafe report'); }
        $xml = simplexml_load_string($source, SimpleXMLElement::class, LIBXML_NONET);
        if (! $xml) { throw new RuntimeException('Invalid report'); }
        return $xml->xpath('//testcase') ?: [];
    };
    $focused = $read('/tmp/phase7-focused-junit.xml'); $classes = [];
    foreach ($focused as $case) {
        if (isset($case->failure) || isset($case->error) || isset($case->skipped)) { throw new RuntimeException('Focused failure'); }
        $classes[(string) $case['class']] = ($classes[(string) $case['class']] ?? 0) + 1;
    }
    foreach (['MobileApiContractTest', 'MobileErrorContractTest', 'MobilePaginationTest', 'MobileRatePolicyTest', 'MobileSerializationTest'] as $class) {
        if (($classes['Tests\\Feature\\Mobile\\Phase7\\'.$class] ?? 0) < 1) { throw new RuntimeException('Missing class'); }
    }
    $full = $read('/tmp/baseline-junit.xml'); $new = 0; $later = 0; $failures = [];
    foreach ($full as $case) {
        if (isset($case->error) || isset($case->skipped)) { throw new RuntimeException('Execution error'); }
        if (str_starts_with((string) $case['class'], 'Tests\\Feature\\Mobile\\Phase7\\')) { $new++; }
        if (str_starts_with((string) $case['class'], 'Tests\\Feature\\Mobile\\Phase8\\')) { $later++; }
        if (isset($case->failure)) { $failures[] = (string) $case['class'].'::'.(string) $case['name']; }
    }
    if (count($focused) !== $new || count($full) !== 647 + $new + $later || (int) ($argv[1] ?? -1) !== 1
        || $failures !== ['Tests\\Feature\\StudentExcelImportTest::test_valid_excel_import_creates_students_inside_current_school']) { throw new RuntimeException('Regression mismatch'); }
    $profile = json_decode(file_get_contents('/tmp/phase7-query-budget.json'), true, 512, JSON_THROW_ON_ERROR);
    if ($profile['status'] !== 'PASS' || $profile['large_rows'] !== 1001 || $profile['returned_rows'] !== 100
        || $profile['large_queries'] > $profile['query_budget']) { throw new RuntimeException('Query budget missing'); }
    $record += ['focused_total' => count($focused), 'focused_passed' => count($focused), 'focused_classes' => $classes,
        'full_total' => count($full), 'full_passed' => count($full)-1, 'full_failed' => 1, 'full_errors' => 0, 'full_skipped' => 0,
        'phase6_baseline_total' => 647, 'new_contract_cases' => $new, 'later_validation_cases' => $later, 'known_preexisting_regression_only' => true,
        'query_profile' => $profile, 'WHATSAPP_IN_SCOPE' => 'NO', 'run_kind' => 'phase7_regression'];
    $record['status'] = 'PASS'; $record['MOBILE_API_CONTRACT_PASS'] = 'PASS';
} catch (Throwable) { $record['classification'] = 'PHASE7_EVIDENCE_REQUIRES_REVIEW'; }
echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($record['status'] === 'PASS' ? 0 : 29);
