<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Requests\Api\V1\GoogleLinkRequest;
use App\Http\Requests\Api\V1\GoogleLoginRequest;
use App\Http\Requests\Api\V1\GoogleUnlinkRequest;
use App\Http\Resources\Api\V1\CurrentUserResource;
use App\Services\Mobile\Google\FirebaseIdentityVerifier;
use App\Services\Mobile\Google\GoogleAuthFailure;
use App\Services\Mobile\Google\GoogleIdentityService;
use App\Services\Mobile\NativeRateLimiter;
use App\Support\NativeApiResponse;
use Illuminate\Http\Request;

final class GoogleSessionController
{
    public function __construct(private readonly FirebaseIdentityVerifier $verifier,
        private readonly GoogleIdentityService $identities, private readonly NativeRateLimiter $limiter) {}

    public function login(GoogleLoginRequest $request)
    {
        try {
            $data = $request->validated();
            $proof = $this->verifier->verify($data['firebase_id_token']);
            $userId = $this->identities->linkedUserId($proof);
            $retry = $this->limiter->consumeGoogleIdentity($proof->projectId, $proof->firebaseUid, $userId);
            if ($retry !== null) {
                return NativeApiResponse::error('RATE_LIMITED', 429, null, ['Retry-After' => (string) $retry]);
            }
            $issued = $this->identities->login($proof, $data['device_uuid'], $data['device_name']);

            return NativeApiResponse::data(['access_token' => $issued->plainTextToken, 'token_type' => 'Bearer',
                'expires_at' => $issued->accessToken->expires_at?->utc()->toISOString(),
                'user' => (new CurrentUserResource($issued->accessToken->tokenable))->resolve($request)], 200, ['Cache-Control' => 'no-store']);
        } catch (GoogleAuthFailure $failure) {
            return $this->failure($failure);
        }
    }

    public function status(Request $request)
    {
        // This resource intentionally accepts no account/tenant selectors.
        if ($request->all() !== []) {
            return NativeApiResponse::error('VALIDATION_FAILED', 422);
        }

        return NativeApiResponse::data($this->identities->status((int) $request->user()->id), 200, ['Cache-Control' => 'private, no-store']);
    }

    public function link(GoogleLinkRequest $request)
    {
        try {
            $data = $request->validated();
            // Reject bad password before invoking an expensive external verifier.
            if (! \Illuminate\Support\Facades\Hash::check($data['current_password'], $request->user()->password)) {
                throw new GoogleAuthFailure('REAUTHENTICATION_FAILED', 403);
            }
            $proof = $this->verifier->verify($data['firebase_id_token']);

            return NativeApiResponse::data($this->identities->link((int) $request->user()->id, $data['current_password'], $proof), 200, ['Cache-Control' => 'private, no-store']);
        } catch (GoogleAuthFailure $failure) {
            return $this->failure($failure, true);
        }
    }

    public function unlink(GoogleUnlinkRequest $request)
    {
        try {
            $this->identities->unlink((int) $request->user()->id, $request->validated('current_password'));

            return response()->noContent();
        } catch (GoogleAuthFailure $failure) {
            return $this->failure($failure);
        }
    }

    private function failure(GoogleAuthFailure $failure, bool $linking = false)
    {
        $status = $linking && $failure->errorCode === 'GOOGLE_IDENTITY_INVALID' ? 403 : $failure->status;
        $headers = $failure->retryAfter === null ? [] : ['Retry-After' => (string) $failure->retryAfter];

        return NativeApiResponse::error($failure->errorCode, $status, null, $headers);
    }
}
