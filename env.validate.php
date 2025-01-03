<?php

use Dotenv\Dotenv;

require __DIR__.'/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$requiredEnvVariables = [
    'APP_DEBUG',
    'APP_ENV',
    'APP_KEY',
    'APP_URL',
    'DB_DATABASE',
    'DB_HOST',
    'DB_PASSWORD',
    'DB_PREFIX',
    'DB_USERNAME',
    'ELASTICSEARCH_HOST',
    'ELASTICSEARCH_PORT',
    'ELASTICSEARCH_SCHEME',
    'ELASTICSEARCH_USER',
    'ELASTICSEARCH_PASS',
    'KAFKA_BROKERS',
    'SENTRY_LARAVEL_DSN',
    'SENTRY_TRACES_SAMPLE_RATE',
];

$missingEnvVariables = array_filter($requiredEnvVariables, fn ($variable) => ! isset($_ENV[$variable]));

if (count($missingEnvVariables) > 0) {
    $message = sprintf('Missing the following environment variables: %s', implode(', ', $missingEnvVariables));
    echo $message;
    exit(1);
}
