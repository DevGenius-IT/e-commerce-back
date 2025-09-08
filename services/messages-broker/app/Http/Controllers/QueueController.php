<?php

namespace App\Http\Controllers;

use App\Services\RabbitMQService;
use Illuminate\Http\JsonResponse;
use Exception;

class QueueController extends Controller
{
    protected RabbitMQService $rabbitMQService;

    public function __construct(RabbitMQService $rabbitMQService)
    {
        $this->rabbitMQService = $rabbitMQService;
    }

    public function index(): JsonResponse
    {
        $queues = config('rabbitmq.queues');
        
        return response()->json([
            'success' => true,
            'queues' => array_keys($queues)
        ]);
    }

    public function stats(string $queue): JsonResponse
    {
        try {
            $stats = $this->rabbitMQService->getQueueStats($queue);
            
            return response()->json([
                'success' => true,
                'queue' => $queue,
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}