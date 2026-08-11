<?php

namespace App\Http\Controllers;

use App\Services\WhatsApp\SignedWebhookPayloadVerifier;
use App\Services\WhatsApp\WhatsAppDispatchClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppDispatchClaimController extends Controller
{
    public function __invoke(
        Request $request,
        SignedWebhookPayloadVerifier $verifier,
        WhatsAppDispatchClaimService $claims
    ): JsonResponse {
        $verified = $verifier->verify(
            $request,
            config('services.n8n.whatsapp.claim_secret'),
            'whatsapp-dispatch-claim'
        );

        if (! $verified['ok']) {
            return response()->json(['message' => $verified['message']], $verified['status']);
        }

        $payload = $verified['payload'];
        $messageUuid = data_get($payload, 'message_id');
        $idempotencyKey = data_get($payload, 'idempotency_key');

        if (! is_string($messageUuid) || ! is_string($idempotencyKey) || $messageUuid === '' || $idempotencyKey === '') {
            return response()->json(['message' => 'Missing claim reference.'], 422);
        }

        return response()->json($claims->claim($messageUuid, $idempotencyKey));
    }
}
