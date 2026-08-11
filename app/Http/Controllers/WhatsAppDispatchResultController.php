<?php

namespace App\Http\Controllers;

use App\Services\WhatsApp\SignedWebhookPayloadVerifier;
use App\Services\WhatsApp\WhatsAppDispatchClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppDispatchResultController extends Controller
{
    public function __invoke(
        Request $request,
        SignedWebhookPayloadVerifier $verifier,
        WhatsAppDispatchClaimService $claims
    ): JsonResponse {
        $verified = $verifier->verify(
            $request,
            config('services.n8n.whatsapp.claim_secret'),
            'whatsapp-dispatch-result'
        );

        if (! $verified['ok']) {
            return response()->json(['message' => $verified['message']], $verified['status']);
        }

        $payload = $verified['payload'];
        $messageUuid = data_get($payload, 'message_id');
        $idempotencyKey = data_get($payload, 'idempotency_key');
        $claimToken = data_get($payload, 'dispatch_claim_token');
        $status = data_get($payload, 'status');

        if (! is_string($messageUuid) || ! is_string($idempotencyKey) || ! is_string($claimToken)) {
            return response()->json(['message' => 'Missing dispatch result reference.'], 422);
        }

        if ($status === 'accepted') {
            $providerId = data_get($payload, 'provider_message_id') ?: data_get($payload, 'wamid');
            if (! is_string($providerId) || $providerId === '') {
                return response()->json(['message' => 'Missing provider message id.'], 422);
            }

            $result = $claims->recordAccepted($messageUuid, $idempotencyKey, $claimToken, $providerId);
        } elseif ($status === 'failed') {
            $result = $claims->recordDefinitiveFailure(
                $messageUuid,
                $idempotencyKey,
                $claimToken,
                data_get($payload, 'failure_code'),
                data_get($payload, 'failure_reason')
            );
        } else {
            return response()->json(['message' => 'Unsupported dispatch result status.'], 422);
        }

        if (! ($result['ok'] ?? false)) {
            return response()->json(['message' => $result['reason'] ?? 'Invalid dispatch result.'], 409);
        }

        return response()->json($result);
    }
}
