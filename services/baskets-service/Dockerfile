FROM php:8.3-fpm

# Set working directory
WORKDIR /var/www

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    netcat-traditional \
    inotify-tools \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd
    
# Install sockets extension
RUN docker-php-ext-install sockets

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Add user for Laravel application
RUN useradd -ms /bin/bash -u 1000 api-gateway

# Ensure proper directory permissions
RUN mkdir -p /var/www/baskets-service && chown -R api-gateway:api-gateway /var/www/baskets-service

WORKDIR /var/www/baskets-service

# Copy application files with proper permissions
COPY --chown=api-gateway:api-gateway services/baskets-service .
COPY --chown=api-gateway:api-gateway shared/ ../../shared

RUN composer update --no-interaction --optimize-autoloader

EXPOSE 8005

# Copy and set permissions for start script
COPY services/baskets-service/tools/start.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"]