# Hướng dẫn sử dụng OAuth2Canva Package

Package này giúp bạn tích hợp OAuth2 authentication với Canva API vào Laravel application.

## Cài đặt

1. Cài đặt package:
```bash
composer require mac/oauth2canva
```

2. Publish config file:
```bash
php artisan vendor:publish --tag="oauth2canva-config"
```

3. Chạy migration:
```bash
php artisan vendor:publish --tag="oauth2canva-migrations"
php artisan migrate
```

4. Cấu hình trong `.env`:
```env
CANVA_CLIENT_ID=your_client_id
CANVA_CLIENT_SECRET=your_client_secret
CANVA_REDIRECT_URI=https://your-app.com/canva/callback
CANVA_SCOPES=asset:read asset:write design:meta:read folder:read
```

## Cách sử dụng

### 1. Tạo Authorization URL

Để bắt đầu OAuth flow, bạn cần redirect user đến Canva authorization page:

```php
use Macoauth2canva\OAuth2Canva\Facades\OAuth2Canva;
use Macoauth2canva\OAuth2Canva\Models\CanvaToken;

// Tạo authorization URL
$authData = OAuth2Canva::getAuthorizationUrl();

// Lưu code_verifier và state vào session hoặc database để verify sau
session([
    'canva_code_verifier' => $authData['code_verifier'],
    'canva_state' => $authData['state'],
]);

// Redirect user đến authorization URL
return redirect($authData['url']);
```

### 2. Xử lý Callback và Lưu Token

Sau khi user authorize, Canva sẽ redirect về `redirect_uri` với `code` và `state`:

```php
use Macoauth2canva\OAuth2Canva\Facades\OAuth2Canva;
use Macoauth2canva\OAuth2Canva\Models\CanvaToken;
use Macoauth2canva\OAuth2Canva\Exceptions\TokenExchangeException;

public function handleCanvaCallback(Request $request)
{
    // Verify state để chống CSRF
    $state = session('canva_state');
    if ($request->get('state') !== $state) {
        abort(403, 'Invalid state parameter');
    }

    try {
        // Exchange authorization code cho access token
        $codeVerifier = session('canva_code_verifier');
        $tokenData = OAuth2Canva::exchangeCodeForToken(
            $request->get('code'),
            $codeVerifier
        );

        // Lưu token vào database
        CanvaToken::create([
            'user_id' => auth()->id(),
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'expires_at' => now()->addSeconds($tokenData['expires_in']),
            'scopes' => $tokenData['scopes'] ?? null,
        ]);

        // Xóa session data
        session()->forget(['canva_code_verifier', 'canva_state']);

        return redirect()->route('dashboard')->with('success', 'Connected to Canva successfully!');
    } catch (TokenExchangeException $e) {
        return redirect()->back()->with('error', 'Failed to connect to Canva: ' . $e->getMessage());
    }
}
```

### 3. Sử dụng Token để Gọi Canva API

```php
use Macoauth2canva\OAuth2Canva\Models\CanvaToken;
use Macoauth2canva\OAuth2Canva\Facades\OAuth2Canva;

// Lấy token của user
$token = CanvaToken::forUser(auth()->id())->first();

if ($token) {
    // Tự động refresh nếu cần
    $accessToken = $token->getValidAccessToken();

    // Gọi Canva API
    $response = OAuth2Canva::makeApiRequest(
        'GET',
        '/rest/v1/users/me',
        $accessToken
    );

    $userData = $response->json();
}
```

### 4. Refresh Token Tự Động

Model `CanvaToken` có method `refreshIfNeeded()` để tự động refresh token:

```php
$token = CanvaToken::forUser(auth()->id())->first();

// Tự động refresh nếu token sắp hết hạn (còn < 5 phút)
$token->refreshIfNeeded();

// Hoặc sử dụng getValidAccessToken() để tự động refresh
$accessToken = $token->getValidAccessToken();
```

### 5. Kiểm tra Token Validity

```php
$token = CanvaToken::forUser(auth()->id())->first();

// Kiểm tra token có còn hiệu lực không (dựa trên expires_at)
if ($token->isValid()) {
    // Token còn hiệu lực
}

// Kiểm tra token có active trên Canva server không
if ($token->isActive()) {
    // Token active trên server
}
```

### 6. Revoke Token

```php
$token = CanvaToken::forUser(auth()->id())->first();

// Revoke token và xóa khỏi database
$token->revoke();
```

### 7. Introspect Token

```php
use Macoauth2canva\OAuth2Canva\Facades\OAuth2Canva;

$result = OAuth2Canva::introspectToken($accessToken);

if ($result['active']) {
    // Token còn active
    $scopes = $result['scope'] ?? [];
    $expiresAt = $result['exp'] ?? null;
}
```

## Ví dụ Route

```php
// routes/web.php
Route::get('/canva/connect', function () {
    $authData = OAuth2Canva::getAuthorizationUrl();
    session([
        'canva_code_verifier' => $authData['code_verifier'],
        'canva_state' => $authData['state'],
    ]);
    return redirect($authData['url']);
})->name('canva.connect');

Route::get('/canva/callback', [CanvaController::class, 'handleCallback'])
    ->name('canva.callback');
```

## API Methods

### OAuth2Canva Facade

- `getAuthorizationUrl(?string $codeVerifier, ?string $state, ?string $scopes, ?string $redirectUri)`: Tạo authorization URL
- `exchangeCodeForToken(string $authorizationCode, string $codeVerifier, ?string $redirectUri)`: Exchange code cho token
- `refreshAccessToken(string $refreshToken)`: Refresh access token
- `introspectToken(string $token)`: Kiểm tra token validity
- `revokeToken(string $token)`: Revoke token
- `makeApiRequest(string $method, string $endpoint, string $accessToken, array $data = [])`: Gọi Canva API

### CanvaToken Model

- `isValid()`: Kiểm tra token có còn hiệu lực không
- `needsRefresh()`: Kiểm tra token có cần refresh không
- `refreshIfNeeded()`: Tự động refresh nếu cần
- `getValidAccessToken()`: Lấy access token, tự động refresh nếu cần
- `revoke()`: Revoke token và xóa khỏi database
- `isActive()`: Kiểm tra token có active trên server không
- `scopeForUser($query, string $userId)`: Query scope
- `scopeValid($query)`: Query scope cho token còn hiệu lực

## Xử lý Lỗi

Package cung cấp các custom exceptions:

- `OAuth2CanvaException`: Base exception
- `TokenExchangeException`: Lỗi khi exchange token
- `TokenRefreshException`: Lỗi khi refresh token

```php
use Macoauth2canva\OAuth2Canva\Exceptions\TokenExchangeException;
use Macoauth2canva\OAuth2Canva\Exceptions\TokenRefreshException;

try {
    $tokenData = OAuth2Canva::exchangeCodeForToken($code, $codeVerifier);
} catch (TokenExchangeException $e) {
    // Xử lý lỗi exchange token
    logger()->error('Token exchange failed', ['error' => $e->getMessage()]);
}
```

