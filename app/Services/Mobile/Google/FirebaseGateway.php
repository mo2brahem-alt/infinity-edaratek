<?php

namespace App\Services\Mobile\Google;

interface FirebaseGateway
{
    /** Cryptographically verified claims; never a decode-only operation. */
    public function verifiedClaims(string $token): array;

    /** Only allowlisted metadata, never credentials or the whole user record. */
    public function userMetadata(string $uid): array;
}
