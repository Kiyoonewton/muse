<?php

namespace App\Console\Commands;

use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use Illuminate\Console\Command;

class ElasticsearchPing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:ping';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(Client $client)
    {
        if ($client->ping()) {
            $this->info('pong');

            return;
        }

        $this->error('Could not connect to Elasticsearch.');
    }
}
