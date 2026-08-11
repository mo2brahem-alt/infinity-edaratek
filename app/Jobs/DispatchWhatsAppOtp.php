<?php

namespace App\Jobs;

use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\N8nWhatsAppClient;
use App\Services\WhatsApp\WhatsAppDispatchClaimService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DispatchWhatsAppOtp implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        private readonly int $messageId,
        private readonly string $code,
    ) {
    }

    public function handle(N8nWhatsAppClient $client, WhatsAppDispatchClaimService $claims): void
    {
        $message = WhatsAppMessage::query()->find($this->messageId);
        if (! $message || in_array($message->status, [
            WhatsAppMessage::STATUS_ACCEPTED,
            WhatsAppMessage::STATUS_SENT,
            WhatsAppMessage::STATUS_DELIVERED,
            WhatsAppMessage::STATUS_READ,
            WhatsAppMessage::STATUS_FAILED,
            WhatsAppMessage::STATUS_UNCERTAIN,
            WhatsAppMessage::STATUS_CANCELLED,
        ], true)) {
            return;
        }

        $message->forceFill([
            'attempts' => (int) $message->attempts + 1,
        ])->save();

        try {
            $response = $client->dispatch($message, [
                'code' => $this->code,
                'expires_in_minutes' => 5,
            ]);

            if (data_get($response, 'decision') === 'do_not_send') {
                $reason = (string) data_get($response, 'reason');

                if ($reason === 'already_sent') {
                    $providerId = data_get($response, 'provider_message_id');
                    if ($providerId) {
                        $message->forceFill([
                            'provider_message_id' => (string) $providerId,
                            'status' => WhatsAppMessage::STATUS_ACCEPTED,
                            'accepted_at' => $message->accepted_at ?: now(),
                        ])->save();
                    }

                    return;
                }

                if (in_array($reason, ['already_claimed', 'uncertain'], true)) {
                    $claims->markUncertain($message->refresh(), 'n8n_dispatch_already_claimed', 'Dispatch claim already exists.');
                }

                return;
            }

            $providerId = data_get($response, 'provider_message_id') ?: data_get($response, 'wamid') ?: data_get($response, 'messages.0.id');

            if ($providerId) {
                $message->forceFill([
                    'provider_message_id' => (string) $providerId,
                    'status' => WhatsAppMessage::STATUS_ACCEPTED,
                    'accepted_at' => now(),
                    'failure_code' => null,
                    'failure_reason' => null,
                ])->save();
            }
        } catch (Throwable $exception) {
            $message = $message->refresh();
            if ($message->dispatch_claimed_at || $message->dispatch_claim_token) {
                $claims->markUncertain($message, 'n8n_dispatch_ambiguous', $exception->getMessage());

                return;
            }

            $message->forceFill([
                'failure_code' => 'n8n_dispatch_failed',
                'failure_reason' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $message = WhatsAppMessage::query()->find($this->messageId);
        if (! $message || $message->dispatch_claimed_at || $message->provider_message_id) {
            return;
        }

        $message->forceFill([
            'status' => WhatsAppMessage::STATUS_FAILED,
            'failed_at' => now(),
            'failure_code' => 'n8n_dispatch_failed',
            'failure_reason' => $exception->getMessage(),
        ])->save();
    }
}
