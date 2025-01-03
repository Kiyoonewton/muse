<?php

declare(strict_types=1);

namespace App\Health\Events;

use App\Health\DTOs\HealthCheckResult;

/**
 * Event fired when a health check completes (regardless of status)
 */
class HealthCheckCompletedEvent extends HealthCheckEvent
{
    /**
     * Create a new event instance
     */
    public function __construct(
        string $checkName,
        HealthCheckResult $result,
        public readonly float $executionTime
    ) {
        parent::__construct($checkName, $result);
    }
}
