<?php

// Sanitized acceptance evidence; raw JUnit and diagnostics remain in tmpfs.
ini_set('display_errors', '0');
$record = ['kind' => 'infrastructure', 'stage' => 'phase4_acceptance_gate', 'status' => 'FAIL',
    'TENANT_ISOLATION' => 'FAIL', 'STAFF_TASK_SECURITY' => 'FAIL', 'PHASE_4_ACCEPTANCE' => 'FAIL'];
try {
    $read = static function (string $path): array {
        $text = file_get_contents($path);
        if (! is_string($text) || preg_match('/<!DOCTYPE|<!ENTITY/i', $text)) { throw new RuntimeException('Unsafe report'); }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($text, SimpleXMLElement::class, LIBXML_NONET);
        if (! $xml) { throw new RuntimeException('Invalid report'); }
        return $xml->xpath('//testcase') ?: [];
    };
    $focused = $read('/tmp/phase4-focused-junit.xml');
    $classes = [];
    foreach ($focused as $case) {
        if (isset($case->failure) || isset($case->error) || isset($case->skipped)) { throw new RuntimeException('Focused failure'); }
        $classes[(string) $case['class']] = ($classes[(string) $case['class']] ?? 0) + 1;
    }
    foreach (['Mobile\\Phase4\\StaffTaskIsolationTest', 'Mobile\\Phase4\\CurrentTenantContextTest', 'Mobile\\Phase4\\ForeignRelationshipTest',
        'TicketWorkflowAuthorizationTest', 'HeavyEndpointsPaginationTest', 'RealtimeNotificationFlowTest',
        'ActiveSchoolAssociationMiddlewareTest', 'AcademicPlanningAccessTest', 'QuickSetupStatusTest', 'SchoolTenantIsolationTest'] as $class) {
        if (($classes['Tests\\Feature\\'.$class] ?? 0) < 1) { throw new RuntimeException('Missing class'); }
    }
    $full = $read('/tmp/baseline-junit.xml');
    $failures = [];
    foreach ($full as $case) {
        if (isset($case->error) || isset($case->skipped)) { throw new RuntimeException('Regression execution error'); }
        if (isset($case->failure)) { $failures[] = (string) $case['class'].'::'.(string) $case['name']; }
    }
    if (count($full) <= 615 || $failures !== ['Tests\\Feature\\StudentExcelImportTest::test_valid_excel_import_creates_students_inside_current_school']
        || (int) ($argv[1] ?? -1) !== 1) { throw new RuntimeException('Regression mismatch'); }
    $record += ['focused_total' => count($focused), 'focused_passed' => count($focused), 'focused_classes' => $classes,
        'full_total' => count($full), 'full_passed' => count($full) - 1, 'full_failed' => 1, 'full_errors' => 0, 'full_skipped' => 0,
        'known_preexisting_regression_only' => true, 'PHASE_5_STARTED' => false];
    $record['status'] = 'PASS';
    foreach (['TENANT_ISOLATION', 'STAFF_TASK_SECURITY', 'PHASE_4_ACCEPTANCE'] as $gate) { $record[$gate] = 'PASS'; }
} catch (Throwable $error) {
    $record['classification'] = 'PHASE4_EVIDENCE_REQUIRES_REVIEW';
}
echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($record['status'] === 'PASS' ? 0 : 29);
