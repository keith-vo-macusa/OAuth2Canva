<?php

namespace Macoauth2canva\OAuth2Canva;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Macoauth2canva\OAuth2Canva\Exceptions\TokenExchangeException;
use Macoauth2canva\OAuth2Canva\Exceptions\TokenRefreshException;

class OAuth2Canva
{
    protected string $clientId;

    protected string $clientSecret;

    protected string $redirectUri;

    protected string $scopes;

    protected string $apiBaseUrl;

    protected string $authorizationUrl;

    protected string $tokenUrl;

    public function __construct()
    {
        $this->clientId = Config::get('oauth2canva.client_id');
        $this->clientSecret = Config::get('oauth2canva.client_secret');
        $this->redirectUri = Config::get('oauth2canva.redirect_uri');
        $this->scopes = Config::get('oauth2canva.scopes', '');
        $this->apiBaseUrl = Config::get('oauth2canva.api_base_url', 'https://api.canva.com');
        $this->authorizationUrl = Config::get('oauth2canva.authorization_url', 'https://www.canva.com/api/oauth/authorize');
        $this->tokenUrl = Config::get('oauth2canva.token_url', 'https://api.canva.com/rest/v1/oauth/token');
    }

    /**
     * Generate a cryptographically random code verifier for PKCE
     * Must be between 43 and 128 characters long
     */
    public function generateCodeVerifier(): string
    {
        return PKCE::generateCodeVerifier();
    }

    /**
     * Generate code challenge from code verifier using SHA-256
     */
    public function generateCodeChallenge(string $codeVerifier): string
    {
        return PKCE::generateCodeChallenge($codeVerifier);
    }

    /**
     * Generate a high-entropy random state string for CSRF protection
     */
    public function generateState(): string
    {
        return PKCE::generateState();
    }

    /**
     * Get the authorization URL for OAuth2 flow
     *
     * @param  string|null  $codeVerifier  If not provided, will be generated automatically
     * @param  string|null  $state  If not provided, will be generated automatically
     * @param  string|null  $scopes  If not provided, will use config scopes
     * @param  string|null  $redirectUri  If not provided, will use config redirect_uri
     * @return array{url: string, code_verifier: string, state: string}
     */
    public function getAuthorizationUrl(
        ?string $codeVerifier = null,
        ?string $state = null,
        ?string $scopes = null,
        ?string $redirectUri = null
    ): array {
        $codeVerifier = $codeVerifier ?? $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);
        $state = $state ?? $this->generateState();
        $scopes = $scopes ?? $this->scopes;
        $redirectUri = $redirectUri ?? $this->redirectUri;

        $params = [
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'scope' => $scopes,
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'state' => $state,
            'redirect_uri' => $redirectUri,
        ];

        $url = $this->authorizationUrl.'?'.http_build_query($params);

        return [
            'url' => $url,
            'code_verifier' => $codeVerifier,
            'state' => $state,
        ];
    }

    /**
     * Exchange authorization code for access token
     *
     * @param  string  $authorizationCode  The authorization code received from Canva
     * @param  string  $codeVerifier  The code verifier used in authorization URL
     * @param  string|null  $redirectUri  The redirect URI used in authorization URL
     * @return array{access_token: string, refresh_token: string|null, expires_in: int, token_type: string}
     *
     * @throws \Exception
     */
    public function exchangeCodeForToken(
        string $authorizationCode,
        string $codeVerifier,
        ?string $redirectUri = null
    ): array {
        $redirectUri = $redirectUri ?? $this->redirectUri;

        $credentials = base64_encode($this->clientId.':'.$this->clientSecret);

        $response = Http::withHeaders([
            'Authorization' => "Basic {$credentials}",
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post($this->tokenUrl, [
            'grant_type' => 'authorization_code',
            'code' => $authorizationCode,
            'code_verifier' => $codeVerifier,
            'redirect_uri' => $redirectUri,
        ]);

        if (! $response->successful()) {
            Log::error('Canva OAuth2 token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new TokenExchangeException('Failed to exchange authorization code for token: '.$response->body());
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => $data['expires_in'] ?? 3600,
            'token_type' => $data['token_type'] ?? 'Bearer',
        ];
    }

    /**
     * Refresh access token using refresh token
     *
     * @param  string  $refreshToken  The refresh token from previous token request
     * @return array{access_token: string, refresh_token: string|null, expires_in: int, token_type: string}
     *
     * @throws \Exception
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $credentials = base64_encode($this->clientId.':'.$this->clientSecret);

        $response = Http::withHeaders([
            'Authorization' => "Basic {$credentials}",
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post($this->tokenUrl, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        if (! $response->successful()) {
            Log::error('Canva OAuth2 token refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new TokenRefreshException('Failed to refresh access token: '.$response->body());
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => $data['expires_in'] ?? 3600,
            'token_type' => $data['token_type'] ?? 'Bearer',
        ];
    }

    /**
     * Introspect an access token or refresh token to check validity
     *
     * @param  string  $token  The access token or refresh token to introspect
     * @return array{active: bool, ...}
     *
     * @throws \Exception
     */
    public function introspectToken(string $token): array
    {
        $credentials = base64_encode($this->clientId.':'.$this->clientSecret);

        $response = Http::withHeaders([
            'Authorization' => "Basic {$credentials}",
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post("{$this->apiBaseUrl}/rest/v1/oauth/introspect", [
            'token' => $token,
        ]);

        if (! $response->successful()) {
            Log::error('Canva OAuth2 token introspection failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Failed to introspect token: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Revoke an access token or refresh token
     *
     * @param  string  $token  The access token or refresh token to revoke
     * @return bool
     *
     * @throws \Exception
     */
    public function revokeToken(string $token): bool
    {
        $credentials = base64_encode($this->clientId.':'.$this->clientSecret);

        $response = Http::withHeaders([
            'Authorization' => "Basic {$credentials}",
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post("{$this->apiBaseUrl}/rest/v1/oauth/revoke", [
            'token' => $token,
        ]);

        if (! $response->successful()) {
            Log::error('Canva OAuth2 token revocation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Failed to revoke token: '.$response->body());
        }

        return true;
    }

    /**
     * Make an authenticated API request to Canva
     *
     * @param  string  $method  HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param  string  $endpoint  API endpoint (relative to api_base_url)
     * @param  string  $accessToken  Access token for authentication
     * @param  array  $data  Request data (for POST/PUT requests)
     * @return \Illuminate\Http\Client\Response
     */
    public function makeApiRequest(
        string $method,
        string $endpoint,
        string $accessToken,
        array $data = []
    ) {
        $url = Str::startsWith($endpoint, 'http') ? $endpoint : "{$this->apiBaseUrl}/{$endpoint}";

        $request = Http::withToken($accessToken);

        return match (strtoupper($method)) {
            'GET' => $request->get($url, $data),
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'PATCH' => $request->patch($url, $data),
            'DELETE' => $request->delete($url, $data),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }
}
