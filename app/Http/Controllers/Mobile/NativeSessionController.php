<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Requests\Api\V1\NativeLoginRequest;
use App\Http\Requests\Api\V1\NativePaginationRequest;
use App\Http\Resources\Api\V1\CurrentUserResource;
use App\Http\Resources\Api\V1\DeviceResource;
use App\Models\User;
use App\Services\Mobile\AccountAccessEvaluator;
use App\Services\Mobile\NativeRateLimiter;
use App\Services\Mobile\NativeTokenService;
use App\Support\NativeApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class NativeSessionController
{
    public function __construct(
        private readonly AccountAccessEvaluator $accounts,
        private readonly NativeRateLimiter $limiter,
        private readonly NativeTokenService $tokens,
    ) {
    }

    public function login(NativeLoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $email = (string) $data['email'];

        // Resolve the database identity before admission so email changes/collation
        // aliases cannot create another budget for the same account. No credential check yet.
        $candidate = User::query()->where('email', $email)->first();
        if (($retryAfter = $this->limiter->consumeLogin($request, $email, $candidate)) !== null) {
            return $this->rateLimited($retryAfter);
        }

        if (! $candidate instanceof User || ! Hash::check((string) $data['password'], (string) $candidate->password)) {
            return NativeApiResponse::error('INVALID_CREDENTIALS', 401);
        }

        $user = $this->accounts->freshUser((int) $candidate->getKey());

        if (! $user instanceof User) {
            return NativeApiResponse::error('INVALID_CREDENTIALS', 401);
        }

        // Authentication occurs before eligibility; blocked credentials never
        // create a PAT and do not mutate a web session.
        if (($denialCode = $this->accounts->denialCode($user)) !== null) {
            return NativeApiResponse::error($denialCode, 403);
        }

        $newToken = $this->tokens->issue($user, (string) $data['device_uuid'], (string) $data['device_name']);
        $accessToken = $newToken->accessToken;

        return NativeApiResponse::data([
            'access_token' => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $accessToken->expires_at?->utc()->toISOString(),
            'user' => (new CurrentUserResource($user))->resolve($request),
        ], 200, ['Cache-Control' => 'no-store']);
    }

    public function me(Request $request): JsonResponse
    {
        return NativeApiResponse::data((new CurrentUserResource($request->user()))->resolve($request));
    }

    public function logout(Request $request): Response
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->noContent();
    }

    public function devices(NativePaginationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $currentTokenId = (int) $user->currentAccessToken()?->getKey();
        $perPage = $request->perPage();
        $page = $request->page();
        $tokens = $this->tokens->nativeTokens($user)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = $tokens->getCollection()->map(function (PersonalAccessToken $token) use ($currentTokenId, $request): array {
            $token->setAttribute('mobile_is_current', (int) $token->getKey() === $currentTokenId);

            return (new DeviceResource($token))->resolve($request);
        })->values()->all();

        return response()->json(\App\Support\NativeCollection::envelope($tokens, $data, '/api/v1/auth/devices'));
    }

    public function destroyDevice(Request $request, string $token): Response
    {
        /** @var User $user */
        $user = $request->user();

        $accessToken = $this->tokens->nativeTokens($user)->whereKey(\App\Support\NativeId::parse($token))->first();

        if (! $accessToken instanceof PersonalAccessToken || ! $this->tokens->isNative($accessToken)) {
            return NativeApiResponse::error('NOT_FOUND', 404);
        }

        $accessToken->delete();

        return response()->noContent();
    }

    public function destroyAllDevices(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->tokens->nativeTokens($user)->get()
            ->filter(fn (PersonalAccessToken $token): bool => $this->tokens->isNative($token))
            ->each->delete();

        return response()->noContent();
    }


    private function rateLimited(int $retryAfter): JsonResponse
    {
        return NativeApiResponse::error('RATE_LIMITED', 429, null, [
            'Retry-After' => (string) $retryAfter,
        ]);
    }
}
