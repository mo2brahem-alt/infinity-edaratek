<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppMessage;
use Illuminate\Support\Str;
use RuntimeException;

class N8nWhatsAppClient
{
    public function __construct(private readonly WebhookSignature $signature)
    {
    }

    public function dispatch(WhatsAppMessage $message, ?array $runtimeParameters = null): array
    {
        // Permanent product-scope retirement, independent of old flags, keys or callers.
        throw new RuntimeException('WHATSAPP_REMOVED');
    }

    public function configured(): bool
    {
        return false;
    }

    public function messageUuid(): string
    {
        return (string) Str::uuid();
    }
}
