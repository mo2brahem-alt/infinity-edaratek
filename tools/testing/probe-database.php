<?php

// Destructive schema operations are restricted to the guarded disposable DB.
ini_set('display_errors', '0');
ob_start();
$record = ['kind' => 'infrastructure', 'stage' => 'migration_schema_probe',
    'MIGRATION_GATE' => 'FAIL', 'operation_stage' => 'guarded_boot'];
$migration = null;
try {
    $argv[1] = 'boot';
    require __DIR__.'/assert-environment.php';
    $files = glob('/app/database/migrations/*.php');
    $known = [];
    foreach ($files as $file) { $known[realpath($file)] = basename($file); }
    $record['migration_file_count'] = count($files);
    $db = Illuminate\Support\Facades\DB::connection();
    $schema = $db->getSchemaBuilder();
    if (config('database.default') !== 'mysql' || $db->getConfig('host') !== 'mysql'
        || !preg_match('/^edaratek_test_[a-f0-9]{12}$/D', getenv('DB_DATABASE') ?: '')
        || $db->selectOne('select database() as current_db')->current_db !== getenv('DB_DATABASE')) {
        throw new RuntimeException('Disposable database identity required');
    }
    $record['migrations_table_before'] = $schema->hasTable('migrations');
    $record['applied_migrations_before'] = $record['migrations_table_before'] ? $db->table('migrations')->count() : 0;
    $settings = $db->selectOne('select @@character_set_database as charset_name, @@collation_database as collation_name, @@sql_mode as sql_mode');
    foreach (['charset_name', 'collation_name', 'sql_mode'] as $field) {
        $value = (string) $settings->{$field};
        $record[$field] = preg_match('/^[A-Za-z0-9_,]*$/D', $value) ? $value : 'UNAVAILABLE';
    }
    $app['events']->listen(Illuminate\Database\Events\MigrationStarted::class,
        static function ($event) use (&$migration, $known): void {
            $file = realpath((new ReflectionClass($event->migration))->getFileName());
            $migration = isset($known[$file]) ? ['name' => $known[$file], 'sha256' => hash_file('sha256', $file)] : null;
        });
    $record['operation_stage'] = 'migrate_fresh';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $exit = $kernel->call('migrate:fresh', ['--force' => true, '--no-interaction' => true, '--no-ansi' => true],
        new Symfony\Component\Console\Output\NullOutput());
    $record['migrate_fresh_exit'] = $exit;
    if ($exit !== 0) { throw new RuntimeException('Migration command failed'); }
    $record['operation_stage'] = 'schema_readiness';
    $record['migrations_table_after'] = $schema->hasTable('migrations');
    $record['applied_migrations_after'] = $db->table('migrations')->count();
    foreach (['users', 'schools', 'roles', 'permissions'] as $table) {
        $record['expected_tables'][$table] = $schema->hasTable($table);
        if (!$record['expected_tables'][$table]) { throw new RuntimeException('Expected schema missing'); }
    }
    Illuminate\Support\Facades\Http::preventStrayRequests();
    Illuminate\Support\Facades\Mail::fake();
    Illuminate\Support\Facades\Notification::fake();
    $record['operation_stage'] = 'user_factory_insert';
    $db->beginTransaction();
    $user = App\Models\User::factory()->create();
    $record['user_factory_insert'] = $db->table('users')->where('id', $user->getKey())->exists();
    $db->rollBack();
    if (!$record['user_factory_insert']) { throw new RuntimeException('Factory verification failed'); }
    $record['operation_stage'] = 'package_discovery_after_migration';
    $record['package_discovery_exit'] = $kernel->call('package:discover', ['--no-ansi' => true],
        new Symfony\Component\Console\Output\NullOutput());
    if ($record['package_discovery_exit'] !== 0) { throw new RuntimeException('Package discovery failed'); }
    $record['MIGRATION_GATE'] = 'PASS';
    $record['status'] = 'PASS';
} catch (Throwable $error) {
    $record['status'] = 'FAIL';
    $record['classification'] = 'TEST_INFRASTRUCTURE';
    $record['exception_class'] = get_class($error);
    $record['migration'] = $record['operation_stage'] === 'migrate_fresh' ? $migration : null;
    for ($cause = $error; $cause !== null; $cause = $cause->getPrevious()) {
        if ($cause instanceof PDOException && is_array($cause->errorInfo)) {
            $state = (string) ($cause->errorInfo[0] ?? '');
            $driver = $cause->errorInfo[1] ?? null;
            $record['sqlstate'] = preg_match('/^[A-Z0-9]{5}$/D', $state) ? $state : null;
            $record['sqlstate_class'] = $record['sqlstate'] ? substr($state, 0, 2) : null;
            $record['driver_error_code'] = is_numeric($driver) ? (int) $driver : null;
            break; // NEVER read/export errorInfo[2], SQL, bindings or messages.
        }
    }
    try {
        if (isset($db, $schema)) {
            $record['migrations_table_after'] = $schema->hasTable('migrations');
            $record['applied_migrations_after'] = $record['migrations_table_after'] ? $db->table('migrations')->count() : 0;
            while ($db->transactionLevel() > 0) { $db->rollBack(); }
        }
    } catch (Throwable $ignored) { /* No raw secondary exception output. */ }
}
while (ob_get_level() > 0) { ob_end_clean(); }
echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($record['MIGRATION_GATE'] === 'PASS' ? 0 : 26);
