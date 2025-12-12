<?php

namespace Macoauth2canva\OAuth2Canva\Models;

use Illuminate\Database\Eloquent\Model;
use Macoauth2canva\OAuth2Canva\OAuth2Canva;
use Macoauth2canva\OAuth2Canva\Exceptions\TokenRefreshException;

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
     * Kiểm tra xem token có còn hiệu lực không
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
     * Kiểm tra xem token có cần refresh không
     */
    public function needsRefresh(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        // Refresh nếu còn ít hơn 5 phút
        return $this->expires_at->subMinutes(5)->isPast();
    }

    /**
     * Refresh access token nếu cần thiết
     *
     * @return bool True nếu đã refresh thành công, false nếu không cần refresh
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
        $this->save();

        return true;
    }

    /**
     * Lấy access token, tự động refresh nếu cần
     *
     * @return string|null
     *
     * @throws TokenRefreshException
     */
    public function getValidAccessToken(): ?string
    {
        if (! $this->isValid()) {
            $this->refreshIfNeeded();
        }

        return $this->access_token;
    }

    /**
     * Revoke token (xóa token khỏi Canva)
     *
     * @return bool
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
     * Kiểm tra token có active không bằng cách introspect
     *
     * @return bool
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
     * Scope query: Tìm token theo user_id
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope query: Tìm token còn hiệu lực
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        })->whereNotNull('access_token');
    }
}

