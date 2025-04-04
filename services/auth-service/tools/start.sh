#!/bin/bash
set -e  # Exit on error

# Wait for the database to be ready
until nc -z -v -w30 auth-db 3306
do
  echo "Waiting for database connection..."
  sleep 5
done

if [ ! -d "vendor" ]; then
  composer install --no-interaction --no-plugins --no-scripts
fi

# Run migrations and seed the database
php artisan migrate:fresh --seed

# Start application
php artisan serve --host=0.0.0.0 --port=8001