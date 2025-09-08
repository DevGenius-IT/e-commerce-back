<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RabbitMQService;

class PurgeQueueCommand extends Command
{
    protected $signature = 'rabbitmq:purge {queue : The queue name to purge} {--force : Skip confirmation}';
    protected $description = 'Purge all messages from a RabbitMQ queue';

    public function __construct(
        private RabbitMQService $rabbitMQ
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $queue = $this->argument('queue');
        $force = $this->option('force');

        if (!$force) {
            $confirmed = $this->confirm(
                "Are you sure you want to purge all messages from queue '{$queue}'? This action cannot be undone."
            );

            if (!$confirmed) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        try {
            $this->rabbitMQ->connect();
            $channel = $this->rabbitMQ->getChannel();
            
            $channel->queue_purge($queue);
            
            $this->info("Queue '{$queue}' has been purged successfully.");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to purge queue: {$e->getMessage()}");
            return Command::FAILURE;
        } finally {
            $this->rabbitMQ->disconnect();
        }
    }
}