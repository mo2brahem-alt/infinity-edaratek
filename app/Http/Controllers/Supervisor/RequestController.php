<?php

namespace App\Http\Controllers\Supervisor;

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
            ->with(['school:id,name,school_id', 'manager:id,name,email'])
            ->where('supervisor_id', $request->user()->id)
            ->latest('id')
            ->get();

        return Inertia::render('Supervisor/Requests', [
            'requests' => $items,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $items = SchoolSupervisionRequest::query()
            ->with(['school:id,name,school_id', 'manager:id,name,email'])
            ->where('supervisor_id', $request->user()->id)
            ->latest('id')
            ->get();

        return response()->json($items);
    }

    public function confirm(Request $request, SchoolSupervisionRequest $schoolSupervisionRequest): JsonResponse
    {
        $this->authorize('confirm', $schoolSupervisionRequest);

        $updated = $this->requestService->supervisorConfirm($schoolSupervisionRequest, $request->user(), $request);

        return response()->json($updated);
    }

    public function cancel(Request $request, SchoolSupervisionRequest $schoolSupervisionRequest): JsonResponse
    {
        $this->authorize('cancel', $schoolSupervisionRequest);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $updated = $this->requestService->supervisorCancel(
            $schoolSupervisionRequest,
            $request->user(),
            $validated['notes'] ?? null,
            $request
        );

        return response()->json($updated);
    }
}
