<?php

declare(strict_types=1);

namespace App\Health\Enums;

/**
 * Enum representing possible health check statuses
 */
enum HealthStatus: string
{
    /**
     * Service is healthy and operating normally
     */
    case HEALTHY = 'healthy';

    /**
     * Service is operational but showing signs of potential issues
     */
    case WARNING = 'warning';

    /**
     * Service is not operational or has critical issues
     */
    case UNHEALTHY = 'unhealthy';

    /**
     * Get the display name for the status
     */
    public function displayName(): string
    {
        return match ($this) {
            self::HEALTHY => 'Healthy',
            self::WARNING => 'Warning',
            self::UNHEALTHY => 'Unhealthy'
        };
    }

    /**
     * Get description of the status
     */
    public function description(): string
    {
        return match ($this) {
            self::HEALTHY => 'The service is operating normally',
            self::WARNING => 'The service is operational but requires attention',
            self::UNHEALTHY => 'The service is not operational or has critical issues'
        };
    }

    /**
     * Get the HTTP status code associated with this health status
     */
    public function httpStatusCode(): int
    {
        return match ($this) {
            self::HEALTHY => 200,
            self::WARNING => 429,
            self::UNHEALTHY => 503
        };
    }

    /**
     * Get the severity level (higher is more severe)
     */
    public function severity(): int
    {
        return match ($this) {
            self::HEALTHY => 0,
            self::WARNING => 1,
            self::UNHEALTHY => 2
        };
    }

    /**
     * Get the color code associated with this status
     */
    public function color(): string
    {
        return match ($this) {
            self::HEALTHY => '#00C853',   // Green
            self::WARNING => '#FFB300',   // Orange
            self::UNHEALTHY => '#D32F2F'  // Red
        };
    }

    /**
     * Get the label class for styling
     */
    public function labelClass(): string
    {
        return match ($this) {
            self::HEALTHY => 'badge-success',
            self::WARNING => 'badge-warning',
            self::UNHEALTHY => 'badge-danger'
        };
    }

    /**
     * Get the emoji representation of the status
     */
    public function emoji(): string
    {
        return match ($this) {
            self::HEALTHY => '✅',
            self::WARNING => '⚠️',
            self::UNHEALTHY => '❌'
        };
    }

    /**
     * Check if the status is worse than the given status
     */
    public function isWorseThan(self $other): bool
    {
        return $this->severity() > $other->severity();
    }

    /**
     * Check if the status is better than the given status
     */
    public function isBetterThan(self $other): bool
    {
        return $this->severity() < $other->severity();
    }

    /**
     * Get the most severe status from a list of statuses
     *
     * @param array<self> $statuses
     */
    public static function mostSevere(array $statuses): self
    {
        if (empty($statuses)) {
            return self::HEALTHY;
        }

        return array_reduce(
            $statuses,
            fn (self $carry, self $status) => $status->isWorseThan($carry) ? $status : $carry,
            self::HEALTHY
        );
    }

    /**
     * Create from a string value (case-insensitive)
     *
     * @throws \InvalidArgumentException
     */
    public static function fromString(string $value): self
    {
        return match (strtolower($value)) {
            'healthy', 'ok', 'success', 'up' => self::HEALTHY,
            'warning', 'warn', 'degraded' => self::WARNING,
            'unhealthy', 'error', 'failed', 'down' => self::UNHEALTHY,
            default => throw new \InvalidArgumentException("Invalid health status: {$value}")
        };
    }

    /**
     * Get all possible values as array
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $status) => $status->value,
            self::cases()
        );
    }

    /**
     * Get all display names as array
     *
     * @return array<string, string>
     */
    public static function displayNames(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $status) => [...$carry, $status->value => $status->displayName()],
            []
        );
    }

    /**
     * Get Prometheus compatible numeric value
     */
    public function toPrometheusValue(): int
    {
        return match ($this) {
            self::HEALTHY => 2,
            self::WARNING => 1,
            self::UNHEALTHY => 0
        };
    }

    /**
     * Get json representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->value,
            'display_name' => $this->displayName(),
            'description' => $this->description(),
            'severity' => $this->severity(),
            'color' => $this->color(),
            'emoji' => $this->emoji(),
        ];
    }
}
