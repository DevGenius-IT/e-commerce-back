FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    zip \
    unzip \
    netcat-openbsd \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl sockets 

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Allow Composer to run as superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

# Set working directory
WORKDIR /var/www

# Copy composer files and install dependencies
COPY ./services/orders-service/composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy the rest of the application
COPY ./services/orders-service .

# Copy shared directory
COPY ./shared /var/www/shared

# Ensure Laravel structure is complete
RUN mkdir -p /var/www/public /var/www/storage/app /var/www/storage/framework/{cache,sessions,views} /var/www/storage/logs
RUN mkdir -p /var/www/bootstrap/cache

# Create proper Laravel index.php
RUN echo '<?php\n\
\n\
use Illuminate\Foundation\Application;\n\
use Illuminate\Http\Request;\n\
\n\
define('\''LARAVEL_START'\'', microtime(true));\n\
\n\
// Determine if the application is in maintenance mode...\n\
if (file_exists($maintenance = __DIR__.'\''/../storage/framework/maintenance.php'\'')) {\n\
    require $maintenance;\n\
}\n\
\n\
// Register the Composer autoloader...\n\
require __DIR__.'\''/../vendor/autoload.php'\'';\n\
\n\
// Bootstrap Laravel and handle the request...\n\
/** @var Application $app */\n\
$app = require_once __DIR__.'\''/../bootstrap/app.php'\'';\n\
\n\
$app->handleRequest(Request::capture());' > /var/www/public/index.php

# Set permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache

# Create start script
RUN echo '#!/bin/bash\n\
echo "Waiting for database connection..."\n\
DB_HOST_ONLY=$(echo ${DB_HOST:-orders-service-mysql} | cut -d: -f1)\n\
while ! nc -z $DB_HOST_ONLY 3306; do\n\
  sleep 0.1\n\
done\n\
echo "Database connected!"\n\
\n\
# Run migrations\n\
php artisan migrate --force\n\
\n\
# Seed database\n\
php artisan db:seed --force\n\
\n\
# Start PHP server\n\
php artisan serve --host=0.0.0.0 --port=8004' > /var/www/start.sh && chmod +x /var/www/start.sh

EXPOSE 8004

CMD ["/var/www/start.sh"]