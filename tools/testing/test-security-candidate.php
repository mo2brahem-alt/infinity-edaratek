<?php

// Test guard decisions using disposable source-only fixtures, never a database.
$root = sys_get_temp_dir().'/edaratek-source-guard-'.bin2hex(random_bytes(6));
$tool = __DIR__.'/verify-security-candidate.php';
$checks = 0;
function inspectFixture(string $tool, string $root, string $mode): array
{
    $process = proc_open([PHP_BINARY, $tool, $mode, $root], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    $record = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    return [$exit, $record['status']];
}
function expectDecision(array $actual, bool $allow): void
{
    global $checks;
    if ($actual !== ($allow ? [0, 'PASS'] : [29, 'FAIL'])) { throw new RuntimeException('Guard self-test failed'); }
    $checks++;
}
try {
    mkdir($root.'/tools/testing', 0700, true);
    file_put_contents($root.'/changed.txt', 'candidate');
    file_put_contents($root.'/stable.txt', 'stable');
    $scope = ['immutable_sources' => [['path' => 'changed.txt', 'sha256' => hash('sha256', 'frozen')], ['path' => 'stable.txt', 'sha256' => hash('sha256', 'stable')]], 'image_overrides' => []];
    $frozen = $root.'/tools/testing/phase8-scope.json';
    file_put_contents($frozen, json_encode($scope));
    $proposal = ['frozen_manifest_sha256' => hash_file('sha256', $frozen), 'reviewed_changes' => [['path' => 'changed.txt', 'frozen_sha256' => hash('sha256', 'frozen'), 'candidate_sha256' => hash('sha256', 'candidate'), 'reason' => 'Synthetic fixture only']], 'candidate_files' => [], 'approval_status' => 'PENDING_PM_REVIEW', 'approved_by' => ''];
    $path = $root.'/tools/testing/security-baseline-proposal.json';
    $write = static fn (array $value) => file_put_contents($path, json_encode($value));
    $write($proposal);
    expectDecision(inspectFixture($tool, $root, 'candidate'), true);
    expectDecision(inspectFixture($tool, $root, 'release'), false);
    file_put_contents($root.'/stable.txt', 'tampered');
    expectDecision(inspectFixture($tool, $root, 'candidate'), false);
    file_put_contents($root.'/stable.txt', 'stable');
    file_put_contents($root.'/changed.txt', 'unexpected candidate');
    expectDecision(inspectFixture($tool, $root, 'candidate'), false);
    file_put_contents($root.'/changed.txt', 'candidate');
    $bad = $proposal; $bad['reviewed_changes'][0]['frozen_sha256'] = str_repeat('0', 64); $write($bad);
    expectDecision(inspectFixture($tool, $root, 'candidate'), false);
    $bad = $proposal; $bad['reviewed_changes'][] = $bad['reviewed_changes'][0]; $write($bad);
    expectDecision(inspectFixture($tool, $root, 'candidate'), false);
    $write($proposal); file_put_contents($frozen, '{}');
    expectDecision(inspectFixture($tool, $root, 'candidate'), false);
    file_put_contents($frozen, json_encode($scope));
    $approved = $proposal; $approved['approval_status'] = 'APPROVED'; $approved['approved_by'] = 'Synthetic fixture'; $write($approved);
    expectDecision(inspectFixture($tool, $root, 'release'), true);
    echo json_encode(['kind' => 'infrastructure', 'stage' => 'source_guard_self_test', 'status' => 'PASS', 'checks' => $checks]).PHP_EOL;
} finally {
    foreach (['changed.txt', 'stable.txt', 'tools/testing/phase8-scope.json', 'tools/testing/security-baseline-proposal.json'] as $file) { if (is_file($root.'/'.$file)) { unlink($root.'/'.$file); } }
    if (is_dir($root.'/tools/testing')) { rmdir($root.'/tools/testing'); }
    if (is_dir($root.'/tools')) { rmdir($root.'/tools'); }
    if (is_dir($root)) { rmdir($root); }
}
