<?php

// config/kafka.php

return [
    /*
    |--------------------------------------------------------------------------
    | Kafka Broker Configuration
    |--------------------------------------------------------------------------
    |
    | Define the Kafka broker list. For production, you should specify multiple
    | brokers for high availability.
    |
    */
    'brokers' => env('KAFKA_BROKERS', ''),

    /*
    |--------------------------------------------------------------------------
    | Consumer Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the Kafka consumer group and topics.
    |
    */
    'consumer' => [
        'group_id' => env('KAFKA_CONSUMER_GROUP_ID', 'my-app-consumer'),
        'topics' => explode(',', env('KAFKA_CONSUMER_TOPICS', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Required Topics
    |--------------------------------------------------------------------------
    |
    | List of topics that must be available for the application to function
    | properly. The health check will warn if any of these topics are not
    | accessible.
    |
    */
    'required_topics' => explode(',', env('KAFKA_REQUIRED_TOPICS', '')),

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the Kafka health check parameters.
    |
    */
    'health_check' => [
        'enabled' => env('KAFKA_HEALTH_CHECK_ENABLED', true),
        'critical' => env('KAFKA_HEALTH_CHECK_CRITICAL', true),
        'timeout' => env('KAFKA_HEALTH_CHECK_TIMEOUT', 5000), // milliseconds
        'interval' => env('KAFKA_HEALTH_CHECK_INTERVAL', 60), // seconds
        'min_brokers' => env('KAFKA_MIN_BROKERS', 1),
        'test_topic' => env('KAFKA_HEALTH_CHECK_TOPIC', 'health-check'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL Configuration (Optional)
    |--------------------------------------------------------------------------
    |
    | Configure SSL if required by your Kafka cluster.
    |
    */
    'ssl' => [
        'enabled' => env('KAFKA_SSL_ENABLED', false),
        'ca_location' => env('KAFKA_SSL_CA_LOCATION'),
        'certificate_location' => env('KAFKA_SSL_CERTIFICATE_LOCATION'),
        'key_location' => env('KAFKA_SSL_KEY_LOCATION'),
        'key_password' => env('KAFKA_SSL_KEY_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SASL Configuration (Optional)
    |--------------------------------------------------------------------------
    |
    | Configure SASL if required by your Kafka cluster.
    |
    */
    'sasl' => [
        'enabled' => env('KAFKA_SASL_ENABLED', false),
        'mechanism' => env('KAFKA_SASL_MECHANISM', 'PLAIN'),
        'username' => env('KAFKA_SASL_USERNAME'),
        'password' => env('KAFKA_SASL_PASSWORD'),
    ],
];
