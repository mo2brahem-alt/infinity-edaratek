<?php

// Pure source integrity: no Laravel bootstrap, archived test loading or external access.
ini_set('display_errors', '0');
$record = ['kind' => 'infrastructure', 'stage' => 'phase8_source_integrity', 'mode' => $argv[1] ?? 'unknown', 'status' => 'FAIL'];
try {
    if (! in_array($record['mode'], ['pre_tests', 'post_tests'], true)) { throw new RuntimeException('Invalid mode'); }
    $scope = json_decode(file_get_contents(__DIR__.'/phase8-scope.json'), true, 512, JSON_THROW_ON_ERROR);
    $mismatches = [];
    foreach (array_merge($scope['immutable_sources'], $scope['image_overrides']) as $file) {
        if (! is_file('/app/'.$file['path']) || hash_file('sha256', '/app/'.$file['path']) !== $file['sha256']) { $mismatches[] = $file['path']; }
    }
    if ($mismatches) { $record['mismatched_source_files'] = array_slice($mismatches, 0, 20); throw new RuntimeException('Source drift'); }
    $record += ['checkpoint' => $scope['checkpoint'], 'immutable_source_count' => count($scope['immutable_sources']),
        'image_override_count' => count($scope['image_overrides']), 'FROZEN_API_V1_UNCHANGED' => 'PASS', 'ACTIVE_TEST_SOURCES_UNCHANGED' => 'PASS'];
    $record['status'] = 'PASS';
} catch (Throwable) { $record['classification'] = 'PHASE8_SOURCE_DRIFT_OR_EVIDENCE_FAILURE'; }
echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($record['status'] === 'PASS' ? 0 : 29);
