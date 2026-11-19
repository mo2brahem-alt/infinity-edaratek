<?php

namespace App\Services\Mobile\Google;

use App\Models\ExternalIdentity;
use App\Models\User;
use App\Services\Mobile\AccountAccessEvaluator;
use App\Services\Mobile\NativeTokenService;
use App\Services\Support\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;

final class GoogleIdentityService
{
    public function __construct(
        private readonly AccountAccessEvaluator $accounts,
        private readonly NativeTokenService $tokens,
        private readonly AuditLogger $audit,
    ) {}

    private function eligible(int $id): User
    {
        // All Google mutations lock owner first, then identity. No cached role authority.
        if (! User::query()->whereKey($id)->lockForUpdate()->first()) {
            throw new GoogleAuthFailure('UNAUTHENTICATED');
        }
        $user = $this->accounts->freshUser($id);
        if (! $user) {
            throw new GoogleAuthFailure('UNAUTHENTICATED');
        }
        if ($code = $this->accounts->denialCode($user)) {
            throw new GoogleAuthFailure($code, 403);
        }

        return $user;
    }

    private function identities()
    {
        return ExternalIdentity::query()->where('firebase_project_id', config('firebase_google.project_id'))->where('provider', 'google.com');
    }

    public function status(int $userId): array
    {
        return $this->summary($this->identities()->where('user_id', $userId)->first());
    }

    private function summary(?ExternalIdentity $identity): array
    {
        return ['linked' => $identity !== null, 'provider' => 'google.com',
            'linked_at' => $identity?->linked_at?->utc()->toISOString()];
    }

    public function linkedUserId(VerifiedGoogleIdentity $proof): ?int
    {
        $identity = $this->identities()->where('firebase_uid', $proof->firebaseUid)->where('provider_uid', $proof->googleUid)->first();

        return $identity ? (int) $identity->user_id : null;
    }

    public function login(VerifiedGoogleIdentity $proof, string $uuid, string $name): NewAccessToken
    {
        $id = $this->linkedUserId($proof);
        if ($id === null) {
            throw new GoogleAuthFailure('GOOGLE_ACCOUNT_NOT_LINKED', 403);
        }

        return DB::transaction(function () use ($id, $proof, $uuid, $name): NewAccessToken {
            $user = $this->eligible($id);
            $identity = $this->identities()->where('user_id', $id)->where('firebase_uid', $proof->firebaseUid)
                ->where('provider_uid', $proof->googleUid)->lockForUpdate()->first();
            if (! $identity) {
                throw new GoogleAuthFailure('GOOGLE_ACCOUNT_NOT_LINKED', 403);
            }
            $issued = $this->tokens->issue($user, $uuid, $name);
            $issued->accessToken->forceFill(['external_identity_id' => $identity->id])->save();
            $identity->forceFill(['last_verified_at' => now()])->save();
            // Response identity is freshly loaded; no provider role/email is copied.
            $issued->accessToken->setRelation('tokenable', $user);

            return $issued;
        }, 3);
    }

    public function link(int $userId, string $password, VerifiedGoogleIdentity $proof): array
    {
        try {
            return DB::transaction(function () use ($userId, $password, $proof): array {
                $user = $this->eligible($userId);
                if (! Hash::check($password, $user->password)) {
                    throw new GoogleAuthFailure('REAUTHENTICATION_FAILED', 403);
                }
                $existing = $this->identities()->where('user_id', $userId)->lockForUpdate()->first();
                if ($existing) {
                    if ($existing->firebase_uid !== $proof->firebaseUid || $existing->provider_uid !== $proof->googleUid) {
                        throw new GoogleAuthFailure('IDENTITY_LINK_CONFLICT', 409);
                    }

                    return $this->summary($existing);
                }
                if ($this->identities()->where(function ($query) use ($proof): void {
                    $query->where('firebase_uid', $proof->firebaseUid)->orWhere('provider_uid', $proof->googleUid);
                })->exists()) {
                    throw new GoogleAuthFailure('IDENTITY_LINK_CONFLICT', 409);
                }
                $identity = ExternalIdentity::create(['user_id' => $userId, 'firebase_project_id' => $proof->projectId,
                    'firebase_uid' => $proof->firebaseUid, 'provider' => 'google.com', 'provider_uid' => $proof->googleUid,
                    'linked_at' => now(), 'last_verified_at' => now()]);
                $this->audit->log('GOOGLE_IDENTITY_LINKED', 'external_identity', $identity->id, ['provider' => 'google.com'], null, $userId);

                return $this->summary($identity);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw new GoogleAuthFailure('IDENTITY_LINK_CONFLICT', 409);
        }
    }

    public function unlink(int $userId, string $password): void
    {
        DB::transaction(function () use ($userId, $password): void {
            $user = $this->eligible($userId);
            if (! Hash::check($password, $user->password)) {
                throw new GoogleAuthFailure('REAUTHENTICATION_FAILED', 403);
            }
            $identity = $this->identities()->where('user_id', $userId)->lockForUpdate()->first();
            if (! $identity) {
                return;
            }
            // FK cascade deletes only sessions attributed to this exact link.
            // Password/legacy sessions have null provenance and remain untouched.
            $identity->delete();
            $this->audit->log('GOOGLE_IDENTITY_UNLINKED', 'external_identity', $identity->id, ['provider' => 'google.com'], null, $userId);
        }, 3);
    }
}
