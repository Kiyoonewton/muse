<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;

class KafkaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/kafka.php', 'kafka');

        $this->app->singleton('kafka.producer', function ($app) {
            $conf = new Conf();
            $conf->set('metadata.broker.list', config('kafka.brokers'));

            if (config('kafka.security.enabled')) {
                $conf->set('security.protocol', config('kafka.security.protocol'));
                $conf->set('sasl.mechanisms', config('kafka.security.mechanisms'));
                $conf->set('sasl.username', config('kafka.security.username'));
                $conf->set('sasl.password', config('kafka.security.password'));
            }

            if (config('kafka.debug.enabled')) {
                $conf->set('log_level', (string) LOG_DEBUG);
                $conf->set('debug', 'all');
            }

            return new Producer($conf);
        });

        $this->app->singleton('kafka.consumer', function ($app) {
            $conf = new Conf();
            $conf->set('metadata.broker.list', config('kafka.brokers'));
            $conf->set('group.id', config('kafka.consumer.group_id'));

            foreach (config('kafka.consumer.options', []) as $key => $value) {
                $conf->set($key, $value);
            }

            if (config('kafka.security.enabled')) {
                $conf->set('security.protocol', config('kafka.security.protocol'));
                $conf->set('sasl.mechanisms', config('kafka.security.mechanisms'));
                $conf->set('sasl.username', config('kafka.security.username'));
                $conf->set('sasl.password', config('kafka.security.password'));
            }

            return new KafkaConsumer($conf);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/kafka.php' => config_path('kafka.php'),
            ], 'kafka-config');
        }
    }
}
