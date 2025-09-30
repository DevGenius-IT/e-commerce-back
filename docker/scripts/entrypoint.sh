#!/bin/bash

# E-commerce Microservices Entrypoint Script
# Unified entrypoint for all Laravel microservices

set -e

# Colors for logging
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Environment setup
setup_environment() {
    log_info "Setting up environment for ${SERVICE_NAME:-microservice}..."
    
    # Set default values
    export CONTAINER_ROLE=${CONTAINER_ROLE:-app}
    export APP_ENV=${APP_ENV:-production}
    export LOG_LEVEL=${LOG_LEVEL:-info}
    
    # Create required directories
    mkdir -p /var/www/services/${SERVICE_NAME}/storage/{app,framework/{cache,sessions,views},logs}
    mkdir -p /var/log/supervisor /var/log/nginx /run/nginx
    
    # Set proper permissions (if running as root, will change to appuser later)
    if [ "$(id -u)" = "0" ]; then
        chown -R appuser:appgroup /var/www/services/${SERVICE_NAME}/storage
        chown -R appuser:appgroup /var/log
    fi
    
    log_success "Environment setup completed"
}

# Wait for dependencies
wait_for_service() {
    local host=$1
    local port=$2
    local service_name=$3
    local timeout=${4:-60}
    
    log_info "Waiting for $service_name at $host:$port..."
    
    local count=0
    while ! nc -z "$host" "$port" >/dev/null 2>&1; do
        count=$((count + 1))
        if [ $count -gt $timeout ]; then
            log_error "Timeout waiting for $service_name at $host:$port"
            return 1
        fi
        log_info "Waiting for $service_name... ($count/$timeout)"
        sleep 1
    done
    
    log_success "$service_name is ready at $host:$port"
}

# Wait for database
wait_for_database() {
    if [ -n "${DB_HOST:-}" ]; then
        wait_for_service "${DB_HOST}" "${DB_PORT:-3306}" "Database" 60
    fi
}

# Wait for RabbitMQ
wait_for_rabbitmq() {
    if [ -n "${RABBITMQ_HOST:-}" ]; then
        wait_for_service "${RABBITMQ_HOST}" "${RABBITMQ_PORT:-5672}" "RabbitMQ" 60
    fi
}

# Laravel application setup
setup_laravel() {
    log_info "Setting up Laravel application..."
    
    # Navigate to service directory
    cd "/var/www/services/${SERVICE_NAME}"
    
    # Generate application key if not set
    if [ -z "${APP_KEY:-}" ] || [ "${APP_KEY}" = "base64:CHANGEME" ]; then
        log_info "Generating application key..."
        php artisan key:generate --force --no-interaction
    fi
    
    # Cache configuration for better performance
    log_info "Optimizing Laravel configuration..."
    php artisan config:cache --no-interaction || log_warning "Config cache failed"
    php artisan route:cache --no-interaction || log_warning "Route cache failed"
    
    # Only run migrations in development or if explicitly requested
    if [ "${APP_ENV}" = "local" ] || [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
        log_info "Running database migrations..."
        php artisan migrate --force --no-interaction || log_warning "Migrations failed"
    fi
    
    # Create storage link
    php artisan storage:link --no-interaction || log_warning "Storage link creation failed"
    
    log_success "Laravel application setup completed"
}

# Health check setup
setup_health_check() {
    log_info "Setting up health check endpoint..."
    
    # Create a simple health check script
    cat > /tmp/health_check.php << 'EOF'
<?php
// Simple health check
$checks = [
    'status' => 'ok',
    'service' => $_ENV['SERVICE_NAME'] ?? 'unknown',
    'timestamp' => date('c'),
    'php_version' => PHP_VERSION,
];

// Check database connection if configured
if (!empty($_ENV['DB_HOST'])) {
    try {
        $pdo = new PDO(
            "mysql:host={$_ENV['DB_HOST']};port=" . ($_ENV['DB_PORT'] ?? 3306),
            $_ENV['DB_USERNAME'] ?? 'root',
            $_ENV['DB_PASSWORD'] ?? ''
        );
        $checks['database'] = 'connected';
    } catch (Exception $e) {
        $checks['database'] = 'disconnected';
        $checks['status'] = 'warning';
    }
}

// Check Redis connection if configured
if (!empty($_ENV['REDIS_HOST'])) {
    try {
        $redis = new Redis();
        $redis->connect($_ENV['REDIS_HOST'], $_ENV['REDIS_PORT'] ?? 6379);
        $redis->ping();
        $checks['redis'] = 'connected';
        $redis->close();
    } catch (Exception $e) {
        $checks['redis'] = 'disconnected';
    }
}

header('Content-Type: application/json');
http_response_code($checks['status'] === 'ok' ? 200 : 503);
echo json_encode($checks, JSON_PRETTY_PRINT);
EOF

    log_success "Health check setup completed"
}

# Container role-based startup
start_container() {
    case "${CONTAINER_ROLE}" in
        app)
            log_info "Starting application container..."
            setup_environment
            wait_for_database
            wait_for_rabbitmq
            setup_laravel
            setup_health_check
            ;;
        queue)
            log_info "Starting queue worker container..."
            setup_environment
            wait_for_database
            wait_for_rabbitmq
            cd "/var/www/services/${SERVICE_NAME}"
            exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600
            ;;
        scheduler)
            log_info "Starting scheduler container..."
            setup_environment
            wait_for_database
            cd "/var/www/services/${SERVICE_NAME}"
            while true; do
                php artisan schedule:run --verbose --no-interaction
                sleep 60
            done
            ;;
        *)
            log_error "Unknown container role: ${CONTAINER_ROLE}"
            exit 1
            ;;
    esac
}

# Signal handling
handle_signal() {
    log_info "Received shutdown signal, stopping gracefully..."
    # Add any cleanup logic here
    exit 0
}

# Set up signal handlers
trap handle_signal SIGTERM SIGINT

# Main execution
main() {
    log_info "Starting ${SERVICE_NAME:-microservice} container..."
    log_info "Container role: ${CONTAINER_ROLE}"
    log_info "Environment: ${APP_ENV}"
    
    # Start the container based on role
    start_container
    
    # Execute the provided command
    log_info "Executing command: $*"
    exec "$@"
}

# Execute main function with all arguments
main "$@"