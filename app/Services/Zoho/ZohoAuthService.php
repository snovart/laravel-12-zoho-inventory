<?php

namespace App\Services\Zoho;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ZohoAuthService (skeleton)
 *
 * Responsibility:
 *  - Owns OAuth for Zoho (Inventory in our case).
 *  - Provides short-lived access tokens via long-lived refresh token.
 *  - Will later implement caching, expiry tracking, retries and error mapping.
 *
 * Notes:
 *  - Configuration is injected via constructor (see config/zoho.php).
 *  - No HTTP logic yet — to be implemented in the next micro step.
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

    /** @var string|null Cached access token */
    protected ?string $accessToken = null;

    /** @var int|null UNIX timestamp for access token expiry */
    protected ?int $expiresAt = null;

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
        // If we already have a token that won't expire within the next 60 seconds, reuse it.
        $now = time();
        if ($this->accessToken && $this->expiresAt && ($this->expiresAt - 60) > $now) {
            return $this->accessToken;
        }

        // Otherwise refresh it.
        $this->refreshAccessToken();

        if (!$this->accessToken) {
            throw new RuntimeException('Not implemented: ZohoAuthService::getAccessToken() — access token is empty after refresh');
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
        $response = Http::asForm()
            ->timeout(max(1, $timeoutMs / 1000))
            ->post(rtrim($this->accountsUrl, '/') . '/oauth/v2/token', [
                'refresh_token' => $this->refreshToken,
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type'    => 'refresh_token',
            ]);

        if (!$response->ok()) {
            // Never log secrets; trim body for safety
            $bodySample = substr($response->body() ?? '', 0, 400);
            throw new RuntimeException(
                'ZohoAuthService: refresh failed with HTTP '
                . $response->status() . ' body=' . $bodySample
            );
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
    }

    /** Optional helper for tests/healthchecks */
    public function getExpiry(): ?int
    {
        return $this->expiresAt;
    }
}
