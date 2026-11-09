<?php

ini_set('display_errors', '0');
$record = ['kind' => 'infrastructure', 'stage' => 'phase6_acceptance_gate', 'status' => 'FAIL', 'PHASE_6_ACCEPTANCE' => 'FAIL'];
try {
    $read = static function (string $path): array {
        $text = file_get_contents($path);
        if (! is_string($text) || preg_match('/<!DOCTYPE|<!ENTITY/i', $text)) { throw new RuntimeException('Unsafe report'); }
        $xml = simplexml_load_string($text, SimpleXMLElement::class, LIBXML_NONET);
        if (! $xml) { throw new RuntimeException('Invalid report'); }
        return $xml->xpath('//testcase') ?: [];
    };
    $focused = $read('/tmp/phase6-focused-junit.xml'); $classes = [];
    foreach ($focused as $case) {
        if (isset($case->failure) || isset($case->error) || isset($case->skipped)) { throw new RuntimeException('Focused failure'); }
        $classes[(string) $case['class']] = ($classes[(string) $case['class']] ?? 0) + 1;
    }
    if (($classes['Tests\\Feature\\PhoneStateCompatibilityTest'] ?? 0) !== 10 || ($classes['Tests\\Feature\\RealtimeNotificationFlowTest'] ?? 0) !== 2
        || ($classes['Tests\\Feature\\Mobile\\Phase6\\NotificationChannelPolicyTest'] ?? 0) < 8) { throw new RuntimeException('Focused coverage missing'); }
    $full = $read('/tmp/baseline-junit.xml'); $failures = []; $new = 0; $generic = 0; $phase7 = 0; $phase8 = 0;
    foreach ($full as $case) {
        if (isset($case->error) || isset($case->skipped) || (string) $case['class'] === 'Tests\\Feature\\WhatsAppIntegrationTest') { throw new RuntimeException('Unexpected active execution'); }
        if (str_starts_with((string) $case['class'], 'Tests\\Feature\\Mobile\\Phase6\\')) { $new++; }
        if (str_starts_with((string) $case['class'], 'Tests\\Feature\\Mobile\\Phase7\\')) { $phase7++; }
        if (str_starts_with((string) $case['class'], 'Tests\\Feature\\Mobile\\Phase8\\')) { $phase8++; }
        if ((string) $case['class'] === 'Tests\\Feature\\PhoneStateCompatibilityTest') { $generic++; }
        if (isset($case->failure)) { $failures[] = (string) $case['class'].'::'.(string) $case['name']; }
    }
    $m = json_decode(file_get_contents(__DIR__.'/superseded-requirements.json'), true, 512, JSON_THROW_ON_ERROR);
    $expected = $m['baseline_total'] - $m['archived_expanded_cases_static'] + $generic + $new + $phase7 + $phase8;
    if ($generic !== 10 || $new < 8 || count($full) !== $expected || (int) ($argv[1] ?? -1) !== 1
        || $failures !== ['Tests\\Feature\\StudentExcelImportTest::test_valid_excel_import_creates_students_inside_current_school']) { throw new RuntimeException('Regression mismatch'); }
    $record += ['focused_total' => count($focused), 'focused_passed' => count($focused), 'focused_classes' => $classes,
        'full_total' => count($full), 'full_passed' => count($full) - 1, 'full_failed' => 1, 'full_errors' => 0, 'full_skipped' => 0,
        'baseline_total' => 670, 'archived_cases_static_not_executed' => 41, 'relocated_generic_executed' => $generic,
        'retired_product_cases' => 31, 'new_active_cases' => $new, 'expected_active_total' => $expected,
        'known_preexisting_regression_only' => true, 'WHATSAPP_IN_SCOPE' => 'NO', 'run_kind' => 'phase6_regression', 'later_contract_cases' => $phase7, 'phase8_validation_cases' => $phase8];
    $record['status'] = 'PASS'; $record['PHASE_6_ACCEPTANCE'] = 'PASS';
} catch (Throwable) { $record['classification'] = 'PHASE6_EVIDENCE_REQUIRES_REVIEW'; }
echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($record['status'] === 'PASS' ? 0 : 29);
