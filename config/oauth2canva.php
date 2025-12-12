<?php

// config for Macoauth2canva/OAuth2Canva
return [
    /*
     * Client ID từ Canva Developer Portal
     */
    'client_id' => env('CANVA_CLIENT_ID'),

    /*
     * Client Secret từ Canva Developer Portal
     */
    'client_secret' => env('CANVA_CLIENT_SECRET'),

    /*
     * Redirect URI sau khi user authorize
     */
    'redirect_uri' => env('CANVA_REDIRECT_URI'),

    /*
     * Scopes được yêu cầu (space-separated)
     * Ví dụ: 'asset:read asset:write design:meta:read'
     */
    'scopes' => env('CANVA_SCOPES', ''),

    /*
     * Canva API base URL
     */
    'api_base_url' => env('CANVA_API_BASE_URL', 'https://api.canva.com'),

    /*
     * Canva OAuth authorization URL
     */
    'authorization_url' => env('CANVA_AUTHORIZATION_URL', 'https://www.canva.com/api/oauth/authorize'),

    /*
     * Canva OAuth token URL
     */
    'token_url' => env('CANVA_TOKEN_URL', 'https://api.canva.com/rest/v1/oauth/token'),
];
