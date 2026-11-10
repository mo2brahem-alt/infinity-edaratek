# Phase 2 native authentication design and source review

Date: 2026-11-08 (Africa/Cairo). Source reviewed and executed: `e448618bffe70e10352b52bf20d3ffc6f43b14f5`.

## Actual framework surface

- Installed `laravel/sanctum` is 4.3.0. `HasApiTokens::createToken` stores a SHA-256 token hash and accepts a per-token `expiresAt`; `PersonalAccessToken::findToken` validates the `id|plaintext` form.
- Sanctum's stock guard can check configured session guards before Bearer credentials. Native routes therefore use `NativeSanctumAuthenticator` and `AuthenticateNativeBearer`: only an exact `Authorization: Bearer <id>|<plaintext>` header is considered. Cookie, query parameter, transient token, and stock-guard fallback are not consulted.
- The native class is deliberately narrow: exact `native:access` ability, canonical `native:<uuid>:<label>` name, user-owned token, and non-null `expires_at` strictly later than the current clock. A token is reloaded from the standard `personal_access_tokens` table and the user, roles, permissions, and school relation are reloaded for every request.
- `config/sanctum.php` keeps global Sanctum expiration nullable for other consumers and adds a separate finite native value. `config/mobile.php` defaults it to 1440 minutes. There is no refresh token or sliding expiration.

## Account and compatibility design

`AccountAccessEvaluator` uses the union of all current Spatie roles and the legacy `users.role`; it never uses `primaryRole`. Any `school_manager` or `supervisor` requires account-wide approved status. Pending and rejected take precedence over inactive. The request-local native user resolver never calls `Auth`, so the configured web/session guard and CSRF flow are not mutated.

The mounted route set is only `/api/v1/auth/login`, `/api/v1/me`, `/api/v1/auth/logout`, and `/api/v1/auth/devices`. Existing `routes/web.php`, `/api/system`, `/api/school`, browser session behavior, and CSRF routes remain in place.

## Executed proof

`tests/Feature/Mobile/NativeAuthenticationTest.php`, `NativeAccountStateTest.php`, `NativeDeviceLifecycleTest.php`, and `NativeWebCompatibilityTest.php` executed 16/16 in guarded MySQL CI run 42486583770. They cover real hashed PAT issuance, exact expiry with a fake clock, cookie/query/conflict rejection, status changes, deletion, owner/device revocation, nonnative preservation, rate limit recovery, and real web-session compatibility.
