<?php

require __DIR__.'/security-case-coverage.php';
$cases = [];
for ($i = 0; $i < 4; $i++) { $cases[] = ['class' => 'Tests\\Feature\\Mobile\\Phase7\\MobileRatePolicyTest', 'name' => 'test_independent_processes_cannot_overbook_a_shared_budget with data set #'.$i]; }
foreach (['test_authorized_manager_can_download_student_import_template', 'test_valid_excel_import_creates_students_inside_current_school',
    'test_excel_import_rejects_classroom_from_another_school', 'test_staff_without_student_structure_permission_cannot_import_students'] as $name) {
    $cases[] = ['class' => 'Tests\\Feature\\StudentExcelImportTest', 'name' => $name];
}
$cases[] = ['class' => 'Tests\\Feature\\Mobile\\Phase5\\StaticPrivateUrlTest', 'name' => 'test_http_layer_denies_sensitive_physical_copies_and_symlinks_on_both_aliases'];
for ($i = 0; $i < 34; $i++) { $cases[] = ['class' => 'Tests\\Feature\\Mobile\\GoogleIdentityVerifierTest', 'name' => 'synthetic_case_'.$i]; }
$checks = 0;
foreach (['concurrency', 'native', 'excel', 'static', 'google', 'full'] as $lane) { assertSecurityCaseCoverage($cases, $lane); $checks++; }
foreach ([0, 4, 8, 9] as $index) {
    $missing = $cases; unset($missing[$index]);
    try { assertSecurityCaseCoverage(array_values($missing), 'full'); throw new LogicException('Missing case accepted'); }
    catch (RuntimeException) { $checks++; }
}
$duplicate = array_slice($cases, 0, 4); $duplicate[3] = $duplicate[2];
try { assertSecurityCaseCoverage($duplicate, 'concurrency'); throw new LogicException('Duplicate dataset accepted'); }
catch (RuntimeException) { $checks++; }
echo json_encode(['kind' => 'infrastructure', 'stage' => 'case_coverage_self_test', 'status' => 'PASS', 'checks' => $checks]).PHP_EOL;
