<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NativeApiResponse
{
    public static function isNativeRequest(Request $request): bool
    {
        return $request->is('api/v1', 'api/v1/*');
    }

    public static function data(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json(['data' => $data], $status, $headers);
    }

    public static function error(string $code, int $status, ?array $errors = null, array $headers = []): JsonResponse
    {
        return ApiError::response($code, $status, $errors, $headers);
    }
}
