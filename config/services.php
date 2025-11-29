<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),

        // Default model for analysis
        'default_model' => env('OPENROUTER_DEFAULT_MODEL', 'anthropic/claude-3.5-sonnet'),
        
        // Alternative models you can use:
        // 'anthropic/claude-3-opus' - Most powerful, best for complex analysis
        // 'anthropic/claude-3.5-sonnet' - Balanced performance and cost
        // 'anthropic/claude-3-haiku' - Fastest and most economical
        // 'openai/gpt-4-turbo' - OpenAI's latest
        // 'google/gemini-pro' - Google's model
        
        // Token limits
        'max_tokens' => env('OPENROUTER_MAX_TOKENS', 4000),
        
        // Temperature (0-1): Lower = more focused, Higher = more creative
        'temperature' => env('OPENROUTER_TEMPERATURE', 0.7),
        
        // Request timeout in seconds
        'timeout' => env('OPENROUTER_TIMEOUT', 120),
        
        // Cache TTL in seconds (1 hour default)
        'cache_ttl' => env('OPENROUTER_CACHE_TTL', 3600),
        
        // Enable/disable caching
        'cache_enabled' => env('OPENROUTER_CACHE_ENABLED', true),
        
        // Rate limiting
        'rate_limit' => [
            'max_requests_per_minute' => env('OPENROUTER_RATE_LIMIT', 60),
        ],
        // Retry configuration
        'retry' => [
            'max_attempts' => env('OPENROUTER_RETRY_MAX_ATTEMPTS', 3),
            'delay' => env('OPENROUTER_RETRY_DELAY', 1000), // in milliseconds
        ],
    ],
];
