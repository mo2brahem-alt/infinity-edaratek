<?php

// Static retirement accounting only. Never load or execute archived delivery tests.
ini_set('display_errors', '0');
$record = ['kind' => 'infrastructure', 'stage' => 'notification_scope_reconciliation', 'status' => 'FAIL'];
try {
    $m = json_decode(file_get_contents(__DIR__.'/superseded-requirements.json'), true, 512, JSON_THROW_ON_ERROR);
    $text = file_get_contents('/app/'.$m['archived_file']);
    if (hash('sha256', $text) !== $m['archived_sha256']) { throw new RuntimeException('Historical source changed'); }
    preg_match_all('/public function test_[A-Za-z0-9_]+\(/', $text, $methods);
    $a = strpos($text, 'public static function phoneNumberCases'); $b = strpos($text, 'private function signedWebhookPost', $a);
    preg_match_all("/^\s*'[^']+'\s*=>\s*\[/m", substr($text, $a, $b - $a), $datasets);
    $cases = count($methods[0]) - 1 + count($datasets[0]);
    if (count($methods[0]) !== 33 || count($datasets[0]) !== 9 || $cases !== $m['archived_expanded_cases_static']) { throw new RuntimeException('Static contribution mismatch'); }
    foreach (['phpunit.xml', 'phpunit.mysql.xml'] as $config) {
        $xml = simplexml_load_file('/app/'.$config, SimpleXMLElement::class, LIBXML_NONET);
        $excludes = $xml->xpath('//testsuites//exclude') ?: [];
        if (count($excludes) !== 1 || (string) $excludes[0] !== $m['archived_file']) { throw new RuntimeException('Unexpected exclusion'); }
    }
    $record += ['archived_file_unchanged' => true, 'archived_cases_static_not_executed' => $cases,
        'generic_cases_relocated_expected' => 10, 'retired_product_cases' => 31, 'only_one_targeted_exclusion' => true,
        'delivery_tests_executed' => false, 'WHATSAPP_IN_SCOPE' => 'NO'];
    $record['status'] = 'PASS';
} catch (Throwable) { $record['classification'] = 'SCOPE_RECONCILIATION_REQUIRES_REVIEW'; }
echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($record['status'] === 'PASS' ? 0 : 29);
