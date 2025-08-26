# Middleware & Global Protection

## Overview

The middleware system provides global form protection capabilities that can automatically protect all forms in a Laravel application without requiring individual form modifications. This system offers multiple layers of protection with configurable rules and exceptions.

## Core Middleware Classes

### GlobalFormSecurityMiddleware

Automatically protects all POST requests that contain form data.

```php
use JTD\FormSecurity\Middleware\GlobalFormSecurityMiddleware;

// In app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        \JTD\FormSecurity\Middleware\GlobalFormSecurityMiddleware::class,
    ],
];
```

### SpamProtectionMiddleware

Targeted protection for specific routes or route groups.

```php
// In routes/web.php
Route::middleware(['spam-protection'])->group(function () {
    Route::post('/contact', [ContactController::class, 'store']);
    Route::post('/register', [RegisterController::class, 'store']);
    Route::post('/comments', [CommentController::class, 'store']);
});
```

### UserRegistrationSecurityMiddleware

Specialized middleware for user registration endpoints with enhanced security.

```php
// Protect registration routes
Route::middleware(['registration-security'])->group(function () {
    Route::post('/register', [RegisterController::class, 'store']);
    Route::post('/api/register', [ApiRegisterController::class, 'store']);
});
```

## GlobalFormSecurityMiddleware Implementation

### Core Functionality

```php
class GlobalFormSecurityMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if not a form submission
        if (!$this->isFormSubmission($request)) {
            return $next($request);
        }
        
        // Skip if route is excluded
        if ($this->isExcludedRoute($request)) {
            return $next($request);
        }
        
        // Skip if user is whitelisted
        if ($this->isWhitelistedUser($request)) {
            return $next($request);
        }
        
        // Perform spam analysis
        $analysis = $this->analyzeFormSubmission($request);
        
        // Block if spam threshold exceeded
        if ($analysis['score'] >= $this->getBlockingThreshold($request)) {
            return $this->blockSubmission($request, $analysis);
        }
        
        // Flag for review if flag threshold exceeded
        if ($analysis['score'] >= $this->getFlaggingThreshold($request)) {
            $this->flagSubmission($request, $analysis);
        }
        
        return $next($request);
    }
}
```

### Form Detection Logic

```php
protected function isFormSubmission(Request $request): bool
{
    // Check HTTP method
    if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
        return false;
    }
    
    // Check content type
    $contentType = $request->header('Content-Type', '');
    if (str_contains($contentType, 'application/json')) {
        return $this->isJsonFormSubmission($request);
    }
    
    // Check for form fields
    return $this->hasFormFields($request);
}

protected function hasFormFields(Request $request): bool
{
    $formFields = ['name', 'email', 'message', 'content', 'comment', 'subject'];
    
    foreach ($formFields as $field) {
        if ($request->has($field)) {
            return true;
        }
    }
    
    return false;
}
```

## Configuration System

### Global Protection Settings

```php
// config/form-security.php
'global_protection' => [
    'enabled' => env('FORM_SECURITY_GLOBAL_ENABLED', false),
    'auto_detect_forms' => true,
    'block_threshold' => 85,
    'flag_threshold' => 65,
    'log_all_submissions' => false,
    'log_blocked_only' => true,
],
```

### Route Exclusions

```php
'excluded_routes' => [
    // Laravel default routes
    'login',
    'logout',
    'password.*',
    'verification.*',
    
    // API routes
    'api/auth/*',
    'api/oauth/*',
    
    // Admin routes
    'admin/*',
    'dashboard/*',
    
    // Custom exclusions
    'webhooks/*',
    'callbacks/*',
],
```

### User Whitelisting

```php
'whitelisted_users' => [
    'roles' => ['admin', 'moderator'],
    'permissions' => ['bypass-spam-protection'],
    'user_ids' => [], // Specific user IDs
    'email_domains' => ['@yourcompany.com'],
],
```

### IP Whitelisting

```php
'whitelisted_ips' => [
    '127.0.0.1',
    '::1',
    // Office IP ranges
    '192.168.1.0/24',
    '10.0.0.0/8',
    // Trusted service IPs
    env('TRUSTED_IP_RANGE'),
],
```

## Form Type Detection

### Automatic Form Type Detection

