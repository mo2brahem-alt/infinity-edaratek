<?php

namespace App\Services\School;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class SchoolContextException extends HttpException
{
    public function __construct(public readonly string $errorCode, int $status, string $message)
    {
        parent::__construct($status, $message);
    }
}
