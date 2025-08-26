# Installation & Integration Guide

## Installation

### Composer Installation

```bash
composer require jerthedev/jtd-form-security
```

### Service Provider Registration

The package uses Laravel's auto-discovery feature, but you can manually register it if needed:

```php
// config/app.php
'providers' => [
    // ...
    JTD\FormSecurity\FormSecurityServiceProvider::class,
],

'aliases' => [
    // ...
    'FormSecurity' => JTD\FormSecurity\Facades\FormSecurity::class,
],
```

### Configuration Publishing

```bash
# Publish configuration files
php artisan vendor:publish --provider="JTD\FormSecurity\FormSecurityServiceProvider" --tag="config"

# Publish database migrations
php artisan vendor:publish --provider="JTD\FormSecurity\FormSecurityServiceProvider" --tag="migrations"

# Publish all package assets
php artisan vendor:publish --provider="JTD\FormSecurity\FormSecurityServiceProvider"
```

### Database Migration

```bash
# Run the migrations
php artisan migrate

# Seed spam patterns (optional)
php artisan db:seed --class="JTD\FormSecurity\Database\Seeders\SpamPatternsSeeder"
```

## Basic Integration

### Step 1: Environment Configuration

Add the following to your `.env` file:

```bash
# Basic Configuration
FORM_SECURITY_ENABLED=true
FORM_SECURITY_USER_BLOCK_THRESHOLD=90
FORM_SECURITY_CONTACT_BLOCK_THRESHOLD=85

# Optional: AI Analysis
FORM_SECURITY_AI_ENABLED=false
FORM_SECURITY_AI_API_KEY=your_ai_api_key_here

# Optional: IP Reputation
ABUSEIPDB_API_KEY=your_abuseipdb_api_key_here

# Optional: Notifications
FORM_SECURITY_SLACK_WEBHOOK=your_slack_webhook_url_here
```

### Step 2: Add Validation Rules to Forms

#### Contact Form Example

```php
use JTD\FormSecurity\Rules\SpamValidationRule;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'email' => 'required|email',
            'message' => [
                'required',
                'string',
                'max:2000',
                new SpamValidationRule('contact', ['name', 'email', 'message'])
            ]
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Process the form...
        ContactSubmission::create($request->validated());
        
        return redirect()->back()->with('success', 'Message sent successfully!');
    }
}
```

#### User Registration Example

```php
use JTD\FormSecurity\Rules\UserRegistrationSpamRule;
use JTD\FormSecurity\Traits\HasSpamProtection;

// Add trait to User model
class User extends Authenticatable
{
    use HasSpamProtection;
    
    protected $fillable = [
        'name', 'email', 'password',
        'registration_ip', 'registration_country_code', 'registration_country_name',
        'registration_region', 'registration_city', 'registration_isp',
        'spam_score', 'spam_indicators',
    ];
}

// Update registration controller
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
                new UserRegistrationSpamRule()
            ],
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Create user with spam protection data
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'registration_ip' => $request->ip(),
            // Additional fields will be populated automatically
        ]);

        return redirect()->route('home');
    }
}
```

## Advanced Integration

### Global Middleware Protection

Enable global protection for all forms:

```php
// config/form-security.php
'global_protection' => [
    'enabled' => true,
    'auto_detect_forms' => true,
    'block_threshold' => 85,
],

// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        \JTD\FormSecurity\Middleware\GlobalFormSecurityMiddleware::class,
    ],
];
```

### Route-Specific Protection

```php
// routes/web.php
use JTD\FormSecurity\Middleware\SpamProtectionMiddleware;

Route::middleware(['spam-protection'])->group(function () {
    Route::post('/contact', [ContactController::class, 'store']);
    Route::post('/newsletter', [NewsletterController::class, 'subscribe']);
    Route::post('/feedback', [FeedbackController::class, 'store']);
});

// For registration routes
Route::middleware(['registration-security'])->group(function () {
    Route::post('/register', [RegisterController::class, 'store']);
});
```

### Form Request Integration

```php
use JTD\FormSecurity\Rules\SpamValidationRule;

class ContactFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:50',
            'email' => 'required|email',
            'subject' => 'required|string|max:100',
            'message' => [
                'required',
                'string',
                'min:10',
                'max:2000',
                new SpamValidationRule('contact', ['name', 'email', 'message'])
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'message.spam_validation' => 'Your message appears to be spam. Please revise and try again.'
        ];
    }
}
```

### Livewire Integration

```php
use JTD\FormSecurity\Rules\SpamValidationRule;
use Livewire\Component;

class ContactForm extends Component
{
    public $name = '';
    public $email = '';
    public $message = '';

    protected function rules()
    {
        return [
            'name' => 'required|string|max:50',
            'email' => 'required|email',
            'message' => [
                'required',
                'string',
                'max:2000',
                new SpamValidationRule('contact', ['name', 'email', 'message'])
            ]
        ];
    }

    public function submit()
    {
        $this->validate();

        ContactSubmission::create([
            'name' => $this->name,
            'email' => $this->email,
            'message' => $this->message,
            'ip_address' => request()->ip(),
        ]);

        $this->reset();
        session()->flash('message', 'Message sent successfully!');
    }

    public function render()
    {
        return view('livewire.contact-form');
    }
}
```

