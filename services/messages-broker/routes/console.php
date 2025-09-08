<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('rabbitmq:consume {queue?}', function ($queue = null) {
    $this->info('Starting RabbitMQ consumer...');
    $this->call('app:consume-messages', ['queue' => $queue]);
})->purpose('Start consuming messages from RabbitMQ');

Artisan::command('rabbitmq:status', function () {
    $this->info('Checking RabbitMQ connection status...');
    $this->call('app:check-rabbitmq-status');
})->purpose('Check RabbitMQ connection status');