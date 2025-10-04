FROM php:8.3-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    inotify-tools \
    nginx \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd sockets

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Allow Composer to run as superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

# Add user for Laravel application
RUN useradd -ms /bin/bash -u 1000 api-gateway

# Ensure proper directory permissions
RUN mkdir -p /var/www/api-gateway && chown -R api-gateway:api-gateway /var/www/api-gateway

WORKDIR /var/www/api-gateway

# Copy application files
COPY --chown=api-gateway:api-gateway services/api-gateway .
COPY --chown=api-gateway:api-gateway shared/ ../../shared

# Install dependencies
RUN composer update --no-interaction --optimize-autoloader

# Copy Nginx configuration
COPY services/api-gateway/docker/nginx/default.conf /etc/nginx/sites-available/default
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && rm -f /etc/nginx/sites-enabled/default.conf

# Copy entrypoint script
COPY services/api-gateway/docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Create necessary directories
RUN mkdir -p /var/log/nginx /run/nginx \
    && chown -R api-gateway:api-gateway /var/log/nginx /run/nginx \
    && chown -R api-gateway:api-gateway /var/www/api-gateway/storage /var/www/api-gateway/bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]