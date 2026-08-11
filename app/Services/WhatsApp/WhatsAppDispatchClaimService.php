<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhatsAppDispatchClaimService
{
    /**
     * @return array<string, mixed>
     */
    public function claim(string $messageUuid, string $idempotencyKey): array
    {
        $token = (string) Str::uuid();
        $now = now();

        $updated = DB::table('whatsapp_messages')
            ->where('uuid', $messageUuid)
            ->where('idempotency_key', $idempotencyKey)
            ->where('status', WhatsAppMessage::STATUS_PENDING)
            ->whereNull('dispatch_claimed_at')
            ->whereNull('dispatch_claim_token')
            ->whereNull('provider_message_id')
            ->update([
                'status' => WhatsAppMessage::STATUS_PROCESSING,
                'dispatch_claimed_at' => $now,
                'dispatch_claim_token' => $token,
                'updated_at' => $now,
            ]);

        if ($updated === 1) {
            return [
                'decision' => 'send',
                'message_id' => $messageUuid,
                'idempotency_key' => $idempotencyKey,
                'dispatch_claim_token' => $token,
            ];
        }

        $message = WhatsAppMessage::query()
            ->where('uuid', $messageUuid)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $message) {
            return [
                'decision' => 'do_not_send',
                'reason' => 'not_found',
            ];
        }

        if ($message->provider_message_id || in_array($message->status, [
            WhatsAppMessage::STATUS_ACCEPTED,
            WhatsAppMessage::STATUS_SENT,
            WhatsAppMessage::STATUS_DELIVERED,
            WhatsAppMessage::STATUS_READ,
        ], true)) {
            return [
                'decision' => 'do_not_send',
                'reason' => 'already_sent',
                'provider_message_id' => $message->provider_message_id,
            ];
        }

        if ($message->status === WhatsAppMessage::STATUS_FAILED) {
            return [
                'decision' => 'do_not_send',
                'reason' => 'terminal_failure',
            ];
        }

        if ($message->status === WhatsAppMessage::STATUS_UNCERTAIN) {
            return [
                'decision' => 'do_not_send',
                'reason' => 'uncertain',
            ];
        }

        if ($message->dispatch_claimed_at || $message->dispatch_claim_token) {
            return [
                'decision' => 'do_not_send',
                'reason' => 'already_claimed',
            ];
        }

        return [
            'decision' => 'do_not_send',
            'reason' => 'not_claimable',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordAccepted(string $messageUuid, string $idempotencyKey, string $claimToken, string $providerMessageId): array
    {
        $message = $this->messageForToken($messageUuid, $idempotencyKey, $claimToken);
        if (! $message) {
            return ['ok' => false, 'reason' => 'invalid_claim_token'];
        }

        if (in_array($message->status, [WhatsAppMessage::STATUS_FAILED, WhatsAppMessage::STATUS_CANCELLED], true)) {
            return ['ok' => false, 'reason' => 'terminal_status'];
        }

        if ($message->provider_message_id && $message->provider_message_id !== $providerMessageId) {
            return ['ok' => false, 'reason' => 'provider_message_mismatch'];
        }

        $message->forceFill([
            'provider_message_id' => $providerMessageId,
            'status' => WhatsAppMessage::STATUS_ACCEPTED,
            'accepted_at' => $message->accepted_at ?: now(),
            'failure_code' => null,
            'failure_reason' => null,
        ])->save();

        return [
            'ok' => true,
            'updated' => true,
            'provider_message_id' => $providerMessageId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordDefinitiveFailure(
        string $messageUuid,
        string $idempotencyKey,
        string $claimToken,
        ?string $failureCode,
        ?string $failureReason
    ): array {
        $message = $this->messageForToken($messageUuid, $idempotencyKey, $claimToken);
        if (! $message) {
            return ['ok' => false, 'reason' => 'invalid_claim_token'];
        }

        if ($message->provider_message_id || in_array($message->status, [
            WhatsAppMessage::STATUS_ACCEPTED,
            WhatsAppMessage::STATUS_SENT,
            WhatsAppMessage::STATUS_DELIVERED,
            WhatsAppMessage::STATUS_READ,
        ], true)) {
            return ['ok' => false, 'reason' => 'already_sent'];
        }

        $message->forceFill([
            'status' => WhatsAppMessage::STATUS_FAILED,
            'failed_at' => now(),
            'failure_code' => $failureCode ?: 'meta_rejected',
            'failure_reason' => $failureReason ? mb_substr($failureReason, 0, 1000) : null,
        ])->save();

        return ['ok' => true, 'updated' => true];
    }

    public function markUncertain(WhatsAppMessage $message, string $failureCode, ?string $failureReason = null): void
    {
        if ($message->provider_message_id || in_array($message->status, [
            WhatsAppMessage::STATUS_ACCEPTED,
            WhatsAppMessage::STATUS_SENT,
            WhatsAppMessage::STATUS_DELIVERED,
            WhatsAppMessage::STATUS_READ,
            WhatsAppMessage::STATUS_CANCELLED,
        ], true)) {
            return;
        }

        $message->forceFill([
            'status' => WhatsAppMessage::STATUS_UNCERTAIN,
            'dispatch_uncertain_at' => now(),
            'failure_code' => $failureCode,
            'failure_reason' => $failureReason ? mb_substr($failureReason, 0, 1000) : null,
        ])->save();
    }

    private function messageForToken(string $messageUuid, string $idempotencyKey, string $claimToken): ?WhatsAppMessage
    {
        if ($claimToken === '') {
            return null;
        }

        return WhatsAppMessage::query()
            ->where('uuid', $messageUuid)
            ->where('idempotency_key', $idempotencyKey)
            ->where('dispatch_claim_token', $claimToken)
            ->first();
    }
}
