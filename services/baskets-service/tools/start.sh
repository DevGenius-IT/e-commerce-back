#!/bin/bash
set -e  # Exit on error

# Wait for the database to be ready
until nc -z -v -w30 baskets-service-mysql.e-commerce.svc.cluster.local 3306
do
  echo "Waiting for database connection..."
  sleep 5
done

if [ ! -d "vendor" ]; then
  composer install --no-interaction --no-plugins --no-scripts
fi

# Run Laravel migrations
php artisan migrate --force

# Start application
php artisan serve --host=0.0.0.0 --port=8000