<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

require_once '/app/tools/testing/dns-fixture.php';

// This file replaces tests/TestCase.php ONLY inside the disposable image.
// Original assertions, tests and business application source stay unchanged.
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        if (getenv('EDARATEK_ISOLATED_TEST') !== '1') {
            throw new \RuntimeException('Isolated test profile required.');
        }
        parent::setUp();
        // Reassert critical effective settings for every new Laravel application.
        if (config('app.env') !== 'testing' || config('database.default') !== 'mysql'
            || config('database.connections.mysql.host') !== 'mysql'
            || !preg_match('/^edaratek_test_[a-f0-9]{12}$/D', (string) config('database.connections.mysql.database'))
            || config('cache.default') !== 'array' || config('session.driver') !== 'array'
            || config('queue.default') !== 'sync' || config('mail.default') !== 'array') {
            throw new \RuntimeException('Isolated application configuration required.');
        }
        Http::preventStrayRequests();
        Mail::fake();
        Notification::fake();
        // sync queue permits existing fake-controlled job tests. No workers.
        // Internal Docker network is the backstop even for raw curl/socket use.
    }
}
