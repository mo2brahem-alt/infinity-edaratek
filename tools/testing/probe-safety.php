<?php

// T005 proof in the disposable image only. No raw child output or credentials.
ini_set('display_errors', '0');
ob_start();
$record = ['kind' => 'infrastructure', 'stage' => 'phase1_safety_probe', 'T005_GATE' => 'FAIL'];
try {
    require '/app/vendor/autoload.php';
    $cases = [
        'non_testing_environment' => ['APP_ENV' => 'production'],
        'unsafe_db_host' => ['DB_HOST' => 'unsafe.invalid'],
        'unsafe_db_name' => ['DB_DATABASE' => 'unapproved_database'],
        'provider_credential_present' => ['N8N_WHATSAPP_WEBHOOK_SECRET' => 'synthetic-invalid-fixture'],
        'cloud_credential_present' => ['AWS_ACCESS_KEY_ID' => 'synthetic-invalid-fixture'],
        'live_hostname_present' => ['PHASE1_SENTINEL' => 'test.edaratek.com'],
        'unsafe_smtp_host' => ['MAIL_HOST' => 'smtp.invalid'],
        'debug_enabled' => ['APP_DEBUG' => 'true'],
    ];
    foreach (['CONFIG', 'EVENTS', 'PACKAGES', 'ROUTES', 'SERVICES'] as $cache) {
        $cases['unsafe_'.strtolower($cache).'_cache'] = ['APP_'.$cache.'_CACHE' => '/app/bootstrap/cache/unsafe.php'];
    }
    $preDatabaseCategories = ['profile', 'workspace_or_ephemeral_identity', 'cache_path',
        'provider_or_host_credential', 'live_hostname', 'unexpected_service_endpoint', 'unexpected_credential'];
    foreach ($cases as $name => $override) {
        $process = new Symfony\Component\Process\Process(
            [PHP_BINARY, 'tools/testing/assert-environment.php', 'preflight'], '/app', $override);
        $process->setTimeout(15);
        $process->run();
        $result = json_decode(trim($process->getOutput()), true);
        $rejected = $process->getExitCode() === 20 && ($result['kind'] ?? '') === 'guard'
            && in_array($result['category'] ?? '', $preDatabaseCategories, true);
        $record['negative_cases'][$name] = ['rejected_before_database' => $rejected,
            'exit' => $process->getExitCode()];
        if (!$rejected) { throw new RuntimeException('Negative guard proof failed'); }
    }
    $argv[1] = 'boot';
    require __DIR__.'/assert-environment.php';
    Illuminate\Support\Facades\Http::preventStrayRequests();
    $blocked = false;
    try { Illuminate\Support\Facades\Http::get('http://unmatched.invalid/phase1'); }
    catch (Illuminate\Http\Client\StrayRequestException $expected) { $blocked = true; }
    if (!$blocked) { throw new RuntimeException('Unmatched HTTP was not rejected'); }
    $record['http_stray_request_blocked_without_transport'] = true;
    Illuminate\Support\Facades\Http::fake([
        'http://fixture.invalid/*' => Illuminate\Support\Facades\Http::response(['synthetic' => true], 200),
    ]);
    if (Illuminate\Support\Facades\Http::get('http://fixture.invalid/check')->status() !== 200) {
        throw new RuntimeException('HTTP fake verification failed');
    }
    Illuminate\Support\Facades\Http::assertSentCount(1);
    $record['http_fake_executed'] = true;
    Illuminate\Support\Facades\Mail::fake();
    Illuminate\Support\Facades\Mail::send((new Illuminate\Mail\Mailable())->html('synthetic')->to('fixture@example.invalid'));
    Illuminate\Support\Facades\Mail::assertSentCount(1);
    $record['mail_fake_intercepted'] = true;
    Illuminate\Support\Facades\Notification::fake();
    Illuminate\Support\Facades\Notification::route('mail', 'fixture@example.invalid')->notify(
        new class extends Illuminate\Notifications\Notification {
            public function via($notifiable) { return ['mail']; }
        });
    Illuminate\Support\Facades\Notification::assertCount(1);
    $record['notification_fake_intercepted'] = true;
    if (config('queue.default') !== 'sync') { throw new RuntimeException('Unexpected queue profile'); }
    $job = new class {
        public bool $handled = false;
        public function handle(): void { $this->handled = true; }
    };
    Illuminate\Support\Facades\Bus::dispatchSync($job);
    if (!$job->handled) { throw new RuntimeException('Controlled synchronous job did not run'); }
    $record['controlled_sync_job_executed_without_worker'] = true;
    // RFC5737 TEST-NET address only, no provider, no request bytes transmitted.
    $socket = @stream_socket_client('tcp://192.0.2.1:9', $number, $message, 0.5);
    if (is_resource($socket)) { fclose($socket); throw new RuntimeException('Unexpected external route'); }
    $record['external_testnet_tcp_blocked'] = true;
    $dsn = 'mysql:host=mysql;port=3306;dbname='.getenv('DB_DATABASE');
    $a = new PDO($dsn, 'edaratek_test', getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $b = new PDO($dsn, 'edaratek_test', getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    try {
        $first = (int) $a->query("SELECT GET_LOCK('edaratek_phase1_probe', 0)")->fetchColumn();
        $denied = (int) $b->query("SELECT GET_LOCK('edaratek_phase1_probe', 0)")->fetchColumn();
        $a->query("SELECT RELEASE_LOCK('edaratek_phase1_probe')");
        $second = (int) $b->query("SELECT GET_LOCK('edaratek_phase1_probe', 0)")->fetchColumn();
        if ($first !== 1 || $denied !== 0 || $second !== 1) { throw new RuntimeException('Shared lock proof failed'); }
        $record['independent_connections_shared_mysql_lock'] = true;
    } finally {
        $a->query("SELECT RELEASE_LOCK('edaratek_phase1_probe')");
        $b->query("SELECT RELEASE_LOCK('edaratek_phase1_probe')");
    }
    $record['future_worker_races_proven'] = false;
    $record['status'] = 'PASS';
    $record['T005_GATE'] = 'PASS';
} catch (Throwable $error) {
    $record['status'] = 'FAIL';
    $record['classification'] = 'TEST_INFRASTRUCTURE';
    $record['exception_class'] = get_class($error);
}
while (ob_get_level() > 0) { ob_end_clean(); }
echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($record['T005_GATE'] === 'PASS' ? 0 : 28);
