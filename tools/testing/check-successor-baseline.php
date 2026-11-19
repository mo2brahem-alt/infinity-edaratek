<?php

// Source-only successor v1. Integrity anchors are review references, not signatures.
// Never boots Laravel, accesses a database/network, or changes historical evidence.
ini_set('display_errors', '0');
set_error_handler(static function (): never { throw new RuntimeException('SOURCE_READ_FAILED'); });

const SUCCESSOR_CANDIDATE = 'cec3e0797512a44b3f66325108d4619eea006984';
const SUCCESSOR_FROZEN = '5862f8395099ac4546ec5a4252443fb5b40ec1c76b978d0233d556702ac9df3d';
const SUCCESSOR_INVENTORY = 'd07dca0602ff4db34d8ddc165c8cf6b8c49baad92836b406bd4bf10fe91c1361';
const SUCCESSOR_PROPOSAL = '788a7cfedc5cb081036f3ec49d2780956a26e5bd7e5e343527e0b5645200da95';
const SUCCESSOR_CHECKER = '6cb9d913405d56528b9ed6ff0e97d13c55d3f6b1aea504caccdf1abf4011e318';
const SUCCESSOR_REVIEW = 'd7e980e31483ba20222ec344770f26e1f06c4c012efee026c01ddc6e951afb13';

function successorRequire(bool $condition, string $code): void
{
    if (!$condition) { throw new RuntimeException($code); }
}

function successorKeys(array $value, array $keys): void
{
    $actual = array_keys($value); sort($actual); sort($keys);
    successorRequire($actual === $keys, 'SCHEMA_KEYS_REJECTED');
}

function successorPath(mixed $path): string
{
    successorRequire(is_string($path) && preg_match('~^[A-Za-z0-9_./-]+$~D', $path)
        && !str_starts_with($path, '/') && !str_contains($path, '..')
        && !str_contains($path, '//') && !str_ends_with($path, '/'), 'PATH_REJECTED');
    return $path;
}

function successorHash(mixed $hash): string
{
    successorRequire(is_string($hash) && preg_match('/^[a-f0-9]{64}$/D', $hash), 'HASH_REJECTED');
    return $hash;
}

function successorActual(string $root, string $path): string
{
    successorPath($path);
    $file = $root.'/'.$path;
    successorRequire(is_file($file) && !is_link($file), 'MISSING_OR_LINKED_SOURCE');
    $real = str_replace('\\', '/', realpath($file));
    successorRequire(str_starts_with($real, $root.'/'), 'SOURCE_ESCAPES_ROOT');
    return hash_file('sha256', $file);
}

function successorJson(string $root, string $path, ?string $anchor = null): array
{
    if ($anchor !== null) { successorRequire(successorActual($root, $path) === $anchor, 'REFERENCE_INTEGRITY_FAILED'); }
    $value = json_decode(file_get_contents($root.'/'.$path), true, 512, JSON_THROW_ON_ERROR);
    successorRequire(is_array($value), 'JSON_OBJECT_REQUIRED');
    return $value;
}

function successorMap(array $rows, array $keys = ['path', 'sha256']): array
{
    successorRequire(array_is_list($rows), 'INVENTORY_LIST_REQUIRED');
    $map = [];
    foreach ($rows as $row) {
        successorRequire(is_array($row), 'INVENTORY_ROW_REJECTED'); successorKeys($row, $keys);
        $path = successorPath($row['path']);
        successorRequire(!isset($map[$path]), 'DUPLICATE_PATH');
        $map[$path] = $row;
    }
    return $map;
}

function successorTree(string $root, string $directory, array &$paths): void
{
    if ($directory === 'bootstrap/cache') { return; } // Explicitly generated runtime cache, never source.
    successorRequire(is_dir($root.'/'.$directory) && !is_link($root.'/'.$directory), 'SOURCE_DIRECTORY_REJECTED');
    foreach (scandir($root.'/'.$directory) as $name) {
        if ($name === '.' || $name === '..') { continue; }
        $path = successorPath($directory.'/'.$name);
        successorRequire(!is_link($root.'/'.$path), 'LINKED_SOURCE_REJECTED');
        if (is_dir($root.'/'.$path)) { successorTree($root, $path, $paths); }
        else { successorRequire(is_file($root.'/'.$path), 'NONREGULAR_SOURCE_REJECTED'); $paths[] = $path; }
    }
}

