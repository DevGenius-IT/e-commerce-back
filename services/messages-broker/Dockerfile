FROM php:8.2-fpm-alpine

# Set working directory
WORKDIR /var/www

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    bash \
    rabbitmq-c-dev \
    autoconf \
    g++ \
    make \
    netcat-openbsd \
    linux-headers

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd sockets

# Install AMQP extension for RabbitMQ
RUN pecl install amqp && \
    docker-php-ext-enable amqp

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Add user for Laravel application
RUN adduser -D -s /bin/bash -u 1000 api-gateway

# Ensure proper directory permissions
RUN mkdir -p /var/www/messages-broker && chown -R api-gateway:api-gateway /var/www/messages-broker

WORKDIR /var/www/messages-broker

# Copy application files with proper permissions
COPY --chown=api-gateway:api-gateway services/messages-broker .
COPY --chown=api-gateway:api-gateway shared/ ../../shared

RUN composer install --no-interaction --optimize-autoloader

EXPOSE 8002

# Copy and set permissions for start script
COPY services/messages-broker/tools/start.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"]