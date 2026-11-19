<?php

namespace App\Services\Mobile\Google;

final class GoogleAuthFailure extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly int $status = 401, public readonly ?int $retryAfter = null)
    {
        // Never retain the provider exception or its credential-bearing message.
        parent::__construct($errorCode);
    }
}
