<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains all the settings for JWT token handling,
    | including issuer details, validation rules, and security settings.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | JWT Authorization Settings
    |--------------------------------------------------------------------------
    */
    'issuer_url' => env('JWT_ISSUER_URL', 'https://mercury.hivedeck.com'),
    'audience' => env('JWT_AUDIENCE', 'https://mercury.hivedeck.com'),

    /*
    |--------------------------------------------------------------------------
    | Token Settings
    |--------------------------------------------------------------------------
    */
    'token' => [
        // JWT algorithm
        'algorithm' => 'RS512',

        // Token type in header
        'type' => 'JWT',

        // Required claims for token validation
        'required_claims' => [
            'sub',     // Subject (user info)
            'iat',     // Issued At
            'nbf',     // Not Before
            'exp',     // Expiration
            'aud',     // Audience
            'iss',     // Issuer
        ],

        // Claim specific validation rules
        'validation' => [
            'exp' => [
                'min_lifetime' => env('JWT_MIN_LIFETIME', 300),  // 5 minutes
                'max_lifetime' => env('JWT_MAX_LIFETIME', 3600), // 1 hour
            ],
            'sub' => [
                'required_fields' => ['uuid', 'email'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Key Management
    |--------------------------------------------------------------------------
    */
    'keys' => [
        // Public key settings
        'public' => [
            'path' => env('JWT_PUBLIC_KEY_PATH', storage_path('public.key')),
            'cache_key' => 'jwt_public_key',
            'cache_ttl' => env('JWT_KEY_CACHE_TTL', 3600), // 1 hour
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Blacklist
    |--------------------------------------------------------------------------
    */
    'blacklist' => [
        // Enable token blacklisting
        'enabled' => env('JWT_BLACKLIST_ENABLED', true),

        // Redis settings for blacklist
        'storage' => [
            'prefix' => 'blacklisted_token:',
            'ttl' => env('JWT_BLACKLIST_TTL', 86400), // 24 hours
        ],

        // Grace period for clock skew (seconds)
        'grace_period' => env('JWT_BLACKLIST_GRACE_PERIOD', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Settings
    |--------------------------------------------------------------------------
    */
    'health' => [
        // Test token endpoint
        'test_endpoint' => '/generate-token',

        // Test token settings
        'test_token' => [
            'lifetime' => env('JWT_TEST_TOKEN_LIFETIME', 300), // 5 minutes
            'cache_ttl' => env('JWT_TEST_TOKEN_CACHE_TTL', 60), // 1 minute
        ],

        // Request timeout
        'request_timeout' => env('JWT_HEALTH_REQUEST_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Messages
    |--------------------------------------------------------------------------
    */
    'messages' => [
        'errors' => [
            'token_invalid' => 'Invalid token structure or signature',
            'token_expired' => 'Token has expired',
            'token_blacklisted' => 'Token has been blacklisted',
            'issuer_invalid' => 'Invalid token issuer',
            'audience_invalid' => 'Invalid token audience',
            'subject_invalid' => 'Invalid token subject structure',
            'signature_invalid' => 'Invalid token signature',
            'claims_invalid' => 'Missing or invalid token claims',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging & Monitoring
    |--------------------------------------------------------------------------
    */
    'logging' => [
        // Enable JWT-specific logging
        'enabled' => env('JWT_LOGGING_ENABLED', true),

        // Log channel
        'channel' => env('JWT_LOG_CHANNEL', 'jwt'),

        // Events to log
        'events' => [
            'verification_failed' => env('JWT_LOG_VERIFICATION_FAILED', true),
            'blacklist_hit' => env('JWT_LOG_BLACKLIST_HIT', true),
            'test_token_generated' => env('JWT_LOG_TEST_TOKEN', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */
    'security' => [
        // Leeway time for clock skew (seconds)
        'leeway' => env('JWT_LEEWAY', 30),

        // Maximum token size (bytes)
        'max_token_size' => env('JWT_MAX_TOKEN_SIZE', 8192),
    ],
];
