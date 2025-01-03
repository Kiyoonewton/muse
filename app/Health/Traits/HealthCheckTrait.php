<?php

namespace App\Health\Traits;

trait HealthCheckTrait
{
    public function displayName(): string
    {
        return ucfirst(str_replace('_', ' ', $this->name()));
    }

    public function description(): string
    {
        return 'Checks the health of '.$this->displayName();
    }

    public function severity(): int
    {
        return 1;
    }

    public function isCritical(): bool
    {
        return false;
    }

    public function timeout(): int
    {
        return 30;
    }

    public function isEnabled(): bool
    {
        return config("health.checks.{$this->name()}.enabled", true);
    }

    public function tags(): array
    {
        return [];
    }

    public function minimumInterval(): int
    {
        return config("health.checks.{$this->name()}.interval", 60);
    }

    public function dependencies(): array
    {
        return [];
    }

    public function handleFailure(\Throwable $exception): void
    {
        logger()->error("Health check {$this->name()} failed", [
            'check' => $this->name(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    public function metadata(): array
    {
        return [
            'display_name' => $this->displayName(),
            'description' => $this->description(),
            'severity' => $this->severity(),
            'is_critical' => $this->isCritical(),
            'timeout' => $this->timeout(),
            'interval' => $this->minimumInterval(),
            'tags' => $this->tags(),
            'dependencies' => $this->dependencies(),
        ];
    }
}
