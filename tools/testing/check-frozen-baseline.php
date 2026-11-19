<?php

// Authoritative, source-only gate. Proposal hashes never override frozen hashes.
ini_set('display_errors', '0');
$root = $argv[1] ?? dirname(__DIR__, 2);
$record = ['kind' => 'infrastructure', 'stage' => 'authoritative_phase8_fingerprints', 'status' => 'FAIL'];
try {
    $manifest = $root.'/tools/testing/phase8-scope.json';
    if (!is_file($manifest) || hash_file('sha256', $manifest) !== '5862f8395099ac4546ec5a4252443fb5b40ec1c76b978d0233d556702ac9df3d') {
        throw new RuntimeException('Authoritative manifest changed');
    }
    $scope = json_decode(file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
    $proposal = json_decode(file_get_contents($root.'/tools/testing/security-baseline-proposal.json'), true, 512, JSON_THROW_ON_ERROR);
    $details = [];
    foreach ($proposal['reviewed_changes'] as $change) { $details[$change['path']] = $change; }
    $mismatches = [];
    foreach ($scope['immutable_sources'] as $file) {
        $path = $root.'/'.$file['path'];
        $actual = is_file($path) ? hash_file('sha256', $path) : null;
        if ($actual !== $file['sha256']) {
            $mismatches[] = ['path' => $file['path'], 'existing_sha256' => $file['sha256'],
                'proposed_sha256' => $actual, 'reason' => $details[$file['path']]['reason'] ?? 'Unreviewed source drift'];
        }
    }
    // Image-only superclass/phpunit replacements are not source-file changes.
    $record += ['authoritative_manifest_unchanged' => true, 'checked' => count($scope['immutable_sources']),
        'mismatch_count' => count($mismatches), 'mismatches' => $mismatches,
        'baseline_update_authorized' => false];
    if ($mismatches) { throw new RuntimeException('Pending PM fingerprint gate'); }
    $record['status'] = 'PASS';
} catch (Throwable) { $record['classification'] = 'FROZEN_BASELINE_PENDING_PM_REVIEW'; }
echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($record['status'] === 'PASS' ? 0 : 29);
