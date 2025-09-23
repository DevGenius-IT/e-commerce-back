#!/bin/bash
set -e  # Exit on error

# Wait for the database to be ready
until nc -z -v -w30 addresses-db 3306
do
  echo "Waiting for database connection..."
  sleep 5
done

# Wait for RabbitMQ to be ready
until nc -z -v -w30 rabbitmq 5672
do
  echo "Waiting for RabbitMQ connection..."
  sleep 5
done

if [ ! -d "vendor" ]; then
  composer install --no-interaction --no-plugins --no-scripts
fi

# Start RabbitMQ listener in background
php artisan rabbitmq:listen-requests &

# Start application (foreground)
php artisan serve --host=0.0.0.0 --port=8009