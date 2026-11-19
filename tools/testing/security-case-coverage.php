<?php

// Pure case-identity checks: counts alone cannot prove mandatory tests executed.
function assertSecurityCaseCoverage(array $cases, string $lane): array
{
    $datasets = [];
    $excel = [];
    $static = $google = 0;
    $excelMethods = ['test_authorized_manager_can_download_student_import_template',
        'test_valid_excel_import_creates_students_inside_current_school',
        'test_excel_import_rejects_classroom_from_another_school',
        'test_staff_without_student_structure_permission_cannot_import_students'];
    foreach ($cases as $case) {
        $class = $case['class'];
        $name = $case['name'];
        if ($class === 'Tests\\Feature\\Mobile\\Phase7\\MobileRatePolicyTest'
            && preg_match('/^test_independent_processes_cannot_overbook_a_shared_budget with data set #([0-3])$/D', $name, $match)) { $datasets[] = (int)$match[1]; }
        if ($class === 'Tests\\Feature\\StudentExcelImportTest' && in_array($name, $excelMethods, true)) { $excel[] = $name; }
        if ($class === 'Tests\\Feature\\Mobile\\Phase5\\StaticPrivateUrlTest'
            && $name === 'test_http_layer_denies_sensitive_physical_copies_and_symlinks_on_both_aliases') { $static++; }
        if (in_array($class, ['Tests\\Feature\\Mobile\\GoogleIdentityVerifierTest',
            'Tests\\Feature\\Mobile\\GoogleSessionContractTest', 'Tests\\Feature\\Mobile\\GoogleIdentityConcurrencyTest'], true)) { $google++; }
    }
    sort($datasets);
    $coverage = ['original_concurrency_datasets' => $datasets === [0, 1, 2, 3],
        'excel_original_cases' => count($excel) === 4 && count(array_unique($excel)) === 4,
        'static_alias_and_symlink_case' => $static === 1, 'google_security_cases' => $google >= 34];
    $required = match ($lane) {
        'concurrency', 'native' => ['original_concurrency_datasets'],
        'excel' => ['excel_original_cases'], 'static' => ['static_alias_and_symlink_case'],
        'google' => ['google_security_cases'], 'full' => array_keys($coverage),
        default => throw new RuntimeException('Unknown lane'),
    };
    foreach ($required as $key) { if (!$coverage[$key]) { throw new RuntimeException('Mandatory case identity missing'); } }
    return array_intersect_key($coverage, array_flip($required));
}
