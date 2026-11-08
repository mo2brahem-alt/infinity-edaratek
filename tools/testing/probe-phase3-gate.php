<?php

ini_set('display_errors', '0');
$record = ['kind' => 'infrastructure', 'stage' => 'phase3_acceptance_gate', 'status' => 'FAIL',
    'ACCOUNT_APPROVAL_HARDENING' => 'FAIL', 'AUTHORIZATION_HARDENING' => 'FAIL', 'PHASE_3_ACCEPTANCE' => 'FAIL'];
try {
    $read = static function (string $path): SimpleXMLElement {
        $text = file_get_contents($path);
        if (! is_string($text) || preg_match('/<!DOCTYPE|<!ENTITY/i', $text)) {
            throw new RuntimeException('Unsafe report');
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($text, SimpleXMLElement::class, LIBXML_NONET);
        if (! $xml) { throw new RuntimeException('Invalid report'); }
        return $xml;
    };
    $classes = [];
    $focused = $read('/tmp/phase3-focused-junit.xml')->xpath('//testcase') ?: [];
    foreach ($focused as $case) {
        if (isset($case->failure) || isset($case->error) || isset($case->skipped)) { throw new RuntimeException('Focused failure'); }
        $classes[(string) $case['class']] = ($classes[(string) $case['class']] ?? 0) + 1;
    }
    foreach (['AccountApprovalMatrixTest', 'PrivilegeEscalationTest', 'EnvironmentSafetyTest', 'WebSessionSafetyTest'] as $class) {
        if (($classes['Tests\\Feature\\Mobile\\Phase3\\'.$class] ?? 0) < 1) { throw new RuntimeException('Missing focused class'); }
    }
    foreach (['Tests\\Feature\\SafeAdminSeederTest', 'Tests\\Feature\\Auth\\PasswordResetTest', 'Tests\\Feature\\AccountApprovalFlowTest', 'Tests\\Feature\\SchoolUserRoleAssignmentApiTest', 'Tests\\Feature\\SubscriptionBillingAndSeatsTest'] as $class) {
        if (($classes[$class] ?? 0) < 1) { throw new RuntimeException('Missing web regression class'); }
    }
    $full = $read('/tmp/baseline-junit.xml')->xpath('//testcase') ?: [];
    $failures = [];
    $errors = 0;
    $skips = 0;
    foreach ($full as $case) {
        if (isset($case->error)) { $errors++; }
        if (isset($case->skipped)) { $skips++; }
        if (isset($case->failure)) {
            $failures[] = (string) $case['class'].'::'.(string) $case['name'];
        }
    }
    $expected = ['Tests\\Feature\\StudentExcelImportTest::test_valid_excel_import_creates_students_inside_current_school'];
    if (count($full) <= 539 || $errors !== 0 || $skips !== 0 || $failures !== $expected || (int) ($argv[1] ?? -1) !== 1) {
        throw new RuntimeException('Regression mismatch');
    }
    $record += ['focused_total' => count($focused), 'focused_passed' => count($focused), 'focused_classes' => $classes,
        'full_total' => count($full), 'full_passed' => count($full) - 1, 'full_failed' => 1, 'full_errors' => 0, 'full_skipped' => 0,
        'known_preexisting_regression_only' => true, 'PHASE_4_STARTED' => false];
    $record['status'] = 'PASS';
    foreach (['ACCOUNT_APPROVAL_HARDENING', 'AUTHORIZATION_HARDENING', 'PHASE_3_ACCEPTANCE'] as $gate) { $record[$gate] = 'PASS'; }
} catch (Throwable $error) {
    $record['classification'] = 'PHASE3_EVIDENCE_REQUIRES_REVIEW';
}
echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($record['status'] === 'PASS' ? 0 : 29);
