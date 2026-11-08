<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Requests\Mobile\NativeLoginRequest;
use App\Http\Requests\Mobile\NativePaginationRequest;
use App\Http\Resources\Mobile\CurrentUserResource;
use App\Http\Resources\Mobile\DeviceResource;
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

        if (($retryAfter = $this->limiter->loginAllowed($request)) !== null
            || ($retryAfter = $this->limiter->invalidCredentialsAllowed($request, $email)) !== null) {
            return $this->rateLimited($retryAfter);
        }

        $candidate = User::query()->where('email', $email)->first();

        if (! $candidate instanceof User || ! Hash::check((string) $data['password'], (string) $candidate->password)) {
            $this->limiter->recordInvalidCredentials($request, $email);

            return NativeApiResponse::error('INVALID_CREDENTIALS', 401);
        }

        $user = $this->accounts->freshUser((int) $candidate->getKey());

        if (! $user instanceof User) {
            return NativeApiResponse::error('INVALID_CREDENTIALS', 401);
        }

        if (($retryAfter = $this->limiter->loginAllowed($request, $user)) !== null) {
            return $this->rateLimited($retryAfter);
        }

        // Authentication occurs before eligibility; blocked credentials never
        // create a PAT and do not mutate a web session.
        $this->limiter->recordLogin($request, $user);
        $this->limiter->clearInvalidCredentials($request, $email);

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

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $tokens->currentPage(),
                'per_page' => $tokens->perPage(),
                'total' => $tokens->total(),
                'last_page' => $tokens->lastPage(),
                'from' => $tokens->firstItem(),
                'to' => $tokens->lastItem(),
            ],
            'links' => $this->paginationLinks($tokens->currentPage(), $tokens->lastPage(), $perPage),
        ]);
    }

    public function destroyDevice(Request $request, string $token): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! ctype_digit($token) || (int) $token < 1) {
            return NativeApiResponse::error('NOT_FOUND', 404);
        }

        $accessToken = $this->tokens->nativeTokens($user)->whereKey((int) $token)->first();

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

    /** @return array{first:string,last:string,prev:?string,next:?string} */
    private function paginationLinks(int $page, int $lastPage, int $perPage): array
    {
        $url = static fn (int $targetPage): string => '/api/v1/auth/devices?page='.$targetPage.'&per_page='.$perPage;

        return [
            'first' => $url(1),
            'last' => $url(max(1, $lastPage)),
            'prev' => $page > 1 ? $url($page - 1) : null,
            'next' => $page < $lastPage ? $url($page + 1) : null,
        ];
    }

    private function rateLimited(int $retryAfter): JsonResponse
    {
        return NativeApiResponse::error('RATE_LIMITED', 429, null, [
            'Retry-After' => (string) $retryAfter,
        ]);
    }
}
