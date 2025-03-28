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
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Add user for Laravel application 
RUN useradd -ms /bin/bash -u 1000 api-gateway

# Ensure proper directory permissions
RUN mkdir -p /var/www/api-gateway && chown -R api-gateway:api-gateway /var/www/api-gateway

WORKDIR /var/www/api-gateway

# Copy application files
COPY --chown=api-gateway:api-gateway services/api-gateway .
COPY --chown=api-gateway:api-gateway shared/ ../../shared

RUN composer install --no-interaction --optimize-autoloader

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]