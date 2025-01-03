<?php

declare(strict_types=1);

namespace App\Health\DTOs;

use App\Health\Enums\HealthStatus;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Data Transfer Object for health check results
 */
final class HealthCheckResult implements JsonSerializable, Arrayable
{
    /**
     * @var array<string, mixed>
     */
    private readonly array $sanitizedMetadata;

    /**
     * Create a new health check result
     *
     * @param string $checkName Name of the health check
     * @param HealthStatus $status Status of the health check
     * @param float $responseTime Response time in milliseconds
     * @param array<string, mixed> $metadata Additional metadata about the check
     * @param string|null $message Optional message providing more details
     * @param string|null $error Error message if check failed
     * @param DateTimeInterface|null $checkedAt When the check was performed
     */
    public function __construct(
        private readonly string $checkName,
        private readonly HealthStatus $status,
        private readonly float $responseTime,
        array $metadata = [],
        private readonly ?string $message = null,
        private readonly ?string $error = null,
        private readonly ?DateTimeInterface $checkedAt = null
    ) {
        $this->sanitizedMetadata = $this->sanitizeMetadata($metadata);
    }

    /**
     * Get the check name
     */
    public function checkName(): string
    {
        return $this->checkName;
    }

    /**
     * Get the status
     */
    public function status(): HealthStatus
    {
        return $this->status;
    }

    /**
     * Get the response time
     */
    public function responseTime(): float
    {
        return $this->responseTime;
    }

    /**
     * Get the metadata
     *
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->sanitizedMetadata;
    }

    /**
     * Get the message
     */
    public function message(): ?string
    {
        return $this->message;
    }

    /**
     * Get the error
     */
    public function error(): ?string
    {
        return $this->error;
    }

    /**
     * Get when the check was performed
     */
    public function checkedAt(): ?DateTimeInterface
    {
        return $this->checkedAt;
    }

    /**
     * Check if the result is healthy
     */
    public function isHealthy(): bool
    {
        return $this->status === HealthStatus::HEALTHY;
    }

    /**
     * Check if the result has a warning
     */
    public function hasWarning(): bool
    {
        return $this->status === HealthStatus::WARNING;
    }

    /**
     * Check if the result is unhealthy
     */
    public function isUnhealthy(): bool
    {
        return $this->status === HealthStatus::UNHEALTHY;
    }

    /**
     * Check if the result has an error
     */
    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'check_name' => $this->checkName,
            'status' => $this->status->value,
            'response_time_ms' => round($this->responseTime, 2),
            'metadata' => $this->sanitizedMetadata,
            'message' => $this->message,
            'error' => $this->error,
            'checked_at' => $this->checkedAt?->toIso8601String(),
            'has_error' => $this->hasError(),
        ];
    }

    /**
     * Convert to JSON
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Create a string representation
     */
    public function __toString(): string
    {
        $status = strtoupper($this->status->value);
        $time = round($this->responseTime, 2);

        if ($this->hasError()) {
            return "{$this->checkName}: {$status} ({$time}ms) - {$this->error}";
        }

        return "{$this->checkName}: {$status} ({$time}ms)";
    }

    /**
     * Sanitize metadata to ensure serializability
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            if ($value instanceof \Closure) {
                continue;
            }

            if (is_object($value)) {
                if ($value instanceof \DateTimeInterface) {
                    $sanitized[$key] = $value->format(\DateTimeInterface::ISO8601);
                } elseif (method_exists($value, '__toString')) {
                    $sanitized[$key] = (string) $value;
                } elseif (method_exists($value, 'toArray')) {
                    $sanitized[$key] = $this->sanitizeMetadata($value->toArray());
                } else {
                    $sanitized[$key] = sprintf('[object:%s]', get_class($value));
                }
                continue;
            }

            if (is_resource($value)) {
                $sanitized[$key] = sprintf('[resource:%s]', get_resource_type($value));
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeMetadata($value);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * Create an error result from an exception
     *
     * @param string $name
     * @param \Throwable $exception
     * @param float|null $executionTime
     * @return HealthCheckResult
     */
    private function createErrorResult(
        string $name,
        \Throwable $exception,
        ?float $executionTime = null
    ): self {
        return new self(
            checkName: $name,
            status: HealthStatus::UNHEALTHY,
            responseTime: $executionTime ?? 0.0,
            metadata: ['exception' => get_class($exception)],
            error: $exception->getMessage()
        );
    }
}
