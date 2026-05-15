<?php

namespace App\Http\Controllers;

use App\Models\AssociationRequest;
use App\Services\Association\AssociationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssociationRequestController extends Controller
{
    public function __construct(private readonly AssociationService $associationService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = AssociationRequest::query()->with(['school:id,name,school_id,status', 'supervisor:id,name,email']);

        if ($user->hasSystemRole('school_manager')) {
            $query->where('manager_user_id', $user->id);
        }

        $requests = $query->latest('id')->get();

        return response()->json($requests);
    }

    public function approve(Request $request, AssociationRequest $associationRequest): JsonResponse
    {
        $this->authorize('respond', $associationRequest);

        $updated = $this->associationService->approve($associationRequest, $request->user(), $request);

        return response()->json($updated);
    }

    public function reject(Request $request, AssociationRequest $associationRequest): JsonResponse
    {
        $this->authorize('respond', $associationRequest);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $updated = $this->associationService->reject(
            $associationRequest,
            $request->user(),
            $validated['notes'] ?? null,
            $request
        );

        return response()->json($updated);
    }
}
