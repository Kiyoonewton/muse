<?php

declare(strict_types=1);

namespace App\Health\Checks;

use App\Health\Contracts\HealthCheckInterface;
use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use App\Health\Exceptions\HealthCheckException;
use App\Health\Traits\HealthCheckTrait;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;

/**
 * Database health check implementation
 */
class DatabaseHealthCheck implements HealthCheckInterface
{
    use HealthCheckTrait;

    /**
     * Default thresholds for connection pool usage
     */
    private const DEFAULT_CONNECTION_WARNING_THRESHOLD = 70;

    private const DEFAULT_CONNECTION_ERROR_THRESHOLD = 85;

    /**
     * Default thresholds for query execution time (ms)
     */
    private const DEFAULT_QUERY_TIME_WARNING = 500;

    private const DEFAULT_QUERY_TIME_ERROR = 1000;

    /**
     * Get the check name
     */
    public function name(): string
    {
        return 'database';
    }

    /**
     * Get the display name
     */
    public function displayName(): string
    {
        return 'Database Connection';
    }

    /**
     * Get the description
     */
    public function description(): string
    {
        return 'Checks database connectivity, performance, and status';
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
        return ['core', 'database'];
    }

    /**
     * Execute health check
     */
    public function check(): HealthCheckResult
    {
        Log::info('testng');

        try {
            $startTime = microtime(true);

            $connection = DB::connection();
            $pdo = $connection->getPdo();

            // Basic connectivity check
            if (! $this->checkConnectivity($pdo)) {
                throw new HealthCheckException('Database connection failed?');
            }

            // Get database metrics
            $metrics = $this->gatherMetrics($pdo);

            // Calculate response time
            $responseTime = $this->calculateResponseTime($startTime);

            // Check connection pool status
            $poolStatus = $this->checkConnectionPool($metrics);
            if ($poolStatus !== HealthStatus::HEALTHY->value) {
                return new HealthCheckResult(
                    checkName: $this->name(),
                    status: HealthStatus::from($poolStatus),
                    responseTime: $responseTime,
                    metadata: [
                        ...$metrics,
                        'issue' => 'Connection pool threshold exceeded',
                    ]
                );
            }

            // Check query performance
            $queryStatus = $this->checkQueryPerformance($pdo);
            if ($queryStatus['status'] !== HealthStatus::HEALTHY->value) {
                return new HealthCheckResult(
                    checkName: $this->name(),
                    status: HealthStatus::from($queryStatus['status']),
                    responseTime: $responseTime,
                    metadata: [
                        ...$metrics,
                        ...$queryStatus['metrics'],
                        'issue' => 'Query performance degraded',
                    ]
                );
            }

            // Check replication if configured
            if ($this->isReplicaSetup()) {
                $replicationStatus = $this->checkReplication($pdo);
                if ($replicationStatus['status'] !== HealthStatus::HEALTHY->value) {
                    return new HealthCheckResult(
                        checkName: $this->name(),
                        status: HealthStatus::from($replicationStatus['status']),
                        responseTime: $responseTime,
                        metadata: [
                            ...$metrics,
                            ...$replicationStatus['metrics'],
                            'issue' => 'Replication issues detected',
                        ]
                    );
                }
            }

            // All checks passed
            return new HealthCheckResult(
                checkName: $this->name(),
                status: HealthStatus::HEALTHY,
                responseTime: $responseTime,
                metadata: $metrics
            );
        } catch (HealthCheckException $e) {
            return new HealthCheckResult(
                checkName: $this->name(),
                status: HealthStatus::UNHEALTHY,
                responseTime: $this->calculateResponseTime($startTime ?? microtime(true)),
                error: $e->getMessage()
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult(
                checkName: $this->name(),
                status: HealthStatus::UNHEALTHY,
                responseTime: $this->calculateResponseTime($startTime ?? microtime(true)),
                error: "Database check failed: {$e->getMessage()}"
            );
        }
    }

    /**
     * Check basic database connectivity
     */
    private function checkConnectivity(PDO $pdo): bool
    {
        try {
            return $pdo->query('SELECT 1')->fetchColumn() == 1;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Calculate response time
     */
    private function calculateResponseTime(float $startTime): float
    {
        return (microtime(true) - $startTime) * 1000;
    }

    /**
     * Gather database metrics
     *
     * @return array<string, mixed>
     */
    private function gatherMetrics(PDO $pdo): array
    {
        $metrics = [];

        // Get connection metrics
        if ($this->isDatabaseMySQL($pdo)) {
            $statusResult = $pdo->query("SHOW STATUS WHERE Variable_name IN (
                'Threads_connected',
                'Max_used_connections',
                'Threads_running',
                'Queries',
                'Slow_queries'
            )")->fetchAll(PDO::FETCH_KEY_PAIR);

            $metrics = [
                'active_connections' => (int) ($statusResult['Threads_connected'] ?? 0),
                'max_used_connections' => (int) ($statusResult['Max_used_connections'] ?? 0),
                'running_threads' => (int) ($statusResult['Threads_running'] ?? 0),
                'total_queries' => (int) ($statusResult['Queries'] ?? 0),
                'slow_queries' => (int) ($statusResult['Slow_queries'] ?? 0),
            ];
        }

        // Add connection pool metrics
        $metrics['max_connections'] = $this->getMaxConnections($pdo);
        $metrics['connection_usage_percent'] = round(
            ($metrics['active_connections'] / $metrics['max_connections']) * 100,
            2
        );

        return $metrics;
    }

    /**
     * Check connection pool status
     *
     * @param array<string, mixed> $metrics
     * @return string
     */
    private function checkConnectionPool(array $metrics): string
    {
        $warningThreshold = Config::get(
            'health.checks.database.connection_warning_threshold',
            self::DEFAULT_CONNECTION_WARNING_THRESHOLD
        );

        $errorThreshold = Config::get(
            'health.checks.database.connection_error_threshold',
            self::DEFAULT_CONNECTION_ERROR_THRESHOLD
        );

        $usagePercent = $metrics['connection_usage_percent'] ?? 0;

        if ($usagePercent >= $errorThreshold) {
            return HealthStatus::UNHEALTHY->value;
        }

        if ($usagePercent >= $warningThreshold) {
            return HealthStatus::WARNING->value;
        }

        return HealthStatus::HEALTHY->value;
    }

    /**
     * Check query performance
     *
     * @param PDO $pdo
     * @return array{status: string, metrics: array<string, mixed>}
     */
    private function checkQueryPerformance(PDO $pdo): array
    {
        try {
            $startTime = microtime(true);
            $pdo->query('SELECT 1')->fetch();
            $queryTime = (microtime(true) - $startTime) * 1000;

            $metrics = ['query_time_ms' => round($queryTime, 2)];

            if ($queryTime >= self::DEFAULT_QUERY_TIME_ERROR) {
                return [
                    'status' => HealthStatus::UNHEALTHY->value,
                    'metrics' => $metrics,
                ];
            }

            if ($queryTime >= self::DEFAULT_QUERY_TIME_WARNING) {
                return [
                    'status' => HealthStatus::WARNING->value,
                    'metrics' => $metrics,
                ];
            }

            return [
                'status' => HealthStatus::HEALTHY->value,
                'metrics' => $metrics,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => HealthStatus::UNHEALTHY->value,
                'metrics' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Check if database is using replication
     *
     * @return bool
     */
    private function isReplicaSetup(): bool
    {
        return Config::get('database.use_replication', false);
    }

    /**
     * Check replication status
     *
     * @param PDO $pdo
     * @return array{status: string, metrics: array<string, mixed>}
     */
    private function checkReplication(PDO $pdo): array
    {
        if (! $this->isDatabaseMySQL($pdo)) {
            return [
                'status' => HealthStatus::HEALTHY->value,
                'metrics' => ['replication_supported' => false],
            ];
        }

        try {
            $result = $pdo->query('SHOW SLAVE STATUS')->fetch(PDO::FETCH_ASSOC);

            if (! $result) {
                return [
                    'status' => HealthStatus::HEALTHY->value,
                    'metrics' => ['replication_active' => false],
                ];
            }

            $metrics = [
                'replication_active' => true,
                'slave_io_running' => $result['Slave_IO_Running'] === 'Yes',
                'slave_sql_running' => $result['Slave_SQL_Running'] === 'Yes',
                'seconds_behind_master' => (int) $result['Seconds_Behind_Master'],
                'last_io_error' => $result['Last_IO_Error'],
                'last_sql_error' => $result['Last_SQL_Error'],
            ];

            // Check for replication issues
            if (! $metrics['slave_io_running'] || ! $metrics['slave_sql_running']) {
                return [
                    'status' => HealthStatus::UNHEALTHY->value,
                    'metrics' => $metrics,
                ];
            }

            // Check replication lag
            if ($metrics['seconds_behind_master'] > 300) { // 5 minutes
                return [
                    'status' => HealthStatus::WARNING->value,
                    'metrics' => $metrics,
                ];
            }

            return [
                'status' => HealthStatus::HEALTHY->value,
                'metrics' => $metrics,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => HealthStatus::UNHEALTHY->value,
                'metrics' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Get maximum allowed connections
     *
     * @param PDO $pdo
     * @return int
     */
    private function getMaxConnections(PDO $pdo): int
    {
        if ($this->isDatabaseMySQL($pdo)) {
            try {
                $result = $pdo->query("SHOW VARIABLES LIKE 'max_connections'")->fetch(PDO::FETCH_ASSOC);

                return (int) ($result['Value'] ?? 0);
            } catch (\Throwable) {
                return 0;
            }
        }

        return 0;
    }

    /**
     * Check if database is MySQL
     *
     * @param PDO $pdo
     * @return bool
     */
    private function isDatabaseMySQL(PDO $pdo): bool
    {
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            return strtolower($driver) === 'mysql';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get database version information
     *
     * @param PDO $pdo
     * @return array<string, string>
     */
    private function getDatabaseVersion(PDO $pdo): array
    {
        try {
            return [
                'version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
                'driver' => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
                'client' => $pdo->getAttribute(PDO::ATTR_CLIENT_VERSION),
            ];
        } catch (\Throwable) {
            return [
                'version' => 'unknown',
                'driver' => 'unknown',
                'client' => 'unknown',
            ];
        }
    }
}
