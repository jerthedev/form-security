# User Registration Enhancement

## Overview

The User Registration Enhancement system provides specialized spam protection and security features specifically designed for user registration forms. This system adapts to different registration form structures and provides comprehensive protection against automated registration attacks.

## Core Features

### Adaptive Field Detection

The system automatically detects and analyzes registration forms regardless of field structure:

```php
class UserRegistrationAnalyzer
{
    /**
     * Detect registration fields from form data
     */
    public function detectRegistrationFields(array $formData): array
    {
        $detectedFields = [];
        
        // Name field detection
        $nameFields = ['name', 'username', 'display_name', 'full_name', 'first_name'];
        foreach ($nameFields as $field) {
            if (isset($formData[$field])) {
                $detectedFields['name'] = $formData[$field];
                break;
            }
        }
        
        // Email field detection
        $emailFields = ['email', 'email_address', 'user_email'];
        foreach ($emailFields as $field) {
            if (isset($formData[$field])) {
                $detectedFields['email'] = $formData[$field];
                break;
            }
        }
        
        // Password field detection (for analysis, not storage)
        $passwordFields = ['password', 'user_password', 'pass'];
        foreach ($passwordFields as $field) {
            if (isset($formData[$field])) {
                $detectedFields['password_provided'] = true;
                break;
            }
        }
        
        return $detectedFields;
    }
}
```

### Enhanced Registration Validation Rule

```php
use JTD\FormSecurity\Rules\UserRegistrationSpamRule;

class RegisterController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'email' => [
                'required',
                'email',
                'unique:users',
                new UserRegistrationSpamRule([
                    'check_ip_reputation' => true,
                    'check_geolocation' => true,
                    'block_temporary_emails' => true,
                    'max_registrations_per_ip' => 5,
                    'time_window_hours' => 24,
                    'enable_ai_analysis' => false,
                ])
            ],
            'password' => 'required|string|min:8|confirmed',
        ]);
        
        // ... rest of registration logic
    }
}
```

## Database Extensions

### User Table Migration

The package provides a migration to extend the users table with spam-related fields:

```php
// Migration: add_form_security_fields_to_users_table.php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        // Registration tracking
        $table->ipAddress('registration_ip')->nullable()->after('email_verified_at');
        $table->string('registration_country_code', 2)->nullable()->after('registration_ip');
        $table->string('registration_country_name')->nullable()->after('registration_country_code');
        $table->string('registration_region')->nullable()->after('registration_country_name');
        $table->string('registration_city')->nullable()->after('registration_region');
        $table->string('registration_isp')->nullable()->after('registration_city');
        
        // Spam scoring
        $table->unsignedTinyInteger('spam_score')->default(0)->after('registration_isp');
        $table->json('spam_indicators')->nullable()->after('spam_score');
        
        // AI analysis tracking
        $table->boolean('ai_analysis_pending')->default(false)->after('spam_indicators');
        $table->timestamp('ai_analysis_failed_at')->nullable()->after('ai_analysis_pending');
        $table->text('ai_analysis_error')->nullable()->after('ai_analysis_failed_at');
        
        // Blocking functionality
        $table->timestamp('blocked_at')->nullable()->after('ai_analysis_error');
        $table->string('blocked_reason')->nullable()->after('blocked_at');
        $table->unsignedBigInteger('blocked_by_user_id')->nullable()->after('blocked_reason');
        
        // Indexes for performance
        $table->index(['registration_ip', 'created_at']);
        $table->index('spam_score');
        $table->index('blocked_at');
        $table->index('ai_analysis_pending');
    });
}
```

### User Model Trait

```php
use JTD\FormSecurity\Traits\HasSpamProtection;

class User extends Authenticatable
{
    use HasSpamProtection;
    
    protected $fillable = [
        'name', 'email', 'password',
        'registration_ip', 'registration_country_code', 'registration_country_name',
        'registration_region', 'registration_city', 'registration_isp',
        'spam_score', 'spam_indicators',
        'ai_analysis_pending', 'ai_analysis_failed_at', 'ai_analysis_error',
        'blocked_at', 'blocked_reason', 'blocked_by_user_id',
    ];
    
    protected $casts = [
        'spam_indicators' => 'array',
        'ai_analysis_pending' => 'boolean',
        'ai_analysis_failed_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];
}
```

## HasSpamProtection Trait

### Core Methods

```php
trait HasSpamProtection
{
    /**
     * Check if user is likely spam
     */
    public function isLikelySpam(): bool
    {
        return $this->spam_score >= config('form-security.thresholds.user.flag', 70);
    }
    
    /**
     * Check if user should be blocked
     */
    public function shouldBeBlocked(): bool
    {
        return $this->spam_score >= config('form-security.thresholds.user.block', 90);
    }
    
    /**
     * Check if user is blocked
     */
    public function isBlocked(): bool
    {
        return !is_null($this->blocked_at);
    }
    
    /**
     * Block user with reason
     */
    public function blockUser(string $reason = 'High spam score', ?int $blockedByUserId = null): void
    {
        $this->update([
            'blocked_at' => now(),
            'blocked_reason' => $reason,
            'blocked_by_user_id' => $blockedByUserId,
            'remember_token' => null, // Force logout
        ]);
    }
    
    /**
     * Unblock user
     */
    public function unblockUser(): void
    {
        $this->update([
            'blocked_at' => null,
            'blocked_reason' => null,
            'blocked_by_user_id' => null,
        ]);
    }
    
    /**
     * Update spam score and indicators
     */
    public function updateSpamScore(int $score, array $indicators = []): void
    {
        $this->update([
            'spam_score' => min(100, max(0, $score)),
            'spam_indicators' => $indicators,
        ]);
    }
    
    /**
     * Mark for AI analysis retry
     */
    public function markForAiAnalysis(?string $error = null): void
    {
        $this->update([
            'ai_analysis_pending' => true,
            'ai_analysis_failed_at' => now(),
            'ai_analysis_error' => $error,
        ]);
    }
}
```

