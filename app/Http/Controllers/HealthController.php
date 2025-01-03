<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Health\Services\HealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Controller for health check endpoints
 */
class HealthController extends Controller
{
    /**
     * Create a new controller instance
     */
    public function __construct(
       private readonly HealthService $healthService
   ) {
        // Apply health check middleware if configured
        if ($middleware = config('health.routes.middleware')) {
            $this->middleware($middleware);
        }
    }

    /**
     * Run health checks and return results
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function check(Request $request): JsonResponse
    {
        try {
            // Handle specific check request
            if ($checkName = $request->get('check')) {
                return $this->runSingleCheck($checkName);
            }

            // Handle critical-only checks
            if ($request->boolean('critical')) {
                $results = $this->healthService->runCriticalChecks();
            } else {
                // Run all checks
                $results = $this->healthService->runChecks();
            }

            $isHealthy = $results->every(fn ($result) => $result->isHealthy());
            $status = $isHealthy ? 200 : 503;

            $response = [
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'timestamp' => now()->toIso8601String(),
                'checks' => $results->map->toArray(),
                'meta' => [
                    'total_checks' => $results->count(),
                    'healthy_checks' => $results->filter->isHealthy()->count(),
                    'unhealthy_checks' => $results->reject->isHealthy()->count(),
                    'duration_ms' => round(microtime(true) - LARAVEL_START, 2),
                ],
            ];

            return new JsonResponse($response, $status);
        } catch (\Throwable $e) {
            report($e);
            throw new ServiceUnavailableHttpException(
                null,
                'Failed to run health checks: '.$e->getMessage()
            );
        }
    }

    /**
     * Get cached health status
     *
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        try {
            $results = $this->healthService->getCachedResults();
            $isHealthy = $results->every(fn ($result) => $result->isHealthy());

            return new JsonResponse(
                [
                    'status' => $isHealthy ? 'healthy' : 'unhealthy',
                    'timestamp' => now()->toIso8601String(),
                    'checks' => $results->map->toArray(),
                ],
                $isHealthy ? 200 : 503
            );
        } catch (\Throwable $e) {
            report($e);
            throw new ServiceUnavailableHttpException(
                null,
                'Failed to get health status: '.$e->getMessage()
            );
        }
    }

    /**
     * Simple ping endpoint for load balancers
     *
     * @return Response
     */
    public function ping(): Response
    {
        try {
            // Check if the application is in maintenance mode
            if (app()->isDownForMaintenance()) {
                throw new ServiceUnavailableHttpException(null, 'Application is in maintenance mode');
            }

            // Optional: Run critical checks
            if (config('health.ping.check_critical', false)) {
                $results = $this->healthService->runCriticalChecks();
                if ($results->some(fn ($result) => ! $result->isHealthy())) {
                    throw new ServiceUnavailableHttpException(null, 'Critical checks failed');
                }
            }

            return response('pong', 200)
                ->header('Content-Type', 'text/plain');
        } catch (\Throwable $e) {
            report($e);
            throw new ServiceUnavailableHttpException(
                null,
                'Service unavailable: '.$e->getMessage()
            );
        }
    }

    /**
     * Return health metrics in Prometheus format
     *
     * @return Response
     */
    public function metrics(): Response
    {
        try {
            $results = $this->healthService->getCachedResults();
            $metrics = [];

            foreach ($results as $result) {
                $checkName = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $result->checkName()));

                // Health status metric
                $metrics[] = "# HELP health_check_status Status of {$checkName} health check";
                $metrics[] = '# TYPE health_check_status gauge';
                $metrics[] = sprintf(
                    'health_check_status{check="%s"} %d',
                    $checkName,
                    $result->isHealthy() ? 1 : 0
                );

                // Response time metric
                $metrics[] = "# HELP health_check_response_time_ms Response time of {$checkName} health check";
                $metrics[] = '# TYPE health_check_response_time_ms gauge';
                $metrics[] = sprintf(
                    'health_check_response_time_ms{check="%s"} %.2f',
                    $checkName,
                    $result->responseTime()
                );
            }

            return response(implode("\n", $metrics), 200)
                ->header('Content-Type', 'text/plain');
        } catch (\Throwable $e) {
            report($e);
            throw new ServiceUnavailableHttpException(
                null,
                'Failed to generate metrics: '.$e->getMessage()
            );
        }
    }

    /**
     * Return health check documentation
     *
     * @return JsonResponse
     */
    public function documentation(): JsonResponse
    {
        try {
            $docs = collect($this->healthService->getRegisteredChecks())
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
                });

            return new JsonResponse([
                'version' => config('health.version', '1.0.0'),
                'endpoints' => [
                    'check' => [
                        'url' => route('health.check'),
                        'method' => 'GET',
                        'description' => 'Run health checks',
                    ],
                    'status' => [
                        'url' => route('health.status'),
                        'method' => 'GET',
                        'description' => 'Get cached health status',
                    ],
                    'ping' => [
                        'url' => route('health.ping'),
                        'method' => 'GET',
                        'description' => 'Simple ping endpoint',
                    ],
                    'metrics' => [
                        'url' => route('health.metrics'),
                        'method' => 'GET',
                        'description' => 'Prometheus metrics endpoint',
                    ],
                ],
                'checks' => $docs,
            ]);
        } catch (\Throwable $e) {
            report($e);
            throw new ServiceUnavailableHttpException(
                null,
                'Failed to get documentation: '.$e->getMessage()
            );
        }
    }

    /**
     * Run a single health check
     *
     * @param string $checkName
     * @return JsonResponse
     */
    private function runSingleCheck(string $checkName): JsonResponse
    {
        if (! $this->healthService->hasCheck($checkName)) {
            return new JsonResponse(
                ['error' => "Health check not found: {$checkName}"],
                404
            );
        }

        try {
            $result = $this->healthService->runCheck($checkName);

            return new JsonResponse(
                [
                    'status' => $result->isHealthy() ? 'healthy' : 'unhealthy',
                    'timestamp' => now()->toIso8601String(),
                    'check' => $result->toArray(),
                ],
                $result->isHealthy() ? 200 : 503
            );
        } catch (\Throwable $e) {
            report($e);
            throw new ServiceUnavailableHttpException(
                null,
                "Failed to run health check '{$checkName}': ".$e->getMessage()
            );
        }
    }
}
