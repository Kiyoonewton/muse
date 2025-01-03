<?php

declare(strict_types=1);

namespace App\Health\Checks;

use App\Health\Contracts\HealthCheckInterface;
use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use App\Health\Exceptions\HealthCheckException;
use App\Health\Traits\HealthCheckTrait;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use RdKafka\Conf;
use RdKafka\Exception as RdKafkaException;
use RdKafka\KafkaConsumer;
use RdKafka\Metadata;
use RdKafka\Producer;
use RdKafka\Topic;

/**
 * Kafka health check implementation
 */
class KafkaHealthCheck implements HealthCheckInterface
{
    use HealthCheckTrait;

    /**
     * Default timeout for Kafka operations in milliseconds
     */
    private const DEFAULT_TIMEOUT = 5000;

    /**
     * Default thresholds for consumer lag (messages)
     */
    private const DEFAULT_LAG_WARNING = 1000;

    private const DEFAULT_LAG_ERROR = 5000;

    /**
     * Execute the health check
     */
    public function check(): HealthCheckResult
    {
        $startTime = microtime(true);

        // Early return if check is disabled
        if (! $this->isEnabled()) {
            return new HealthCheckResult(
                checkName: $this->name(),
                status: HealthStatus::WARNING,
                responseTime: $this->calculateResponseTime($startTime),
                message: 'Kafka health check is disabled'
            );
        }

        // Early return if brokers not configured
        if (empty($this->getBrokerList())) {
            return new HealthCheckResult(
                checkName: $this->name(),
                status: HealthStatus::WARNING,
                responseTime: $this->calculateResponseTime($startTime),
                message: 'Kafka brokers not configured'
            );
        }

        try {
            // Create producer for metadata checks
            $producer = $this->createProducer();
            $metadata = $this->getBrokerMetadata($producer);

            $metrics = $this->gatherBrokerMetrics($metadata);

            // Check if we have enough brokers
            if ($metrics['broker_count'] < $this->getMinBrokerCount()) {
                $message = sprintf(
                    'Insufficient broker count: %d (minimum required: %d)',
                    $metrics['broker_count'],
                    $this->getMinBrokerCount()
                );
                Log::warning('Kafka health check: '.$message, $metrics);

                return new HealthCheckResult(
                    checkName: $this->name(),
                    status: HealthStatus::WARNING,
                    responseTime: $this->calculateResponseTime($startTime),
                    metadata: $metrics,
                    message: $message
                );
            }

            // Check topics accessibility
            $topicStatus = $this->checkTopics($metadata);
            if ($topicStatus['status'] !== HealthStatus::HEALTHY->value) {
                return new HealthCheckResult(
                    checkName: $this->name(),
                    status: HealthStatus::from($topicStatus['status']),
                    responseTime: $this->calculateResponseTime($startTime),
                    metadata: [...$metrics, ...$topicStatus['metrics']],
                    message: $topicStatus['message'] ?? 'Topic accessibility issues detected'
                );
            }

            // Check consumer groups if enabled
            if ($this->isConsumerEnabled()) {
                $consumerStatus = $this->checkConsumerGroups();
                if ($consumerStatus['status'] !== HealthStatus::HEALTHY->value) {
                    return new HealthCheckResult(
                        checkName: $this->name(),
                        status: HealthStatus::from($consumerStatus['status']),
                        responseTime: $this->calculateResponseTime($startTime),
                        metadata: [...$metrics, ...$consumerStatus['metrics']],
                        message: $consumerStatus['message'] ?? 'Consumer group issues detected'
                    );
                }
            }

            // Test producer functionality
            $producerStatus = $this->testProducer($producer);
            if (! $producerStatus['success']) {
                return new HealthCheckResult(
                    checkName: $this->name(),
                    status: HealthStatus::UNHEALTHY,
                    responseTime: $this->calculateResponseTime($startTime),
                    metadata: $metrics,
                    message: $producerStatus['error'] ?? 'Producer test failed'
                );
            }

            // All checks passed
            return new HealthCheckResult(
                checkName: $this->name(),
                status: HealthStatus::HEALTHY,
                responseTime: $this->calculateResponseTime($startTime),
                metadata: [
                    ...$metrics,
                    'producer_test' => 'passed',
                    'consumer_enabled' => $this->isConsumerEnabled(),
                ]
            );
        } catch (RdKafkaException $e) {
            Log::error('Kafka health check failed with RdKafka error', [
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new HealthCheckResult(
                checkName: $this->name(),
                status: HealthStatus::WARNING,
                responseTime: $this->calculateResponseTime($startTime),
                message: 'Kafka check failed: '.$e->getMessage()
            );
        } catch (\Throwable $e) {
            Log::error('Kafka health check failed with unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new HealthCheckResult(
                checkName: $this->name(),
                status: HealthStatus::WARNING,
                responseTime: $this->calculateResponseTime($startTime),
                message: 'Kafka check failed: '.$e->getMessage()
            );
        }
    }

    /**
     * Check topics accessibility and configuration
     *
     * @param Metadata $metadata
     * @return array{status: string, message?: string, metrics: array<string, mixed>}
     */
    private function checkTopics(Metadata $metadata): array
    {
        $requiredTopics = Config::get('kafka.required_topics', []);
        $topicMetrics = [];
        $missingTopics = [];

        foreach ($metadata->getTopics() as $topic) {
            $name = $topic->getTopic();
            $partitions = $topic->getPartitions();

            $topicMetrics[$name] = [
                'partition_count' => count($partitions),
                'error_code' => $topic->getErr(),
                'has_error' => $topic->getErr() !== RD_KAFKA_RESP_ERR_NO_ERROR,
            ];

            if (in_array($name, $requiredTopics) && $topic->getErr() !== RD_KAFKA_RESP_ERR_NO_ERROR) {
                $missingTopics[] = $name;
            }
        }

        if (! empty($missingTopics)) {
            return [
                'status' => HealthStatus::WARNING->value,
                'message' => 'Required topics not accessible: '.implode(', ', $missingTopics),
                'metrics' => [
                    'topics' => $topicMetrics,
                    'missing_required_topics' => $missingTopics,
                ],
            ];
        }

        if (empty($topicMetrics)) {
            return [
                'status' => HealthStatus::WARNING->value,
                'message' => 'No topics found in Kafka cluster',
                'metrics' => ['topics' => []],
            ];
        }

        return [
            'status' => HealthStatus::HEALTHY->value,
            'metrics' => [
                'topics' => $topicMetrics,
            ],
        ];
    }

    /**
     * Check consumer groups health
     *
     * @return array{status: string, message?: string, metrics: array<string, mixed>}
     */
    private function checkConsumerGroups(): array
    {
        try {
            $consumer = $this->createConsumer();
            $subscription = Config::get('kafka.consumer.topics', []);

            if (empty($subscription)) {
                return [
                    'status' => HealthStatus::WARNING->value,
                    'message' => 'No consumer topics configured',
                    'metrics' => ['consumer_enabled' => false],
                ];
            }

            $consumer->subscribe($subscription);

            // Check consumer lag
            $metrics = $this->getConsumerMetrics($consumer);

            if ($metrics['total_lag'] > self::DEFAULT_LAG_ERROR) {
                return [
                    'status' => HealthStatus::WARNING->value,
                    'message' => sprintf('High consumer lag detected: %d messages', $metrics['total_lag']),
                    'metrics' => $metrics,
                ];
            }

            if ($metrics['total_lag'] > self::DEFAULT_LAG_WARNING) {
                return [
                    'status' => HealthStatus::WARNING->value,
                    'message' => sprintf('Consumer lag warning: %d messages', $metrics['total_lag']),
                    'metrics' => $metrics,
                ];
            }

            return [
                'status' => HealthStatus::HEALTHY->value,
                'metrics' => $metrics,
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to check consumer groups', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => HealthStatus::WARNING->value,
                'message' => 'Failed to check consumer groups: '.$e->getMessage(),
                'metrics' => [
                    'consumer_error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Get check name
     */
    public function name(): string
    {
        return 'kafka';
    }

    /**
     * Get check display name
     */
    public function displayName(): string
    {
        return 'Apache Kafka';
    }

    /**
     * Get check description
     */
    public function description(): string
    {
        return 'Checks Kafka broker connectivity, consumer groups, and producer functionality';
    }

    /**
     * Get check timeout in seconds
     */
    public function timeout(): int
    {
        // Convert milliseconds to seconds
        return (int) ceil($this->getTimeoutMs() / 1000);
    }

    /**
     * Get minimum interval between checks in seconds
     */
    public function minimumInterval(): int
    {
        return Config::get('kafka.health_check.interval', 60);
    }

    /**
     * Get check severity
     */
    public function severity(): int
    {
        return 2; // High severity
    }

    /**
     * Is this check critical?
     */
    public function isCritical(): bool
    {
        return Config::get('kafka.health_check.critical', true);
    }

    /**
     * Is this check enabled?
     */
    public function isEnabled(): bool
    {
        return Config::get('kafka.health_check.enabled', false) &&
               ! empty($this->getBrokerList());
    }

    /**
     * Get check tags
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return ['messaging', 'kafka', 'core'];
    }

    /**
     * Get check dependencies
     *
     * @return array<string>
     */
    public function dependencies(): array
    {
        return [];
    }

    /**
     * Handle check failure
     */
    public function handleFailure(\Throwable $exception): void
    {
        Log::error('Kafka health check failed', [
            'check' => $this->name(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Calculate response time in milliseconds
     */
    private function calculateResponseTime(float $startTime): float
    {
        return (microtime(true) - $startTime) * 1000;
    }

    /**
     * Get configured broker list
     */
    private function getBrokerList(): string
    {
        return Config::get('kafka.brokers', '');
    }

    /**
     * Get timeout in milliseconds
     */
    private function getTimeoutMs(): int
    {
        return (int) Config::get('kafka.health_check.timeout', self::DEFAULT_TIMEOUT);
    }

    /**
     * Get minimum required broker count
     */
    private function getMinBrokerCount(): int
    {
        return (int) Config::get('kafka.health_check.min_brokers', 1);
    }

    /**
     * Check if consumer is enabled
     */
    private function isConsumerEnabled(): bool
    {
        return ! empty(Config::get('kafka.consumer.topics', []));
    }

    /**
     * Create Kafka producer instance
     *
     * @throws HealthCheckException
     */
    private function createProducer(): Producer
    {
        try {
            $conf = new Conf();
            $conf->set('metadata.broker.list', $this->getBrokerList());
            $conf->set('socket.timeout.ms', (string) $this->getTimeoutMs());

            return new Producer($conf);
        } catch (\Throwable $e) {
            throw new HealthCheckException(
                "Failed to create Kafka producer: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Get broker metadata
     *
     * @throws HealthCheckException
     */
    private function getBrokerMetadata(Producer $producer): Metadata
    {
        try {
            return $producer->getMetadata(true, null, $this->getTimeoutMs());
        } catch (\Throwable $e) {
            throw new HealthCheckException(
                "Failed to get broker metadata: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Gather broker metrics
     *
     * @param Metadata $metadata
     * @return array<string, mixed>
     */
    private function gatherBrokerMetrics(Metadata $metadata): array
    {
        try {
            // Convert broker collection to array
            $brokers = iterator_to_array($metadata->getBrokers());
            $topics = iterator_to_array($metadata->getTopics());

            $brokerInfo = [];
            foreach ($brokers as $broker) {
                try {
                    $brokerInfo[] = [
                        'id' => $broker->getId(),
                        'host' => $broker->getHost(),
                        'port' => $broker->getPort(),
                    ];
                } catch (\Throwable $e) {
                    Log::warning('Failed to get broker info', [
                        'error' => $e->getMessage(),
                        'broker' => $broker,
                    ]);
                }
            }

            return [
                'broker_count' => count($brokers),
                'topic_count' => count($topics),
                'brokers' => $brokerInfo,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to gather broker metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'broker_count' => 0,
                'topic_count' => 0,
                'brokers' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create Kafka consumer
     *
     * @throws HealthCheckException
     */
    private function createConsumer(): KafkaConsumer
    {
        try {
            $conf = new Conf();
            $conf->set('metadata.broker.list', $this->getBrokerList());
            $conf->set('group.id', Config::get('kafka.consumer.group_id', 'health-check-group'));
            $conf->set('auto.offset.reset', 'earliest');

            return new KafkaConsumer($conf);
        } catch (\Throwable $e) {
            throw new HealthCheckException(
                "Failed to create Kafka consumer: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Test producer functionality
     *
     * @param Producer $producer
     * @return array{success: bool, error?: string}
     */
    private function testProducer(Producer $producer): array
    {
        try {
            $testTopic = Config::get('kafka.health_check.test_topic', 'health-check');
            $topic = $producer->newTopic($testTopic);

            $topic->produce(
                partition: RD_KAFKA_PARTITION_UA,
                msgflags: 0,
                payload: 'health-check-'.time()
            );

            $producer->flush($this->getTimeoutMs());

            return ['success' => true];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get consumer metrics including lag
     *
     * @param KafkaConsumer $consumer
     * @return array<string, mixed>
     */
    private function getConsumerMetrics(KafkaConsumer $consumer): array
    {
        $metrics = [
            'total_lag' => 0,
            'partitions' => [],
        ];

        $assignments = $consumer->getAssignment();

        foreach ($assignments as $topicPartition) {
            $low = $consumer->queryWatermarkOffsets(
                $topicPartition->getTopic(),
                $topicPartition->getPartition(),
                $low,
                $high,
                $this->getTimeoutMs()
            );

            $metrics['partitions'][] = [
                'topic' => $topicPartition->getTopic(),
                'partition' => $topicPartition->getPartition(),
                'low_watermark' => $low,
                'high_watermark' => $high,
                'lag' => max(0, $high - $low),
            ];

            $metrics['total_lag'] += max(0, $high - $low);
        }

        return $metrics;
    }
}
