# Form Validation System

## Overview

The form validation system provides Laravel validation rules that can be easily integrated into any form to provide spam protection. The system is designed to be flexible, configurable, and adaptable to different form types and requirements.

## Core Validation Rules

### SpamValidationRule (Universal)

The primary validation rule that can be applied to any form field to trigger spam analysis.

```php
use JTD\FormSecurity\Rules\SpamValidationRule;

// Basic usage
$validator = Validator::make($data, [
    'message' => [
        'required',
        'string',
        'max:2000',
        new SpamValidationRule()
    ]
]);

// Advanced usage with configuration
$validator = Validator::make($data, [
    'message' => [
        'required',
        'string',
        'max:2000',
        new SpamValidationRule(
            formType: 'contact',
            requiredFields: ['name', 'email', 'message'],
            enableAi: false,
            customThresholdKey: 'custom.spam.threshold'
        )
    ]
]);
```

### Constructor Parameters

```php
public function __construct(
    string $formType = 'generic',           // Form type for specialized analysis
    array $requiredFields = ['name', 'email'], // Fields required for analysis
    bool $enableAi = false,                 // Enable AI analysis
    ?string $customThresholdKey = null,     // Custom config key for threshold
    ?array $customConfig = null             // Override default configuration
)
```

### Form Type Specializations

#### User Registration Forms
```php
new SpamValidationRule(
    formType: 'user_registration',
    requiredFields: ['name', 'email'],
    enableAi: config('form-security.ai_analysis.registration_enabled', false)
)
```

#### Contact Forms
```php
new SpamValidationRule(
    formType: 'contact',
    requiredFields: ['name', 'email', 'message'],
    enableAi: config('form-security.ai_analysis.contact_enabled', false)
)
```

#### Comment Forms
```php
new SpamValidationRule(
    formType: 'comment',
    requiredFields: ['content'],
    enableAi: config('form-security.ai_analysis.comment_enabled', true)
)
```

#### Newsletter Signup
```php
new SpamValidationRule(
    formType: 'newsletter',
    requiredFields: ['email'],
    enableAi: false // Usually not needed for simple email collection
)
```

## Specialized Validation Rules

### UserRegistrationSpamRule

Enhanced validation specifically designed for user registration forms with additional checks.

```php
use JTD\FormSecurity\Rules\UserRegistrationSpamRule;

$validator = Validator::make($data, [
    'email' => [
        'required',
        'email',
        'unique:users',
        new UserRegistrationSpamRule([
            'check_ip_reputation' => true,
            'check_geolocation' => true,
            'enable_ai_analysis' => false,
            'block_temporary_emails' => true,
            'max_registrations_per_ip' => 5,
            'time_window_hours' => 24,
        ])
    ]
]);
```

### ContactFormSpamRule

Specialized rule for contact forms with message content analysis.

```php
use JTD\FormSecurity\Rules\ContactFormSpamRule;

$validator = Validator::make($data, [
    'message' => [
        'required',
        'string',
        'min:10',
        'max:2000',
        new ContactFormSpamRule([
            'analyze_message_content' => true,
            'check_promotional_keywords' => true,
            'max_links_allowed' => 2,
            'enable_ai_analysis' => true,
        ])
    ]
]);
```

## Validation Rule Implementation

### Core Validation Logic

```php
class SpamValidationRule implements ValidationRule, DataAwareRule
{
    protected array $data = [];
    
    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }
    
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Skip if required fields are missing
        if (!$this->hasRequiredFields()) {
            return;
        }
        
        try {
            $spamAnalysis = $this->performSpamAnalysis();
            $threshold = $this->getSpamThreshold();
            
            if ($spamAnalysis['score'] >= $threshold) {
                $this->logBlockedAttempt($spamAnalysis);
                $fail($this->getErrorMessage($spamAnalysis));
            }
        } catch (\Exception $e) {
            $this->handleValidationError($e);
        }
    }
}
```

### Error Messages

Customizable error messages based on form type and spam indicators:

```php
protected function getErrorMessage(array $spamAnalysis): string
{
    $messages = config('form-security.error_messages', []);
    
    return match ($this->formType) {
        'user_registration' => $messages['registration'] ?? 
            'Registration failed spam verification. Please use different information or contact support.',
        'contact' => $messages['contact'] ?? 
            'Your message was flagged as potential spam. Please revise and try again.',
        'comment' => $messages['comment'] ?? 
            'Your comment was flagged as potential spam. Please revise your content.',
        'newsletter' => $messages['newsletter'] ?? 
            'Subscription failed verification. Please try again or contact support.',
        default => $messages['generic'] ?? 
            'Your submission was flagged as potential spam. Please revise and try again.'
    ];
}
```

## Integration Patterns

### Basic Form Integration

```php
// In your controller
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
}
```

### Form Request Integration

```php
class ContactFormRequest extends FormRequest
{
    public function rules(): array
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
        
        // Process the form...
    }
}
```

## Advanced Configuration

### Per-Form Thresholds

```php
// config/form-security.php
'thresholds' => [
    'user_registration' => [
        'block' => 90,
        'flag' => 70,
        'review' => 50,
    ],
    'contact' => [
        'block' => 85,
        'flag' => 65,
        'review' => 45,
    ],
    'comment' => [
        'block' => 95,
        'flag' => 75,
        'review' => 55,
    ],
    'newsletter' => [
        'block' => 80,
        'flag' => 60,
        'review' => 40,
    ],
],
```

### Custom Field Mapping

```php
// Handle different field names across forms
'field_mapping' => [
    'contact' => [
        'name' => ['name', 'full_name', 'contact_name'],
        'email' => ['email', 'email_address', 'contact_email'],
        'message' => ['message', 'content', 'inquiry', 'body'],
    ],
    'user_registration' => [
        'name' => ['name', 'username', 'display_name'],
        'email' => ['email', 'email_address'],
    ],
],
```

### Conditional AI Analysis

```php
'ai_analysis' => [
    'conditions' => [
        'score_range' => [30, 70], // Only analyze borderline cases
        'form_types' => ['contact', 'comment'], // Only for specific forms
        'high_risk_ips' => true, // Always analyze high-risk IPs
        'repeat_offenders' => true, // Always analyze repeat offenders
    ],
],
```

## Validation Events

### Event System Integration

```php
// Events fired during validation
Event::listen(SpamDetected::class, function ($event) {
    // Log spam detection
    Log::info('Spam detected', [
        'form_type' => $event->formType,
        'score' => $event->score,
        'ip' => $event->ip,
    ]);
});

Event::listen(SubmissionBlocked::class, function ($event) {
    // Notify administrators of blocked submission
    Notification::route('slack', config('form-security.slack_webhook'))
        ->notify(new SpamBlockedNotification($event));
});
```

### Custom Event Handlers

```php
class SpamDetectionEventHandler
{
    public function handleSpamDetected(SpamDetected $event): void
    {
        // Custom spam detection handling
    }
    
    public function handleSubmissionBlocked(SubmissionBlocked $event): void
    {
        // Custom blocking handling
    }
    
    public function handleHighRiskPattern(HighRiskPatternDetected $event): void
    {
        // Handle high-risk patterns
    }
}
```

This validation system provides flexible, powerful spam protection that can be easily integrated into any Laravel form while maintaining high performance and user experience.
