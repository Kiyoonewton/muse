// app/Health/Events/HealthCheckSkippedEvent.php
<?php

declare(strict_types=1);

namespace App\Health\Events;

/**
 * Event fired when a health check is skipped
 */
class HealthCheckSkippedEvent
{
    use Dispatchable;

    /**
     * Create a new event instance
     */
    public function __construct(
        public readonly string $checkName,
        public readonly string $reason
    ) {
    }
}
