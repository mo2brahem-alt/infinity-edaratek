<?php

namespace App\Services\WhatsApp;

use App\Jobs\DispatchWhatsAppNotification;
use App\Models\Notification;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\DB;
use Throwable;

class WhatsAppNotificationService
{
    public function __construct(
        private readonly N8nWhatsAppClient $client,
        private readonly WhatsAppRecipientResolver $recipientResolver,
    ) {
    }

    public function queueForNotification(Notification $notification): ?WhatsAppMessage
    {
        return null; // Retired channel: preserve caller compatibility without fan-out or state writes.
        if (! $this->client->configured()) {
            return null;
        }

        $user = User::query()->find($notification->user_id);
        if (! $user) {
            return null;
        }

        $phone = $this->recipientResolver->phoneForUser($user);
        if (! $phone) {
            return null;
        }

        $template = (string) config('services.whatsapp.templates.notification', 'edaratek_notifications_enabled');
        $idempotencyKey = $this->idempotencyKey(
            (string) $notification->type,
            'notification',
            (int) $notification->id,
            $phone
        );

        $message = WhatsAppMessage::query()->createOrFirst(
            ['idempotency_key' => $idempotencyKey],
            [
                'uuid' => $this->client->messageUuid(),
                'user_id' => (int) $user->id,
                'school_id' => $user->school_id,
                'event_key' => (string) $notification->type,
                'recipient_phone' => $phone,
                'template_name' => $template,
                'language' => 'ar',
                'parameters' => [
                    'user_name' => (string) $user->name,
                    'title' => (string) $notification->title,
                    'body' => (string) ($notification->body ?? ''),
                ],
                'status' => WhatsAppMessage::STATUS_PENDING,
            ]
        );

        if ($message->wasRecentlyCreated) {
            DB::afterCommit(function () use ($message): void {
                try {
                    DispatchWhatsAppNotification::dispatch($message->id)->afterCommit();
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
        }

        return $message;
    }

    public function idempotencyKey(string $eventKey, string $entityType, int|string $entityId, string $recipient): string
    {
        return hash('sha256', implode('|', [$eventKey, $entityType, (string) $entityId, $recipient]));
    }
}
