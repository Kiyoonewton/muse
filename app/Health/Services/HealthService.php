<?php

declare(strict_types=1);

namespace App\Health\Services;

use App\Health\Contracts\HealthCheckInterface;
use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use App\Health\Events\HealthCheckFailedEvent;
use App\Health\Events\HealthCheckWarningEvent;
use App\Health\Exceptions\HealthCheckException;
use Illuminate\Cache\Repository as Cache;
use Illuminate\Config\Repository as Config;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service for managing and executing health checks
 */
class HealthService
{
    /**
     * Cache key for health check results
     */
    private const CACHE_KEY = 'health_check_results';

    /**
     * Registered health checks
     *
     * @var array<string, class-string<HealthCheckInterface>>
     */
    private array $registeredChecks = [];

    /**
     * Registered check callbacks
     *
     * @var array<string, callable>
     */
    private array $checkCallbacks = [];

    /**
     * Create a new health service instance
     */
    public function __construct(
       private readonly Config $config,
       private readonly Cache $cache,
       private readonly LoggerInterface $logger
   ) {
    }

    /**
     * Register a health check
     *
     * @param class-string<HealthCheckInterface> $checkClass
     * @throws \InvalidArgumentException
     */
    public function registerCheck(string $checkClass): void
    {
        if (! class_exists($checkClass) || ! is_subclass_of($checkClass, HealthCheckInterface::class)) {
            throw new \InvalidArgumentException("Invalid health check class: {$checkClass}");
        }

        $check = new $checkClass();
        $this->registeredChecks[$check->name()] = $checkClass;
    }

    /**
     * Register a callback-based health check
     *
     * @param string $name
     * @param callable $callback
     */
    public function registerCallback(string $name, callable $callback): void
    {
        $this->checkCallbacks[$name] = $callback;
    }

    /**
     * Unregister a health check
     *
     * @param string $name
     */
    public function unregisterCheck(string $name): void
    {
        unset($this->registeredChecks[$name], $this->checkCallbacks[$name]);
    }

    /**
     * Run all registered health checks
     *
     * @return Collection<HealthCheckResult>
     */
    public function runChecks(): Collection
    {
        $results = collect();

        // Run registered class-based checks
        foreach ($this->registeredChecks as $name => $checkClass) {
            try {
                $result = $this->runSingleCheck($name);
                $results->put($name, $result);
            } catch (Throwable $e) {
                $this->logger->error("Failed to run health check: {$name}", [
                    'exception' => $e,
                    'check' => $name,
                ]);
                $results->put($name, $this->createErrorResult($name, $e));
            }
        }

        // Run callback-based checks
        foreach ($this->checkCallbacks as $name => $callback) {
            try {
                $result = $this->runCallback($name, $callback);
                $results->put($name, $result);
            } catch (Throwable $e) {
                $this->logger->error("Failed to run callback check: {$name}", [
                    'exception' => $e,
                    'check' => $name,
                ]);
                $results->put($name, $this->createErrorResult($name, $e));
            }
        }

        // Cache results
        if ($this->shouldCache()) {
            $this->cacheResults($results);
        }

        return $results;
    }

    /**
     * Run critical health checks only
     *
     * @return Collection<HealthCheckResult>
     */
    public function runCriticalChecks(): Collection
    {
        return $this->runChecks()->filter(function ($result, $name) {
            $checkClass = $this->registeredChecks[$name] ?? null;
            if (! $checkClass) {
                return false;
            }

            return (new $checkClass())->isCritical();
        });
    }

    /**
     * Run a single health check
     *
     * @param string $name
     * @return HealthCheckResult
     * @throws HealthCheckException
     */
    public function runCheck(string $name): HealthCheckResult
    {
        // Check if it's a callback-based check
        if (isset($this->checkCallbacks[$name])) {
            return $this->runCallback($name, $this->checkCallbacks[$name]);
        }

        // Check if it's a registered class-based check
        if (! isset($this->registeredChecks[$name])) {
            throw new HealthCheckException("Health check not found: {$name}");
        }

        return $this->runSingleCheck($name);
    }

    /**
     * Get cached health check results
     *
     * @return Collection<HealthCheckResult>
     */
    public function getCachedResults(): Collection
    {
        if (! $this->shouldCache()) {
            return $this->runChecks();
        }

        $cached = $this->cache->get(self::CACHE_KEY);
        if (! $cached) {
            return $this->runChecks();
        }

        return collect($cached)->map(function ($data) {
            return new HealthCheckResult(
                checkName: $data['check_name'],
                status: HealthStatus::from($data['status']),
                responseTime: $data['response_time_ms'],
                metadata: $data['metadata'] ?? [],
                message: $data['message'] ?? null,
                error: $data['error'] ?? null,
                checkedAt: isset($data['checked_at'])
                    ? new \DateTime($data['checked_at'])
                    : null
            );
        });
    }

