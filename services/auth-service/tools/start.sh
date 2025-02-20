#!/bin/bash
set -e  # Exit on error

# Run migrations and seed the database
php artisan migrate:fresh --seed

# Start application
php artisan serve --host=0.0.0.0 --port=8001