<?php

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    
    'exchanges' => [
        'events' => [
            'name' => env('RABBITMQ_EXCHANGE', 'microservices_events'),
            'type' => 'topic',
            'durable' => true,
            'auto_delete' => false,
        ],
    ],
    
    'queues' => [
        'default' => [
            'name' => env('RABBITMQ_QUEUE', 'default_queue'),
            'durable' => true,
            'exclusive' => false,
            'auto_delete' => false,
        ],
        'auth_events' => [
            'name' => 'auth_events',
            'durable' => true,
            'exclusive' => false,
            'auto_delete' => false,
            'routing_keys' => ['auth.*'],
        ],
        'user_events' => [
            'name' => 'user_events',
            'durable' => true,
            'exclusive' => false,
            'auto_delete' => false,
            'routing_keys' => ['user.*'],
        ],
        'order_events' => [
            'name' => 'order_events',
            'durable' => true,
            'exclusive' => false,
            'auto_delete' => false,
            'routing_keys' => ['order.*'],
        ],
        'product_events' => [
            'name' => 'product_events',
            'durable' => true,
            'exclusive' => false,
            'auto_delete' => false,
            'routing_keys' => ['product.*'],
        ],
    ],
    
    'consumer' => [
        'tag' => env('RABBITMQ_CONSUMER_TAG', 'microservices_consumer'),
        'no_local' => false,
        'no_ack' => false,
        'exclusive' => false,
        'nowait' => false,
    ],
    
    'qos' => [
        'prefetch_size' => 0,
        'prefetch_count' => 1,
        'global' => false,
    ],
];