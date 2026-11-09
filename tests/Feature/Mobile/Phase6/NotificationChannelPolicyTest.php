<?php

namespace Tests\Feature\Mobile\Phase6;

use App\Models\{Notification, User, WhatsAppMessage};
use App\Services\Support\NotificationService;
use App\Services\WhatsApp\WhatsAppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Queue};
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationChannelPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp(); Http::preventStrayRequests(); Http::fake(); Queue::fake();
    }

    public function test_application_notification_does_not_resolve_a_retired_channel_dependency(): void
    {
        $this->app->bind(WhatsAppNotificationService::class, function () { throw new \LogicException('Retired dependency must not resolve'); });
        $user = User::factory()->create(['role' => 'staff']);
        $service = app(NotificationService::class);
        $data = $service->withRoute(['entity_id' => 7], 'staff.dashboard', [], 'staff.dashboard');
        $item = $service->notifyUser($user->id, 'SYNTHETIC_APP_EVENT', 'تنبيه التطبيق', 'محتوى مصطنع', $data);
        $this->assertDatabaseHas('notifications', ['id' => $item->id, 'user_id' => $user->id, 'type' => 'SYNTHETIC_APP_EVENT']);
        $this->assertSame('staff.dashboard', $item->data['action_route_name']);
        $this->assertDatabaseCount('whatsapp_messages', 0); Http::assertNothingSent(); Queue::assertNothingPushed();
    }

    public function test_batch_notification_preserves_recipient_deduplication_and_does_not_fan_out(): void
    {
        $users = User::factory()->count(3)->create(['role' => 'staff']);
        app(NotificationService::class)->notifyUsers([$users[0]->id, $users[1]->id, $users[0]->id], 'APP', 'إشعار');
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseMissing('notifications', ['user_id' => $users[2]->id]);
        $this->assertDatabaseCount('whatsapp_messages', 0); Http::assertNothingSent(); Queue::assertNothingPushed();
    }

    public function test_web_list_and_read_actions_remain_owned_and_cannot_read_another_recipient(): void
    {
        $a = User::factory()->create(['role' => 'staff']); $b = User::factory()->create(['role' => 'staff']);
        $service = app(NotificationService::class);
        $own = $service->notifyUser($a->id, 'APP', 'Owned'); $foreign = $service->notifyUser($b->id, 'APP', 'Foreign');
        $this->actingAs($a)->getJson(route('notifications.index', ['limit' => 1]))->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $own->id);
        $this->postJson(route('notifications.read', $foreign))->assertForbidden(); $this->assertNull($foreign->fresh()->read_at);
        $this->postJson(route('notifications.read', $own))->assertOk(); $this->assertNotNull($own->fresh()->read_at);
        $this->postJson(route('notifications.read', $own))->assertOk();
        $this->assertNull($foreign->fresh()->read_at); Http::assertNothingSent();
    }

    public function test_read_all_changes_only_current_recipient_and_reports_its_actual_count(): void
    {
        $a = User::factory()->create(['role' => 'staff']); $b = User::factory()->create(['role' => 'staff']);
        foreach ([$a, $a, $b] as $user) { app(NotificationService::class)->notifyUser($user->id, 'APP', 'Synthetic'); }
        $this->actingAs($a)->postJson(route('notifications.read_all'))->assertOk()->assertJsonPath('marked_count', 2);
        $this->postJson(route('notifications.read_all'))->assertOk()->assertJsonPath('marked_count', 0);
        $this->assertSame(1, Notification::where('user_id', $b->id)->whereNull('read_at')->count());
    }

    public function test_notification_web_entry_points_still_require_authentication(): void
    {
        $this->getJson(route('notifications.index'))->assertUnauthorized();
        $this->postJson(route('notifications.read_all'))->assertUnauthorized();
    }

    public function test_stale_configuration_cannot_restore_a_removed_notification_channel(): void
    {
        config(['services.n8n.whatsapp.enabled' => true]); // No URLs, credentials or provider configuration.
        $user = User::factory()->create(['role' => 'staff']);
        app(NotificationService::class)->notifyUser($user->id, 'APP', 'مستمر داخل التطبيق');
        $this->assertFalse(app(\App\Services\WhatsApp\N8nWhatsAppClient::class)->configured());
        $this->assertDatabaseCount('notifications', 1); $this->assertDatabaseCount('whatsapp_messages', 0);
        Http::assertNothingSent(); Queue::assertNothingPushed();
    }

    public function test_retired_local_entry_points_are_gone_without_echo_or_state_mutation(): void
    {
        $user = User::factory()->create(['role' => 'staff', 'phone_verified_at' => null]); $this->actingAs($user);
        foreach ([['GET', '/webhooks/whatsapp/meta/verify'], ['POST', '/webhooks/whatsapp/status'], ['POST', '/webhooks/whatsapp/dispatch/claim'],
            ['POST', '/webhooks/whatsapp/dispatch/result'], ['POST', '/phone/verification/whatsapp/send'], ['POST', '/phone/verification/whatsapp/verify']] as [$method, $url]) {
            $this->json($method, $url, ['ignored_input' => 'not echoed'])->assertStatus(410)->assertExactJson([
                'code' => 'WHATSAPP_REMOVED', 'message' => 'هذه القناة لم تعد مدعومة.',
            ]);
        }
        $this->assertNull($user->fresh()->phone_verified_at);
        $this->assertDatabaseCount('whatsapp_messages', 0); $this->assertDatabaseCount('phone_verification_otps', 0);
        Http::assertNothingSent(); Queue::assertNothingPushed();
    }

    public function test_active_notifications_do_not_rewrite_historical_channel_records(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $historical = WhatsAppMessage::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id,
            'event_key' => 'historical', 'recipient_phone' => '000000000000', 'template_name' => 'historical',
            'status' => WhatsAppMessage::STATUS_UNCERTAIN, 'attempts' => 2, 'idempotency_key' => hash('sha256', 'synthetic-archive')]);
        $before = $historical->fresh()->getRawOriginal();
        app(NotificationService::class)->notifyUser($user->id, 'APP', 'New app notification');
        $this->assertSame($before, $historical->fresh()->getRawOriginal());
        $this->assertDatabaseCount('notifications', 1); Http::assertNothingSent(); Queue::assertNothingPushed();
    }
}
