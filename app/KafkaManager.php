<?php

namespace App;

use App\Models\Blog;
use Enqueue\RdKafka\RdKafkaConnectionFactory;
use Illuminate\Support\Facades\Log;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use OpenTelemetry\API\Globals;
use OpenTelemetry\Extension\Propagator\B3\B3Propagator;

class KafkaManager implements Processor
{
    private RdKafkaConnectionFactory $connectionFactory;

    private B3Propagator $propagator;

    const TOPICS = [
        'SITE_CREATED' => 'SITE_CREATED',
    ];

    public function __construct()
    {
        $this->connectionFactory = new RdKafkaConnectionFactory([
            'global' => [
                'metadata.broker.list' => env('KAFKA_BROKERS'),
                'enable.auto.commit' => 'true',
            ],
            'topic' => [
                'auto.offset.reset' => 'latest',
            ],
            'commit_async' => true,
        ]);

        $this->propagator = B3Propagator::getB3MultiHeaderInstance();
    }

    public function send(string $topic, string $message)
    {
        try {
            $context = $this->connectionFactory->createContext();

            $headers = [];

            $this->propagator->inject($headers);

            $messageObject = $context->createMessage($message, [
                'enqueue.topic_name' => $topic,
            ], $headers);

            $topicObject = $context->createTopic($topic);

            $producer = $context->createProducer();

            $producer->send($topicObject, $messageObject);

            $producer->flush(10);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        } catch (\Interop\Queue\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function process(Message $message, \Interop\Queue\Context $context)
    {
        $topic = $message->getProperty('enqueue.topic_name');
        $body = $message->getBody();
        $headers = $message->getHeaders();

        if (! $body) {
            return;
        }

        $context = $this->propagator->extract($headers);
        $scope = $context->activate();
        $tracer = Globals::tracerProvider()->getTracer('muse');
        $span = $tracer->spanBuilder($topic)->startSpan();
        $span->storeInContext($context);

        switch ($topic) {
            case self::TOPICS['SITE_CREATED']:
                // if a new site has been created lets create access to the site
                $span->addEvent(sprintf('Consuming %s', $topic));
                $data = json_decode($body, true);
                $site_uuid = $data['site']['uuid'];

                Blog::create([
                    'site_uuid' => $data['site']['uuid'],
                    'domain' => $data['site']['domain'],
                ]);
                Log::channel('logstash')->info(sprintf('Muse - Created blog for site with uuid %s', $site_uuid));
                break;
            default:
                break;
        }

        $span->end();
        $scope?->detach();
    }
}
