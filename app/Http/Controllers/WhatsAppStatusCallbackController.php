<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\SignedWebhookPayloadVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppStatusCallbackController extends Controller
{
    public function __invoke(Request $request, SignedWebhookPayloadVerifier $verifier): JsonResponse
    {
        $verified = $verifier->verify(
            $request,
            config('services.n8n.whatsapp.status_secret'),
            'whatsapp-status-callback'
        );

        if (! $verified['ok']) {
            return response()->json(['message' => $verified['message']], $verified['status']);
        }

        $payload = $verified['payload'];
        $status = data_get($payload, 'status') ?: data_get($payload, 'statuses.0.status');
        $providerId = data_get($payload, 'provider_message_id')
            ?: data_get($payload, 'wamid')
            ?: data_get($payload, 'statuses.0.id');
        $messageUuid = data_get($payload, 'message_id');

        if (! $status || (! $providerId && ! $messageUuid)) {
            return response()->json(['message' => 'Missing status reference.'], 422);
        }

        $message = WhatsAppMessage::query()
            ->when($providerId, fn ($query) => $query->where('provider_message_id', (string) $providerId))
            ->when(! $providerId && $messageUuid, fn ($query) => $query->where('uuid', (string) $messageUuid))
            ->first();

        if (! $message) {
            return response()->json(['message' => 'Message not found.'], 404);
        }

        if ($providerId && ! $message->provider_message_id) {
            $message->forceFill(['provider_message_id' => (string) $providerId])->save();
        }

        $updated = $message->applyProviderStatus(
            (string) $status,
            data_get($payload, 'failure_code') ?: data_get($payload, 'errors.0.code'),
            data_get($payload, 'failure_reason') ?: data_get($payload, 'errors.0.message')
        );

        return response()->json([
            'ok' => true,
            'updated' => $updated,
        ]);
    }
}
