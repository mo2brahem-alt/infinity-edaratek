<?php

namespace Egulias\EmailValidator\Validation;

// Hermetic DNS transport fixture for the exact domain used by existing
// onboarding tests. RFC syntax and reserved-domain validation stay unchanged.
// This shadows ONLY DNSGetRecordWrapper's unqualified namespaced lookup;
// global DNS, service discovery and application production files are untouched.
if (getenv('EDARATEK_ISOLATED_TEST') !== '1' || getenv('APP_ENV') !== 'testing') {
    throw new \RuntimeException('Isolated DNS fixture profile required');
}

function dns_get_record(string $hostname, int $type = DNS_ANY): array
{
    if (getenv('EDARATEK_ISOLATED_TEST') !== '1' || getenv('APP_ENV') !== 'testing') {
        throw new \RuntimeException('Isolated DNS fixture profile required');
    }
    if (strtolower(rtrim($hostname, '.')) === 'gmail.com' && ($type & DNS_MX) !== 0) {
        return [['host' => 'gmail.com', 'class' => 'IN', 'ttl' => 60,
            'type' => 'MX', 'pri' => 10, 'target' => 'mail.fixture.invalid']];
    }
    return [];
}
