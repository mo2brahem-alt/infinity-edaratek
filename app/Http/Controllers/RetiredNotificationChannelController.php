<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class RetiredNotificationChannelController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['code' => 'WHATSAPP_REMOVED', 'message' => 'هذه القناة لم تعد مدعومة.'], 410)
            ->header('Cache-Control', 'private, no-store');
    }
}
