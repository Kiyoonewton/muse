<?php

declare(strict_types=1);

namespace App\Health\Checks;

use App\Health\Contracts\HealthCheckInterface;
use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use App\Health\Exceptions\HealthCheckException;
use Illuminate\Support\Facades\Log;

/**
 * Abstract base class for health checks providing common functionality
 */
abstract class BaseHealthCheck implements HealthCheckInterface
{
    /**
     * Maximum execution time for health checks in seconds
     */
    protected const MAX_EXECUTION_TIME = 5;

    /**
     * Get the name of the health check
     *
     * @return string
     */
    abstract public function name(): string;

    /**
     * Execute the actual health check logic
     * Should be implemented by concrete check classes
     *
     * @return array{status: string, metadata?: array<string, mixed>}
     * @throws HealthCheckException
     */
    abstract protected function executeCheck(): array;

    /**
     * Run the health check with timing and error handling
     *
     * @return HealthCheckResult
     */
    public function check(): HealthCheckResult
    {
        $startTime = microtime(true);

        try {
            // Set timeout for the check
            set_time_limit(static::MAX_EXECUTION_TIME);

            // Execute the actual check
            $result = $this->executeCheck();

            $responseTime = $this->calculateResponseTime($startTime);

            return new HealthCheckResult(
                name: $this->name(),
                status: $result['status'],
                responseTime: $responseTime,
                metadata: $result['metadata'] ?? [],
                error: null
            );
        } catch (HealthCheckException $e) {
            // Log specific health check exceptions
            Log::warning("Health check {$this->name()} failed", [
                'exception' => $e->getMessage(),
                'check' => $this->name(),
            ]);

            return $this->createFailedResult($e->getMessage(), $startTime);
        } catch (\Throwable $e) {
            // Log unexpected exceptions
            Log::error("Unexpected error in health check {$this->name()}", [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'check' => $this->name(),
            ]);

            return $this->createFailedResult(
                "Unexpected error: {$e->getMessage()}",
                $startTime
            );
        }
    }

    /**
     * Measure execution time of a callback
     *
     * @param callable $callback
     * @return array{result: mixed, executionTime: float}
     */
    protected function measureExecutionTime(callable $callback): array
    {
        $startTime = microtime(true);
        $result = $callback();
        $executionTime = $this->calculateResponseTime($startTime);

        return [
            'result' => $result,
            'executionTime' => $executionTime,
        ];
    }

    /**
     * Calculate response time from start time
     *
     * @param float $startTime
     * @return float Response time in milliseconds
     */
    protected function calculateResponseTime(float $startTime): float
    {
        return round((microtime(true) - $startTime) * 1000, 2);
    }

    /**
     * Create a failed health check result
     *
     * @param string $error
     * @param float $startTime
     * @return HealthCheckResult
     */
    protected function createFailedResult(string $error, float $startTime): HealthCheckResult
    {
        return new HealthCheckResult(
            name: $this->name(),
            status: HealthStatus::UNHEALTHY->value,
            responseTime: $this->calculateResponseTime($startTime),
            metadata: [],
            error: $error
        );
    }

    /**
     * Create a warning health check result
     *
     * @param string $warning
     * @param float $startTime
     * @param array<string, mixed> $metadata
     * @return HealthCheckResult
     */
    protected function createWarningResult(
       string $warning,
       float $startTime,
       array $metadata = []
   ): HealthCheckResult {
        return new HealthCheckResult(
            name: $this->name(),
            status: HealthStatus::WARNING->value,
            responseTime: $this->calculateResponseTime($startTime),
            metadata: $metadata,
            error: $warning
        );
    }

    /**
     * Create a healthy check result
     *
     * @param float $startTime
     * @param array<string, mixed> $metadata
     * @return HealthCheckResult
     */
    protected function createHealthyResult(
       float $startTime,
       array $metadata = []
   ): HealthCheckResult {
        return new HealthCheckResult(
            name: $this->name(),
            status: HealthStatus::HEALTHY->value,
            responseTime: $this->calculateResponseTime($startTime),
            metadata: $metadata,
            error: null
        );
    }

    /**
     * Check if a value exceeds a warning threshold
     *
     * @param float $value
     * @param float $warningThreshold
     * @param float $errorThreshold
     * @return string|null HealthStatus value if threshold exceeded, null otherwise
     */
    protected function checkThreshold(
       float $value,
       float $warningThreshold,
       float $errorThreshold
   ): ?string {
        if ($value >= $errorThreshold) {
            return HealthStatus::UNHEALTHY->value;
        }

        if ($value >= $warningThreshold) {
            return HealthStatus::WARNING->value;
        }

        return null;
    }
}
