<?php

ini_set('display_errors', '0'); ob_start();
$record = ['kind' => 'infrastructure', 'stage' => 'private_manifest_migration_rehearsal', 'status' => 'FAIL'];
try {
    $argv[1] = 'boot'; require __DIR__.'/assert-environment.php';
    $db = Illuminate\Support\Facades\DB::connection(); $schema = $db->getSchemaBuilder();
    if ($db->getConfig('host') !== 'mysql' || ! preg_match('/^edaratek_test_[a-f0-9]{12}$/D', getenv('DB_DATABASE') ?: '')) { throw new RuntimeException('Disposable scope required'); }
    $name = '2026_11_09_000000_create_private_file_migrations_table';
    $columns = ['schema_version', 'inventory_key', 'attachment_type', 'attachment_id', 'source_disk', 'source_path', 'destination_disk', 'destination_path', 'source_size', 'source_hash', 'observed_source_metadata', 'state', 'safe_failure_code'];
    if (! $schema->hasColumns('private_file_migrations', $columns)) { throw new RuntimeException('Missing schema'); }
    $m = App\Models\PrivateFileMigration::create(['inventory_key' => hash('sha256', 'synthetic-schema-rehearsal'), 'schema_version' => 1,
        'source_disk' => 'public', 'source_path' => 'synthetic/source', 'destination_disk' => 'school_attachments',
        'destination_path' => 'synthetic/private', 'state' => 'verified']);
    $before = $db->table('users')->count(); $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $record['rollback_exit'] = $kernel->call('migrate:rollback', ['--path' => ['database/migrations/'.$name.'.php'],
        '--batch' => (int) $db->table('migrations')->where('migration', $name)->value('batch'), '--force' => true, '--no-interaction' => true], new Symfony\Component\Console\Output\NullOutput());
    $record['manifest_retained_on_rollback'] = $schema->hasTable('private_file_migrations') && App\Models\PrivateFileMigration::whereKey($m->id)->where('state', 'verified')->exists();
    $record['reapply_exit'] = $kernel->call('migrate', ['--force' => true, '--no-interaction' => true], new Symfony\Component\Console\Output\NullOutput());
    $record['repeat_exit'] = $kernel->call('migrate', ['--force' => true, '--no-interaction' => true], new Symfony\Component\Console\Output\NullOutput());
    $record['existing_record_preserved'] = App\Models\PrivateFileMigration::whereKey($m->id)->where('state', 'verified')->exists();
    $record['unrelated_users_preserved'] = $db->table('users')->count() === $before;
    if ($record['rollback_exit'] || $record['reapply_exit'] || $record['repeat_exit'] || ! $record['manifest_retained_on_rollback'] || ! $record['existing_record_preserved'] || ! $record['unrelated_users_preserved']) { throw new RuntimeException('Rehearsal failure'); }
    $m->delete(); $record['status'] = 'PASS';
} catch (Throwable) { $record['classification'] = 'PRIVATE_MANIFEST_SCHEMA_REQUIRES_REVIEW'; }
while (ob_get_level()) { ob_end_clean(); }
echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($record['status'] === 'PASS' ? 0 : 29);