## Registration-Specific Analysis

### Enhanced Spam Detection

```php
class UserRegistrationSpamAnalyzer
{
    public function analyzeRegistration(array $userData, string $ip): array
    {
        $score = 0;
        $indicators = [];
        
        // Basic pattern analysis
        $nameAnalysis = $this->spamDetectionService->checkNamePatterns($userData['name']);
        $score += $nameAnalysis['score'];
        $indicators = array_merge($indicators, $nameAnalysis['indicators']);
        
        $emailAnalysis = $this->spamDetectionService->checkEmailPatterns($userData['email']);
        $score += $emailAnalysis['score'];
        $indicators = array_merge($indicators, $emailAnalysis['indicators']);
        
        // Registration-specific checks
        $ipAnalysis = $this->analyzeRegistrationIP($ip);
        $score += $ipAnalysis['score'];
        $indicators = array_merge($indicators, $ipAnalysis['indicators']);
        
        // Velocity checks
        $velocityAnalysis = $this->checkRegistrationVelocity($ip);
        $score += $velocityAnalysis['score'];
        $indicators = array_merge($indicators, $velocityAnalysis['indicators']);
        
        // Geolocation analysis
        $geoAnalysis = $this->analyzeGeolocation($ip);
        $score += $geoAnalysis['score'];
        $indicators = array_merge($indicators, $geoAnalysis['indicators']);
        
        return [
            'score' => min(100, $score),
            'indicators' => $indicators,
            'geolocation' => $geoAnalysis['geolocation'] ?? null,
        ];
    }
}
```

### Registration Velocity Checking

```php
protected function checkRegistrationVelocity(string $ip): array
{
    $timeWindow = config('form-security.registration.velocity_window_hours', 24);
    $maxRegistrations = config('form-security.registration.max_per_ip', 5);
    
    $recentRegistrations = User::where('registration_ip', $ip)
        ->where('created_at', '>=', now()->subHours($timeWindow))
        ->count();
    
    $score = 0;
    $indicators = [];
    
    if ($recentRegistrations >= $maxRegistrations) {
        $score += 50;
        $indicators[] = "Too many registrations from IP ({$recentRegistrations} in {$timeWindow}h)";
    } elseif ($recentRegistrations >= ($maxRegistrations * 0.7)) {
        $score += 25;
        $indicators[] = "High registration velocity from IP ({$recentRegistrations} in {$timeWindow}h)";
    }
    
    return ['score' => $score, 'indicators' => $indicators];
}
```

## Registration Middleware

### UserRegistrationSecurityMiddleware

```php
class UserRegistrationSecurityMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if not a registration request
        if (!$this->isRegistrationRequest($request)) {
            return $next($request);
        }
        
        // Check IP-based rate limiting
        if ($this->isRateLimited($request)) {
            return $this->rateLimitResponse($request);
        }
        
        // Check for blocked IPs
        if ($this->isBlockedIP($request->ip())) {
            return $this->blockedIPResponse($request);
        }
        
        // Pre-registration spam analysis
        $analysis = $this->preAnalyzeRegistration($request);
        
        if ($analysis['score'] >= config('form-security.thresholds.registration.block', 90)) {
            return $this->blockRegistrationResponse($request, $analysis);
        }
        
        // Add analysis data to request for use in controller
        $request->merge(['_spam_analysis' => $analysis]);
        
        return $next($request);
    }
}
```

## Configuration Options

### Registration-Specific Settings

```php
// config/form-security.php
'registration' => [
    'enabled' => true,
    'auto_populate_fields' => true,
    'block_temporary_emails' => true,
    'check_ip_reputation' => true,
    'check_geolocation' => true,
    'velocity_checking' => [
        'enabled' => true,
        'max_per_ip' => 5,
        'window_hours' => 24,
        'block_duration_hours' => 24,
    ],
    'ai_analysis' => [
        'enabled' => false,
        'trigger_threshold' => 50,
        'model' => 'grok-3-mini-fast',
    ],
    'notifications' => [
        'enabled' => true,
        'channels' => ['slack'],
        'threshold' => 80,
    ],
],
```

### Temporary Email Domains

```php
'temporary_email_domains' => [
    'tempmail.org',
    '10minutemail.com',
    'guerrillamail.com',
    'mailinator.com',
    'throwaway.email',
    'temp-mail.org',
    'yopmail.com',
    // ... extensive list
],
```

## Event System

### Registration Events

```php
// Events fired during registration analysis
Event::listen(UserRegistrationAnalyzed::class, function ($event) {
    if ($event->analysis['score'] >= 80) {
        // Send notification to administrators
        Notification::route('slack', config('form-security.slack_webhook'))
            ->notify(new HighRiskRegistrationNotification($event));
    }
});

Event::listen(UserRegistrationBlocked::class, function ($event) {
    // Log blocked registration attempt
    Log::warning('User registration blocked', [
        'ip' => $event->ip,
        'email' => $event->email,
        'score' => $event->score,
        'indicators' => $event->indicators,
    ]);
});
```

## Console Commands

### Registration Analysis Commands

```php
// Analyze existing users for spam patterns
php artisan form-security:analyze-users

// Backfill geolocation data for existing users
php artisan form-security:backfill-geolocation

// Clean up old blocked registration attempts
php artisan form-security:cleanup-blocked-registrations

// Generate registration spam report
php artisan form-security:registration-report --days=30
```

This registration enhancement system provides comprehensive protection while maintaining flexibility to work with any registration form structure.
