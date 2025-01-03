<?php

declare(strict_types=1);

namespace App\Health\Checks;

use App\Health\Contracts\HealthCheckInterface;
use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use App\Health\Exceptions\HealthCheckException;
use App\Health\Traits\HealthCheckTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Predis\Client;

/**
 * Redis health check implementation
 */
class RedisHealthCheck implements HealthCheckInterface
{
    use HealthCheckTrait;

    /**
     * Default memory usage thresholds (percentage)
     */
    private const DEFAULT_MEMORY_WARNING_THRESHOLD = 75;

    private const DEFAULT_MEMORY_ERROR_THRESHOLD = 90;

    /**
     * Default response time thresholds (milliseconds)
     */
    private const DEFAULT_RESPONSE_WARNING_THRESHOLD = 100;

    private const DEFAULT_RESPONSE_ERROR_THRESHOLD = 200;

    /**
     * Get the check name
     */
    public function name(): string
    {
        return 'redis';
    }

    /**
     * Get the display name
     */
    public function displayName(): string
    {
        return 'Redis Server';
    }

    /**
     * Get the description
     */
    public function description(): string
    {
        return 'Checks Redis server connectivity, memory usage, and performance';
    }

    /**
     * Is this a critical check?
     */
    public function isCritical(): bool
    {
        return true;
    }

    /**
     * Get the tags
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return ['core', 'cache', 'redis'];
    }

    /**
     * Execute health check
     */
    public function check(): HealthCheckResult
    {
        try {
            $startTime = microtime(true);
            $redis = $this->getRedisConnection();

            // Basic connectivity check
            $pingSuccess = $this->testConnection($redis);
            if (! $pingSuccess) {
                throw new HealthCheckException('Redis ping failed');
            }

            // Gather server metrics
            $metrics = $this->gatherServerMetrics($redis);
            $metrics['response_time_ms'] = $this->calculateResponseTime($startTime);

            // Check for metric gathering errors
            if (isset($metrics['error'])) {
                return new HealthCheckResult(
                    checkName: $this->name(),
                    status: HealthStatus::WARNING,
                    responseTime: $metrics['response_time_ms'],
                    metadata: $metrics,
                    message: 'Some Redis metrics unavailable'
                );
            }

            // Check response time
            if ($metrics['response_time_ms'] > self::DEFAULT_RESPONSE_ERROR_THRESHOLD) {
                return new HealthCheckResult(
                    checkName: $this->name(),
                    status: HealthStatus::UNHEALTHY,
                    responseTime: $metrics['response_time_ms'],
                    metadata: $metrics,
                    error: 'Response time exceeded error threshold'
                );
            }

            // Only check memory if we have valid metrics
            if ($metrics['memory_usage_percent'] > 0) {
                if ($metrics['memory_usage_percent'] > self::DEFAULT_MEMORY_ERROR_THRESHOLD) {
                    return new HealthCheckResult(
                        checkName: $this->name(),
                        status: HealthStatus::UNHEALTHY,
                        responseTime: $metrics['response_time_ms'],
                        metadata: $metrics,
                        error: 'Memory usage exceeded error threshold'
                    );
                }

                if ($metrics['memory_usage_percent'] > self::DEFAULT_MEMORY_WARNING_THRESHOLD) {
                    return new HealthCheckResult(
                        checkName: $this->name(),
                        status: HealthStatus::WARNING,
                        responseTime: $metrics['response_time_ms'],
                        metadata: $metrics,
                        message: 'Memory usage exceeded warning threshold'
                    );
                }
            }

            // Test cache operations
            $cacheTest = $this->testCacheOperations();
            if (! $cacheTest['success']) {
                return new HealthCheckResult(
                    checkName: $this->name(),
                    status: HealthStatus::UNHEALTHY,
                    responseTime: $metrics['response_time_ms'],
                    metadata: $metrics,
                    error: $cacheTest['error']
                );
            }

            // All checks passed
            return new HealthCheckResult(
                checkName: $this->name(),
                status: HealthStatus::HEALTHY,
                responseTime: $metrics['response_time_ms'],
                metadata: $metrics
            );
        } catch (HealthCheckException $e) {
            Log::error('Redis health check exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new HealthCheckResult(
                checkName: $this->name(),
                status: HealthStatus::UNHEALTHY,
                responseTime: $this->calculateResponseTime($startTime ?? microtime(true)),
                error: $e->getMessage()
            );
        } catch (\Throwable $e) {
            Log::error('Redis health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new HealthCheckResult(
                checkName: $this->name(),
                status: HealthStatus::UNHEALTHY,
                responseTime: $this->calculateResponseTime($startTime ?? microtime(true)),
                error: "Redis check failed: {$e->getMessage()}"
            );
        }
    }

