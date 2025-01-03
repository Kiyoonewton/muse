<?php

declare(strict_types=1);

namespace App\Health\Checks;

use App\Health\Contracts\HealthCheckInterface;
use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use App\Health\Exceptions\HealthCheckException;
use App\Health\Traits\HealthCheckTrait;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * System health check implementation for containerized environments
 */
class SystemHealthCheck implements HealthCheckInterface
{
    use HealthCheckTrait;

    /**
     * cgroup paths for container metrics
     */
    private const CGROUP_CPU_PATH = '/sys/fs/cgroup/cpu';

    private const CGROUP_MEMORY_PATH = '/sys/fs/cgroup/memory';

    private const CONTAINER_LIMITS_PATH = '/sys/fs/cgroup';

    /**
     * Default thresholds for container resource usage
     */
    private const DEFAULT_CPU_WARNING_THRESHOLD = 80;

    private const DEFAULT_CPU_ERROR_THRESHOLD = 90;

    private const DEFAULT_MEMORY_WARNING_THRESHOLD = 80;

    private const DEFAULT_MEMORY_ERROR_THRESHOLD = 90;

    private const DEFAULT_DISK_WARNING_THRESHOLD = 80;

    private const DEFAULT_DISK_ERROR_THRESHOLD = 90;

    /**
     * Get PID 1 command line
     *
     * @return string|null
     */
    private function getPid1Cmdline(): ?string
    {
        try {
            return file_get_contents('/proc/1/cmdline');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get container information
     *
     * @return array<string, mixed>
     */
    private function getContainerInfo(): array
    {
        $info = [
            'is_container' => $this->isRunningInContainer(),
            'container_id' => $this->getContainerId(),
            'container_runtime' => $this->detectContainerRuntime(),
            'hostname' => gethostname(),
            'pid_1_cmdline' => $this->getPid1Cmdline(),
        ];

        // Add orchestration metadata if available
        if (getenv('KUBERNETES_SERVICE_HOST')) {
            $info['orchestration'] = 'kubernetes';
            $info['pod_name'] = getenv('HOSTNAME');
            $info['namespace'] = getenv('POD_NAMESPACE');
        }

        return $info;
    }

    /**
     * Get check name
     */
    public function name(): string
    {
        return 'system';
    }

    /**
     * Get check display name
     */
    public function displayName(): string
    {
        return 'System Resources';
    }

    /**
     * Get check description
     */
    public function description(): string
    {
        return 'Monitors system resources including CPU, memory, and disk usage';
    }

    /**
     * Execute health check
     */
    public function check(): HealthCheckResult
    {
        try {
            $startTime = microtime(true);

            // Collect container metrics
            $metrics = [
                'container' => $this->getContainerInfo(),
                'resources' => $this->getContainerResources(),
                'filesystem' => $this->getFilesystemMetrics(),
                'php' => $this->getPhpMetrics(),
            ];

            // Check memory usage
            $memoryUsage = $metrics['resources']['memory']['usage_percent'] ?? 0;
            if ($memoryUsage >= self::DEFAULT_MEMORY_ERROR_THRESHOLD) {
                return new HealthCheckResult(
                    checkName: $this->name(),
                    status: HealthStatus::UNHEALTHY,
                    responseTime: $this->calculateResponseTime($startTime),
                    metadata: $metrics,
                    error: "Memory usage at {$memoryUsage}% exceeds error threshold"
                );
            }

            if ($memoryUsage >= self::DEFAULT_MEMORY_WARNING_THRESHOLD) {
                return new HealthCheckResult(
                    checkName: $this->name(),
                    status: HealthStatus::WARNING,
                    responseTime: $this->calculateResponseTime($startTime),
                    metadata: $metrics,
                    message: "Memory usage at {$memoryUsage}% exceeds warning threshold"
                );
            }

            // Check CPU usage
            $cpuUsage = $metrics['resources']['cpu']['usage_percent'] ?? 0;
            if ($cpuUsage >= self::DEFAULT_CPU_ERROR_THRESHOLD) {
                return new HealthCheckResult(
                    checkName: $this->name(),
                    status: HealthStatus::UNHEALTHY,
                    responseTime: $this->calculateResponseTime($startTime),
                    metadata: $metrics,
                    error: "CPU usage at {$cpuUsage}% exceeds error threshold"
                );
            }

            if ($cpuUsage >= self::DEFAULT_CPU_WARNING_THRESHOLD) {
                return new HealthCheckResult(
                    checkName: $this->name(),
                    status: HealthStatus::WARNING,
                    responseTime: $this->calculateResponseTime($startTime),
                    metadata: $metrics,
                    message: "CPU usage at {$cpuUsage}% exceeds warning threshold"
                );
            }

            // Check disk usage
            $diskUsage = $metrics['filesystem']['usage_percent'] ?? 0;
            if ($diskUsage >= self::DEFAULT_DISK_ERROR_THRESHOLD) {
                return new HealthCheckResult(
                    checkName: $this->name(),
                    status: HealthStatus::UNHEALTHY,
                    responseTime: $this->calculateResponseTime($startTime),
                    metadata: $metrics,
                    error: "Disk usage at {$diskUsage}% exceeds error threshold"
                );
            }

            if ($diskUsage >= self::DEFAULT_DISK_WARNING_THRESHOLD) {
                return new HealthCheckResult(
                    checkName: $this->name(),
                    status: HealthStatus::WARNING,
                    responseTime: $this->calculateResponseTime($startTime),
                    metadata: $metrics,
                    message: "Disk usage at {$diskUsage}% exceeds warning threshold"
                );
            }

            return new HealthCheckResult(
                checkName: $this->name(),
                status: HealthStatus::HEALTHY,
                responseTime: $this->calculateResponseTime($startTime),
                metadata: $metrics
            );
        } catch (\Throwable $e) {
            Log::error('System health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new HealthCheckResult(
                checkName: $this->name(),
                status: HealthStatus::UNHEALTHY,
                responseTime: $this->calculateResponseTime($startTime ?? microtime(true)),
                error: "System check failed: {$e->getMessage()}"
            );
        }
    }

    /**
     * Calculate response time from start time
     */
    private function calculateResponseTime(float $startTime): float
    {
        return (microtime(true) - $startTime) * 1000;
    }

    /**
     * Format bytes to human readable string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $level = 0;

        while ($bytes > 1024 && $level < count($units) - 1) {
            $bytes /= 1024;
            $level++;
        }

        return round($bytes, 2).' '.$units[$level];
    }

    /**
     * Parse memory info from proc
     *
     * @return array<string, int>
     */
    private function parseMemInfo(): array
    {
        $memInfo = [];
        $content = @file_get_contents('/proc/meminfo');

        if ($content) {
            foreach (explode("\n", $content) as $line) {
                if (preg_match('/^(\w+):\s+(\d+)/', $line, $matches)) {
                    $memInfo[$matches[1]] = (int) $matches[2] * 1024; // Convert from KB to bytes
                }
            }
        }

        return $memInfo;
    }

    /**
     * Get tags for this check
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return ['core', 'system', 'resources'];
    }

    /**
     * Is this a critical check?
     */
    public function isCritical(): bool
    {
        return true;
    }

    /**
     * Get container resource metrics
     *
     * @return array<string, mixed>
     */
    private function getContainerResources(): array
    {
        return [
            'memory' => $this->getContainerMemoryMetrics(),
            'cpu' => $this->getContainerCpuMetrics(),
            'pids' => $this->getContainerPids(),
        ];
    }

    /**
     * Get container memory metrics
     *
     * @return array<string, mixed>
     */
    private function getContainerMemoryMetrics(): array
    {
        $metrics = [
            'limit' => 0,
            'usage' => 0,
            'usage_percent' => 0,
        ];

        try {
            // Try to get memory limits from cgroup
            if (is_readable(self::CGROUP_MEMORY_PATH.'/memory.limit_in_bytes')) {
                $metrics['limit'] = (int) file_get_contents(self::CGROUP_MEMORY_PATH.'/memory.limit_in_bytes');
                $metrics['usage'] = (int) file_get_contents(self::CGROUP_MEMORY_PATH.'/memory.usage_in_bytes');

                if ($metrics['limit'] > 0) {
                    $metrics['usage_percent'] = round(($metrics['usage'] / $metrics['limit']) * 100, 2);
                }

                $metrics['limit_human'] = $this->formatBytes($metrics['limit']);
                $metrics['usage_human'] = $this->formatBytes($metrics['usage']);
            }

            // Fallback to container memory stats from /proc/meminfo
            if ($metrics['limit'] === 0 && is_readable('/proc/meminfo')) {
                $memInfo = $this->parseMemInfo();
                $metrics['limit'] = $memInfo['MemTotal'] ?? 0;
                $metrics['usage'] = ($memInfo['MemTotal'] ?? 0) - ($memInfo['MemAvailable'] ?? 0);

                if ($metrics['limit'] > 0) {
                    $metrics['usage_percent'] = round(($metrics['usage'] / $metrics['limit']) * 100, 2);
                }

                $metrics['limit_human'] = $this->formatBytes($metrics['limit']);
                $metrics['usage_human'] = $this->formatBytes($metrics['usage']);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to get container memory metrics', ['error' => $e->getMessage()]);
        }

        return $metrics;
    }

    /**
     * Get container CPU metrics
     *
     * @return array<string, mixed>
     */
    private function getContainerCpuMetrics(): array
    {
        $metrics = [
            'shares' => 0,
            'usage_percent' => 0,
            'quota' => -1,
            'period' => -1,
        ];

        try {
            // Get CPU shares (relative weight)
            if (is_readable(self::CGROUP_CPU_PATH.'/cpu.shares')) {
                $metrics['shares'] = (int) file_get_contents(self::CGROUP_CPU_PATH.'/cpu.shares');
            }

            // Get CPU quota and period
            if (is_readable(self::CGROUP_CPU_PATH.'/cpu.cfs_quota_us')) {
                $metrics['quota'] = (int) file_get_contents(self::CGROUP_CPU_PATH.'/cpu.cfs_quota_us');
                $metrics['period'] = (int) file_get_contents(self::CGROUP_CPU_PATH.'/cpu.cfs_period_us');

                // Calculate number of CPUs allocated
                if ($metrics['quota'] > 0) {
                    $metrics['cpu_count'] = $metrics['quota'] / $metrics['period'];
                }
            }

            // Calculate CPU usage percentage
            if (is_readable(self::CGROUP_CPU_PATH.'/cpuacct.usage')) {
                $usage1 = (int) file_get_contents(self::CGROUP_CPU_PATH.'/cpuacct.usage');
                usleep(100000); // 100ms
                $usage2 = (int) file_get_contents(self::CGROUP_CPU_PATH.'/cpuacct.usage');

                $delta_usage = $usage2 - $usage1;
                $delta_time = 100000; // 100ms in microseconds

                $metrics['usage_percent'] = round(($delta_usage / ($delta_time * 1000)) * 100, 2);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to get container CPU metrics', ['error' => $e->getMessage()]);
        }

        return $metrics;
    }

    /**
     * Get container filesystem metrics
     *
     * @return array<string, mixed>
     */
    private function getFilesystemMetrics(): array
    {
        $path = Config::get('health.checks.system.disk_path', '/');

        $metrics = [
            'path' => $path,
            'total' => 0,
            'used' => 0,
            'free' => 0,
            'usage_percent' => 0,
        ];

        try {
            $total = disk_total_space($path);
            $free = disk_free_space($path);
            $used = $total - $free;

            $metrics['total'] = $total;
            $metrics['used'] = $used;
            $metrics['free'] = $free;
            $metrics['usage_percent'] = $total > 0 ? round(($used / $total) * 100, 2) : 0;
            $metrics['total_human'] = $this->formatBytes($total);
            $metrics['used_human'] = $this->formatBytes($used);
            $metrics['free_human'] = $this->formatBytes($free);
        } catch (\Throwable $e) {
            Log::warning('Failed to get filesystem metrics', ['error' => $e->getMessage()]);
        }

        return $metrics;
    }

    /**
     * Get number of processes in container
     *
     * @return array<string, int>
     */
    private function getContainerPids(): array
    {
        $metrics = ['current' => 0, 'limit' => 0];

        try {
            if (is_readable(self::CONTAINER_LIMITS_PATH.'/pids.current')) {
                $metrics['current'] = (int) file_get_contents(self::CONTAINER_LIMITS_PATH.'/pids.current');
                $metrics['limit'] = (int) file_get_contents(self::CONTAINER_LIMITS_PATH.'/pids.max');
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to get container PID metrics', ['error' => $e->getMessage()]);
        }

        return $metrics;
    }

    /**
     * Get PHP-specific metrics
     *
     * @return array<string, mixed>
     */
    private function getPhpMetrics(): array
    {
        return [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'opcache_enabled' => function_exists('opcache_get_status') &&
                               opcache_get_status(false)['opcache_enabled'] ?? false,
        ];
    }

    /**
     * Check if running in a container
     *
     * @return bool
     */
    private function isRunningInContainer(): bool
    {
        return
            is_readable('/.dockerenv') ||
            is_readable('/run/.containerenv') ||
            str_contains(file_get_contents('/proc/1/cgroup'), 'docker') ||
            str_contains(file_get_contents('/proc/self/cgroup'), 'docker');
    }

    /**
     * Get container ID
     *
     * @return string|null
     */
    private function getContainerId(): ?string
    {
        try {
            $cgroup = file_get_contents('/proc/self/cgroup');
            if (preg_match('/docker[/-]([a-f0-9]{64})/', $cgroup, $matches)) {
                return $matches[1];
            }
        } catch (\Throwable) {
            // Failed to get container ID
        }

        return null;
    }

    /**
     * Detect container runtime
     *
     * @return string
     */
    private function detectContainerRuntime(): string
    {
        if (is_readable('/.dockerenv')) {
            return 'docker';
        }
        if (is_readable('/run/.containerenv')) {
            return 'podman';
        }
        if (getenv('KUBERNETES_SERVICE_HOST')) {
            return 'kubernetes';
        }

        return 'unknown';
    }
}
