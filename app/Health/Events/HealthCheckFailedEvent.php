<?php

declare(strict_types=1);

namespace App\Health\Events;

use App\Health\DTOs\HealthCheckResult;

/**
 * Event fired when a health check fails
 */
class HealthCheckFailedEvent extends HealthCheckEvent
{
    /**
     * Create a new event instance
     */
    public function __construct(
        string $checkName,
        HealthCheckResult $result,
        public readonly ?\Throwable $exception = null
    ) {
        parent::__construct($checkName, $result);
    }
}
