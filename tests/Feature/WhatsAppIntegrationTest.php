<?php

namespace Tests\Feature;

use App\Jobs\DispatchWhatsAppNotification;
use App\Models\EducationalDirectorate;
use App\Models\PhoneVerificationOtp;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolSupervisionRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\Auth\UserApprovalService;
use App\Services\Subscription\SubscriptionService;
use App\Services\Support\NotificationService;
use App\Services\Supervision\SchoolSupervisionRequestService;
use App\Services\WhatsApp\N8nWhatsAppClient;
use App\Services\WhatsApp\PhoneNumberNormalizer;
use App\Services\WhatsApp\WhatsAppDispatchClaimService;
use App\Services\WhatsApp\WhatsAppOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.n8n.whatsapp.enabled' => true,
            'services.n8n.whatsapp.webhook_url' => 'https://n8n.infinity-4tech.com:5050/webhook/edaratek-whatsapp-dispatch',
            'services.n8n.whatsapp.webhook_secret' => 'test-dispatch-secret',
            'services.n8n.whatsapp.status_secret' => 'test-status-secret',
            'services.n8n.whatsapp.claim_secret' => 'test-status-secret',
            'services.whatsapp.meta_verify_token' => 'test-meta-verify-token',
        ]);

        RateLimiter::clear('whatsapp-otp|send-hour|1|966500000001|127.0.0.1');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_internal_notification_creates_idempotent_whatsapp_message_record(): void
    {
        Queue::fake();

        $directorate = EducationalDirectorate::create([
            'name' => 'WhatsApp Region',
            'governorate' => 'Riyadh',
        ]);

        $school = School::create([
            'directorate_id' => $directorate->id,
            'name' => 'WhatsApp School',
            'school_id' => 'WA-001',
            'phone' => '0500000002',
        ]);

        $user = User::factory()->create([
            'name' => 'Manager',
            'mobile' => '0500000001',
            'school_id' => $school->id,
        ]);

        $notification = app(NotificationService::class)->notifyUser(
            (int) $user->id,
            'user.approval.approved',
            'تمت الموافقة على الحساب',
            'يمكنك الآن استخدام المنصة.'
        );

        app(NotificationService::class)->notifyUser(
            (int) $user->id,
            'user.approval.approved',
            'تمت الموافقة على الحساب',
            'يمكنك الآن استخدام المنصة.'
        );

        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $user->id,
            'school_id' => $school->id,
            'event_key' => 'user.approval.approved',
            'recipient_phone' => '966500000001',
            'template_name' => 'edaratek_notifications_enabled',
            'status' => WhatsAppMessage::STATUS_PENDING,
        ]);

        $this->assertSame(1, WhatsAppMessage::query()
            ->where('event_key', 'user.approval.approved')
            ->where('recipient_phone', '966500000001')
            ->where('idempotency_key', hash('sha256', implode('|', [
                'user.approval.approved',
                'notification',
                (string) $notification->id,
                '966500000001',
            ])))
            ->count());
    }

    public function test_status_callback_requires_valid_fresh_hmac_and_rejects_replay(): void
    {
        $message = WhatsAppMessage::create([
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'event_key' => 'user.approval.approved',
            'recipient_phone' => '966500000001',
            'provider_message_id' => 'wamid.test',
            'status' => WhatsAppMessage::STATUS_ACCEPTED,
            'idempotency_key' => 'callback-test',
        ]);

        $body = json_encode([
            'provider_message_id' => 'wamid.test',
            'status' => 'delivered',
        ], JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-status-secret');

        $response = $this->call('POST', route('webhooks.whatsapp.status'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_EDARATEK_TIMESTAMP' => $timestamp,
            'HTTP_X_EDARATEK_SIGNATURE' => $signature,
        ], $body);

        $response->assertOk()->assertJsonPath('updated', true);
        $this->assertSame(WhatsAppMessage::STATUS_DELIVERED, $message->refresh()->status);

        $replay = $this->call('POST', route('webhooks.whatsapp.status'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_EDARATEK_TIMESTAMP' => $timestamp,
            'HTTP_X_EDARATEK_SIGNATURE' => $signature,
        ], $body);

        $replay->assertStatus(409);
    }

    public function test_meta_webhook_verification_returns_plain_challenge_for_valid_request(): void
    {
        $response = $this->get(route('webhooks.whatsapp.meta.verify', [
            'hub.mode' => 'subscribe',
            'hub.verify_token' => 'test-meta-verify-token',
            'hub.challenge' => 'edaratek_meta_verify_test',
        ]));

        $response->assertOk();
        $this->assertSame('text/plain; charset=UTF-8', $response->headers->get('content-type'));
        $this->assertSame('edaratek_meta_verify_test', $response->getContent());
    }

    public function test_meta_webhook_verification_rejects_invalid_token(): void
    {
        $this->get(route('webhooks.whatsapp.meta.verify', [
            'hub.mode' => 'subscribe',
            'hub.verify_token' => 'wrong-token',
            'hub.challenge' => 'edaratek_meta_verify_test',
        ]))->assertForbidden();
    }

    public function test_meta_webhook_verification_rejects_invalid_mode(): void
    {
        $this->get(route('webhooks.whatsapp.meta.verify', [
            'hub.mode' => 'unsubscribe',
            'hub.verify_token' => 'test-meta-verify-token',
            'hub.challenge' => 'edaratek_meta_verify_test',
        ]))->assertForbidden();
    }

    public function test_meta_webhook_verification_rejects_missing_parameters(): void
    {
        $this->get(route('webhooks.whatsapp.meta.verify', [
            'hub.mode' => 'subscribe',
            'hub.verify_token' => 'test-meta-verify-token',
        ]))->assertForbidden();
    }

    public function test_whatsapp_dispatch_failure_does_not_duplicate_message_and_retry_can_succeed(): void
    {
        Queue::fake();

        $user = User::factory()->create(['mobile' => '0500000001']);
        app(NotificationService::class)->notifyUser(
            (int) $user->id,
            'user.approval.approved',
            'تمت الموافقة على الحساب',
            'يمكنك الآن استخدام المنصة.'
        );

        $message = WhatsAppMessage::query()->firstOrFail();

        Http::fake([
            '*' => Http::sequence()
                ->push(['message' => 'temporary failure'], 500)
                ->push(['wamid' => 'wamid.retry-ok'], 200),
        ]);

        try {
            (new DispatchWhatsAppNotification((int) $message->id))->handle(
                app(N8nWhatsAppClient::class),
                app(WhatsAppDispatchClaimService::class)
            );
        } catch (\Throwable) {
        }

        $this->assertSame(1, WhatsAppMessage::query()->count());
        $this->assertSame(WhatsAppMessage::STATUS_PENDING, $message->refresh()->status);
        $this->assertSame(1, (int) $message->attempts);

        (new DispatchWhatsAppNotification((int) $message->id))->handle(
            app(N8nWhatsAppClient::class),
            app(WhatsAppDispatchClaimService::class)
        );

        $this->assertSame(1, WhatsAppMessage::query()->count());
        $this->assertSame(WhatsAppMessage::STATUS_ACCEPTED, $message->refresh()->status);
        $this->assertSame('wamid.retry-ok', $message->provider_message_id);
        $this->assertSame(2, (int) $message->attempts);
    }

    public function test_status_callback_rejects_invalid_and_stale_signatures(): void
    {
        $body = json_encode(['provider_message_id' => 'wamid.test', 'status' => 'sent'], JSON_UNESCAPED_UNICODE);

        $this->call('POST', route('webhooks.whatsapp.status'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_EDARATEK_TIMESTAMP' => (string) time(),
            'HTTP_X_EDARATEK_SIGNATURE' => 'invalid',
        ], $body)->assertStatus(401);

        $staleTimestamp = (string) (time() - 600);
        $staleSignature = hash_hmac('sha256', $staleTimestamp . '.' . $body, 'test-status-secret');

        $this->call('POST', route('webhooks.whatsapp.status'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_EDARATEK_TIMESTAMP' => $staleTimestamp,
            'HTTP_X_EDARATEK_SIGNATURE' => $staleSignature,
        ], $body)->assertStatus(401);
    }

    public function test_status_callback_does_not_regress_read_message_to_sent(): void
    {
        $message = WhatsAppMessage::create([
            'uuid' => '22222222-2222-4222-8222-222222222222',
            'event_key' => 'user.approval.approved',
            'recipient_phone' => '966500000001',
            'provider_message_id' => 'wamid.read',
            'status' => WhatsAppMessage::STATUS_READ,
            'idempotency_key' => 'callback-regression-test',
        ]);

        $body = json_encode([
            'provider_message_id' => 'wamid.read',
            'status' => 'sent',
        ], JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-status-secret');

        $this->call('POST', route('webhooks.whatsapp.status'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_EDARATEK_TIMESTAMP' => $timestamp,
            'HTTP_X_EDARATEK_SIGNATURE' => $signature,
        ], $body)->assertOk()->assertJsonPath('updated', false);

        $this->assertSame(WhatsAppMessage::STATUS_READ, $message->refresh()->status);
    }

    public function test_otp_is_hashed_expires_in_five_minutes_and_resend_invalidates_old_code(): void
    {
        Queue::fake();

        $user = User::factory()->create(['mobile' => '0500000001']);

        $first = app(WhatsAppOtpService::class)->send($user, '127.0.0.1');
        $first->forceFill(['sent_at' => now()->subSeconds(61)])->save();

        $second = app(WhatsAppOtpService::class)->send($user, '127.0.0.1');

        $this->assertNotSame('123456', $second->code_hash);
        $this->assertTrue(Hash::needsRehash('123456'));
        $this->assertTrue($second->expires_at->equalTo($second->created_at->copy()->addMinutes(5)));
        $this->assertNotNull($first->refresh()->consumed_at);
        $this->assertNull($second->consumed_at);
    }

    public function test_otp_wrong_attempts_limit_and_success_sets_phone_verified_at_once(): void
    {
        Queue::fake();

        $user = User::factory()->create(['mobile' => '0500000001']);
        $otp = app(WhatsAppOtpService::class)->send($user, '127.0.0.1');
        $otp->forceFill(['code_hash' => Hash::make('123456')])->save();

        try {
            app(WhatsAppOtpService::class)->verify($user, '654321');
        } catch (ValidationException) {
        }

        $this->assertSame(1, $otp->refresh()->attempts);

        $this->assertTrue(app(WhatsAppOtpService::class)->verify($user, '123456'));
        $this->assertNotNull($user->refresh()->phone_verified_at);
        $this->assertNotNull($otp->refresh()->consumed_at);

        $this->expectException(ValidationException::class);
        app(WhatsAppOtpService::class)->verify($user, '123456');
    }

    public function test_otp_rate_limit_blocks_more_than_five_sends_per_hour(): void
    {
        Queue::fake();

        $user = User::factory()->create(['mobile' => '0500000001']);
        $service = app(WhatsAppOtpService::class);

        for ($i = 0; $i < 5; $i++) {
            $otp = $service->send($user, '127.0.0.1');
            $otp->forceFill(['sent_at' => now()->subSeconds(61)])->save();
        }

        $this->expectException(ValidationException::class);
        $service->send($user, '127.0.0.1');
    }

    public function test_manager_subscription_creation_creates_subscriber_and_admin_whatsapp_messages(): void
    {
        Queue::fake();

        $admin = $this->createSuperAdmin('0500000100');
        $manager = $this->createUserWithRole('school_manager', '0500000101');
        $plan = $this->makePlan(Plan::ROLE_SCHOOL_MANAGER, 'Manager WhatsApp Plan');

        app(SubscriptionService::class)->createForUser($manager, $plan, false, ['source' => 'test']);

        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $manager->id,
            'event_key' => 'subscription.created',
            'recipient_phone' => '966500000101',
        ]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $admin->id,
            'event_key' => 'subscription.created.admin',
            'recipient_phone' => '966500000100',
        ]);
    }

    public function test_supervisor_subscription_creation_creates_subscriber_and_admin_whatsapp_messages(): void
    {
        Queue::fake();

        $admin = $this->createSuperAdmin('0500000102');
        $supervisor = $this->createUserWithRole('supervisor', '0500000103');
        $plan = $this->makePlan(Plan::ROLE_SUPERVISOR, 'Supervisor WhatsApp Plan');

        app(SubscriptionService::class)->createForUser($supervisor, $plan, false, ['source' => 'test']);

        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $supervisor->id,
            'event_key' => 'subscription.created',
            'recipient_phone' => '966500000103',
        ]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $admin->id,
            'event_key' => 'subscription.created.admin',
            'recipient_phone' => '966500000102',
        ]);
    }

    public function test_account_approval_and_rejection_create_whatsapp_messages_after_business_action(): void
    {
        Queue::fake();

        $admin = $this->createSuperAdmin('0500000104');
        $approvedUser = $this->createPendingUser('supervisor', '0500000105');
        $rejectedUser = $this->createPendingUser('school_manager', '0500000106');
        $plan = $this->makePlan(Plan::ROLE_SUPERVISOR, 'Approval Plan');

        Subscription::create([
            'user_id' => $approvedUser->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_PENDING,
        ]);
        Subscription::create([
            'user_id' => $rejectedUser->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_PENDING,
        ]);

        app(UserApprovalService::class)->approve($approvedUser, $admin);
        app(UserApprovalService::class)->reject($rejectedUser, $admin);

        $this->assertSame(User::APPROVAL_APPROVED, $approvedUser->refresh()->approval_status);
        $this->assertSame(User::APPROVAL_REJECTED, $rejectedUser->refresh()->approval_status);
        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $approvedUser->id,
            'event_key' => 'user.approval.approved',
        ]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $rejectedUser->id,
            'event_key' => 'user.approval.rejected',
        ]);
    }

    public function test_supervision_lifecycle_creates_whatsapp_messages_for_correct_recipients(): void
    {
        Queue::fake();

        $this->createSuperAdmin('0500000107');
        $manager = $this->createUserWithRole('school_manager', '0500000108');
        $supervisor = $this->createUserWithRole('supervisor', '0500000109');
        $school = $this->makeSchool($manager);

        app(SchoolSupervisionRequestService::class)->createBySupervisorSelection(
            $supervisor,
            (int) $school->directorate_id,
            [(int) $school->id]
        );

        $requestItem = SchoolSupervisionRequest::query()->where('school_id', $school->id)->firstOrFail();

        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $manager->id,
            'event_key' => 'SUPERVISION_REQUEST_CREATED',
        ]);

        app(SchoolSupervisionRequestService::class)->managerApprove($requestItem, $manager);

        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $supervisor->id,
            'event_key' => 'SUPERVISION_REQUEST_MANAGER_APPROVED',
        ]);

        $schoolForReject = $this->makeSchool($manager, 'WA-SCH-REJECT');
        app(SchoolSupervisionRequestService::class)->createBySupervisorSelection(
            $supervisor,
            (int) $schoolForReject->directorate_id,
            [(int) $schoolForReject->id]
        );
        $rejectedRequest = SchoolSupervisionRequest::query()->where('school_id', $schoolForReject->id)->firstOrFail();
        app(SchoolSupervisionRequestService::class)->managerReject($rejectedRequest, $manager);

        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $supervisor->id,
            'event_key' => 'SUPERVISION_REQUEST_MANAGER_REJECTED',
        ]);
    }

    public function test_whatsapp_outage_does_not_break_notification_when_sync_queue_is_used(): void
    {
        config(['queue.default' => 'sync']);
        Http::fake(['*' => Http::response(['message' => 'down'], 500)]);

        $user = User::factory()->create(['mobile' => '0500000110']);

        $notification = app(NotificationService::class)->notifyUser(
            (int) $user->id,
            'user.approval.approved',
            'Approved',
            'Body'
        );

        $this->assertNotNull($notification->id);
        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $user->id,
            'status' => WhatsAppMessage::STATUS_FAILED,
        ]);
    }

    public function test_dispatch_request_signs_the_exact_raw_json_body(): void
    {
        $message = WhatsAppMessage::create([
            'uuid' => '33333333-3333-4333-8333-333333333333',
            'event_key' => 'user.approval.approved',
            'recipient_phone' => '966500000001',
            'template_name' => 'edaratek_notifications_enabled',
            'language' => 'ar',
            'parameters' => ['title' => 'مرحبا'],
            'status' => WhatsAppMessage::STATUS_PENDING,
            'idempotency_key' => 'raw-body-test',
        ]);

        Http::fake(function ($request) {
            $timestamp = $request->header('X-Edaratek-Timestamp')[0] ?? '';
            $signature = $request->header('X-Edaratek-Signature')[0] ?? '';
            $expected = hash_hmac('sha256', $timestamp . '.' . $request->body(), 'test-dispatch-secret');

            $this->assertSame($expected, $signature);

            return Http::response(['wamid' => 'wamid.raw'], 200);
        });

        $response = app(N8nWhatsAppClient::class)->dispatch($message);

        $this->assertSame('wamid.raw', $response['wamid']);
    }

    public function test_status_callback_rejects_missing_modified_and_future_hmac_requests(): void
    {
        $body = json_encode(['provider_message_id' => 'wamid.test', 'status' => 'sent'], JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-status-secret');

        $this->call('POST', route('webhooks.whatsapp.status'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_EDARATEK_TIMESTAMP' => $timestamp,
        ], $body)->assertStatus(401);

        $this->call('POST', route('webhooks.whatsapp.status'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_EDARATEK_TIMESTAMP' => $timestamp,
            'HTTP_X_EDARATEK_SIGNATURE' => $signature,
        ], json_encode(['provider_message_id' => 'wamid.test', 'status' => 'read'], JSON_UNESCAPED_UNICODE))->assertStatus(401);

        $futureTimestamp = (string) (time() + 600);
        $futureSignature = hash_hmac('sha256', $futureTimestamp . '.' . $body, 'test-status-secret');

        $this->call('POST', route('webhooks.whatsapp.status'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_EDARATEK_TIMESTAMP' => $futureTimestamp,
            'HTTP_X_EDARATEK_SIGNATURE' => $futureSignature,
        ], $body)->assertStatus(401);
    }

    public function test_duplicate_status_callback_with_fresh_signature_is_idempotent(): void
    {
        $message = WhatsAppMessage::create([
            'uuid' => '44444444-4444-4444-8444-444444444444',
            'event_key' => 'user.approval.approved',
            'recipient_phone' => '966500000001',
            'provider_message_id' => 'wamid.duplicate',
            'status' => WhatsAppMessage::STATUS_DELIVERED,
            'idempotency_key' => 'callback-duplicate-test',
        ]);

        $body = json_encode(['provider_message_id' => 'wamid.duplicate', 'status' => 'delivered'], JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-status-secret');

        $this->call('POST', route('webhooks.whatsapp.status'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_EDARATEK_TIMESTAMP' => $timestamp,
            'HTTP_X_EDARATEK_SIGNATURE' => $signature,
        ], $body)->assertOk()->assertJsonPath('updated', false);

        $this->assertSame(WhatsAppMessage::STATUS_DELIVERED, $message->refresh()->status);
    }

    public function test_status_callback_stores_failed_status_details_and_prevents_regression(): void
    {
        $message = WhatsAppMessage::create([
            'uuid' => '55555555-5555-4555-8555-555555555555',
            'event_key' => 'user.approval.approved',
            'recipient_phone' => '966500000001',
            'provider_message_id' => 'wamid.failure',
            'status' => WhatsAppMessage::STATUS_DELIVERED,
            'idempotency_key' => 'callback-failure-test',
        ]);

        $failureBody = json_encode([
            'provider_message_id' => 'wamid.failure',
            'status' => 'failed',
            'failure_code' => '131000',
            'failure_reason' => 'Meta failure',
        ], JSON_UNESCAPED_UNICODE);
        $failureTimestamp = (string) time();
        $failureSignature = hash_hmac('sha256', $failureTimestamp . '.' . $failureBody, 'test-status-secret');

        $this->call('POST', route('webhooks.whatsapp.status'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_EDARATEK_TIMESTAMP' => $failureTimestamp,
            'HTTP_X_EDARATEK_SIGNATURE' => $failureSignature,
        ], $failureBody)->assertOk()->assertJsonPath('updated', true);

        $this->assertSame(WhatsAppMessage::STATUS_FAILED, $message->refresh()->status);
        $this->assertSame('131000', $message->failure_code);

        $regressionBody = json_encode(['provider_message_id' => 'wamid.failure', 'status' => 'sent'], JSON_UNESCAPED_UNICODE);
        $regressionTimestamp = (string) (time() - 2);
        $regressionSignature = hash_hmac('sha256', $regressionTimestamp . '.' . $regressionBody, 'test-status-secret');

        $this->call('POST', route('webhooks.whatsapp.status'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_EDARATEK_TIMESTAMP' => $regressionTimestamp,
            'HTTP_X_EDARATEK_SIGNATURE' => $regressionSignature,
        ], $regressionBody)->assertOk()->assertJsonPath('updated', false);

        $this->assertSame(WhatsAppMessage::STATUS_FAILED, $message->refresh()->status);
    }

    public function test_dispatch_claim_endpoint_grants_one_send_and_rejects_duplicate_claim(): void
    {
        $message = $this->makeWhatsAppMessage([
            'uuid' => '66666666-6666-4666-8666-666666666666',
            'idempotency_key' => 'claim-once-test',
        ]);

        $payload = [
            'message_id' => (string) $message->uuid,
            'idempotency_key' => (string) $message->idempotency_key,
        ];

        $first = $this->signedWebhookPost(route('webhooks.whatsapp.dispatch.claim'), $payload);
        $first->assertOk()->assertJsonPath('decision', 'send');

        $message->refresh();
        $this->assertSame(WhatsAppMessage::STATUS_PROCESSING, $message->status);
        $this->assertNotEmpty($message->dispatch_claim_token);
        $firstToken = (string) $message->dispatch_claim_token;

        $second = $this->signedWebhookPost(route('webhooks.whatsapp.dispatch.claim'), $payload, time() - 1);
        $second->assertOk()
            ->assertJsonPath('decision', 'do_not_send')
            ->assertJsonPath('reason', 'already_claimed');

        $this->assertSame($firstToken, (string) $message->refresh()->dispatch_claim_token);
    }

    public function test_atomic_claim_service_allows_only_one_claim_for_same_idempotency_key(): void
    {
        $message = $this->makeWhatsAppMessage([
            'uuid' => '77777777-7777-4777-8777-777777777777',
            'idempotency_key' => 'atomic-claim-test',
        ]);

        $service = app(WhatsAppDispatchClaimService::class);

        $first = $service->claim((string) $message->uuid, (string) $message->idempotency_key);
        $second = $service->claim((string) $message->uuid, (string) $message->idempotency_key);

        $this->assertSame('send', $first['decision']);
        $this->assertSame('do_not_send', $second['decision']);
        $this->assertSame('already_claimed', $second['reason']);
    }

    public function test_claim_endpoint_never_allows_already_accepted_or_delivered_messages_to_send(): void
    {
        $accepted = $this->makeWhatsAppMessage([
            'uuid' => '88888888-8888-4888-8888-888888888888',
            'idempotency_key' => 'already-accepted-test',
            'provider_message_id' => 'wamid.accepted',
            'status' => WhatsAppMessage::STATUS_ACCEPTED,
        ]);

        $acceptedClaim = app(WhatsAppDispatchClaimService::class)
            ->claim((string) $accepted->uuid, (string) $accepted->idempotency_key);

        $this->assertSame('do_not_send', $acceptedClaim['decision']);
        $this->assertSame('already_sent', $acceptedClaim['reason']);

        $delivered = $this->makeWhatsAppMessage([
            'uuid' => '99999999-9999-4999-8999-999999999999',
            'idempotency_key' => 'already-delivered-test',
            'provider_message_id' => 'wamid.delivered',
            'status' => WhatsAppMessage::STATUS_DELIVERED,
        ]);

        $deliveredClaim = app(WhatsAppDispatchClaimService::class)
            ->claim((string) $delivered->uuid, (string) $delivered->idempotency_key);

        $this->assertSame('do_not_send', $deliveredClaim['decision']);
        $this->assertSame('already_sent', $deliveredClaim['reason']);
    }

    public function test_ambiguous_dispatch_retry_marks_uncertain_and_does_not_allow_second_send(): void
    {
        $message = $this->makeWhatsAppMessage([
            'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'idempotency_key' => 'ambiguous-claim-test',
        ]);

        $claim = app(WhatsAppDispatchClaimService::class)
            ->claim((string) $message->uuid, (string) $message->idempotency_key);

        $this->assertSame('send', $claim['decision']);

        Http::fake([
            '*' => Http::response([
                'decision' => 'do_not_send',
                'reason' => 'already_claimed',
            ], 200),
        ]);

        (new DispatchWhatsAppNotification((int) $message->id))->handle(
            app(N8nWhatsAppClient::class),
            app(WhatsAppDispatchClaimService::class)
        );

        $message->refresh();
        $this->assertSame(WhatsAppMessage::STATUS_UNCERTAIN, $message->status);
        $this->assertNotNull($message->dispatch_uncertain_at);

        $retryClaim = app(WhatsAppDispatchClaimService::class)
            ->claim((string) $message->uuid, (string) $message->idempotency_key);

        $this->assertSame('do_not_send', $retryClaim['decision']);
        $this->assertSame('uncertain', $retryClaim['reason']);
    }

    public function test_dispatch_result_callback_requires_matching_claim_token(): void
    {
        $message = $this->makeWhatsAppMessage([
            'uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'idempotency_key' => 'result-token-test',
        ]);

        $claim = app(WhatsAppDispatchClaimService::class)
            ->claim((string) $message->uuid, (string) $message->idempotency_key);

        $body = [
            'message_id' => (string) $message->uuid,
            'idempotency_key' => (string) $message->idempotency_key,
            'dispatch_claim_token' => $claim['dispatch_claim_token'],
            'provider_message_id' => 'wamid.result-token',
            'status' => 'accepted',
        ];

        $this->signedWebhookPost(route('webhooks.whatsapp.dispatch.result'), $body)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $message->refresh();
        $this->assertSame(WhatsAppMessage::STATUS_ACCEPTED, $message->status);
        $this->assertSame('wamid.result-token', $message->provider_message_id);

        $wrongTokenBody = array_merge($body, [
            'dispatch_claim_token' => 'wrong-token',
            'provider_message_id' => 'wamid.other',
        ]);
        $this->signedWebhookPost(route('webhooks.whatsapp.dispatch.result'), $wrongTokenBody, time() - 1)
            ->assertStatus(409);

        $missingTokenBody = $body;
        unset($missingTokenBody['dispatch_claim_token']);
        $this->signedWebhookPost(route('webhooks.whatsapp.dispatch.result'), $missingTokenBody, time() - 2)
            ->assertStatus(422);
    }

    public function test_definitive_failure_callback_marks_terminal_failure_without_reopening_claim(): void
    {
        $message = $this->makeWhatsAppMessage([
            'uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'idempotency_key' => 'definitive-failure-test',
        ]);

        $claim = app(WhatsAppDispatchClaimService::class)
            ->claim((string) $message->uuid, (string) $message->idempotency_key);

        $body = [
            'message_id' => (string) $message->uuid,
            'idempotency_key' => (string) $message->idempotency_key,
            'dispatch_claim_token' => $claim['dispatch_claim_token'],
            'status' => 'failed',
            'failure_code' => 'meta_rejected',
            'failure_reason' => 'Meta rejected the template request.',
        ];

        $this->signedWebhookPost(route('webhooks.whatsapp.dispatch.result'), $body)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $message->refresh();
        $this->assertSame(WhatsAppMessage::STATUS_FAILED, $message->status);
        $this->assertSame('meta_rejected', $message->failure_code);

        $newClaim = app(WhatsAppDispatchClaimService::class)
            ->claim((string) $message->uuid, (string) $message->idempotency_key);

        $this->assertSame('do_not_send', $newClaim['decision']);
        $this->assertSame('terminal_failure', $newClaim['reason']);
    }

    public function test_otp_routes_require_authenticated_current_user_and_ignore_submitted_phone(): void
    {
        Queue::fake();

        $this->postJson(route('phone.verification.whatsapp.send'))->assertUnauthorized();

        $user = User::factory()->create(['mobile' => '0500000111']);

        $this->actingAs($user)->postJson(route('phone.verification.whatsapp.send'), [
            'phone' => '0509999999',
        ])->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $user->id,
            'event_key' => 'phone_verification_otp',
            'recipient_phone' => '966500000111',
        ]);
        $this->assertDatabaseMissing('whatsapp_messages', [
            'recipient_phone' => '966509999999',
        ]);
    }

    public function test_user_cannot_verify_another_users_otp(): void
    {
        Queue::fake();

        $owner = User::factory()->create(['mobile' => '0500000112']);
        $other = User::factory()->create(['mobile' => '0500000113']);
        $otp = app(WhatsAppOtpService::class)->send($owner, '127.0.0.1');
        $otp->forceFill(['code_hash' => Hash::make('123456')])->save();

        $this->actingAs($other)
            ->postJson(route('phone.verification.whatsapp.verify'), ['code' => '123456'])
            ->assertStatus(422);

        $this->assertNull($other->refresh()->phone_verified_at);
        $this->assertNull($owner->refresh()->phone_verified_at);
    }

    public function test_phone_change_invalidates_phone_verification(): void
    {
        $user = User::factory()->create([
            'mobile' => '0500000114',
            'phone_verified_at' => now(),
        ]);

        $user->update(['mobile' => '0500000115']);

        $this->assertNull($user->refresh()->phone_verified_at);
    }

    public function test_otp_verification_rate_limit_and_max_attempts_are_enforced(): void
    {
        Queue::fake();

        $user = User::factory()->create(['mobile' => '0500000116']);
        $otp = app(WhatsAppOtpService::class)->send($user, '127.0.0.1');
        $otp->forceFill(['code_hash' => Hash::make('123456'), 'attempts' => 5])->save();

        $this->expectException(ValidationException::class);
        app(WhatsAppOtpService::class)->verify($user, '123456', '127.0.0.1');
    }

    public function test_otp_verification_request_rate_limit_blocks_repeated_codes(): void
    {
        Queue::fake();

        $user = User::factory()->create(['mobile' => '0500000117']);
        $otp = app(WhatsAppOtpService::class)->send($user, '127.0.0.1');
        $otp->forceFill(['code_hash' => Hash::make('123456')])->save();

        for ($i = 0; $i < 10; $i++) {
            try {
                app(WhatsAppOtpService::class)->verify($user, '654321', '127.0.0.1');
            } catch (ValidationException) {
            }
        }

        $this->expectException(ValidationException::class);
        app(WhatsAppOtpService::class)->verify($user, '123456', '127.0.0.1');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('phoneNumberCases')]
    public function test_phone_number_normalization_is_strict_for_supported_saudi_and_egypt_numbers(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, app(PhoneNumberNormalizer::class)->normalize($input));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function phoneNumberCases(): array
    {
        return [
            'saudi local' => ['0552470119', '966552470119'],
            'saudi plus' => ['+966552470119', '966552470119'],
            'saudi international' => ['966552470119', '966552470119'],
            'egypt local' => ['01022827443', '201022827443'],
            'egypt plus' => ['+201022827443', '201022827443'],
            'egypt international' => ['201022827443', '201022827443'],
            'duplicate country code' => ['+966966552470119', null],
            'malformed short' => ['12345', null],
            'ambiguous generic' => ['1234567890', null],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function signedWebhookPost(string $url, array $payload, ?int $timestamp = null)
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = $timestamp ?: time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-status-secret');

        return $this->call('POST', $url, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_EDARATEK_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_EDARATEK_SIGNATURE' => $signature,
        ], (string) $body);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeWhatsAppMessage(array $overrides = []): WhatsAppMessage
    {
        return WhatsAppMessage::create(array_merge([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'event_key' => 'user.approval.approved',
            'recipient_phone' => '966500000001',
            'template_name' => 'edaratek_notifications_enabled',
            'language' => 'ar',
            'parameters' => ['title' => 'Test'],
            'status' => WhatsAppMessage::STATUS_PENDING,
            'idempotency_key' => 'whatsapp-message-' . \Illuminate\Support\Str::uuid(),
        ], $overrides));
    }

    private function createRole(string $name): Role
    {
        return Role::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]);
    }

    private function createSuperAdmin(string $mobile): User
    {
        return $this->createUserWithRole('super_admin', $mobile);
    }

    private function createUserWithRole(string $role, string $mobile): User
    {
        $this->createRole($role);

        $user = User::factory()->create([
            'role' => $role,
            'mobile' => $mobile,
            'is_active' => true,
            'approval_status' => User::APPROVAL_APPROVED,
            'approved_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function createPendingUser(string $role, string $mobile): User
    {
        $this->createRole($role);

        $user = User::factory()->create([
            'role' => $role,
            'mobile' => $mobile,
            'is_active' => false,
            'approval_status' => User::APPROVAL_PENDING,
            'approved_at' => null,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makePlan(string $roleType, string $name): Plan
    {
        return Plan::create([
            'name' => $name,
            'role_type' => $roleType,
            'price' => 100,
            'billing_cycle' => Plan::BILLING_MONTHLY,
            'is_active' => true,
        ]);
    }

    private function makeSchool(User $manager, string $code = 'WA-SCH-001'): School
    {
        $region = EducationalDirectorate::create([
            'name' => 'WhatsApp Supervision Region ' . $code,
            'governorate' => 'Riyadh',
        ]);

        $school = School::create([
            'directorate_id' => $region->id,
            'name' => 'WhatsApp School ' . $code,
            'school_id' => $code,
            'phone' => '0500000999',
            'status' => School::STATUS_SUSPENDED,
            'manager_user_id' => $manager->id,
        ]);

        $manager->forceFill(['school_id' => $school->id])->save();

        return $school;
    }
}
