#!/bin/bash
set -e

echo "🚀 Starting deliveries-service..."

# Wait for database to be ready
echo "⏳ Waiting for database connection..."
while ! nc -z deliveries-service-mysql.e-commerce.svc.cluster.local 3306; do
    echo "Database not ready, waiting..."
    sleep 2
done
echo "✅ Database connection established!"

# Change to application directory
cd /var/www/deliveries-service

# Generate application key if not exists
if [ ! -f .env ]; then
    echo "📄 Creating .env file..."
    cp .env.example .env
    php artisan key:generate
fi

# Set proper permissions
echo "🔐 Setting file permissions..."
chmod -R 755 storage bootstrap/cache
chown -R api-gateway:api-gateway storage bootstrap/cache

# Install/update dependencies
echo "📦 Installing dependencies..."
composer install --no-interaction --optimize-autoloader

# Run database migrations
echo "🗃️ Running database migrations..."
php artisan migrate --force

# Seed database if in development
if [ "$APP_ENV" = "local" ]; then
    echo "🌱 Seeding database..."
    php artisan db:seed --force
fi

# Clear and cache config
echo "🧹 Clearing and caching configuration..."
php artisan config:clear
# Skip cache:clear since we don't have cache table
php artisan config:cache

echo "🎉 deliveries-service started successfully!"

# Start PHP built-in server
exec php artisan serve --host=0.0.0.0 --port=8000