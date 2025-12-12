# This is my package oauth2canva

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mac/oauth2canva.svg?style=flat-square)](https://packagist.org/packages/mac/oauth2canva)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mac/oauth2canva/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mac/oauth2canva/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/mac/oauth2canva/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/mac/oauth2canva/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mac/oauth2canva.svg?style=flat-square)](https://packagist.org/packages/mac/oauth2canva)

Package Laravel để tích hợp OAuth2 với Canva Connect API. Package này hỗ trợ đầy đủ OAuth 2.0 Authorization Code flow với PKCE (SHA-256) theo [tài liệu chính thức của Canva](https://www.canva.dev/docs/connect/authentication/).

## Tính năng

- ✅ **OAuth 2.0 Authorization Code flow với PKCE** - Tuân thủ đầy đủ OAuth 2.0 với PKCE (SHA-256)
- ✅ **Generate authorization URL** - Tự động tạo authorization URL với PKCE parameters
- ✅ **Exchange authorization code** - Đổi authorization code để lấy access token và refresh token
- ✅ **Refresh access token** - Tự động refresh token khi hết hạn
- ✅ **Introspect token** - Kiểm tra tính hợp lệ của token trên server
- ✅ **Revoke token** - Hủy token khi không cần thiết
- ✅ **Helper methods** - Các method tiện ích để gọi Canva API
- ✅ **Model CanvaToken** - Model Eloquent để quản lý tokens với các helper methods
- ✅ **Custom Exceptions** - Xử lý lỗi rõ ràng với custom exceptions
- ✅ **Auto-refresh** - Tự động refresh token khi sắp hết hạn

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/OAuth2Canva.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/OAuth2Canva)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require mac/oauth2canva
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="oauth2canva-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="oauth2canva-config"
```

Thêm các biến môi trường vào file `.env`:

```env
CANVA_CLIENT_ID=your_client_id
CANVA_CLIENT_SECRET=your_client_secret
CANVA_REDIRECT_URI=https://your-app.com/canva/callback
CANVA_SCOPES=asset:read asset:write design:meta:read
```

Nội dung file config:

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

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="oauth2canva-views"
```

## Tài liệu chi tiết

Xem [USAGE.md](USAGE.md) để biết hướng dẫn sử dụng chi tiết với các ví dụ đầy đủ.

## Usage

### Bước 1: Tạo Authorization URL

```php
use Macoauth2canva\OAuth2Canva\Facades\OAuth2Canva;

// Tạo authorization URL
$authData = OAuth2Canva::getAuthorizationUrl();

// Lưu code_verifier và state vào session để sử dụng sau
session([
    'canva_code_verifier' => $authData['code_verifier'],
    'canva_state' => $authData['state'],
]);

// Redirect user đến authorization URL
return redirect($authData['url']);
```

### Bước 2: Xử lý Callback

```php
use Macoauth2canva\OAuth2Canva\Facades\OAuth2Canva;
use Macoauth2canva\OAuth2Canva\Models\CanvaToken;

// Trong route callback
public function handleCallback(Request $request)
{
    // Verify state để chống CSRF
    $state = $request->query('state');
    if ($state !== session('canva_state')) {
        abort(403, 'Invalid state parameter');
    }

    // Lấy authorization code
    $code = $request->query('code');
    $codeVerifier = session('canva_code_verifier');

    // Exchange code để lấy access token
    $tokenData = OAuth2Canva::exchangeCodeForToken($code, $codeVerifier);

    // Lưu token vào database
    CanvaToken::create([
        'user_id' => auth()->id(),
        'access_token' => $tokenData['access_token'],
        'refresh_token' => $tokenData['refresh_token'],
        'expires_at' => now()->addSeconds($tokenData['expires_in']),
        'scopes' => $request->query('scope'),
    ]);

    // Xóa session data
    session()->forget(['canva_code_verifier', 'canva_state']);

    return redirect()->route('dashboard')->with('success', 'Đã kết nối với Canva thành công!');
}
```

### Bước 3: Sử dụng Access Token để gọi API

```php
use Macoauth2canva\OAuth2Canva\Facades\OAuth2Canva;
use Macoauth2canva\OAuth2Canva\Models\CanvaToken;

// Lấy token của user
$token = CanvaToken::forUser(auth()->id())->first();

// Tự động refresh nếu cần (còn < 5 phút)
$accessToken = $token->getValidAccessToken();

// Gọi Canva API
$response = OAuth2Canva::makeApiRequest(
    'GET',
    '/rest/v1/users/me',
    $accessToken
);

$userData = $response->json();
```

### Các methods khác

```php
// Introspect token để kiểm tra validity
$tokenInfo = OAuth2Canva::introspectToken($accessToken);
if ($tokenInfo['active']) {
    // Token còn active
}

// Revoke token
OAuth2Canva::revokeToken($accessToken);

// Hoặc sử dụng model method
$token->revoke(); // Revoke và xóa khỏi database

// Kiểm tra token validity
if ($token->isValid()) {
    // Token còn hiệu lực
}

if ($token->isActive()) {
    // Token active trên server
}

// Generate PKCE values (nếu cần tự tạo)
$codeVerifier = OAuth2Canva::generateCodeVerifier();
$codeChallenge = OAuth2Canva::generateCodeChallenge($codeVerifier);
$state = OAuth2Canva::generateState();
```

## API Reference

### OAuth2Canva Facade

- `getAuthorizationUrl(?string $codeVerifier, ?string $state, ?string $scopes, ?string $redirectUri)`: Tạo authorization URL với PKCE
- `exchangeCodeForToken(string $authorizationCode, string $codeVerifier, ?string $redirectUri)`: Exchange code cho token
- `refreshAccessToken(string $refreshToken)`: Refresh access token
- `introspectToken(string $token)`: Kiểm tra token validity trên server
- `revokeToken(string $token)`: Revoke token
- `makeApiRequest(string $method, string $endpoint, string $accessToken, array $data = [])`: Gọi Canva API

### CanvaToken Model

- `isValid()`: Kiểm tra token có còn hiệu lực không (dựa trên expires_at)
- `needsRefresh()`: Kiểm tra token có cần refresh không
- `refreshIfNeeded()`: Tự động refresh nếu cần
- `getValidAccessToken()`: Lấy access token, tự động refresh nếu cần
- `revoke()`: Revoke token và xóa khỏi database
- `isActive()`: Kiểm tra token có active trên server không
- `scopeForUser($query, string $userId)`: Query scope
- `scopeValid($query)`: Query scope cho token còn hiệu lực

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [keith-vo-macusa](https://github.com/keith.vo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
