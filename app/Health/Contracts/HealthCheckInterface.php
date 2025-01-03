<?php

declare(strict_types=1);

namespace App\Health\Contracts;

use App\Health\DTOs\HealthCheckResult;

/**
 * Interface for health check implementations
 */
interface HealthCheckInterface
{
    /**
     * Get the name of the health check
     * This should be a unique identifier for the check
     *
     * @return string
     */
    public function name(): string;

    /**
     * Execute the health check
     * Should return a HealthCheckResult object containing the check status and metadata
     *
     * @return HealthCheckResult
     * @throws \App\Health\Exceptions\HealthCheckException When check execution fails
     */
    public function check(): HealthCheckResult;

    /**
     * Get the display name of the health check
     * This is used for human-readable output
     *
     * @return string
     */
    public function displayName(): string;

    /**
     * Get check description
     * This should describe what the check does and what it's monitoring
     *
     * @return string
     */
    public function description(): string;

    /**
     * Get the severity level of the check
     * Higher values indicate more critical checks
     *
     * @return int
     */
    public function severity(): int;

    /**
     * Returns whether the check is critical
     * Critical checks failing will mark the entire health status as unhealthy
     *
     * @return bool
     */
    public function isCritical(): bool;

    /**
     * Get the check timeout in seconds
     * After this time, the check will be considered failed
     *
     * @return int
     */
    public function timeout(): int;

    /**
     * Check if the health check is enabled
     * Allows for dynamic enabling/disabling of checks
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Get the check's tags
     * Tags can be used to group and filter checks
     *
     * @return array<string>
     */
    public function tags(): array;

    /**
     * Get the minimum interval between check executions in seconds
     * This helps prevent too frequent check executions
     *
     * @return int
     */
    public function minimumInterval(): int;

    /**
     * Get check dependencies
     * Returns array of check names that must pass before this check runs
     *
     * @return array<string>
     */
    public function dependencies(): array;

    /**
     * Handle check failure
     * Called when check fails, can be used for notifications, logging, etc.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function handleFailure(\Throwable $exception): void;

    /**
     * Get check metadata
     * Additional information about the check configuration
     *
     * @return array<string, mixed>
     */
    public function metadata(): array;
}
