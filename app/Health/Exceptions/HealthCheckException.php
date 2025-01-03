<?php

declare(strict_types=1);

namespace App\Health\Exceptions;

use App\Health\DTOs\HealthCheckResult;
use RuntimeException;
use Throwable;

/**
 * Exception thrown by health checks when they fail
 */
class HealthCheckException extends RuntimeException
{
    /**
     * The name of the health check that failed
     */
    private string $checkName;

    /**
     * Additional context about the failure
     *
     * @var array<string, mixed>
     */
    private array $context;

    /**
     * Response time when the failure occurred
     */
    private ?float $responseTime;

    /**
     * Create a new health check exception
     *
     * @param string $message The error message
     * @param string $checkName The name of the failed check
     * @param array<string, mixed> $context Additional context
     * @param float|null $responseTime Response time when failure occurred
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
       string $message,
       string $checkName = '',
       array $context = [],
       ?float $responseTime = null,
       int $code = 0,
       ?Throwable $previous = null
   ) {
        parent::__construct($message, $code, $previous);

        $this->checkName = $checkName;
        $this->context = $context;
        $this->responseTime = $responseTime;
    }

    /**
     * Create exception from another exception
     *
     * @param string $checkName The name of the failed check
     * @param \Throwable $previous The previous exception
     * @param array<string, mixed> $context Additional context
     * @param float|null $responseTime Response time when failure occurred
     * @return static
     */
    public static function fromException(
       string $checkName,
       Throwable $previous,
       array $context = [],
       ?float $responseTime = null
   ): static {
        return new static(
            message: "Health check '{$checkName}' failed: {$previous->getMessage()}",
            checkName: $checkName,
            context: $context,
            responseTime: $responseTime,
            previous: $previous
        );
    }

    /**
     * Create exception for a timeout
     *
     * @param string $checkName The name of the failed check
     * @param int $timeout The timeout in seconds
     * @param array<string, mixed> $context Additional context
     * @return static
     */
    public static function timeout(string $checkName, int $timeout, array $context = []): static
    {
        return new static(
            message: "Health check '{$checkName}' timed out after {$timeout} seconds",
            checkName: $checkName,
            context: $context,
            responseTime: $timeout * 1000 // Convert to milliseconds
        );
    }

    /**
     * Create exception for a service dependency failure
     *
     * @param string $checkName The name of the failed check
     * @param string $dependency The name of the failed dependency
     * @param string $reason The reason for the failure
     * @param array<string, mixed> $context Additional context
     * @return static
     */
    public static function dependencyFailure(
       string $checkName,
       string $dependency,
       string $reason,
       array $context = []
   ): static {
        return new static(
            message: "Health check '{$checkName}' failed: Dependency '{$dependency}' is unavailable - {$reason}",
            checkName: $checkName,
            context: array_merge($context, ['failed_dependency' => $dependency])
        );
    }

    /**
     * Create exception for a configuration error
     *
     * @param string $checkName The name of the failed check
     * @param string $reason The reason for the misconfiguration
     * @param array<string, mixed> $context Additional context
     * @return static
     */
    public static function misconfigured(string $checkName, string $reason, array $context = []): static
    {
        return new static(
            message: "Health check '{$checkName}' is misconfigured: {$reason}",
            checkName: $checkName,
            context: $context
        );
    }

    /**
     * Create exception for invalid threshold values
     *
     * @param string $checkName The name of the failed check
     * @param string $metric The metric that exceeded the threshold
     * @param mixed $current The current value
     * @param mixed $threshold The threshold value
     * @param array<string, mixed> $context Additional context
     * @return static
     */
    public static function thresholdExceeded(
       string $checkName,
       string $metric,
       mixed $current,
       mixed $threshold,
       array $context = []
   ): static {
        return new static(
            message: "Health check '{$checkName}' failed: {$metric} ({$current}) exceeds threshold ({$threshold})",
            checkName: $checkName,
            context: array_merge($context, [
                'metric' => $metric,
                'current_value' => $current,
                'threshold' => $threshold,
            ])
        );
    }

    /**
     * Get the name of the failed check
     *
     * @return string
     */
    public function getCheckName(): string
    {
        return $this->checkName;
    }

    /**
     * Get the failure context
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get the response time when the failure occurred
     *
     * @return float|null
     */
    public function getResponseTime(): ?float
    {
        return $this->responseTime;
    }

    /**
     * Convert the exception to a health check result
     *
     * @return HealthCheckResult
     */
    public function toHealthCheckResult(): HealthCheckResult
    {
        return HealthCheckResult::unhealthy(
            checkName: $this->checkName,
            responseTime: $this->responseTime ?? 0.0,
            error: $this->getMessage(),
            metadata: $this->context,
            exception: $this
        );
    }

    /**
     * Get array representation of the exception
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'check_name' => $this->checkName,
            'context' => $this->context,
            'response_time' => $this->responseTime,
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
            'previous' => $this->getPrevious()?->getMessage(),
        ];
    }

    /**
     * Convert to JSON string
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    /**
     * Log the exception with context
     *
     * @return void
     */
    public function log(): void
    {
        logger()->error($this->getMessage(), [
            'exception' => $this,
            'check_name' => $this->checkName,
            'context' => $this->context,
            'response_time' => $this->responseTime,
        ]);
    }
}
