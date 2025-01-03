<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use App\Health\Services\HealthService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;

/**
 * Command to run health checks from the CLI
 */
class HealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command
     *
     * @var string
     */
    protected $signature = 'health:check
                         {check? : Optional specific check to run}
                         {--critical : Run only critical checks}
                         {--format=table : Output format (table, json, prometheus)}
                         {--no-cache : Skip cache and force fresh checks}
                         {--notify : Send notifications for failures}
                         {--timeout=30 : Maximum execution time in seconds}';

    /**
     * The console command description
     *
     * @var string
     */
    protected $description = 'Run application health checks';

    /**
     * Execute the console command
     */
    public function handle(HealthService $healthService): int
    {
        try {
            // Set execution timeout
            $timeout = (int) $this->option('timeout');
            set_time_limit($timeout);

            // Clear cache if requested
            if ($this->option('no-cache')) {
                $healthService->clearCache();
            }

            // Run specific check if provided
            if ($check = $this->argument('check')) {
                return $this->runSingleCheck($healthService, $check);
            }

            // Run critical checks only if requested
            $results = $this->option('critical')
                ? $healthService->runCriticalChecks()
                : $healthService->runChecks();

            // Handle empty results
            if ($results->isEmpty()) {
                $this->error('No health checks configured!');

                return self::FAILURE;
            }

            // Output results in requested format
            $this->outputResults($results);

            // Send notifications if requested
            if ($this->option('notify')) {
                $this->sendNotifications($results);
            }

            // Return appropriate exit code
            return $results->every(fn ($result) => $result->isHealthy())
                ? self::SUCCESS
                : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error("Failed to run health checks: {$e->getMessage()}");
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    /**
     * Run a single health check
     *
     * @param HealthService $healthService
     * @param string $checkName
     * @return int
     */
    private function runSingleCheck(HealthService $healthService, string $checkName): int
    {
        try {
            if (! $healthService->hasCheck($checkName)) {
                $this->error("Health check not found: {$checkName}");

                return self::FAILURE;
            }

            $result = $healthService->runCheck($checkName);
            $this->outputResults(collect([$result]));

            return $result->isHealthy() ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error("Failed to run health check '{$checkName}': {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Output results in the requested format
     *
     * @param \Illuminate\Support\Collection<HealthCheckResult> $results
     */
    private function outputResults($results): void
    {
        match ($this->option('format')) {
            'json' => $this->outputJson($results),
            'prometheus' => $this->outputPrometheus($results),
            default => $this->outputTable($results)
        };
    }

    /**
     * Output results as a table
     *
     * @param \Illuminate\Support\Collection<HealthCheckResult> $results
     */
    private function outputTable($results): void
    {
        $rows = $results->map(function (HealthCheckResult $result) {
            return [
                $result->checkName(),
                $this->formatStatus($result->status()),
                "{$result->responseTime()}ms",
                $result->error() ?? $result->message() ?? '-',
            ];
        });

        $this->newLine();
        $this->table(
            ['Check', 'Status', 'Response Time', 'Message'],
            $rows
        );

        $this->outputSummary($results);
    }

    /**
     * Output results as JSON
     *
     * @param \Illuminate\Support\Collection<HealthCheckResult> $results
     */
    private function outputJson($results): void
    {
        $json = [
            'status' => $results->every(fn ($r) => $r->isHealthy()) ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $results->map->toArray(),
        ];

        $this->line(json_encode($json, JSON_PRETTY_PRINT));
    }

    /**
     * Output results in Prometheus format
     *
     * @param \Illuminate\Support\Collection<HealthCheckResult> $results
     */
    private function outputPrometheus($results): void
    {
        $metrics = $results->map(function (HealthCheckResult $result) {
            $checkName = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $result->checkName()));

            return [
                "# HELP health_check_status Status of {$checkName} health check",
                '# TYPE health_check_status gauge',
                "health_check_status{check=\"{$checkName}\"} ".
                    ($result->isHealthy() ? '1' : '0'),
                '',
                "# HELP health_check_response_time_ms Response time of {$checkName} health check",
                '# TYPE health_check_response_time_ms gauge',
                "health_check_response_time_ms{check=\"{$checkName}\"} {$result->responseTime()}",
            ];
        })->flatten();

        $this->line($metrics->join("\n"));
    }

    /**
     * Output summary of check results
     *
     * @param \Illuminate\Support\Collection<HealthCheckResult> $results
     */
    private function outputSummary($results): void
    {
        $total = $results->count();
        $healthy = $results->filter->isHealthy()->count();
        $warnings = $results->filter->hasWarning()->count();
        $failed = $total - $healthy - $warnings;

        $this->newLine();
        $this->line('Summary:');
        $this->line("Total Checks: {$total}");
        $this->line("✅ Healthy: {$healthy}");

        if ($warnings > 0) {
            $this->warn("⚠️  Warnings: {$warnings}");
        }

        if ($failed > 0) {
            $this->error("❌ Failed: {$failed}");
        }

        $this->newLine();
    }

    /**
     * Format status for display
     *
     * @param HealthStatus $status
     * @return string
     */
    private function formatStatus(HealthStatus $status): string
    {
        return match ($status) {
            HealthStatus::HEALTHY => '<fg=green>✓ Healthy</>',
            HealthStatus::WARNING => '<fg=yellow>⚠ Warning</>',
            HealthStatus::UNHEALTHY => '<fg=red>✗ Unhealthy</>'
        };
    }

    /**
     * Send notifications for failed checks
     *
     * @param \Illuminate\Support\Collection<HealthCheckResult> $results
     */
    private function sendNotifications($results): void
    {
        $failedChecks = $results->reject->isHealthy();

        if ($failedChecks->isNotEmpty()) {
            $notifications = config('health.notifications', []);

            foreach ($notifications as $notification) {
                try {
                    \Notification::route($notification['channel'], $notification['route'])
                        ->notify(new \App\Health\Notifications\HealthCheckFailed($failedChecks));
                } catch (\Throwable $e) {
                    $this->error("Failed to send notification: {$e->getMessage()}");
                }
            }
        }
    }

    /**
     * Get the list of available checks
     *
     * @param HealthService $healthService
     * @return array<string>
     */
    public function getChecks(HealthService $healthService): array
    {
        return $healthService->getRegisteredChecks();
    }
}
