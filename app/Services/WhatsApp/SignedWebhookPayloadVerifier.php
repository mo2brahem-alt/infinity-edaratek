<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SignedWebhookPayloadVerifier
{
    public function __construct(private readonly WebhookSignature $signature)
    {
    }

    /**
     * @return array{ok: bool, status: int, message: string, payload?: array<string, mixed>, body?: string}
     */
    public function verify(Request $request, ?string $secret, string $replayPrefix): array
    {
        $body = $request->getContent();
        $timestamp = $request->header('X-Edaratek-Timestamp');
        $providedSignature = $request->header('X-Edaratek-Signature');

        if (! $this->signature->verify($body, $timestamp, $providedSignature, $secret)) {
            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Invalid signature.',
            ];
        }

        $replayKey = $replayPrefix . ':' . hash('sha256', (string) $timestamp . '.' . (string) $providedSignature);
        if (! Cache::add($replayKey, true, now()->addMinutes(5))) {
            return [
                'ok' => false,
                'status' => 409,
                'message' => 'Replay rejected.',
            ];
        }

        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Malformed payload.',
            ];
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'OK',
            'payload' => $payload,
            'body' => $body,
        ];
    }
}
