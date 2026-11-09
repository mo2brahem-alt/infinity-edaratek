<?php

namespace App\Jobs;

use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\N8nWhatsAppClient;
use App\Services\WhatsApp\WhatsAppDispatchClaimService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DispatchWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(private readonly int $messageId)
    {
    }

    public function handle(N8nWhatsAppClient $client, WhatsAppDispatchClaimService $claims): void
    {
        // Compatibility for historical serialized jobs only. No I/O, attempts or state changes.
        return;
    }

    public function failed(Throwable $exception): void
    {
        // Preserve historical records; retirement is not delivery failure.
        return;
    }
}
