<?php

namespace Macoauth2canva\OAuth2Canva\Models;

use Illuminate\Database\Eloquent\Model;
use Macoauth2canva\OAuth2Canva\Exceptions\TokenRefreshException;
use Macoauth2canva\OAuth2Canva\OAuth2Canva;

class CanvaToken extends Model
{
    protected $table = 'oauth2canva_tokens';

    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'scopes',
        'state',
        'code_verifier',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Check if the token is still valid.
     */
    public function isValid(): bool
    {
        if (! $this->access_token) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if the token needs to be refreshed.
     */
    public function needsRefresh(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        // Refresh if less than 5 minutes left (using copy to avoid modifying the original value)
        return $this->expires_at->copy()->subMinutes(5)->isPast();
    }

    /**
     * Refresh the access token if needed.
     *
     * @return bool True if the refresh was successful, false if not needed
     *
     * @throws TokenRefreshException
     */
    public function refreshIfNeeded(): bool
    {
        if (! $this->needsRefresh() || ! $this->refresh_token) {
            return false;
        }

        $oauth2Canva = app(OAuth2Canva::class);
        $tokenData = $oauth2Canva->refreshAccessToken($this->refresh_token);

        $this->access_token = $tokenData['access_token'];
        $this->refresh_token = $tokenData['refresh_token'] ?? $this->refresh_token;
        $this->expires_at = now()->addSeconds($tokenData['expires_in']);

        if (! $this->save()) {
            throw new TokenRefreshException('Failed to save refreshed token to database');
        }

        return true;
    }

    /**
     * Get a valid access token, auto-refresh if necessary.
     *
     * @throws TokenRefreshException
     */
    public function getValidAccessToken(): ?string
    {
        if (! $this->isValid()) {
            // If the token is invalid, try to refresh it
            if (! $this->refreshIfNeeded()) {
                // If it cannot be refreshed (no refresh_token or not needed) and it's expired, return null
                return null;
            }
        }

        return $this->access_token;
    }

    /**
     * Revoke the token (remove it from Canva).
     *
     * @throws TokenRefreshException
     */
    public function revoke(): bool
    {
        $oauth2Canva = app(OAuth2Canva::class);

        if ($this->access_token) {
            $oauth2Canva->revokeToken($this->access_token);
        }

        if ($this->refresh_token) {
            $oauth2Canva->revokeToken($this->refresh_token);
        }

        return $this->delete();
    }

    /**
     * Check if the token is active using introspection.
     */
    public function isActive(): bool
    {
        if (! $this->access_token) {
            return false;
        }

        try {
            $oauth2Canva = app(OAuth2Canva::class);
            $result = $oauth2Canva->introspectToken($this->access_token);

            return $result['active'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Scope query: Find token by user_id.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope query: Find valid tokens.
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        })->whereNotNull('access_token');
    }
}
