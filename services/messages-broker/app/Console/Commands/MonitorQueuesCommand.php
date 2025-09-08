<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RabbitMQService;

class MonitorQueuesCommand extends Command
{
    protected $signature = 'rabbitmq:monitor {--interval=5 : Refresh interval in seconds}';
    protected $description = 'Monitor RabbitMQ queues status';

    public function __construct(
        private RabbitMQService $rabbitMQ
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $interval = (int) $this->option('interval');
        
        $this->info('Monitoring RabbitMQ queues... Press Ctrl+C to stop.');
        
        while (true) {
            $this->displayQueueStats();
            sleep($interval);
        }

        return Command::SUCCESS;
    }

    private function displayQueueStats(): void
    {
        $queues = config('rabbitmq.queues', []);
        
        $headers = ['Queue', 'Messages', 'Consumers', 'Memory (MB)', 'Status'];
        $rows = [];

        foreach ($queues as $queue) {
            try {
                $stats = $this->rabbitMQ->getQueueStats($queue);
                
                $rows[] = [
                    $queue,
                    $stats['messages'] ?? 0,
                    $stats['consumers'] ?? 0,
                    round(($stats['memory'] ?? 0) / 1024 / 1024, 2),
                    $stats['state'] ?? 'unknown'
                ];
            } catch (\Exception $e) {
                $rows[] = [
                    $queue,
                    'N/A',
                    'N/A',
                    'N/A',
                    'Error: ' . $e->getMessage()
                ];
            }
        }

        $this->newLine();
        $this->table($headers, $rows);
    }
}