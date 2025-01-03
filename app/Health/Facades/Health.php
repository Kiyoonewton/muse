<?php

declare(strict_types=1);

namespace App\Health\Facades;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Services\HealthService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * Health check facade
 *
 * @method static Collection<HealthCheckResult> runChecks()
 * @method static Collection<HealthCheckResult> getCachedResults()
 * @method static HealthCheckResult runCheck(string $name)
 * @method static void registerCheck(string $checkClass)
 * @method static void unregisterCheck(string $name)
 * @method static array<string> getRegisteredChecks()
 * @method static bool hasCheck(string $name)
 * @method static void clearCache()
 * @method static array<string, mixed> getSystemStatus()
 * @method static void notifyOnFailure(HealthCheckResult $result)
 * @method static Collection<HealthCheckResult> runCriticalChecks()
 *
 * @see \App\Health\Services\HealthService
 */
class Health extends Facade
{
    /**
     * Get the registered name of the component
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return HealthService::class;
    }

    /**
     * Get quick status check for a service
     *
     * @param string $service Service identifier
     * @return bool True if service is healthy
     */
    public static function isHealthy(string $service): bool
    {
        try {
            $result = static::runCheck($service);

            return $result->isHealthy();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Start monitoring a service
     *
     * @param string $service Service identifier
     * @param callable $callback Check implementation
     * @return void
     */
    public static function monitor(string $service, callable $callback): void
    {
        $healthService = static::getFacadeRoot();
        $healthService->registerCallback($service, $callback);
    }

    /**
     * Check multiple services
     *
     * @param array<string> $services Service identifiers
     * @return Collection<HealthCheckResult>
     */
    public static function checkServices(array $services): Collection
    {
        return collect($services)
            ->map(fn (string $service) => static::runCheck($service));
    }

    /**
     * Get overall system health status
     *
     * @return array<string, mixed>
     */
    public static function getStatus(): array
    {
        $results = static::getCachedResults();

        return [
            'status' => $results->every(fn ($result) => $result->isHealthy())
                ? 'healthy'
                : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'services' => $results->map->toArray(),
        ];
    }

    /**
     * Get status for Kubernetes probes
     *
     * @return array<string, mixed>
     */
    public static function getKubernetesProbe(): array
    {
        $results = static::runCriticalChecks();

        return [
            'status' => [
                'ready' => $results->every(fn ($result) => $result->isHealthy()),
                'live' => true, // Application is running
                'startup' => true, // Application has completed startup
            ],
            'checks' => $results->map->toArray(),
        ];
    }

    /**
     * Get Prometheus metrics
     *
     * @return string
     */
    public static function getPrometheusMetrics(): string
    {
        $results = static::getCachedResults();

        $metrics = $results->map(function (HealthCheckResult $result) {
            return $result->toPrometheusMetrics();
        });

        return $metrics->join("\n");
    }

    /**
     * Get metrics for external monitoring systems
     *
     * @param string $format Metrics format (prometheus, datadog, etc)
     * @return mixed
     */
    public static function getMetrics(string $format = 'prometheus'): mixed
    {
        $results = static::getCachedResults();

        return match ($format) {
            'prometheus' => static::getPrometheusMetrics(),
            'datadog' => $results->map->toDatadogMetrics()->collapse()->toArray(),
            default => throw new \InvalidArgumentException("Unsupported metrics format: {$format}")
        };
    }

    /**
     * Register a custom check implementation
     *
     * @param string $name Check identifier
     * @param callable $callback Check implementation
     * @return void
     */
    public static function extend(string $name, callable $callback): void
    {
        $healthService = static::getFacadeRoot();
        $healthService->registerCallback($name, $callback);
    }

    /**
     * Run maintenance checks
     *
     * @return Collection<HealthCheckResult>
     */
    public static function maintenance(): Collection
    {
        $results = static::runChecks();

        // Notify on failures
        $results->filter(fn ($result) => ! $result->isHealthy())
            ->each(fn ($result) => static::notifyOnFailure($result));

        // Clear old cache entries
        static::clearCache();

        return $results;
    }

    /**
     * Get check documentation
     *
     * @return array<string, mixed>
     */
    public static function documentation(): array
    {
        $healthService = static::getFacadeRoot();

        return collect($healthService->getRegisteredChecks())
            ->mapWithKeys(function ($checkClass) {
                $check = new $checkClass();

                return [
                    $check->name() => [
                        'name' => $check->displayName(),
                        'description' => $check->description(),
                        'is_critical' => $check->isCritical(),
                        'timeout' => $check->timeout(),
                        'interval' => $check->minimumInterval(),
                        'tags' => $check->tags(),
                    ],
                ];
            })
            ->toArray();
    }
}
