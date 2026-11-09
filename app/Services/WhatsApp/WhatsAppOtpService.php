<?php

namespace App\Services\WhatsApp;

use App\Jobs\DispatchWhatsAppOtp;
use App\Models\PhoneVerificationOtp;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class WhatsAppOtpService
{
    public const PURPOSE_PHONE_VERIFICATION = 'phone_verification';

    public function __construct(
        private readonly N8nWhatsAppClient $client,
        private readonly WhatsAppRecipientResolver $recipientResolver,
        private readonly WhatsAppNotificationService $notificationService,
    ) {
    }

    public function send(User $user, ?string $ip = null): PhoneVerificationOtp
    {
        throw new \Symfony\Component\HttpKernel\Exception\GoneHttpException('WHATSAPP_REMOVED');
        // Historical implementation below is retained for audit, not an active feature.
        if (! $this->client->configured()) {
            throw ValidationException::withMessages([
                'phone' => 'خدمة التحقق عبر واتساب غير مفعلة حاليًا.',
            ]);
        }

        $phone = $this->recipientResolver->phoneForUser($user);
        if (! $phone) {
            throw ValidationException::withMessages([
                'phone' => 'يرجى إضافة رقم جوال صالح قبل طلب رمز التحقق.',
            ]);
        }

        $hourlyKey = $this->rateKey('send-hour', $user, $phone, $ip);
        if (RateLimiter::tooManyAttempts($hourlyKey, 5)) {
            throw ValidationException::withMessages([
                'phone' => 'تم تجاوز الحد المسموح لإرسال رموز التحقق. حاول لاحقًا.',
            ]);
        }

        $latest = PhoneVerificationOtp::query()
            ->where('user_id', $user->id)
            ->where('phone_e164', $phone)
            ->where('purpose', self::PURPOSE_PHONE_VERIFICATION)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($latest && $latest->sent_at && $latest->sent_at->gt(now()->subSeconds(60))) {
            throw ValidationException::withMessages([
                'phone' => 'يمكنك طلب رمز جديد بعد مرور 60 ثانية.',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        return DB::transaction(function () use ($user, $phone, $code, $hourlyKey): PhoneVerificationOtp {
            PhoneVerificationOtp::query()
                ->where('user_id', $user->id)
                ->where('purpose', self::PURPOSE_PHONE_VERIFICATION)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            $otp = PhoneVerificationOtp::create([
                'user_id' => (int) $user->id,
                'phone_e164' => $phone,
                'purpose' => self::PURPOSE_PHONE_VERIFICATION,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(5),
                'sent_at' => now(),
            ]);

            $template = (string) config('services.whatsapp.templates.phone_verification_otp', 'edaratek_phone_verification_otp');
            $message = WhatsAppMessage::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => (int) $user->id,
                'school_id' => $user->school_id,
                'event_key' => 'phone_verification_otp',
                'recipient_phone' => $phone,
                'template_name' => $template,
                'language' => 'ar',
                'parameters' => ['purpose' => self::PURPOSE_PHONE_VERIFICATION],
                'status' => WhatsAppMessage::STATUS_PENDING,
                'idempotency_key' => $this->notificationService->idempotencyKey(
                    'phone_verification_otp',
                    'phone_verification_otp',
                    (int) $otp->id,
                    $phone
                ),
            ]);

            RateLimiter::hit($hourlyKey, 3600);
            DB::afterCommit(function () use ($message, $code): void {
                try {
                    DispatchWhatsAppOtp::dispatch($message->id, $code)->afterCommit();
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

            return $otp;
        });
    }

    public function verify(User $user, string $code, ?string $ip = null): bool
    {
        throw new \Symfony\Component\HttpKernel\Exception\GoneHttpException('WHATSAPP_REMOVED');
        // No retired OTP may create a new phone-verification assertion.
        if (! preg_match('/^\d{6}$/', $code)) {
            throw ValidationException::withMessages(['code' => 'رمز التحقق يجب أن يتكون من 6 أرقام.']);
        }

        $phone = $this->recipientResolver->phoneForUser($user);
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => 'لا يوجد رقم جوال صالح للتحقق.']);
        }

        $verifyKey = $this->rateKey('verify', $user, $phone, $ip);
        if (RateLimiter::tooManyAttempts($verifyKey, 10)) {
            throw ValidationException::withMessages(['code' => 'تم تجاوز عدد محاولات التحقق. حاول لاحقًا.']);
        }

        RateLimiter::hit($verifyKey, 300);

        $verificationResult = DB::transaction(function () use ($user, $phone, $code): string {
            $otp = PhoneVerificationOtp::query()
                ->where('user_id', $user->id)
                ->where('phone_e164', $phone)
                ->where('purpose', self::PURPOSE_PHONE_VERIFICATION)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $otp || $otp->expires_at->isPast()) {
                return 'expired';
            }

            if ((int) $otp->attempts >= 5) {
                return 'max_attempts';
            }

            if (! Hash::check($code, (string) $otp->code_hash)) {
                $otp->increment('attempts');
                return 'invalid';
            }

            $otp->forceFill(['consumed_at' => now()])->save();
            $user->forceFill(['phone_verified_at' => now()])->save();

            return 'verified';
        });

        if ($verificationResult === 'expired') {
            throw ValidationException::withMessages(['code' => 'انتهت صلاحية رمز التحقق.']);
        }

        if ($verificationResult === 'max_attempts') {
            throw ValidationException::withMessages(['code' => 'تم تجاوز عدد المحاولات المسموح.']);
        }

        if ($verificationResult === 'invalid') {
            throw ValidationException::withMessages(['code' => 'رمز التحقق غير صحيح.']);
        }

        return true;
    }

    private function rateKey(string $scope, User $user, string $phone, ?string $ip): string
    {
        return implode('|', ['whatsapp-otp', $scope, (int) $user->id, $phone, (string) $ip]);
    }
}
