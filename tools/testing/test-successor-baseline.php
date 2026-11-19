<?php

// Real source fixtures, original assertions untouched. No Laravel/DB/network/Git.
ini_set('display_errors', '0');
set_error_handler(static function (): never { throw new RuntimeException('FIXTURE_IO_FAILED'); });
$source = dirname(__DIR__, 2);
$root = sys_get_temp_dir().'/edaratek-successor-'.bin2hex(random_bytes(6));
$checks = $negative = 0;
$result = 0;
$labels = [];

function successorTestRemove(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) as $name) { if ($name !== '.' && $name !== '..') { successorTestRemove($path.'/'.$name); } }
        rmdir($path);
    } elseif (file_exists($path) || is_link($path)) { unlink($path); }
}

try {
    $base = json_decode(file_get_contents($source.'/tools/testing/successor-source-inventory-v1.json'), true, 512, JSON_THROW_ON_ERROR);
    $original = json_decode(file_get_contents($source.'/tools/testing/successor-acceptance-v1.json'), true, 512, JSON_THROW_ON_ERROR);
    $scope = json_decode(file_get_contents($source.'/tools/testing/phase8-scope.json'), true, 512, JSON_THROW_ON_ERROR);
    $files = array_unique(array_merge(array_column($base['files'], 'path'), array_column($original['gate_files'], 'path'),
        ['tools/testing/successor-source-inventory-v1.json', 'tools/testing/successor-acceptance-v1.json']));
    mkdir($root, 0700);
    foreach ($files as $path) {
        if (!is_dir(dirname($root.'/'.$path))) { mkdir(dirname($root.'/'.$path), 0700, true); }
        copy($source.'/'.$path, $root.'/'.$path);
    }
    $baseMap = array_column($base['files'], 'sha256', 'path');
    $profile = hash_file('sha256', $source.'/tests/TestCase.php') === $baseMap['tests/TestCase.php'] ? 'source' : 'image';
    $inspect = static function (string $label, callable $mutate, bool $pass = false, ?string $requestedProfile = null) use ($root, $original, $profile, &$checks, &$negative, &$labels): void {
        $restore = [];
        $replace = static function (string $path, ?string $bytes) use ($root, &$restore): void {
            if (!array_key_exists($path, $restore)) { $restore[$path] = is_file($root.'/'.$path) ? file_get_contents($root.'/'.$path) : null; }
            if ($bytes === null) { unlink($root.'/'.$path); }
            else { file_put_contents($root.'/'.$path, $bytes); }
        };
        $manifest = static function (callable $edit) use ($original, $replace): void {
            $value = $original; $edit($value);
            $replace('tools/testing/successor-acceptance-v1.json', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        };
        try {
            $mutate($replace, $manifest);
            $process = proc_open([PHP_BINARY, $root.'/tools/testing/check-successor-baseline.php', $root, $requestedProfile ?? $profile],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) { throw new RuntimeException('PROCESS_START_FAILED'); }
            fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($process);
            $record = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
            if ($exit !== ($pass ? 0 : 29) || ($record['status'] ?? '') !== ($pass ? 'PASS' : 'FAIL') || $err !== '') {
                throw new RuntimeException('UNEXPECTED_DECISION_'.$label);
            }
            if ($pass && (($record['approved_differences'] ?? 0) !== 6 || ($record['source_inventory_files_checked'] ?? 0) !== 781
                || ($record['historical_source_bytes_identical'] ?? null) !== false)) { throw new RuntimeException('INVALID_PASS_EVIDENCE_'.$label); }
            $checks++; if (!$pass) { $negative++; } $labels[] = $label;
        } finally {
            foreach ($restore as $path => $bytes) {
                if ($bytes === null) { if (is_file($root.'/'.$path)) { unlink($root.'/'.$path); } }
                else { file_put_contents($root.'/'.$path, $bytes); }
            }
        }
    };
    $inspect('approved_successor', static function (): void {}, true);
    $inspect('isolated_image_exact_overrides', static function ($replace) use ($source): void {
        $replace('tests/TestCase.php', file_get_contents($source.'/tools/testing/IsolatedTestCase.php'));
        $replace('phpunit.xml', file_get_contents($source.'/phpunit.mysql.xml'));
    }, true, 'image');
    $inspect('approval_absent', static fn ($r, $m) => $m(static function (&$v): void { unset($v['approval']); }));
    $inspect('approval_pending', static fn ($r, $m) => $m(static function (&$v): void { $v['approval']['status'] = 'PENDING'; }));
    $inspect('approval_date_absent', static fn ($r, $m) => $m(static function (&$v): void { unset($v['approval']['approved_on']); }));
    $inspect('approval_authority_absent', static fn ($r, $m) => $m(static function (&$v): void { $v['approval']['authority'] = ''; }));
    $inspect('fabricated_github_approval', static fn ($r, $m) => $m(static function (&$v): void { $v['approval']['github_human_approval_claimed'] = true; }));
    $inspect('fabricated_signature', static fn ($r, $m) => $m(static function (&$v): void { $v['approval']['cryptographic_signature_claimed'] = true; }));
    $inspect('provenance_absent', static fn ($r, $m) => $m(static function (&$v): void { unset($v['review_provenance']); }));
    $inspect('ci_provenance_wrong', static fn ($r, $m) => $m(static function (&$v): void { $v['ci_evidence']['url'] = 'https://example.invalid/'; }));
    $inspect('false_new_ci_pass', static fn ($r, $m) => $m(static function (&$v): void { $v['ci_evidence']['successor_github_execution'] = 'PASS'; }));
    $inspect('candidate_commit_wrong', static fn ($r, $m) => $m(static function (&$v): void { $v['candidate_commit_sha'] = str_repeat('0', 40); }));
    $inspect('historical_identity_falsified', static fn ($r, $m) => $m(static function (&$v): void { $v['historical_checkpoint']['historical_source_bytes_identical'] = true; }));
    $inspect('five_deltas', static fn ($r, $m) => $m(static function (&$v): void { array_pop($v['approved_deltas']); }));
    $inspect('seven_deltas', static fn ($r, $m) => $m(static function (&$v): void { $v['approved_deltas'][] = $v['approved_deltas'][0]; }));
    $inspect('duplicate_delta', static fn ($r, $m) => $m(static function (&$v): void { $v['approved_deltas'][1] = $v['approved_deltas'][0]; }));
    $inspect('unapproved_delta_path', static fn ($r, $m) => $m(static function (&$v): void { $v['approved_deltas'][0]['path'] = 'app/unapproved.php'; }));
    $inspect('wrong_approved_hash', static fn ($r, $m) => $m(static function (&$v): void { $v['approved_deltas'][0]['candidate_sha256'] = str_repeat('0', 64); }));
    $inspect('wrong_frozen_hash', static fn ($r, $m) => $m(static function (&$v): void { $v['approved_deltas'][0]['frozen_sha256'] = str_repeat('0', 64); }));
    $approvedPath = $original['approved_deltas'][0]['path'];
    $stablePath = null;
    foreach ($scope['immutable_sources'] as $row) { if (!in_array($row['path'], array_column($original['approved_deltas'], 'path'), true)) { $stablePath = $row['path']; break; } }
    if ($stablePath === null) { throw new RuntimeException('MISSING_STABLE_FIXTURE'); }
    $inspect('additional_original_source_drift', static fn ($r) => $r($stablePath, 'unexpected source'));
    $inspect('missing_original_source', static fn ($r) => $r($stablePath, null));
    $inspect('approved_source_tamper', static fn ($r) => $r($approvedPath, 'unexpected approved source'));
    $inspect('missing_approved_source', static fn ($r) => $r($approvedPath, null));
    $inspect('unapproved_candidate_drift', static fn ($r) => $r('app/Services/Mobile/Google/GoogleIdentityService.php', 'unexpected candidate'));
    $inspect('missing_candidate_file', static fn ($r) => $r('config/firebase_google.php', null));
    $inspect('unexpected_new_source', static fn ($r) => $r('app/unapproved-successor-fixture.php', '<?php // unapproved'));
    $inspect('historical_manifest_tamper', static fn ($r) => $r('tools/testing/phase8-scope.json', '{}'));
    $inspect('historical_checker_tamper', static fn ($r) => $r('tools/testing/check-frozen-baseline.php', '<?php exit(0);'));
    $inspect('historical_proposal_tamper', static fn ($r) => $r('tools/testing/security-baseline-proposal.json', '{}'));
    $inspect('historical_review_tamper', static fn ($r) => $r('docs/EDARATEK_LINUX_FROZEN_BASELINE_REVIEW.md', 'rewritten'));
    $inspect('source_inventory_tamper', static fn ($r) => $r('tools/testing/successor-source-inventory-v1.json', '{}'));
    $inspect('inventory_anchor_removed', static fn ($r, $m) => $m(static function (&$v): void { unset($v['candidate_inventory']); }));
    $inspect('missing_gate_file', static fn ($r) => $r('tools/testing/test-successor-baseline.php', null));
    $inspect('gate_file_inventory_reduced', static fn ($r, $m) => $m(static function (&$v): void { array_pop($v['gate_files']); }));
    $inspect('extra_infrastructure_exception', static fn ($r, $m) => $m(static function (&$v): void { $v['gate_infrastructure_updates'][] = ['path' => 'app/unapproved.php', 'candidate_sha256' => str_repeat('0', 64), 'successor_sha256' => str_repeat('1', 64), 'reason' => 'unapproved']; }));
    $inspect('wrong_infrastructure_parent', static fn ($r, $m) => $m(static function (&$v): void { $v['gate_infrastructure_updates'][0]['candidate_sha256'] = str_repeat('0', 64); }));
    $inspect('workflow_drift', static fn ($r) => $r('.github/workflows/security-validation.yml', 'unexpected workflow'));
    $inspect('unknown_manifest_field', static fn ($r, $m) => $m(static function (&$v): void { $v['bypass'] = true; }));
    $inspect('invalid_profile', static function (): void {}, false, 'unrestricted');
    echo json_encode(['kind' => 'infrastructure', 'stage' => 'successor_guard_self_test', 'status' => 'PASS',
        'checks' => $checks, 'positive_checks' => $checks-$negative, 'negative_checks' => $negative,
        'cases' => $labels, 'database_operations' => 0, 'network_operations' => 0], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    $code = $error instanceof RuntimeException ? $error->getMessage() : 'INVALID_FIXTURE_OR_JSON';
    echo json_encode(['kind' => 'infrastructure', 'stage' => 'successor_guard_self_test', 'status' => 'FAIL',
        'checks' => $checks, 'classification' => preg_match('/^[A-Z_a-z0-9]+$/D', $code) ? $code : 'FIXTURE_FAILURE'], JSON_THROW_ON_ERROR).PHP_EOL;
    $result = 29;
} finally {
    $realParent = realpath(dirname($root));
    if ($realParent === realpath(sys_get_temp_dir()) && preg_match('/^edaratek-successor-[a-f0-9]{12}$/D', basename($root))
        && is_dir($root) && !is_link($root)) { successorTestRemove($root); }
}
exit($result);
