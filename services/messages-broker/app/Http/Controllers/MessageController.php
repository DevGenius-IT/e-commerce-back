<?php

namespace App\Http\Controllers;

use App\Services\RabbitMQService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class MessageController extends Controller
{
    protected RabbitMQService $rabbitMQService;

    public function __construct(RabbitMQService $rabbitMQService)
    {
        $this->rabbitMQService = $rabbitMQService;
    }

    public function publish(Request $request): JsonResponse
    {
        $request->validate([
            'exchange' => 'required|string',
            'routing_key' => 'required|string',
            'data' => 'required|array',
        ]);

        try {
            $this->rabbitMQService->publish(
                $request->input('exchange'),
                $request->input('routing_key'),
                $request->input('data')
            );

            return response()->json([
                'success' => true,
                'message' => 'Message published successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function status(): JsonResponse
    {
        try {
            $isConnected = $this->rabbitMQService->isConnected();
            
            return response()->json([
                'success' => true,
                'connected' => $isConnected,
                'service' => 'RabbitMQ',
                'status' => $isConnected ? 'Connected' : 'Disconnected'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}