<?php

// Emit case symbols and counts only, never failure text, SQL or credentials.
ini_set('display_errors', '0');
require __DIR__.'/security-case-coverage.php';
$record = ['kind' => 'baseline', 'stage' => 'security_'.($argv[1] ?? 'unknown'), 'status' => 'FAIL'];
try {
    $lane = $argv[1] ?? '';
    if (!in_array($lane, ['concurrency', 'google', 'native', 'excel', 'static', 'full'], true)) { throw new RuntimeException('Unknown lane'); }
    // Optional local fixture directory is for portable tool self-tests only;
    // the Linux runner always uses its fixed /tmp evidence directory.
    $directory = realpath($argv[3] ?? '/tmp');
    if ($directory === false || !is_dir($directory)) { throw new RuntimeException('Missing evidence directory'); }
    $xml = simplexml_load_file($directory.'/security-'.$lane.'.xml', SimpleXMLElement::class, LIBXML_NONET);
    if (!$xml) { throw new RuntimeException('Missing execution evidence'); }
    $cases = $xml->xpath('//testcase');
    $failed = $errors = $skipped = $assertions = 0;
    $concurrency = [];
    $symbols = [];
    $caseRecords = [];
    foreach ($cases as $case) {
        $caseRecords[] = ['class' => (string)$case['class'], 'name' => (string)$case['name']];
        $assertions += (int)$case['assertions'];
        $failed += isset($case->failure) ? 1 : 0;
        $errors += isset($case->error) ? 1 : 0;
        $skipped += isset($case->skipped) ? 1 : 0;
        if ((string)$case['class'] === 'Tests\\Feature\\Mobile\\Phase7\\MobileRatePolicyTest'
            && preg_match('/^test_independent_processes_cannot_overbook_a_shared_budget with data set #([0-3])$/D', (string)$case['name'], $match)) {
            $concurrency[['login', 'read', 'session', 'reset'][(int)$match[1]]] = [
                'status' => isset($case->failure) ? 'FAIL' : (isset($case->error) ? 'ERROR' : (isset($case->skipped) ? 'SKIPPED' : 'PASS')),
                'assertions' => (int)$case['assertions']];
        }
        if (isset($case->failure) || isset($case->error) || isset($case->skipped)) {
            $class = (string)$case['class'];
            $method = preg_split('/\s+with data set/', (string)$case['name'])[0];
            if (preg_match('/^[A-Za-z0-9_\\\\]+$/D', $class) && preg_match('/^[A-Za-z0-9_]+$/D', $method)) { $symbols[] = $class.'::'.$method; }
        }
    }
    $record += ['total' => count($cases), 'passed' => count($cases)-$failed-$errors-$skipped, 'failed' => $failed, 'errors' => $errors, 'skipped' => $skipped, 'assertions' => $assertions, 'nonpassing_symbols' => array_values(array_unique($symbols))];
    if ($concurrency) { $record['original_concurrency_results'] = $concurrency; }
    $minimum = ['concurrency' => 4, 'google' => 34, 'native' => 153, 'excel' => 4, 'static' => 1, 'full' => 761][$lane];
    $record['mandatory_coverage'] = assertSecurityCaseCoverage($caseRecords, $lane);
    if (count($cases) < $minimum || (in_array($lane, ['concurrency', 'excel', 'static'], true) && count($cases) !== $minimum)
        || $failed || $errors || $skipped || (int)($argv[2] ?? -1) !== 0) { throw new RuntimeException('Tests did not pass completely'); }
    $record['status'] = 'PASS';
} catch (Throwable) { $record['classification'] = 'INCOMPLETE_OR_FAILING_SECURITY_REGRESSION'; }
echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($record['status'] === 'PASS' ? 0 : 29);
