<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class NativeApiResponse
{
    public static function isNativeRequest(Request $request): bool
    {
        return $request->is('api/v1/*');
    }

    public static function data(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json(['data' => $data], $status, $headers);
    }

    public static function error(string $code, int $status, ?array $errors = null, array $headers = []): JsonResponse
    {
        $payload = [
            'code' => $code,
            'message' => self::messageFor($code),
            'request_id' => (string) Str::uuid(),
        ];

        if ($errors !== null && $errors !== []) {
            $payload['errors'] = $errors;
        }

        if ($code === 'RATE_LIMITED' && isset($headers['Retry-After'])) {
            $payload['retry_after'] = max(1, (int) $headers['Retry-After']);
        }

        return response()->json($payload, $status, $headers);
    }

    private static function messageFor(string $code): string
    {
        return match ($code) {
            'INVALID_CREDENTIALS' => 'The supplied credentials are invalid.',
            'UNAUTHENTICATED' => 'Authentication is required.',
            'ACCOUNT_PENDING_APPROVAL' => 'This account is pending approval.',
            'ACCOUNT_REJECTED' => 'This account has been rejected.',
            'ACCOUNT_DISABLED' => 'This account is disabled.',
            'RATE_LIMITED' => 'Too many requests. Please try again later.',
            'NOT_FOUND' => 'The requested resource was not found.',
            'FORBIDDEN' => 'You are not allowed to perform this action.',
            default => 'The request could not be completed.',
        };
    }
}
