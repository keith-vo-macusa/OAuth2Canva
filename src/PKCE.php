<?php

namespace Macoauth2canva\OAuth2Canva;

class PKCE
{
    /**
     * Generate code verifier (43-128 characters, URL-safe)
     */
    public static function generateCodeVerifier(): string
    {
        // Generate 96 random bytes and encode as base64url (43-128 chars)
        return bin2hex(random_bytes(48)); // 96 hex characters = 96 chars
    }

    /**
     * Generate code challenge from code verifier using SHA-256
     */
    public static function generateCodeChallenge(string $codeVerifier): string
    {
        // SHA-256 hash the code verifier and encode as base64url
        return self::base64UrlEncode(
            hash('sha256', $codeVerifier, true)
        );
    }

    /**
     * Generate state parameter for CSRF protection
     */
    public static function generateState(): string
    {
        return bin2hex(random_bytes(48)); // 96 hex characters
    }

    /**
     * Base64 URL-safe encode (without padding)
     */
    protected static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