    /**
     * Get Redis connection
     *
     * @throws HealthCheckException
     */
    private function getRedisConnection()
    {
        try {
            return Redis::connection(
                Config::get('health.checks.redis.connection', 'default')
            );
        } catch (\Throwable $e) {
            throw new HealthCheckException(
                "Failed to establish Redis connection: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Test Redis connection
     *
     * @param mixed $redis
     * @return bool
     */
    private function testConnection($redis): bool
    {
        try {
            $pong = $redis->ping();

            return $pong === true || $pong === '+PONG' || $pong === 'PONG';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Gather server metrics
     *
     * @param mixed $redis
     * @return array<string, mixed>
     */
    private function gatherServerMetrics($redis): array
    {
        try {
            $info = $redis->info();

            // Memory metrics
            $memoryMetrics = $this->getMemoryMetrics($info);

            return [
                'version' => $info['redis_version'] ?? 'unknown',
                'uptime_days' => round(($info['uptime_in_seconds'] ?? 0) / 86400, 1),
                'connected_clients' => (int) ($info['connected_clients'] ?? 0),
                'used_memory' => $memoryMetrics['used_memory_human'],
                'max_memory' => $memoryMetrics['max_memory_human'],
                'memory_usage_percent' => $memoryMetrics['usage_percent'],
                'evicted_keys' => (int) ($info['evicted_keys'] ?? 0),
                'hit_rate' => $this->calculateHitRate($info),
                'ops_per_second' => (int) ($info['instantaneous_ops_per_sec'] ?? 0),
                'keyspace_hits' => (int) ($info['keyspace_hits'] ?? 0),
                'keyspace_misses' => (int) ($info['keyspace_misses'] ?? 0),
                'total_connections_received' => (int) ($info['total_connections_received'] ?? 0),
                'total_commands_processed' => (int) ($info['total_commands_processed'] ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to gather Redis metrics', [
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => 'Failed to gather metrics',
                'message' => $e->getMessage(),
                'memory_usage_percent' => 0,  // Provide default value
            ];
        }
    }

    /**
     * Get memory metrics
     *
     * @param array<string, mixed> $info
     * @return array<string, mixed>
     */
    private function getMemoryMetrics(array $info): array
    {
        try {
            $usedMemory = $this->parseMemoryValue((string) $info['used_memory'] ?? '0');

            // Try different ways to get max memory
            $maxMemory = $this->parseMemoryValue((string) $info['maxmemory'] ?? '0');
            if ($maxMemory <= 0) {
                $maxMemory = $this->parseMemoryValue((string) $info['total_system_memory'] ?? '0');
            }

            // If we still don't have max memory, try to get it via config
            if ($maxMemory <= 0) {
                try {
                    $configMaxMemory = $this->redis->config('GET', 'maxmemory');
                    if (isset($configMaxMemory['maxmemory'])) {
                        $maxMemory = (int) $configMaxMemory['maxmemory'];
                    }
                } catch (\Throwable) {
                    // Ignore config get errors
                }
            }

            // Calculate usage percentage
            $usagePercent = $maxMemory > 0
                ? round(($usedMemory / $maxMemory) * 100, 2)
                : 0;

            return [
                'used_memory' => $usedMemory,
                'max_memory' => $maxMemory,
                'used_memory_human' => $this->formatBytes($usedMemory),
                'max_memory_human' => $this->formatBytes($maxMemory),
                'usage_percent' => $usagePercent,
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to calculate Redis memory metrics', [
                'error' => $e->getMessage(),
            ]);

            return [
                'used_memory' => 0,
                'max_memory' => 0,
                'used_memory_human' => '0 B',
                'max_memory_human' => '0 B',
                'usage_percent' => 0,
            ];
        }
    }

    /**
     * Test cache operations
     *
     * @return array{success: bool, error?: string}
     */
    private function testCacheOperations(): array
    {
        try {
            $key = 'health:test:'.time();
            $value = 'test-value';

            // Test write
            if (! Cache::set($key, $value, 60)) {
                return [
                    'success' => false,
                    'error' => 'Failed to write to cache',
                ];
            }

            // Test read
            $retrieved = Cache::get($key);
            if ($retrieved !== $value) {
                return [
                    'success' => false,
                    'error' => 'Cache read verification failed',
                ];
            }

            // Test delete
            if (! Cache::forget($key)) {
                return [
                    'success' => false,
                    'error' => 'Failed to delete from cache',
                ];
            }

            return ['success' => true];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate cache hit rate
     *
     * @param array<string, mixed> $info
     * @return float
     */
    private function calculateHitRate(array $info): float
    {
        $hits = (int) ($info['keyspace_hits'] ?? 0);
        $misses = (int) ($info['keyspace_misses'] ?? 0);
        $total = $hits + $misses;

        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }

    /**
     * Parse memory value from Redis info
     *
     * @param string $value
     * @return int
     */
    private function parseMemoryValue(string $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        $units = ['b' => 1, 'kb' => 1024, 'mb' => 1024 * 1024, 'gb' => 1024 * 1024 * 1024];
        $value = strtolower($value);

        foreach ($units as $unit => $multiplier) {
            if (str_ends_with($value, $unit)) {
                return (int) $value * $multiplier;
            }
        }

        return (int) $value;
    }

    /**
     * Format bytes to human readable string
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $level = 0;

        while ($bytes > 1024 && $level < count($units) - 1) {
            $bytes /= 1024;
            $level++;
        }

        return round($bytes, 2).' '.$units[$level];
    }

    /**
     * Calculate response time
     */
    private function calculateResponseTime(float $startTime): float
    {
        return (microtime(true) - $startTime) * 1000;
    }
}