```php
protected function detectFormType(Request $request): string
{
    $route = $request->route();
    $routeName = $route ? $route->getName() : '';
    $uri = $request->getRequestUri();
    
    // Route-based detection
    if (str_contains($routeName, 'register')) {
        return 'user_registration';
    }
    
    if (str_contains($routeName, 'contact')) {
        return 'contact';
    }
    
    if (str_contains($routeName, 'comment')) {
        return 'comment';
    }
    
    // URI-based detection
    if (preg_match('/\/(register|signup|join)/', $uri)) {
        return 'user_registration';
    }
    
    if (preg_match('/\/(contact|support|inquiry)/', $uri)) {
        return 'contact';
    }
    
    // Field-based detection
    if ($request->has(['name', 'email', 'password'])) {
        return 'user_registration';
    }
    
    if ($request->has(['name', 'email', 'message'])) {
        return 'contact';
    }
    
    return 'generic';
}
```

### Custom Form Type Mapping

```php
'form_type_mapping' => [
    'routes' => [
        'newsletter.subscribe' => 'newsletter',
        'support.ticket' => 'support',
        'feedback.submit' => 'feedback',
    ],
    'uris' => [
        '/api/newsletter' => 'newsletter',
        '/support/new' => 'support',
        '/feedback' => 'feedback',
    ],
    'field_patterns' => [
        ['email'] => 'newsletter',
        ['name', 'email', 'phone'] => 'contact',
        ['title', 'description'] => 'support',
    ],
],
```

## Response Handling

### Blocking Responses

```php
protected function blockSubmission(Request $request, array $analysis): Response
{
    $this->logBlockedSubmission($request, $analysis);
    $this->fireBlockedEvent($request, $analysis);
    
    if ($request->expectsJson()) {
        return response()->json([
            'message' => 'Submission blocked due to spam detection.',
            'error_code' => 'SPAM_DETECTED',
            'score' => $analysis['score'],
        ], 422);
    }
    
    return back()
        ->withErrors(['form' => $this->getBlockingMessage($analysis)])
        ->withInput($this->getSafeInput($request));
}
```

### Custom Error Messages

```php
'blocking_messages' => [
    'user_registration' => 'Registration blocked due to suspicious activity. Please contact support if you believe this is an error.',
    'contact' => 'Your message was blocked due to spam detection. Please revise your content and try again.',
    'comment' => 'Your comment was blocked due to spam detection. Please revise and resubmit.',
    'generic' => 'Your submission was blocked due to spam detection. Please try again or contact support.',
],
```

## Advanced Features

### Rate Limiting Integration

```php
'rate_limiting' => [
    'enabled' => true,
    'max_attempts' => 5,
    'decay_minutes' => 60,
    'key_generator' => 'ip', // 'ip', 'user', 'session'
    'blocked_duration' => 1440, // minutes
],
```

### Honeypot Integration

```php
'honeypot' => [
    'enabled' => true,
    'field_name' => 'website', // Hidden field name
    'time_limit' => 3, // Minimum seconds to fill form
    'check_time' => true,
    'check_honeypot' => true,
],
```

### CAPTCHA Integration

```php
'captcha' => [
    'enabled' => false,
    'provider' => 'recaptcha', // 'recaptcha', 'hcaptcha', 'turnstile'
    'trigger_threshold' => 50, // Show CAPTCHA if score >= threshold
    'required_threshold' => 75, // Require CAPTCHA if score >= threshold
],
```

## Monitoring & Analytics

### Real-time Monitoring

```php
'monitoring' => [
    'enabled' => true,
    'alert_threshold' => 100, // Blocked submissions per hour
    'notification_channels' => ['slack', 'email'],
    'dashboard_enabled' => true,
    'metrics_retention_days' => 30,
],
```

### Performance Metrics

```php
class FormSecurityMetrics
{
    public function getBlockedSubmissionsCount(int $hours = 24): int;
    public function getSpamScoreDistribution(): array;
    public function getTopSpamIndicators(): array;
    public function getGeographicDistribution(): array;
    public function getFormTypeBreakdown(): array;
}
```

## Testing & Development

### Development Mode

```php
'development' => [
    'enabled' => env('APP_DEBUG', false),
    'bypass_protection' => true,
    'log_all_requests' => true,
    'show_debug_info' => true,
    'test_mode' => false,
],
```

### Testing Utilities

```php
class FormSecurityTestHelper
{
    public static function bypassProtection(): void;
    public static function enableProtection(): void;
    public static function simulateSpamSubmission(array $data): array;
    public static function getLastAnalysis(): ?array;
}
```

This middleware system provides comprehensive, configurable global protection while maintaining flexibility and performance.
