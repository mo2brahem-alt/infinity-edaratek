<?php

namespace App\Services\Mobile\Google;

interface FirebaseIdentityVerifier
{
    public function verify(string $token): VerifiedGoogleIdentity;
}
