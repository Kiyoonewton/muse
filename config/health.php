<?php

return [
    'routes' => [
        'enabled' => true,
        'prefix' => '',
        'middleware' => ['web'],
        'domain' => null,
        'metrics' => true,
        'ping' => true,
        'documentation' => false,
    ],

    'auto_discover' => true,

    'cache' => [
        'enabled' => env('HEALTH_CACHE_ENABLED', true),
        'ttl' => env('HEALTH_CACHE_TTL', 300),
    ],

    'checks' => [
        'database' => [
            'enabled' => true,
            'class' => \App\Health\Checks\DatabaseHealthCheck::class,
            'connection_warning_threshold' => env('HEALTH_DB_WARNING_THRESHOLD', 70),
            'connection_error_threshold' => env('HEALTH_DB_ERROR_THRESHOLD', 85),
        ],
        'redis' => [
            'enabled' => true,
            'class' => \App\Health\Checks\RedisHealthCheck::class,
            'connection' => env('HEALTH_REDIS_CONNECTION', 'default'),
            'memory_warning_threshold' => env('HEALTH_REDIS_MEMORY_WARNING', 75),
            'memory_error_threshold' => env('HEALTH_REDIS_MEMORY_ERROR', 90),
        ],
        'system' => [
            'enabled' => true,
            'class' => \App\Health\Checks\SystemHealthCheck::class,
            'disk_warning_threshold' => env('HEALTH_DISK_WARNING_THRESHOLD', 80),
            'disk_error_threshold' => env('HEALTH_DISK_ERROR_THRESHOLD', 90),
            'memory_warning_threshold' => env('HEALTH_MEMORY_WARNING_THRESHOLD', 80),
            'memory_error_threshold' => env('HEALTH_MEMORY_ERROR_THRESHOLD', 90),
        ],
        'jwt' => [
            'enabled' => true,
            'class' => \App\Health\Checks\JWTHealthCheck::class,
            'request_warning_threshold' => env('HEALTH_JWT_WARNING_THRESHOLD', 1000),  // 1 second
            'request_error_threshold' => env('HEALTH_JWT_ERROR_THRESHOLD', 3000),      // 3 seconds
            'request_timeout' => env('HEALTH_JWT_TIMEOUT', 5),                         // 5 seconds
            'sample_token' => env('HEALTH_JWT_SAMPLE_TOKEN', 'eyJhbGciOiJSUzUxMiIsInR5cCI6IkpXVCJ9.eyJzdWIiOnsidXVpZCI6ImJjN2NlZGYyLWExZTctNDk4Zi1hZWM1LTVlZDYxOWU1YWM5ZiIsImVtYWlsIjoiRWRnYXIuQmFocmluZ2VyMzlAaG90bWFpbC5jb20ifSwiaWF0IjoxNzMxMDY2NDQ4LCJuYmYiOjE3MzEwNjY0NDgsImV4cCI6MTczMTA2NjQ1MywiYXVkIjoiaHR0cHM6Ly9tZXJjdXJ5LmhpdmVkZWNrLmNvbSIsImlzcyI6Imh0dHBzOi8vbWVyY3VyeS5oaXZlZGVjay5jb20ifQ'),
            'required_claims' => ['sub', 'iat', 'nbf', 'exp', 'aud', 'iss'],
            'required_subject_fields' => ['uuid', 'email'],
        ],
        'kafka' => [
            'enabled' => true,
            'class' => \App\Health\Checks\KafkaHealthCheck::class,
            'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
            'timeout' => env('HEALTH_KAFKA_TIMEOUT', 5000),
        ],
        // 's3' => [
        //     'enabled' => true,
        //     'class' => \App\Health\Checks\S3HealthCheck::class,
        //     'bucket' => env('AWS_BUCKET'),
        //     'size_warning_threshold' => env('HEALTH_S3_SIZE_WARNING', 5000000000), // 5GB
        //     'size_error_threshold' => env('HEALTH_S3_SIZE_ERROR', 8000000000),    // 8GB
        // ],
    ],

    'notifications' => [
        'enabled' => env('HEALTH_NOTIFICATIONS_ENABLED', true),
        'channels' => ['email', 'slack'],
        'notify_on' => ['failure', 'warning'], // or just ['failure'] for failures only
        'email' => [
            'to' => env('HEALTH_NOTIFICATION_EMAIL'),
        ],
        'slack' => [
            'webhook_url' => env('HEALTH_SLACK_WEBHOOK'),
        ],
    ],

    'schedule' => [
        'enabled' => true,
        'cron' => env('HEALTH_CHECK_CRON', '* * * * *'), // Every minute
    ],

    // 'listeners' => [
    //     'health.check.failed' => [
    //         \App\Listeners\NotifyHealthCheckFailure::class,
    //     ],
    // ],
];
