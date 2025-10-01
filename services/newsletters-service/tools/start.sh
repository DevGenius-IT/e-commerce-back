#!/bin/bash

echo "🚀 Starting Newsletters Service..."

# Wait for database connection
echo "⏳ Waiting for database connection..."
while ! nc -z newsletters-service-mysql.e-commerce.svc.cluster.local 3306; do
  echo "Database not ready, waiting 2 seconds..."
  sleep 2
done
echo "✅ Database connection established"

# Install/update Composer dependencies
echo "📦 Installing Composer dependencies..."
composer install --no-interaction --optimize-autoloader

# Generate application key if not exists
if [ ! -f .env ]; then
    echo "⚙️  Creating .env file..."
    cp .env.example .env
fi

# Check if APP_KEY is set, if not generate one
if ! grep -q "APP_KEY=.*" .env || grep -q "APP_KEY=$" .env; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force
fi

# Run database migrations
echo "🗄️  Running database migrations..."
php artisan migrate --force

# Clear various caches
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Set proper permissions
echo "🔧 Setting permissions..."
chown -R api-gateway:api-gateway /var/www/newsletters-service
chmod -R 775 /var/www/newsletters-service/storage
chmod -R 775 /var/www/newsletters-service/bootstrap/cache

echo "✅ Newsletters Service is ready!"

# Start PHP-FPM
exec php artisan serve --host=0.0.0.0 --port=8000