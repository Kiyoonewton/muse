// app/Health/Events/HealthCheckStartingEvent.php
<?php

declare(strict_types=1);

namespace App\Health\Events;

/**
 * Event fired before a health check starts
 */
class HealthCheckStartingEvent
{
    use Dispatchable;

    /**
     * Create a new event instance
     */
    public function __construct(
        public readonly string $checkName
    ) {
    }
}
