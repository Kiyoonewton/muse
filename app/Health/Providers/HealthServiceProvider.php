<?php

declare(strict_types=1);

namespace App\Health\Providers;

use App\Console\Commands\HealthCheckCommand;
use App\Health\Contracts\HealthCheckInterface;
use App\Health\Events\HealthCheckCompletedEvent;
use App\Health\Events\HealthCheckFailedEvent;
use App\Health\Events\HealthCheckWarningEvent;
use App\Health\Facades\Health;
use App\Health\Services\HealthService;
use App\Http\Controllers\HealthController;
use Illuminate\Cache\Repository as Cache;
use Illuminate\Config\Repository as Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for health checks
 */
class HealthServiceProvider extends ServiceProvider
{
    /**
     * Register health service bindings
     */
    public function register(): void
    {
        // Register config
        $this->mergeConfigFrom(
            __DIR__.'/../../../config/health.php',
            'health'
        );

        // Register Health Service singleton
        $this->app->singleton(HealthService::class, function ($app) {
            return new HealthService(
                config: $app['config'],
                cache: $app['cache.store'],
                logger: $app['log']
            );
        });

        // Register Health facade
        $this->app->alias(HealthService::class, 'health');

        // Register event listeners
        $this->registerEventListeners();
    }

    /**
     * Bootstrap health check services
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerRoutes();
        $this->registerHealthChecks();
        $this->registerScheduledTasks();
    }

    /**
     * Register publishing
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Config
            $this->publishes([
                __DIR__.'/../../../config/health.php' => config_path('health.php'),
            ], 'health-config');

            // Views
            $this->publishes([
                __DIR__.'/../../../resources/views' => resource_path('views/vendor/health'),
            ], 'health-views');

            // Migrations
            $this->publishes([
                __DIR__.'/../../../database/migrations' => database_path('migrations'),
            ], 'health-migrations');
        }
    }

    /**
     * Register commands
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                HealthCheckCommand::class,
            ]);
        }
    }

    /**
     * Register health check routes
     */
    protected function registerRoutes(): void
    {
        if (! config('health.routes.enabled', true)) {
            return;
        }

        Route::group($this->routeConfiguration(), function () {
            Route::get('/health/check', [HealthController::class, 'check'])
                ->name('health.check')
                ->middleware(config('health.routes.middleware.check', []));

            Route::get('/health/status', [HealthController::class, 'status'])
                ->name('health.status')
                ->middleware(config('health.routes.middleware.status', []));

            if (config('health.routes.ping', true)) {
                Route::get('/health/ping', [HealthController::class, 'ping'])
                    ->name('health.ping')
                    ->middleware(config('health.routes.middleware.ping', []));
            }

            if (config('health.routes.metrics', true)) {
                Route::get('/health/metrics', [HealthController::class, 'metrics'])
                    ->name('health.metrics')
                    ->middleware(config('health.routes.middleware.metrics', []));
            }

            if (config('health.routes.docs', true)) {
                Route::get('/health/docs', [HealthController::class, 'documentation'])
                    ->name('health.docs')
                    ->middleware(config('health.routes.middleware.docs', []));
            }
        });
    }

    /**
     * Get route group configuration
     *
     * @return array<string, mixed>
     */
    protected function routeConfiguration(): array
    {
        return [
            'prefix' => config('health.routes.prefix', ''),
            'middleware' => config('health.routes.middleware.group', ['web']),
            'domain' => config('health.routes.domain', null),
            'name' => config('health.routes.name', 'health.'),
        ];
    }

    /**
     * Register event listeners
     */
    protected function registerEventListeners(): void
    {
        Event::listen(HealthCheckFailedEvent::class, function ($event) {
            Log::error("Health check failed: {$event->checkName}", [
                'check' => $event->checkName,
                'error' => $event->result->error(),
                'metadata' => $event->result->metadata(),
            ]);

            if (config('health.notifications.on_failure', true)) {
                // Send notifications
                $this->sendFailureNotification($event);
            }
        });

        Event::listen(HealthCheckWarningEvent::class, function ($event) {
            Log::warning("Health check warning: {$event->checkName}", [
                'check' => $event->checkName,
                'message' => $event->result->message(),
                'metadata' => $event->result->metadata(),
            ]);

            if (config('health.notifications.on_warning', false)) {
                // Send notifications
                $this->sendWarningNotification($event);
            }
        });

        Event::listen(HealthCheckCompletedEvent::class, function ($event) {
            Log::info("Health check completed: {$event->checkName}", [
                'check' => $event->checkName,
                'status' => $event->result->status()->value,
                'execution_time' => $event->executionTime,
            ]);
        });
    }

    /**
     * Register configured health checks
     */
    protected function registerHealthChecks(): void
    {
        $healthService = $this->app->make(HealthService::class);
        $checks = config('health.checks', []);

        foreach ($checks as $name => $check) {
            if (! isset($check['enabled']) || ! $check['enabled']) {
                continue;
            }

            if (! isset($check['class'])) {
                Log::warning("Health check '{$name}' has no class defined");
                continue;
            }

            $class = $check['class'];

            if (! class_exists($class)) {
                Log::warning("Health check class not found: {$class}");
                continue;
            }

            if (! is_subclass_of($class, HealthCheckInterface::class)) {
                Log::warning("Invalid health check class: {$class}");
                continue;
            }

            try {
                $healthService->registerCheck($class);
                Log::debug("Registered health check: {$name}");
            } catch (\Throwable $e) {
                Log::error("Failed to register health check: {$class}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * Register scheduled tasks
     */
    protected function registerScheduledTasks(): void
    {
        if (! config('health.schedule.enabled', true)) {
            return;
        }

        $this->app->booted(function () {
            $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);

            // Regular health checks
            $schedule->command('health:check --notify')
                ->cron(config('health.schedule.cron', '*/5 * * * *'))
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/health-checks.log'));

            // Daily cache cleanup
            $schedule->command('health:check --no-cache')
                ->daily()
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/health-checks.log'));

            // Weekly report generation
            if (config('health.schedule.weekly_report', false)) {
                $schedule->command('health:report --type=weekly')
                    ->weekly()
                    ->withoutOverlapping()
                    ->appendOutputTo(storage_path('logs/health-reports.log'));
            }
        });
    }

    /**
     * Send failure notification
     */
    protected function sendFailureNotification($event): void
    {
        // Implementation depends on your notification system
    }

    /**
     * Send warning notification
     */
    protected function sendWarningNotification($event): void
    {
        // Implementation depends on your notification system
    }

    /**
     * Get the services provided by the provider
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            HealthService::class,
            'health',
        ];
    }
}
