<?php

namespace App\Services\WhatsApp;

use App\Models\User;

class WhatsAppRecipientResolver
{
    public function __construct(private readonly PhoneNumberNormalizer $normalizer)
    {
    }

    public function phoneForUser(User $user): ?string
    {
        return $this->normalizer->normalize($user->mobile ?: $user->phone);
    }
}
