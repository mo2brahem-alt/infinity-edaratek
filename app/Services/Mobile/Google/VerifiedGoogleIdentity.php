<?php

namespace App\Services\Mobile\Google;

final readonly class VerifiedGoogleIdentity
{
    public function __construct(
        public string $projectId,
        public string $firebaseUid,
        public string $googleUid,
    ) {}
}
