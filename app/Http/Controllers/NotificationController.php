<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', 100);
        $limit = max(1, min($limit, 200));

        $items = Notification::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit($limit)
            ->get();

        return response()->json($items);
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);

        $notification->update(['read_at' => now()]);

        return response()->json($notification);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $affected = Notification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'marked_count' => $affected,
        ]);
    }
}
