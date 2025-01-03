<?php

namespace App\Helper;

use App\LogstashLogger;
use App\Models\Blog;
use Exception;

class HealthCheck
{
    public function checkDatabaseConnection()
    {
        try {
            $blog = Blog::inRandomOrder()->first();

            return true;
        } catch (Exception $exception) {
            return false;
        }
    }

    public function checkLogstashConnection()
    {
        try {
            $logger = (new LogstashLogger())(['host' => env('LOGSTASH_HOST'), 'port' => env('LOGSTASH_PORT')]);
            $handler = $logger->getHandlers()[0];
            $logger->info('connection check!');
            $isConnected = $handler->isConnected();
            $logger->close();

            return $isConnected;
        } catch (Exception $exception) {
            return false;
        }
    }
}