$record = ['kind' => 'infrastructure', 'stage' => 'approved_successor_checkpoint_v1', 'status' => 'FAIL'];
try {
    successorRequire($argc <= 3, 'ARGUMENTS_REJECTED');
    $resolved = realpath($argv[1] ?? dirname(__DIR__, 2));
    successorRequire($resolved !== false, 'ROOT_REJECTED');
    $root = rtrim(str_replace('\\', '/', $resolved), '/');
    $profile = $argv[2] ?? ($root === '/app' ? 'image' : 'source');
    successorRequire(in_array($profile, ['source', 'image'], true), 'PROFILE_REJECTED');
    $scope = successorJson($root, 'tools/testing/phase8-scope.json', SUCCESSOR_FROZEN);
    successorRequire($scope['checkpoint'] === '8020e8b9363e1d100a1012c9b0a68d245169f2c1', 'HISTORICAL_CHECKPOINT_REJECTED');
    successorRequire(successorActual($root, 'tools/testing/check-frozen-baseline.php') === SUCCESSOR_CHECKER, 'HISTORICAL_CHECKER_CHANGED');
    $proposal = successorJson($root, 'tools/testing/security-baseline-proposal.json', SUCCESSOR_PROPOSAL);
    $base = successorJson($root, 'tools/testing/successor-source-inventory-v1.json', SUCCESSOR_INVENTORY);
    successorRequire($base['candidate_commit_sha'] === SUCCESSOR_CANDIDATE, 'INVENTORY_CANDIDATE_REJECTED');
    $manifest = successorJson($root, 'tools/testing/successor-acceptance-v1.json');
    successorKeys($manifest, ['schema_version', 'checkpoint', 'historical_checkpoint', 'candidate_commit_sha', 'approval',
        'review_provenance', 'ci_evidence', 'approved_deltas', 'candidate_inventory', 'gate_infrastructure_updates', 'gate_files']);
    successorRequire($manifest['schema_version'] === 1 && $manifest['checkpoint'] === 'edaratek-google-successor-v1'
        && $manifest['candidate_commit_sha'] === SUCCESSOR_CANDIDATE, 'SUCCESSOR_IDENTITY_REJECTED');
    successorRequire($manifest['historical_checkpoint'] === [
        'commit_sha' => $scope['checkpoint'], 'manifest_path' => 'tools/testing/phase8-scope.json',
        'manifest_sha256' => SUCCESSOR_FROZEN, 'checker_path' => 'tools/testing/check-frozen-baseline.php',
        'checker_sha256' => SUCCESSOR_CHECKER, 'historical_source_bytes_identical' => false,
    ], 'HISTORICAL_REFERENCE_REJECTED');
    successorRequire($manifest['approval'] === [
        'status' => 'APPROVED_SUCCESSOR_ONLY', 'approved_on' => '2026-11-19',
        'authority' => 'PM user instruction in the current conversation',
        'instruction_title' => 'EDARATEK — APPROVED SIX-FINGERPRINT SUCCESSOR GATE',
        'github_human_approval_claimed' => false, 'cryptographic_signature_claimed' => false,
        'scope' => 'Exactly six reviewed source deltas; immutable historical Phase 8 evidence; local successor tooling only; no commit, push or deployment',
    ], 'PM_APPROVAL_METADATA_REJECTED');
    successorRequire($manifest['review_provenance'] === [
        'proposal_path' => 'tools/testing/security-baseline-proposal.json', 'proposal_sha256' => SUCCESSOR_PROPOSAL,
        'review_path' => 'docs/EDARATEK_LINUX_FROZEN_BASELINE_REVIEW.md', 'review_sha256' => SUCCESSOR_REVIEW,
        'record_type' => 'Conversation authorization recorded by implementation; not a signed attestation or GitHub review',
    ], 'REVIEW_PROVENANCE_REJECTED');
    successorRequire(successorActual($root, 'docs/EDARATEK_LINUX_FROZEN_BASELINE_REVIEW.md') === SUCCESSOR_REVIEW, 'REVIEW_DOCUMENT_CHANGED');
    successorRequire($manifest['ci_evidence'] === [
        'url' => 'https://github.com/mo2brahem-alt/infinity-edaratek/actions/runs/44535861202',
        'candidate_commit_sha' => SUCCESSOR_CANDIDATE, 'functional_job_result' => 'PASS',
        'full_suite_passed' => 761, 'full_suite_assertions' => 7254,
        'overall_run_result' => 'FAILURE_HISTORICAL_FINGERPRINT_GATE', 'successor_github_execution' => 'NOT_EXECUTED',
    ], 'CI_PROVENANCE_REJECTED');
    $approved = successorMap($manifest['approved_deltas'], ['path', 'frozen_sha256', 'candidate_sha256', 'reason']);
    $reviewed = successorMap($proposal['reviewed_changes'], ['path', 'frozen_sha256', 'candidate_sha256', 'reason']);
    successorRequire(count($approved) === 6 && count($reviewed) === 6, 'EXACTLY_SIX_DELTAS_REQUIRED');
    foreach ($approved as $path => $change) {
        successorRequire(isset($reviewed[$path]) && $change === $reviewed[$path], 'UNAPPROVED_DELTA');
    }
    $immutable = successorMap($scope['immutable_sources']);
    successorRequire(count($immutable) === 162, 'HISTORICAL_INVENTORY_REJECTED');
    $used = [];
    foreach ($immutable as $path => $source) {
        $expected = $source['sha256'];
        if (isset($approved[$path])) {
            $change = $approved[$path];
            successorRequire($change['frozen_sha256'] === $expected && $change['candidate_sha256'] !== $expected, 'INVALID_APPROVED_DELTA');
            $expected = successorHash($change['candidate_sha256']); $used[] = $path;
        }
        successorRequire(successorActual($root, $path) === $expected, 'ORIGINAL_IMMUTABLE_SOURCE_DRIFT');
    }
    successorRequire(count($used) === 6, 'MISSING_APPROVED_DELTA');
    $inventory = successorMap($base['files']);
    successorRequire(count($inventory) === 777 && $manifest['candidate_inventory'] === [
        'path' => 'tools/testing/successor-source-inventory-v1.json', 'sha256' => SUCCESSOR_INVENTORY,
        'base_source_count' => 777, 'proposal_candidate_count' => 44,
        'manifest_self_hash' => 'Excluded to avoid circular hashing; strict schema, fixed approval and anchored references are verified',
    ], 'CANDIDATE_INVENTORY_REJECTED');
    $candidates = successorMap($proposal['candidate_files']);
    successorRequire(count($candidates) === 44, 'CANDIDATE_SET_REJECTED');
    foreach ($candidates as $path => $source) {
        successorRequire(isset($inventory[$path]) && $inventory[$path]['sha256'] === $source['sha256'], 'CANDIDATE_PROVENANCE_REJECTED');
    }
    $updates = successorMap($manifest['gate_infrastructure_updates'], ['path', 'candidate_sha256', 'successor_sha256', 'reason']);
    $allowed = ['.github/workflows/security-validation.yml', 'tools/testing/run-security-gate.sh', 'tools/testing/run-tests.ps1'];
    $actualUpdates = array_keys($updates); sort($actualUpdates); sort($allowed);
    successorRequire($actualUpdates === $allowed, 'GATE_UPDATE_SET_REJECTED');
    foreach ($updates as $path => $update) {
        successorRequire(!isset($immutable[$path]) && $update['candidate_sha256'] === $inventory[$path]['sha256']
            && is_string($update['reason']) && trim($update['reason']) !== '', 'GATE_UPDATE_PROVENANCE_REJECTED');
        $inventory[$path]['sha256'] = successorHash($update['successor_sha256']);
    }
    $new = successorMap($manifest['gate_files']);
    $expectedNew = ['tools/testing/check-successor-baseline.php', 'tools/testing/test-successor-baseline.php'];
    $actualNew = array_keys($new); sort($actualNew); sort($expectedNew);
    successorRequire($actualNew === $expectedNew, 'GATE_FILE_SET_REJECTED');
    foreach ($new as $path => $source) { successorRequire(!isset($inventory[$path]), 'GATE_FILE_COLLISION'); $inventory[$path] = $source; }
    if ($profile === 'image') {
        foreach ($scope['image_overrides'] as $source) { $inventory[$source['path']] = $source; }
    }
    foreach ($inventory as $path => $source) {
        successorRequire(successorActual($root, $path) === successorHash($source['sha256']), 'CANDIDATE_SOURCE_DRIFT');
    }
    $paths = [];
    foreach ($base['protected_directories'] as $directory) { successorTree($root, $directory, $paths); }
    foreach (array_merge($base['root_files'], $base['documentation_files']) as $path) {
        // The anchor inventories only files that existed in the approved commit.
        if (isset($inventory[$path])) { $paths[] = $path; }
    }
    $expectedPaths = array_merge(array_keys($inventory), ['tools/testing/successor-source-inventory-v1.json', 'tools/testing/successor-acceptance-v1.json']);
    sort($paths); sort($expectedPaths);
    successorRequire($paths === $expectedPaths, 'UNEXPECTED_OR_MISSING_SOURCE_FILE');
    $record += ['checkpoint' => $manifest['checkpoint'], 'historical_manifest_integrity' => 'PASS',
        'historical_checker_integrity' => 'PASS', 'historical_immutable_sources_checked' => count($immutable),
        'historical_source_bytes_identical' => false, 'approved_differences' => 6,
        'base_candidate_sources_checked' => 777, 'proposal_candidate_sources_checked' => 44,
        'gate_infrastructure_updates_checked' => 3, 'source_inventory_files_checked' => count($paths),
        'profile' => $profile, 'approval_status' => 'APPROVED_SUCCESSOR_ONLY',
        'functional_tests' => 'INDEPENDENT_GATE_REQUIRED', 'successor_github_execution' => 'NOT_EXECUTED'];
    $record['status'] = 'PASS';
} catch (Throwable $error) {
    $code = $error instanceof RuntimeException ? $error->getMessage() : 'INVALID_MANIFEST_OR_SOURCE';
    $record['classification'] = preg_match('/^[A-Z_]+$/D', $code) ? $code : 'INVALID_MANIFEST_OR_SOURCE';
}
echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($record['status'] === 'PASS' ? 0 : 29);
