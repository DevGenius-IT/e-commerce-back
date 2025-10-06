#!/bin/bash
set -e

echo "Starting API Gateway entrypoint..."

# Create necessary directories
mkdir -p /var/log/nginx
mkdir -p /run/nginx

# Set permissions
chown -R api-gateway:api-gateway /var/www/api-gateway/storage /var/www/api-gateway/bootstrap/cache
chmod -R 775 /var/www/api-gateway/storage /var/www/api-gateway/bootstrap/cache

# Create .env file from environment variables
cat > /var/www/api-gateway/.env <<EOF
APP_NAME="${APP_NAME:-Api Gateway}"
APP_URL="${APP_URL:-http://localhost}"
APP_ENV=production
APP_DEBUG=true
APP_KEY="${APP_KEY}"

SERVICE_NAME="${SERVICE_NAME:-api-gateway}"
JWT_SECRET="${JWT_SECRET}"

# Microservices URLs
AUTH_SERVICE_URL="${AUTH_SERVICE_URL:-http://auth-service.e-commerce.svc.cluster.local}"
AUTH_SERVICE_SECRET="${AUTH_SERVICE_SECRET:-shared-secret}"
ADDRESSES_SERVICE_URL="${ADDRESSES_SERVICE_URL:-http://addresses-service.e-commerce.svc.cluster.local}"
ADDRESSES_SERVICE_SECRET="${ADDRESSES_SERVICE_SECRET:-shared-secret}"
PRODUCTS_SERVICE_URL="${PRODUCTS_SERVICE_URL:-http://products-service.e-commerce.svc.cluster.local}"
PRODUCTS_SERVICE_SECRET="${PRODUCTS_SERVICE_SECRET:-shared-secret}"
BASKETS_SERVICE_URL="${BASKETS_SERVICE_URL:-http://baskets-service.e-commerce.svc.cluster.local}"
BASKETS_SERVICE_SECRET="${BASKETS_SERVICE_SECRET:-shared-secret}"
ORDERS_SERVICE_URL="${ORDERS_SERVICE_URL:-http://orders-service.e-commerce.svc.cluster.local}"
ORDERS_SERVICE_SECRET="${ORDERS_SERVICE_SECRET:-shared-secret}"

RABBITMQ_HOST="${RABBITMQ_HOST:-rabbitmq}"
RABBITMQ_PORT="${RABBITMQ_PORT:-5672}"
RABBITMQ_USER="${RABBITMQ_USER:-guest}"
RABBITMQ_PASSWORD="${RABBITMQ_PASSWORD:-guest}"
RABBITMQ_VHOST="${RABBITMQ_VHOST:-/}"
RABBITMQ_EXCHANGE="${RABBITMQ_EXCHANGE:-microservices_exchange}"

LOG_CHANNEL=stderr
LOG_LEVEL=info
LOG_STDERR_FORMATTER=default

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
EOF

# Set .env permissions
chown api-gateway:api-gateway /var/www/api-gateway/.env
chmod 644 /var/www/api-gateway/.env

# Start PHP-FPM in background
echo "Starting PHP-FPM..."
php-fpm -D

# Wait for PHP-FPM to start
sleep 2

# Ensure log directory and file have correct permissions (after PHP-FPM starts)
mkdir -p /var/www/api-gateway/storage/logs
touch /var/www/api-gateway/storage/logs/laravel.log
chown -R api-gateway:api-gateway /var/www/api-gateway/storage/logs
chmod -R 775 /var/www/api-gateway/storage/logs

# Start Nginx in foreground
echo "Starting Nginx..."
exec nginx -g 'daemon off;'
