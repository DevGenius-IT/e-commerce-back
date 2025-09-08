<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\RabbitMQService;
use App\Services\MessageHandlerRegistry;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RabbitMQService::class, function ($app) {
            return new RabbitMQService(config('rabbitmq'));
        });
        
        $this->app->singleton(MessageHandlerRegistry::class, function ($app) {
            return new MessageHandlerRegistry();
        });
    }

    public function boot(): void
    {
        //
    }
}