    /**
     * Clear cached results
     */
    public function clearCache(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * Get list of registered check names
     *
     * @return array<string>
     */
    public function getRegisteredChecks(): array
    {
        return array_keys($this->registeredChecks);
    }

    /**
     * Check if a health check is registered
     *
     * @param string $name
     * @return bool
     */
    public function hasCheck(string $name): bool
    {
        return isset($this->registeredChecks[$name]) || isset($this->checkCallbacks[$name]);
    }

    /**
     * Get system status summary
     *
     * @return array<string, mixed>
     */
    public function getSystemStatus(): array
    {
        $results = $this->getCachedResults();

        return [
            'status' => $results->every(fn ($result) => $result->isHealthy())
                ? HealthStatus::HEALTHY->value
                : HealthStatus::UNHEALTHY->value,
            'timestamp' => now()->toIso8601String(),
            'checks' => $results->map->toArray(),
            'meta' => [
                'total_checks' => $results->count(),
                'healthy_checks' => $results->filter->isHealthy()->count(),
                'unhealthy_checks' => $results->reject->isHealthy()->count(),
            ],
        ];
    }

    /**
     * Run a single class-based health check
     *
     * @param string $name
     * @return HealthCheckResult
     * @throws HealthCheckException
     */
    private function runSingleCheck(string $name): HealthCheckResult
    {
        $checkClass = $this->registeredChecks[$name];
        $check = new $checkClass();

        // Check if the check is enabled
        if (! $check->isEnabled()) {
            return new HealthCheckResult(
                checkName: $name,
                status: HealthStatus::WARNING,
                responseTime: 0.0,
                metadata: ['message' => 'Check is disabled']
            );
        }

        // Check dependencies
        foreach ($check->dependencies() as $dependency) {
            if (! $this->isDependencyHealthy($dependency)) {
                throw HealthCheckException::dependencyFailure(
                    $name,
                    $dependency,
                    'Dependency check failed'
                );
            }
        }

        // Run the check with timeout
        $timeout = $check->timeout();
        $startTime = microtime(true);

        try {
            // Set timeout
            set_time_limit($timeout);

            $result = $check->check();

            $executionTime = (microtime(true) - $startTime) * 1000;

            // Handle results
            if (! $result->isHealthy()) {
                if ($result->hasWarning()) {
                    event(new HealthCheckWarningEvent($name, $result));
                } else {
                    event(new HealthCheckFailedEvent($name, $result));
                }
            }

            return $result;
        } catch (Throwable $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;
            throw HealthCheckException::fromException($name, $e, [], $executionTime);
        } finally {
            // Reset timeout
            set_time_limit(0);
        }
    }

    /**
     * Run a callback-based health check
     *
     * @param string $name
     * @param callable $callback
     * @return HealthCheckResult
     */
    private function runCallback(string $name, callable $callback): HealthCheckResult
    {
        $startTime = microtime(true);

        try {
            $result = $callback();
            $executionTime = (microtime(true) - $startTime) * 1000;

            if ($result instanceof HealthCheckResult) {
                return $result;
            }

            return new HealthCheckResult(
                checkName: $name,
                status: $result === true ? HealthStatus::HEALTHY : HealthStatus::UNHEALTHY,
                responseTime: $executionTime,
                error: $result === true ? null : 'Check failed'
            );
        } catch (Throwable $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;

            return $this->createErrorResult($name, $e, $executionTime);
        }
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
        Throwable $exception,
        ?float $executionTime = null
    ): HealthCheckResult {
        return new HealthCheckResult(
            checkName: $name,
            status: HealthStatus::UNHEALTHY,
            responseTime: $executionTime ?? 0.0,
            metadata: ['exception' => get_class($exception)],
            error: $exception->getMessage()
        );
    }

    /**
     * Check if a dependency is healthy
     *
     * @param string $name
     * @return bool
     */
    private function isDependencyHealthy(string $name): bool
    {
        try {
            $result = $this->runCheck($name);

            return $result->isHealthy();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Cache health check results
     *
     * @param Collection<HealthCheckResult> $results
     */
    private function cacheResults(Collection $results): void
    {
        try {
            $cacheData = $results->map(function ($result) {
                return $result->toArray();
            })->all();

            $ttl = $this->config->get('health.cache.ttl', 300);
            $this->cache->put(self::CACHE_KEY, $cacheData, $ttl);
        } catch (\Throwable $e) {
            // Log the error but don't fail the health check
            $this->logger->warning('Failed to cache health check results', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if results should be cached
     *
     * @return bool
     */
    private function shouldCache(): bool
    {
        return $this->config->get('health.cache.enabled', true);
    }
}