## External Service Setup

### AbuseIPDB Integration

1. Sign up at [AbuseIPDB](https://www.abuseipdb.com/)
2. Get your API key from the dashboard
3. Add to your `.env` file:

```bash
ABUSEIPDB_API_KEY=your_api_key_here
FORM_SECURITY_IP_REPUTATION_ENABLED=true
```

### GeoLite2 Database Setup

1. Download GeoLite2 database from MaxMind
2. Extract to your storage directory (e.g., `storage/app/GeoLite2-City-CSV_20250722`)
3. Configure the path:

```bash
FORM_SECURITY_GEOLITE2_PATH=/path/to/geolite2/database
FORM_SECURITY_GEOLOCATION_ENABLED=true
```

4. Import the database using the memory-efficient chunked importer:

```bash
# Import in chunks (recommended for large datasets)
php artisan geolite2:import-chunked --limit=100000 --batch-size=1000

# Resume import from a specific point if needed
php artisan geolite2:import-chunked --skip=100000 --limit=100000

# Import locations data
php artisan form-security:import-geolite2-locations

# Verify import
php artisan form-security:verify-geolite2
```

The chunked importer is designed for memory efficiency and can handle the large GeoLite2 datasets without running out of memory. It supports resumable imports and provides detailed progress information.

### AI Service Integration

#### xAI (Grok) Setup

```bash
FORM_SECURITY_AI_ENABLED=true
FORM_SECURITY_AI_PROVIDER=xai
FORM_SECURITY_AI_MODEL=grok-3-mini-fast
FORM_SECURITY_AI_API_KEY=your_xai_api_key_here
FORM_SECURITY_AI_API_URL=https://api.x.ai/v1
```

#### OpenAI Setup

```bash
FORM_SECURITY_AI_ENABLED=true
FORM_SECURITY_AI_PROVIDER=openai
FORM_SECURITY_AI_MODEL=gpt-3.5-turbo
FORM_SECURITY_AI_API_KEY=your_openai_api_key_here
```

## Monitoring & Notifications

### Slack Notifications

1. Create a Slack webhook URL
2. Configure in your `.env`:

```bash
FORM_SECURITY_SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
FORM_SECURITY_SLACK_CHANNEL=#security
FORM_SECURITY_MONITORING_ENABLED=true
```

### Dashboard Access

The package includes a monitoring dashboard accessible at `/form-security/dashboard` (configurable).

## Console Commands

### Available Commands

```bash
# Install and setup the package
php artisan form-security:install

# Test spam detection
php artisan form-security:test-detection

# Analyze IP reputation
php artisan form-security:analyze-ip 192.168.1.1

# Update spam patterns
php artisan form-security:update-patterns

# Generate analytics report
php artisan form-security:report --days=30

# Cleanup old data
php artisan form-security:cleanup --days=90

# GeoLite2 Database Management
php artisan geolite2:import-chunked --limit=100000 --batch-size=1000
php artisan geolite2:import-chunked --skip=100000 --limit=100000  # Resume import
php artisan form-security:import-geolite2-locations
php artisan form-security:verify-geolite2

# Refresh IP reputation cache
php artisan form-security:refresh-ip-cache
```

## Testing

### Feature Testing

```php
use JTD\FormSecurity\Testing\FormSecurityTestHelper;

class ContactFormTest extends TestCase
{
    public function test_contact_form_blocks_spam()
    {
        $spamData = [
            'name' => 'FREE MONEY WIN NOW',
            'email' => 'spam@tempmail.org',
            'message' => 'BUY NOW! CHEAP DEALS! CLICK HERE!'
        ];

        $response = $this->post('/contact', $spamData);

        $response->assertSessionHasErrors();
        $this->assertStringContains('spam', session('errors')->first('message'));
    }

    public function test_legitimate_contact_form_passes()
    {
        FormSecurityTestHelper::bypassProtection();

        $legitimateData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => 'I would like to inquire about your services.'
        ];

        $response = $this->post('/contact', $legitimateData);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }
}
```

## Troubleshooting

### Common Issues

1. **High False Positives**: Lower the spam thresholds in configuration
2. **AI Analysis Failing**: Check API keys and network connectivity
3. **Performance Issues**: Enable caching and optimize database indexes
4. **Missing Geolocation Data**: Ensure GeoLite2 database is properly imported

### Debug Mode

Enable debug mode for detailed logging:

```bash
FORM_SECURITY_DEBUG=true
FORM_SECURITY_LOG_ALL_REQUESTS=true
```

This comprehensive integration guide covers all aspects of installing and configuring the JTD-FormSecurity package for various use cases and deployment scenarios.
