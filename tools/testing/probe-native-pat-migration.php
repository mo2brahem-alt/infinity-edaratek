<?php

// The only rollback rehearsal runs after the existing disposable-DB guard.
// It never receives host credentials, external routes, or application logs.
ini_set('display_errors', '0');
ob_start();

$record = [
    'kind' => 'infrastructure',
    'stage' => 'native_pat_migration_rehearsal',
    'status' => 'FAIL',
    'NATIVE_PAT_MIGRATION_GATE' => 'FAIL',
];

try {
    $argv[1] = 'boot';
    require __DIR__.'/assert-environment.php';

    $db = Illuminate\Support\Facades\DB::connection();
    $schema = $db->getSchemaBuilder();
    $databaseName = getenv('DB_DATABASE') ?: '';
    if (config('database.default') !== 'mysql' || $db->getConfig('host') !== 'mysql'
        || ! preg_match('/^edaratek_test_[a-f0-9]{12}$/D', $databaseName)) {
        throw new RuntimeException('Disposable database identity required');
    }

    $migration = '2026_11_08_120000_create_personal_access_tokens_table';
    $columns = ['id', 'tokenable_type', 'tokenable_id', 'name', 'token', 'abilities', 'last_used_at', 'expires_at', 'created_at', 'updated_at'];
    $record['schema_before'] = $schema->hasTable('personal_access_tokens');
    $record['columns_before'] = $record['schema_before'] ? $schema->hasColumns('personal_access_tokens', $columns) : false;
    $record['migration_recorded_before'] = $db->table('migrations')->where('migration', $migration)->exists();
    if (! $record['schema_before'] || ! $record['columns_before'] || ! $record['migration_recorded_before']) {
        throw new RuntimeException('Expected PAT migration is not applied');
    }

    App\Models\User::factory()->create();
    $record['unrelated_users_before'] = $db->table('users')->count();
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $record['rollback_exit'] = $kernel->call('migrate:rollback', [
        '--step' => 1,
        '--force' => true,
        '--no-interaction' => true,
        '--no-ansi' => true,
    ], new Symfony\Component\Console\Output\NullOutput());
    $record['schema_after_rollback'] = $schema->hasTable('personal_access_tokens');
    $record['unrelated_users_after_rollback'] = $db->table('users')->count();
    if ($record['rollback_exit'] !== 0 || $record['schema_after_rollback']
        || $record['unrelated_users_before'] !== $record['unrelated_users_after_rollback']) {
        throw new RuntimeException('PAT rollback rehearsal failed');
    }

    $record['migrate_exit'] = $kernel->call('migrate', [
        '--force' => true,
        '--no-interaction' => true,
        '--no-ansi' => true,
    ], new Symfony\Component\Console\Output\NullOutput());
    $record['repeat_migrate_exit'] = $kernel->call('migrate', [
        '--force' => true,
        '--no-interaction' => true,
        '--no-ansi' => true,
    ], new Symfony\Component\Console\Output\NullOutput());
    $record['schema_after_repeat'] = $schema->hasTable('personal_access_tokens');
    $record['columns_after_repeat'] = $record['schema_after_repeat'] ? $schema->hasColumns('personal_access_tokens', $columns) : false;
    $record['migration_recorded_after'] = $db->table('migrations')->where('migration', $migration)->exists();
    if ($record['migrate_exit'] !== 0 || $record['repeat_migrate_exit'] !== 0
        || ! $record['schema_after_repeat'] || ! $record['columns_after_repeat'] || ! $record['migration_recorded_after']) {
        throw new RuntimeException('PAT forward/repeat rehearsal failed');
    }

    $record['status'] = 'PASS';
    $record['NATIVE_PAT_MIGRATION_GATE'] = 'PASS';
} catch (\Throwable $error) {
    $record['classification'] = 'TEST_INFRASTRUCTURE';
    $record['exception_class'] = get_class($error);
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($record['NATIVE_PAT_MIGRATION_GATE'] === 'PASS' ? 0 : 29);
