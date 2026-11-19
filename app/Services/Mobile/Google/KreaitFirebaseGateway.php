<?php

namespace App\Services\Mobile\Google;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\UserDisabled;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Http\HttpClientOptions;

final class KreaitFirebaseGateway implements FirebaseGateway
{
    private ?Auth $auth = null;

    public function __construct(private readonly FirebaseSigningKeys $keys, ?Auth $auth = null)
    {
        $this->auth = $auth;
    }

    private function emulatorConfigured(): bool
    {
        return (bool) (getenv('FIREBASE_AUTH_EMULATOR_HOST') ?: ($_ENV['FIREBASE_AUTH_EMULATOR_HOST'] ?? null) ?: ($_SERVER['FIREBASE_AUTH_EMULATOR_HOST'] ?? null));
    }

    private function auth(): Auth
    {
        if ($this->auth !== null) {
            return $this->auth;
        }
        $path = config('firebase_google.credentials_file');
        $real = is_string($path) ? realpath($path) : false;
        $root = strtolower(str_replace('\\', '/', realpath(base_path()).'/'));
        $file = $real === false ? '' : strtolower(str_replace('\\', '/', $real));
        $lexical = is_string($path) ? strtolower(str_replace('\\', '/', $path)) : '';
        $absolute = is_string($path) && (str_starts_with($path, '/') || (strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':' && in_array($path[2], ['/', '\\'], true)));
        if (! config('firebase_google.enabled') || ! $absolute || $real === false || ! is_file($real)
            || str_starts_with($file, $root) || str_starts_with($lexical, $root) || $this->emulatorConfigured()) {
            throw new GoogleAuthFailure('AUTH_PROVIDER_UNAVAILABLE', 503);
        }
        for ($directory = dirname($real); ; $directory = dirname($directory)) {
            if (file_exists($directory.DIRECTORY_SEPARATOR.'.git')) {
                throw new GoogleAuthFailure('AUTH_PROVIDER_UNAVAILABLE', 503);
            }
            if (dirname($directory) === $directory) {
                break;
            }
        }
        try {
            return $this->auth = (new Factory)
                ->withProjectId((string) config('firebase_google.project_id'))
                ->withServiceAccount($real)
                ->withHttpClientOptions(HttpClientOptions::default()->withConnectTimeout(3)->withTimeout(8)->withGuzzleConfigOption('allow_redirects', false))
                ->createAuth();
        } catch (\Throwable) {
            throw new GoogleAuthFailure('AUTH_PROVIDER_UNAVAILABLE', 503);
        }
    }

    public function verifiedClaims(string $token): array
    {
        if (! config('firebase_google.enabled') || $this->emulatorConfigured()) {
            throw new GoogleAuthFailure('AUTH_PROVIDER_UNAVAILABLE', 503);
        }
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                throw new GoogleAuthFailure('GOOGLE_IDENTITY_INVALID');
            }
            $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true, 8, JSON_THROW_ON_ERROR);
            $kid = $header['kid'] ?? null;
            if (($header['alg'] ?? null) !== 'RS256' || ! is_string($kid) || ! preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $kid)) {
                throw new GoogleAuthFailure('GOOGLE_IDENTITY_INVALID');
            }
            // This maintained cryptographic verifier enforces the declared algorithm.
            // Public keys come only from the pinned Google endpoint, never jku/x5u.
            $claims = JWT::decode($token, new Key($this->keys->key($kid), 'RS256'));

            return json_decode(json_encode($claims, JSON_THROW_ON_ERROR), true, 32, JSON_THROW_ON_ERROR);
        } catch (GoogleAuthFailure $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new GoogleAuthFailure('GOOGLE_IDENTITY_INVALID');
        }
    }

    public function userMetadata(string $uid): array
    {
        try {
            $user = $this->auth()->getUser($uid);
            $google = [];
            foreach ($user->providerData as $provider) {
                if ($provider->providerId === 'google.com') {
                    $google[] = $provider->uid;
                }
            }

            return ['uid' => $user->uid, 'disabled' => $user->disabled,
                'email_verified' => $user->emailVerified, 'google_uids' => $google,
                'valid_since' => $user->tokensValidAfterTime?->getTimestamp() ?? 0];
        } catch (GoogleAuthFailure $failure) {
            throw $failure;
        } catch (UserNotFound|UserDisabled) {
            throw new GoogleAuthFailure('GOOGLE_IDENTITY_INVALID');
        } catch (\Throwable) {
            throw new GoogleAuthFailure('AUTH_PROVIDER_UNAVAILABLE', 503);
        }
    }
}
