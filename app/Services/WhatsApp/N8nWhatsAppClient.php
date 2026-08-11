<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class N8nWhatsAppClient
{
    public function __construct(private readonly WebhookSignature $signature)
    {
    }

    public function dispatch(WhatsAppMessage $message, ?array $runtimeParameters = null): array
    {
        $url = (string) config('services.n8n.whatsapp.webhook_url');
        $secret = (string) config('services.n8n.whatsapp.webhook_secret');

        if ($url === '' || $secret === '') {
            throw new RuntimeException('N8N WhatsApp webhook is not configured.');
        }

        $payload = [
            'message_id' => (string) $message->uuid,
            'event_key' => (string) $message->event_key,
            'message_type' => 'template',
            'recipient' => [
                'user_id' => $message->user_id,
                'phone' => (string) $message->recipient_phone,
            ],
            'template' => [
                'name' => (string) $message->template_name,
                'language' => (string) ($message->language ?: 'ar'),
                'parameters' => $runtimeParameters ?? (array) ($message->parameters ?? []),
            ],
            'metadata' => [
                'entity_type' => 'whatsapp_message',
                'entity_id' => (int) $message->id,
                'school_id' => $message->school_id,
            ],
            'idempotency_key' => (string) $message->idempotency_key,
        ];

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();

        $response = Http::timeout((int) config('services.n8n.whatsapp.timeout', 15))
            ->withHeaders([
                'X-Edaratek-Timestamp' => $timestamp,
                'X-Edaratek-Signature' => $this->signature->sign((string) $body, $timestamp, $secret),
                'Idempotency-Key' => (string) $message->idempotency_key,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->withBody((string) $body, 'application/json')
            ->post($url);

        if ($response->failed()) {
            throw new RuntimeException('n8n WhatsApp dispatch failed with HTTP ' . $response->status());
        }

        return $response->json() ?: [];
    }

    public function configured(): bool
    {
        return (bool) config('services.n8n.whatsapp.enabled', false)
            && filled(config('services.n8n.whatsapp.webhook_url'))
            && filled(config('services.n8n.whatsapp.webhook_secret'));
    }

    public function messageUuid(): string
    {
        return (string) Str::uuid();
    }
}
