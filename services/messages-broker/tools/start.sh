#!/bin/bash

# Wait for RabbitMQ to be ready
echo "Waiting for RabbitMQ to be ready..."
while ! nc -z rabbitmq.e-commerce-messaging.svc.cluster.local 5672; do
    sleep 1
done
echo "RabbitMQ is ready!"

# Setup RabbitMQ exchanges and queues
echo "Setting up RabbitMQ infrastructure..."
php artisan rabbitmq:setup

# Start the consume workers
echo "Starting message consumer..."
php artisan rabbitmq:consume &

# Start PHP-FPM
echo "Starting PHP-FPM..."
php-fpm
