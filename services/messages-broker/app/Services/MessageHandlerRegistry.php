<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class MessageHandlerRegistry
{
    protected array $handlers = [];

    public function register(string $eventType, callable $handler): void
    {
        if (!isset($this->handlers[$eventType])) {
            $this->handlers[$eventType] = [];
        }
        
        $this->handlers[$eventType][] = $handler;
        
        Log::info("Handler registered for event type: {$eventType}");
    }

    public function handle(string $eventType, array $data): void
    {
        if (!isset($this->handlers[$eventType])) {
            Log::warning("No handlers registered for event type: {$eventType}");
            return;
        }

        foreach ($this->handlers[$eventType] as $handler) {
            try {
                $handler($data);
                Log::info("Successfully handled event: {$eventType}");
            } catch (Exception $e) {
                Log::error("Error handling event {$eventType}: " . $e->getMessage());
                throw $e;
            }
        }
    }

    public function getHandlers(string $eventType = null): array
    {
        if ($eventType) {
            return $this->handlers[$eventType] ?? [];
        }
        
        return $this->handlers;
    }

    public function hasHandler(string $eventType): bool
    {
        return isset($this->handlers[$eventType]) && count($this->handlers[$eventType]) > 0;
    }

    public function clearHandlers(string $eventType = null): void
    {
        if ($eventType) {
            unset($this->handlers[$eventType]);
            Log::info("Cleared handlers for event type: {$eventType}");
        } else {
            $this->handlers = [];
            Log::info("Cleared all handlers");
        }
    }
}