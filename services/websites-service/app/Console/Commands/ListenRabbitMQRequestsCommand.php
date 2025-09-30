<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class ListenRabbitMQRequestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to RabbitMQ requests for websites service';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting RabbitMQ listener for websites service...');

        try {
            $connection = new AMQPStreamConnection(
                config('services.rabbitmq.host'),
                config('services.rabbitmq.port'),
                config('services.rabbitmq.user'),
                config('services.rabbitmq.password')
            );

            $channel = $connection->channel();

            // Declare exchange
            $channel->exchange_declare(
                config('services.rabbitmq.exchange'),
                'direct',
                false,
                true,
                false
            );

            // Declare queue
            $queueName = 'websites_service_queue';
            $channel->queue_declare($queueName, false, true, false, false);

            // Bind queue to exchange
            $channel->queue_bind($queueName, config('services.rabbitmq.exchange'), 'websites.request');

            $callback = function (AMQPMessage $msg) {
                $this->info('Received message: ' . $msg->body);
                
                try {
                    $data = json_decode($msg->body, true);
                    
                    // Process the message based on action
                    $response = $this->processMessage($data);
                    
                    $this->info('Processed message successfully');
                    
                    // Acknowledge the message
                    $msg->ack();
                    
                } catch (\Exception $e) {
                    $this->error('Error processing message: ' . $e->getMessage());
                    $msg->nack();
                }
            };

            $channel->basic_qos(null, 1, null);
            $channel->basic_consume($queueName, '', false, false, false, false, $callback);

            $this->info('Waiting for messages. To exit press CTRL+C');

            while ($channel->is_consuming()) {
                $channel->wait();
            }

            $channel->close();
            $connection->close();

        } catch (\Exception $e) {
            $this->error('Failed to connect to RabbitMQ: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Process incoming RabbitMQ message
     */
    private function processMessage(array $data): array
    {
        $action = $data['action'] ?? null;
        
        switch ($action) {
            case 'get_websites':
                return $this->getWebsites($data);
            case 'create_website':
                return $this->createWebsite($data);
            case 'update_website':
                return $this->updateWebsite($data);
            case 'delete_website':
                return $this->deleteWebsite($data);
            default:
                throw new \InvalidArgumentException("Unknown action: {$action}");
        }
    }

    private function getWebsites(array $data): array
    {
        // Implementation for getting websites
        return ['status' => 'success', 'action' => 'get_websites'];
    }

    private function createWebsite(array $data): array
    {
        // Implementation for creating website
        return ['status' => 'success', 'action' => 'create_website'];
    }

    private function updateWebsite(array $data): array
    {
        // Implementation for updating website
        return ['status' => 'success', 'action' => 'update_website'];
    }

    private function deleteWebsite(array $data): array
    {
        // Implementation for deleting website
        return ['status' => 'success', 'action' => 'delete_website'];
    }
}