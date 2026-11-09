<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ApiError extends HttpException
{
    public function __construct(public readonly string $errorCode, int $status = 409)
    {
        if (! in_array($errorCode, ['BUSINESS_CONFLICT', 'IMPACT_CONFIRMATION_REQUIRED'], true) || $status !== 409) {
            throw new \InvalidArgumentException('Unsupported domain conflict');
        }
        parent::__construct($status);
    }

    public static function requestId(?Request $request = null): string
    {
        $request ??= app()->bound('request') ? request() : null;
        if (! $request) { return (string) Str::uuid(); }
        if (! $request->attributes->has('_native_request_id')) {
            $request->attributes->set('_native_request_id', (string) Str::uuid());
        }
        return $request->attributes->get('_native_request_id');
    }

    public static function response(string $code, int $status, ?array $errors = null, array $headers = []): JsonResponse
    {
        $messages = [
            'INVALID_CREDENTIALS' => 'بيانات الدخول غير صحيحة.', 'UNAUTHENTICATED' => 'يلزم تسجيل الدخول.',
            'ACCOUNT_PENDING_APPROVAL' => 'الحساب قيد الموافقة.', 'ACCOUNT_REJECTED' => 'لم تتم الموافقة على الحساب.',
            'ACCOUNT_DISABLED' => 'الحساب غير نشط.', 'FORBIDDEN' => 'لا تملك صلاحية هذه العملية.',
            'SCHOOL_SUSPENDED' => 'المدرسة موقوفة.', 'SCHOOL_CONTEXT_REQUIRED' => 'يلزم ارتباط مدرسي صالح.',
            'NOT_FOUND' => 'المورد غير موجود.', 'BUSINESS_CONFLICT' => 'تتعارض العملية مع الحالة الحالية.',
            'IMPACT_CONFIRMATION_REQUIRED' => 'يلزم تأكيد أثر العملية.', 'VALIDATION_FAILED' => 'تحقق من الحقول المطلوبة.',
            'RATE_LIMITED' => 'طلبات كثيرة. حاول لاحقاً.', 'INTERNAL_ERROR' => 'تعذر إتمام الطلب.',
            'METHOD_NOT_ALLOWED' => 'طريقة الطلب غير متاحة.', 'BAD_REQUEST' => 'الطلب غير صالح.', 'PAYLOAD_TOO_LARGE' => 'حجم الطلب أكبر من المسموح.',
        ];
        $payload = ['code' => $code, 'message' => $messages[$code] ?? $messages['INTERNAL_ERROR'], 'request_id' => self::requestId()];
        if ($errors) {
            $safe = [];
            foreach (array_slice(array_keys($errors), 0, 50) as $field) {
                if (is_string($field) && preg_match('/^[A-Za-z][A-Za-z0-9_.]{0,79}$/D', $field)) {
                    // Field names identify validation failures; arbitrary domain/input messages never leave the server.
                    $safe[$field] = ['قيمة هذا الحقل غير صالحة.'];
                }
            }
            if ($safe) { $payload['errors'] = $safe; }
        }
        $safeHeaders = ['Cache-Control' => 'private, no-store'];
        if ($code === 'RATE_LIMITED') {
            $retry = filter_var($headers['Retry-After'] ?? 1, FILTER_VALIDATE_INT);
            $payload['retry_after'] = max(1, min(86400, $retry === false ? 1 : $retry));
            $safeHeaders['Retry-After'] = (string) $payload['retry_after'];
        }
        if ($status === 405 && isset($headers['Allow']) && preg_match('/^(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)(, ?(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS))*$/D', $headers['Allow'])) {
            $safeHeaders['Allow'] = $headers['Allow'];
        }
        return response()->json($payload, $status, $safeHeaders);
    }
}
