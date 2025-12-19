# OAuth2Canva

Laravel package for integrating OAuth 2.0 with Canva Connect API. This package provides full support for OAuth 2.0 Authorization Code flow with PKCE (SHA-256) according to Canva's official documentation.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mac/oauth2canva.svg?style=flat-square)](https://packagist.org/packages/mac/oauth2canva)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mac/oauth2canva/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mac/oauth2canva/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/mac/oauth2canva/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/mac/oauth2canva/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mac/oauth2canva.svg?style=flat-square)](https://packagist.org/packages/mac/oauth2canva)

## Features

- **OAuth 2.0 Authorization Code flow with PKCE** - Full OAuth 2.0 compliance with PKCE (SHA-256) according to Canva standards
- **Generate authorization URL** - Automatically generate authorization URL with PKCE parameters
- **Exchange authorization code** - Exchange authorization code for access token and refresh token
- **Refresh access token** - Automatically refresh token when expired
- **Introspect token** - Verify token validity on the server
- **Revoke token** - Revoke token when no longer needed
- **Helper methods** - Convenient methods for calling Canva API
- **CanvaToken Model** - Eloquent model for managing tokens with helper methods
- **Custom Exceptions** - Clear error handling with custom exceptions
- **Auto-refresh** - Automatically refresh token when about to expire

## Requirements

- PHP >= 8.2
- Laravel >= 11.0 or >= 12.0

## Installation

Install the package via Composer:

```bash
composer require mac/oauth2canva
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="oauth2canva-migrations"
php artisan migrate
```

Publish the config file:

```bash
php artisan vendor:publish --tag="oauth2canva-config"
```

Add the following environment variables to your `.env` file:

```env
CANVA_CLIENT_ID=your_client_id
CANVA_CLIENT_SECRET=your_client_secret
CANVA_REDIRECT_URI=https://your-app.com/canva/callback
CANVA_SCOPES=asset:read asset:write design:meta:read
```

The config file (`config/oauth2canva.php`) contents:

```php
return [
    'client_id' => env('CANVA_CLIENT_ID'),
    'client_secret' => env('CANVA_CLIENT_SECRET'),
    'redirect_uri' => env('CANVA_REDIRECT_URI'),
    'scopes' => env('CANVA_SCOPES', ''),
    'api_base_url' => env('CANVA_API_BASE_URL', 'https://api.canva.com'),
    'authorization_url' => env('CANVA_AUTHORIZATION_URL', 'https://www.canva.com/api/oauth/authorize'),
    'token_url' => env('CANVA_TOKEN_URL', 'https://api.canva.com/rest/v1/oauth/token'),
];
```

Optionally, you can publish the views using:

```bash
php artisan vendor:publish --tag="oauth2canva-views"
```

## Documentation

See [USAGE.md](USAGE.md) for detailed usage instructions with complete examples.

## Usage

### Step 1: Generate Authorization URL

```php
use Macoauth2canva\OAuth2Canva\Facades\OAuth2Canva;

// Generate authorization URL
$authData = OAuth2Canva::getAuthorizationUrl();

// Store code_verifier and state in session for later use
session([
    'canva_code_verifier' => $authData['code_verifier'],
    'canva_state' => $authData['state'],
]);

// Redirect user to authorization URL
return redirect($authData['url']);
```

### Step 2: Handle Callback

```php
use Macoauth2canva\OAuth2Canva\Facades\OAuth2Canva;
use Macoauth2canva\OAuth2Canva\Models\CanvaToken;

// In your callback route
public function handleCallback(Request $request)
{
    // Verify state to prevent CSRF attacks
    $state = $request->query('state');
    if ($state !== session('canva_state')) {
        abort(403, 'Invalid state parameter');
    }

    // Get authorization code
    $code = $request->query('code');
    $codeVerifier = session('canva_code_verifier');

    // Exchange code for access token
    $tokenData = OAuth2Canva::exchangeCodeForToken($code, $codeVerifier);

    // Save token to database
    CanvaToken::create([
        'user_id' => auth()->id(),
        'access_token' => $tokenData['access_token'],
        'refresh_token' => $tokenData['refresh_token'],
        'expires_at' => now()->addSeconds($tokenData['expires_in']),
        'scopes' => $request->query('scope'),
    ]);

    // Clear session data
    session()->forget(['canva_code_verifier', 'canva_state']);

    return redirect()->route('dashboard')->with('success', 'Successfully connected to Canva!');
}
```

### Step 3: Use Access Token to Call API

```php
use Macoauth2canva\OAuth2Canva\Facades\OAuth2Canva;
use Macoauth2canva\OAuth2Canva\Models\CanvaToken;

// Get user's token
$token = CanvaToken::forUser(auth()->id())->first();

// Automatically refresh if needed (less than 5 minutes remaining)
$accessToken = $token->getValidAccessToken();

// Call Canva API
$response = OAuth2Canva::makeApiRequest(
    'GET',
    '/rest/v1/users/me',
    $accessToken
);

$userData = $response->json();
```

### Additional Methods

```php
// Introspect token to check validity
$tokenInfo = OAuth2Canva::introspectToken($accessToken);
if ($tokenInfo['active']) {
    // Token is still active
}

// Revoke token
OAuth2Canva::revokeToken($accessToken);

// Or use model method
$token->revoke(); // Revoke and delete from database

// Check token validity
if ($token->isValid()) {
    // Token is still valid
}

if ($token->isActive()) {
    // Token is active on server
}

// Generate PKCE values (if you need to create them manually)
$codeVerifier = OAuth2Canva::generateCodeVerifier();
$codeChallenge = OAuth2Canva::generateCodeChallenge($codeVerifier);
$state = OAuth2Canva::generateState();
```

## API Reference

### OAuth2Canva Facade

- `getAuthorizationUrl(?string $codeVerifier, ?string $state, ?string $scopes, ?string $redirectUri)`: Generate authorization URL with PKCE
- `exchangeCodeForToken(string $authorizationCode, string $codeVerifier, ?string $redirectUri)`: Exchange code for token
- `refreshAccessToken(string $refreshToken)`: Refresh access token
- `introspectToken(string $token)`: Check token validity on server
- `revokeToken(string $token)`: Revoke token
- `makeApiRequest(string $method, string $endpoint, string $accessToken, array $data = [])`: Make Canva API request
- `generateCodeVerifier()`: Generate code verifier for PKCE
- `generateCodeChallenge(string $codeVerifier)`: Generate code challenge from code verifier
- `generateState()`: Generate state parameter for CSRF protection

### CanvaToken Model

- `isValid()`: Check if token is still valid (based on expires_at)
- `needsRefresh()`: Check if token needs to be refreshed
- `refreshIfNeeded()`: Automatically refresh if needed
- `getValidAccessToken()`: Get access token, automatically refresh if needed
- `revoke()`: Revoke token and delete from database
- `isActive()`: Check if token is active on server
- `scopeForUser($query, string $userId)`: Query scope
- `scopeValid($query)`: Query scope for valid tokens

## Testing

Run the test suite:

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Contributions are welcome from the community. Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

If you discover a security vulnerability, please send an email directly to the maintainer instead of using the issue tracker. All security vulnerabilities will be promptly addressed.

## Credits

- [keith-vo-macusa](https://github.com/keith.vo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.
