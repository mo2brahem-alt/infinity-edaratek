<?php

namespace App\Http\Controllers;

use App\Services\WhatsApp\WhatsAppOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppPhoneVerificationController extends Controller
{
    public function send(Request $request, WhatsAppOtpService $otpService): JsonResponse
    {
        $otpService->send($request->user(), $request->ip());

        return response()->json([
            'message' => 'تم إرسال رمز التحقق عبر واتساب.',
            'expires_in_seconds' => 300,
            'resend_after_seconds' => 60,
        ]);
    }

    public function verify(Request $request, WhatsAppOtpService $otpService): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $otpService->verify($request->user(), $validated['code'], $request->ip());

        return response()->json([
            'message' => 'تم تأكيد رقم الجوال بنجاح.',
            'verified' => true,
        ]);
    }
}
