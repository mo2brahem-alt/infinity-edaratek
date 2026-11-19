<?php

namespace Tests\Feature\Mobile;

use App\Models\ExternalIdentity;
use App\Models\User;
use App\Services\Mobile\Google\GoogleIdentityService;
use App\Services\Mobile\Google\VerifiedGoogleIdentity;
use App\Services\Mobile\NativeTokenService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class GoogleIdentityConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        $connection = config('database.connections.mysql');
        if (! app()->environment('testing') || ! in_array($connection['host'], ['127.0.0.1', 'mysql'], true)
            || ! preg_match('/^edaratek_(?:google|test)_[a-f0-9]{12}$/D', $connection['database'])) {
            throw new \RuntimeException('Disposable database profile required');
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->app !== null) {
                $this->beforeTruncatingDatabase();
                // Other suites use transaction rollback. Do not leave committed
                // multi-process fixtures for those tests to inherit.
                $this->truncateTablesForAllConnections();
            }
        } finally {
            parent::tearDown();
        }
    }

    private function pair(string $first, int $firstUser, string $second, int $secondUser, ?string $cacheRoot = null): array
    {
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $directory = realpath($directory);
        $barrier = $directory.DIRECTORY_SEPARATOR.'google-race-'.Str::uuid();
        $config = config('database.connections.mysql');
        $environment = ['APP_ENV' => 'testing', 'APP_URL' => 'http://127.0.0.1', 'APP_KEY' => config('app.key'),
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'],
            'DB_DATABASE' => $config['database'], 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'],
            'APP_CONFIG_CACHE' => $directory.DIRECTORY_SEPARATOR.'no-config-cache.php', 'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'GOOGLE_TEST_CACHE_DIR' => $cacheRoot ?? ''];
        $processes = [];
        try {
            foreach ([[$first, $firstUser], [$second, $secondUser]] as $index => [$action, $userId]) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/google_identity_worker.php'), $action, (string) $userId, $barrier, $barrier.'.'.$index], base_path(), $environment);
                $process->setTimeout(20);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            while ((! is_file($barrier.'.0') || ! is_file($barrier.'.1')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($barrier.'.0');
            $this->assertFileExists($barrier.'.1');
            touch($barrier);
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), 'Concurrent worker failed safely');
                $results[] = trim($process->getOutput());
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach ([$barrier, $barrier.'.0', $barrier.'.1'] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function test_real_transactions_serialize_ownership_and_unlink_against_issuance(): void
    {
        $this->assertSame('mysql', config('database.default'), 'Concurrency requires the disposable MySQL lane');
        $users = [];
        for ($i = 0; $i < 2; $i++) {
            $users[] = User::factory()->create(['role' => 'staff', 'is_active' => true, 'approval_status' => 'approved', 'password' => Hash::make('Synthetic concurrency-only password')]);
        }
        $results = $this->pair('link', $users[0]->id, 'link', $users[1]->id);
        sort($results);
        $this->assertSame(['IDENTITY_LINK_CONFLICT', 'OK'], $results);
        $this->assertDatabaseCount('external_identities', 1);
        $owner = User::findOrFail(ExternalIdentity::firstOrFail()->user_id);
        $this->assertSame(['OK', 'OK'], $this->pair('link', $owner->id, 'link', $owner->id));
        $this->assertDatabaseCount('external_identities', 1);
        $password = app(NativeTokenService::class)->issue($owner, (string) Str::uuid(), 'Synthetic password client')->plainTextToken;
        $this->pair('login', $owner->id, 'unlink', $owner->id);
        $this->assertDatabaseCount('external_identities', 0);
        $this->assertSame(0, PersonalAccessToken::whereNotNull('external_identity_id')->count());
        $this->assertNotNull(PersonalAccessToken::findToken($password));
    }

    public function test_migration_rollback_revokes_google_sessions_and_preserves_password_sessions(): void
    {
        $user = User::factory()->create(['role' => 'staff', 'is_active' => true, 'approval_status' => 'approved', 'password' => Hash::make('Synthetic concurrency-only password')]);
        $password = app(NativeTokenService::class)->issue($user, (string) Str::uuid(), 'Synthetic password client')->plainTextToken;
        $identity = new VerifiedGoogleIdentity(config('firebase_google.project_id'), 'rollback-firebase', 'rollback-google');
        app(GoogleIdentityService::class)->link($user->id, 'Synthetic concurrency-only password', $identity);
        app(GoogleIdentityService::class)->login($identity, (string) Str::uuid(), 'Synthetic Google client');
        $this->assertDatabaseCount('personal_access_tokens', 2);
        $migration = require database_path('migrations/2026_11_19_140000_create_external_identities_table.php');
        $migration->down();
        try {
            $this->assertDatabaseCount('personal_access_tokens', 1);
            $this->assertNotNull(PersonalAccessToken::findToken($password));
            $this->assertDatabaseCount('users', 1);
            $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('external_identities'));
        } finally {
            $migration->up();
        }
    }

    public function test_independent_google_exchanges_cannot_overbook_the_shared_budget(): void
    {
        $root = storage_path('framework/testing/google-budget-'.Str::uuid());
        \Illuminate\Support\Facades\File::makeDirectory($root, 0700, true);
        try {
            config(['cache.limiter' => 'google_race_budget', 'cache.stores.google_race_budget' => ['driver' => 'file', 'path' => $root, 'lock_path' => $root]]);
            $limiter = app(\App\Services\Mobile\NativeRateLimiter::class);
            for ($i = 0; $i < 4; $i++) {
                $this->assertNull($limiter->consumeGoogleIdentity(config('firebase_google.project_id'), 'concurrency-firebase', 123));
            }
            $results = $this->pair('budget', 123, 'budget', 123, $root);
            sort($results);
            $this->assertSame(['OK', 'RATE_LIMITED'], $results);
            $this->assertNotNull($limiter->consumeGoogleIdentity(config('firebase_google.project_id'), 'concurrency-firebase', 123));
        } finally {
            config(['cache.limiter' => null]);
            if (realpath(dirname($root)) === realpath(storage_path('framework/testing')) && preg_match('/^google-budget-[a-f0-9-]{36}$/D', basename($root))) {
                \Illuminate\Support\Facades\File::deleteDirectory($root);
            }
        }
    }
}
