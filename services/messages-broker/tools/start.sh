#!/bin/bash

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
while ! nc -z messages-broker-db 3306; do
    sleep 1
done
echo "MySQL is ready!"

# Wait for RabbitMQ to be ready
echo "Waiting for RabbitMQ to be ready..."
while ! nc -z rabbitmq 5672; do
    sleep 1
done
echo "RabbitMQ is ready!"

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Setup RabbitMQ exchanges and queues
echo "Setting up RabbitMQ infrastructure..."
php artisan rabbitmq:setup

# Start the consume workers
echo "Starting message consumer..."
php artisan rabbitmq:consume &

# Start PHP-FPM
echo "Starting PHP-FPM..."
php-fpm