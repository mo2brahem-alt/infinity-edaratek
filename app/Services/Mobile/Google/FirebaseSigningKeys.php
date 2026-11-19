<?php

namespace App\Services\Mobile\Google;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class FirebaseSigningKeys
{
    private const CACHE = 'firebase-google:public-signing-keys:v1';

    private const URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    public function key(string $kid): string
    {
        try {
            $keys = Cache::get(self::CACHE);
            if (is_array($keys) && isset($keys[$kid])) {
                return $keys[$kid];
            }

            return Cache::lock(self::CACHE.':lock', 10)->block(2, function () use ($kid): string {
                $keys = Cache::get(self::CACHE);
                if (is_array($keys) && isset($keys[$kid])) {
                    return $keys[$kid];
                }
                // Unknown key IDs cannot force one Google fetch per attacker request.
                if (! Cache::add(self::CACHE.':refresh', true, 60)) {
                    throw new GoogleAuthFailure(is_array($keys) ? 'GOOGLE_IDENTITY_INVALID' : 'AUTH_PROVIDER_UNAVAILABLE', is_array($keys) ? 401 : 503);
                }
                $response = Http::connectTimeout(3)->timeout(8)->withOptions(['allow_redirects' => false])->get(self::URL);
                $keys = $response->json();
                if (! $response->ok() || ! is_array($keys) || $keys === [] || count($keys) > 20) {
                    throw new GoogleAuthFailure('AUTH_PROVIDER_UNAVAILABLE', 503);
                }
                foreach ($keys as $id => $pem) {
                    if (! is_string($id) || ! is_string($pem) || strlen($pem) > 8192 || openssl_pkey_get_public($pem) === false) {
                        throw new GoogleAuthFailure('AUTH_PROVIDER_UNAVAILABLE', 503);
                    }
                }
                preg_match('/(?:^|[,\s])max-age=(\d+)/i', $response->header('Cache-Control'), $age);
                $ttl = isset($age[1]) ? max(1, min(86400, (int) $age[1])) : 60;
                Cache::put(self::CACHE, $keys, $ttl);
                if (! isset($keys[$kid])) {
                    throw new GoogleAuthFailure('GOOGLE_IDENTITY_INVALID');
                }

                return $keys[$kid];
            });
        } catch (GoogleAuthFailure $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new GoogleAuthFailure('AUTH_PROVIDER_UNAVAILABLE', 503);
        }
    }
}
