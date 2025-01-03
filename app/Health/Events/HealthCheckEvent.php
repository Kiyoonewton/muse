<?php

declare(strict_types=1);

namespace App\Health\Events;

use App\Health\DTOs\HealthCheckResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for health check events
 */
abstract class HealthCheckEvent
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance
     */
    public function __construct(
        public readonly string $checkName,
        public readonly HealthCheckResult $result
    ) {
    }
}
