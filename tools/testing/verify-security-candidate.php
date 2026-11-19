<?php

// Source-only verification. Never boots Laravel or changes frozen evidence.
ini_set('display_errors', '0');
$mode = $argv[1] ?? 'candidate';
$root = rtrim($argv[2] ?? '/app', '/\\');
$record = ['kind' => 'infrastructure', 'stage' => 'security_candidate_sources', 'status' => 'FAIL'];
try {
    if (!in_array($mode, ['candidate', 'release'], true)) { throw new RuntimeException('Mode rejected'); }
    $proposal = json_decode(file_get_contents($root.'/tools/testing/security-baseline-proposal.json'), true, 512, JSON_THROW_ON_ERROR);
    $frozen = $root.'/tools/testing/phase8-scope.json';
    if (hash_file('sha256', $frozen) !== $proposal['frozen_manifest_sha256']) { throw new RuntimeException('Frozen manifest changed'); }
    $scope = json_decode(file_get_contents($frozen), true, 512, JSON_THROW_ON_ERROR);
    $exceptions = [];
    foreach ($proposal['reviewed_changes'] as $change) {
        if (isset($exceptions[$change['path']]) || !$change['reason']) { throw new RuntimeException('Invalid reviewed change'); }
        $exceptions[$change['path']] = $change;
    }
    $used = [];
    foreach ($scope['immutable_sources'] as $source) {
        $expected = $source['sha256'];
        if (isset($exceptions[$source['path']])) {
            $change = $exceptions[$source['path']];
            if ($change['frozen_sha256'] !== $expected) { throw new RuntimeException('Frozen provenance mismatch'); }
            $expected = $change['candidate_sha256'];
            $used[] = $source['path'];
        }
        if (!is_file($root.'/'.$source['path']) || hash_file('sha256', $root.'/'.$source['path']) !== $expected) { throw new RuntimeException('Unreviewed source drift'); }
    }
    if (count($used) !== count($exceptions)) { throw new RuntimeException('Unknown exception'); }
    foreach ($proposal['candidate_files'] as $source) {
        if (!preg_match('~^[A-Za-z0-9_./-]+$~D', $source['path']) || str_contains($source['path'], '..')
            || !is_file($root.'/'.$source['path']) || hash_file('sha256', $root.'/'.$source['path']) !== $source['sha256']) { throw new RuntimeException('Candidate drift'); }
    }
    if ($root === '/app') {
        foreach ($scope['image_overrides'] as $source) {
            if (hash_file('sha256', $root.'/'.$source['path']) !== $source['sha256']) { throw new RuntimeException('Isolation bootstrap drift'); }
        }
    }
    $record += ['frozen_manifest_unchanged' => true, 'reviewed_change_count' => count($used), 'baseline_approval' => $proposal['approval_status']];
    if ($mode === 'release' && ($proposal['approval_status'] !== 'APPROVED' || trim($proposal['approved_by'] ?? '') === '')) {
        throw new RuntimeException('PM baseline approval required');
    }
    $record['status'] = 'PASS';
} catch (Throwable) { $record['classification'] = 'SOURCE_OR_APPROVAL_GATE_REQUIRES_REVIEW'; }
echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($record['status'] === 'PASS' ? 0 : 29);
