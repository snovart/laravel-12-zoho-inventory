<?php

namespace App\Services\Zoho;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * ZohoAuthService (extended with caching)
 *
 * Responsibility:
 *  - Owns OAuth for Zoho (Inventory in our case).
 *  - Provides short-lived access tokens via long-lived refresh token.
 *  - Implements caching, expiry tracking, retries and error mapping.
 *
 * Notes:
 *  - Configuration is injected via constructor (see config/zoho.php).
 *  - Now includes cache and rate-limit protection for refresh_token flow.
 */
class ZohoAuthService
{
    /** @var string */
    protected string $accountsUrl;

    /** @var string */
    protected string $clientId;

    /** @var string */
    protected string $clientSecret;

    /** @var string */
    protected string $refreshToken;

    /** @var string|null Cached access token (in-memory) */
    protected ?string $accessToken = null;

    /** @var int|null UNIX timestamp for access token expiry */
    protected ?int $expiresAt = null;

    /** @var string Cache key for shared token */
    protected string $cacheKeyToken = 'zoho.access_token';

    /** @var string Cache key for expiry */
    protected string $cacheKeyExp = 'zoho.access_token_expires_at';

    /**
     * @param array{
     *   accounts_url:string,
     *   client_id:string,
     *   client_secret:string,
     *   refresh_token:string
     * } $config
     */
    public function __construct(array $config)
    {
        $this->accountsUrl  = (string) ($config['accounts_url'] ?? '');
        $this->clientId     = (string) ($config['client_id'] ?? '');
        $this->clientSecret = (string) ($config['client_secret'] ?? '');
        $this->refreshToken = (string) ($config['refresh_token'] ?? '');
    }

    /**
     * Ensure a valid access token and return it.
     * Implementation will refresh if missing/expired.
     */
    public function getAccessToken(): string
    {
        // 1. Reuse in-memory token if still valid
        $now = time();
        if ($this->accessToken && $this->expiresAt && ($this->expiresAt - 60) > $now) {
            return $this->accessToken;
        }

        // 2. Try cache (shared between requests)
        $cachedToken = Cache::get($this->cacheKeyToken);
        $cachedExp   = (int) Cache::get($this->cacheKeyExp, 0);
        if ($cachedToken && $cachedExp > ($now + 60)) {
            $this->accessToken = $cachedToken;
            $this->expiresAt   = $cachedExp;
            return $cachedToken;
        }

        // 3. Refresh the token if no valid one found
        $this->refreshAccessToken();

        if (!$this->accessToken) {
            throw new RuntimeException('ZohoAuthService::getAccessToken() — access token is empty after refresh');
        }

        return $this->accessToken;
    }

    /**
     * Force refresh access token using refresh_token grant.
     * Should set $this->accessToken and $this->expiresAt.
     */
    public function refreshAccessToken(): void
    {
        // Pull timeout from config; default ~20s
        $timeoutMs = (int) config('zoho.timeout_ms', 20000);

        // Execute refresh_token grant against Zoho Accounts (EU)
        $url = rtrim($this->accountsUrl, '/') . '/oauth/v2/token';

        // Try up to 3 times with backoff if rate limited (429/5xx)
        $response = Http::asForm()
            ->timeout(max(1, $timeoutMs / 1000))
            ->retry(3, 300, fn($e) => in_array(optional($e->response())->status(), [429, 500, 502, 503, 504], true))
            ->post($url, [
                'refresh_token' => $this->refreshToken,
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type'    => 'refresh_token',
            ]);

        if (!$response->ok()) {
            $bodySample = substr($response->body() ?? '', 0, 400);
            // Handle rate limit error more clearly
            if (str_contains($bodySample, 'too many requests') || $response->status() === 429) {
                throw new RuntimeException('ZohoAuthService: rate limit on token refresh. Please retry in a few minutes.');
            }
            throw new RuntimeException('ZohoAuthService: refresh failed with HTTP ' . $response->status() . ' body=' . $bodySample);
        }

        $json = $response->json();

        // Extract access_token and expiry seconds (fallback to 3600s)
        $token   = (string) ($json['access_token'] ?? '');
        $expires = (int)    ($json['expires_in']   ?? ($json['expires_in_sec'] ?? 3600));

        if ($token === '') {
            throw new RuntimeException('ZohoAuthService: refresh succeeded but access_token missing');
        }

        // Cache token and compute absolute expiry with a small safety buffer
        $this->accessToken = $token;
        $this->expiresAt   = time() + max(60, $expires - 30);

        // Save to cache for cross-request reuse
        $ttl = $this->expiresAt - time();
        Cache::put($this->cacheKeyToken, $this->accessToken, $ttl);
        Cache::put($this->cacheKeyExp,   $this->expiresAt,   $ttl);

        Log::info('[ZohoAuthService] Refreshed access token', ['expires_in_sec' => $ttl]);
    }

    /** Optional helper for tests/healthchecks */
    public function getExpiry(): ?int
    {
        return $this->expiresAt ?? (int) Cache::get($this->cacheKeyExp, 0);
    }
}
