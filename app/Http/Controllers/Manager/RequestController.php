<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\SchoolSupervisionRequest;
use App\Services\Supervision\SchoolSupervisionRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RequestController extends Controller
{
    public function __construct(private readonly SchoolSupervisionRequestService $requestService)
    {
    }

    public function page(Request $request): Response
    {
        $items = SchoolSupervisionRequest::query()
            ->with(['school:id,name,school_id', 'supervisor:id,name,email', 'manager:id,name,email'])
            ->where('school_id', $request->user()->school_id)
            ->latest('id')
            ->get();

        return Inertia::render('Manager/Requests', [
            'requests' => $items,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->school_id) {
            return response()->json([]);
        }

        $items = SchoolSupervisionRequest::query()
            ->with(['school:id,name,school_id', 'supervisor:id,name,email', 'manager:id,name,email'])
            ->where('school_id', $request->user()->school_id)
            ->latest('id')
            ->get();

        return response()->json($items);
    }

    public function approve(Request $request, SchoolSupervisionRequest $schoolSupervisionRequest): JsonResponse
    {
        $this->authorize('approve', $schoolSupervisionRequest);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $updated = $this->requestService->managerApprove(
            $schoolSupervisionRequest,
            $request->user(),
            $validated['notes'] ?? null,
            $request
        );

        return response()->json($updated);
    }

    public function reject(Request $request, SchoolSupervisionRequest $schoolSupervisionRequest): JsonResponse
    {
        $this->authorize('reject', $schoolSupervisionRequest);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $updated = $this->requestService->managerReject(
            $schoolSupervisionRequest,
            $request->user(),
            $validated['notes'] ?? null,
            $request
        );

        return response()->json($updated);
    }
}
