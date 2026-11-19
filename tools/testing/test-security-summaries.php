<?php

// Synthetic XML validates rejection rules, never reports application test PASS.
$root = sys_get_temp_dir().'/edaratek-summary-'.bin2hex(random_bytes(6));
mkdir($root, 0700);
$checks = 0;
$concurrency = [];
for ($i = 0; $i < 4; $i++) { $concurrency[] = ['Tests\\Feature\\Mobile\\Phase7\\MobileRatePolicyTest', 'test_independent_processes_cannot_overbook_a_shared_budget with data set #'.$i]; }
$excel = [];
foreach (['test_authorized_manager_can_download_student_import_template', 'test_valid_excel_import_creates_students_inside_current_school',
    'test_excel_import_rejects_classroom_from_another_school', 'test_staff_without_student_structure_permission_cannot_import_students'] as $name) { $excel[] = ['Tests\\Feature\\StudentExcelImportTest', $name]; }
$static = [['Tests\\Feature\\Mobile\\Phase5\\StaticPrivateUrlTest', 'test_http_layer_denies_sensitive_physical_copies_and_symlinks_on_both_aliases']];
$google = [];
for ($i = 0; $i < 34; $i++) { $google[] = ['Tests\\Feature\\Mobile\\GoogleIdentityVerifierTest', 'test_fixture_'.$i]; }
$padding = array_fill(0, 718, ['Fixture\\Safe', 'test_fixture']);
$inspect = static function (string $lane, array $cases, bool $allow, string $status = '', int $code = 0) use ($root, &$checks): void {
    $xml = '<testsuites><testsuite>';
    foreach ($cases as $index => [$class, $name]) {
        $xml .= '<testcase class="'.htmlspecialchars($class, ENT_XML1).'" name="'.htmlspecialchars($name, ENT_XML1).'">'.($index === 0 ? $status : '').'</testcase>';
    }
    file_put_contents($root.'/security-'.$lane.'.xml', $xml.'</testsuite></testsuites>');
    $process = proc_open([PHP_BINARY, __DIR__.'/summarize-security-tests.php', $lane, (string)$code, $root], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    fclose($pipes[0]); $output = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process); $record = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    if (($exit === 0) !== $allow || ($record['status'] === 'PASS') !== $allow || str_contains($output, 'PRIVATE_FIXTURE')) { throw new RuntimeException('Summary guard self-test failed'); }
    $checks++;
};
try {
    $inspect('concurrency', $concurrency, true);
    $inspect('concurrency', array_slice($concurrency, 0, 3), false);
    $duplicate = $concurrency; $duplicate[3] = $duplicate[2]; $inspect('concurrency', $duplicate, false);
    $inspect('concurrency', array_fill(0, 4, ['Fixture\\Safe', 'test_fixture']), false);
    foreach (['<failure>PRIVATE_FIXTURE</failure>', '<error>PRIVATE_FIXTURE</error>', '<skipped/>'] as $status) { $inspect('concurrency', $concurrency, false, $status); }
    $inspect('concurrency', $concurrency, false, '', 1);
    $inspect('google', $google, true);
    $inspect('native', array_merge($concurrency, array_slice($padding, 0, 149)), true);
    $inspect('excel', $excel, true);
    $inspect('excel', array_slice($excel, 0, 3), false);
    $inspect('static', $static, true);
    $inspect('static', [['Fixture\\Safe', 'test_fixture']], false);
    $full = array_merge($concurrency, $excel, $static, $google, $padding);
    $inspect('full', $full, true);
    $missing = $full; $missing[0] = ['Fixture\\Safe', 'test_fixture']; $inspect('full', $missing, false);
    $missing = $full; $missing[4] = ['Fixture\\Safe', 'test_fixture']; $inspect('full', $missing, false);
    echo json_encode(['kind'=>'infrastructure', 'stage'=>'summary_guard_self_test', 'status'=>'PASS', 'checks'=>$checks]).PHP_EOL;
} finally {
    foreach (['concurrency', 'google', 'native', 'excel', 'static', 'full'] as $lane) { if (is_file($root.'/security-'.$lane.'.xml')) { unlink($root.'/security-'.$lane.'.xml'); } }
    rmdir($root);
}
