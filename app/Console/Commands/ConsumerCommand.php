<?php

namespace App\Console\Commands;

use App\KafkaManager;
use Enqueue\RdKafka\RdKafkaConnectionFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\SignalableCommandInterface;

class ConsumerCommand extends Command implements SignalableCommandInterface
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka:consumer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reads and prints messages from kafka.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $topics = ['SITE_CREATED'];

        $connectionFactory = new RdKafkaConnectionFactory([
            'global' => [
                'metadata.broker.list' => env('KAFKA_BROKERS'),
                'enable.auto.commit' => 'true',
            ],
            'topic' => [
                'auto.offset.reset' => 'latest',
            ],
            'commit_async' => true,
        ]);
        $context = $connectionFactory->createContext();
        $consumer = $context->createConsumer($context->createQueue(implode(',', $topics)));
        $kafkaManager = new KafkaManager();

        while (true) {
            // This is so we know that this container os listening to Kafka
            echo "Listening!\n";

            try {
                $message = $consumer->receive();

                if ($message) {
                    $kafkaManager->process($message, $context);
                    $consumer->acknowledge($message);
                }
            } catch (\Exception $e) {
                Log::error($e->getMessage());
                continue;
            }

            usleep(0);
        }

        return 0;
    }

    public function handleSignal(int $signal): void
    {
        echo "CATCH!\n";
        exit;
    }

    public function getSubscribedSignals(): array
    {
        return [SIGINT, SIGTERM];
    }
}
