# Dockerfile pour Products Service - Catalogue + Inventaire + Recherche
ARG BUILD_DATE
ARG VCS_REF

FROM php:8.3-fpm AS base

# Build arguments
ARG BUILD_DATE
ARG VCS_REF
ENV SERVICE_NAME=products-service

# Labels
LABEL maintainer="DevOps Team <devops@yourcompany.com>" \
      org.label-schema.name="products-service" \
      org.label-schema.description="E-commerce Products Catalog Service" \
      org.label-schema.build-date="${BUILD_DATE}" \
      org.label-schema.vcs-ref="${VCS_REF}"

# Install system dependencies + image processing for product images
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libxml2-dev zip unzip default-mysql-client \
    nginx supervisor libonig-dev libfreetype6-dev libjpeg62-turbo-dev \
    libzip-dev libicu-dev build-essential netcat-traditional \
    libmagickwand-dev imagemagick \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
    pdo_mysql mbstring exif pcntl bcmath gd zip intl sockets opcache

# Install Redis and ImageMagick extensions
RUN pecl install redis imagick \
    && docker-php-ext-enable redis imagick

# Install Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Allow Composer to run as superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

# Set working directory
WORKDIR /var/www

# Copy dependency files
COPY shared/composer.json shared/composer.lock ./shared/
COPY services/products-service/composer.json services/products-service/composer.lock ./services/products-service/

# Install dependencies
RUN cd shared && composer install --no-dev --no-autoloader --no-scripts --no-interaction
RUN cd services/products-service && composer install --no-dev --no-autoloader --no-scripts --no-interaction

# Copy application source
COPY shared/ ./shared/
COPY services/products-service/ ./services/products-service/

# Generate autoloader
RUN cd shared && composer dump-autoload --optimize
RUN cd services/products-service && composer dump-autoload --optimize

# Copy Docker configuration
COPY docker/config/nginx.conf /etc/nginx/nginx.conf
COPY docker/config/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/config/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Copy supervisord config specific to products service
COPY services/products-service/docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy entrypoint
COPY docker/scripts/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Create directories and set permissions
RUN mkdir -p /var/log/supervisor /var/log/nginx /run/nginx \
    && mkdir -p /var/www/services/products-service/storage/{app/public/products,framework/{cache,sessions,views},logs} \
    && groupadd -r appgroup && useradd -r -g appgroup appuser \
    && chown -R appuser:appgroup /var/www /var/log /run/nginx

# Switch to service directory
WORKDIR /var/www/services/products-service

# Health check for products service
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:8000/api/products/health || exit 1

# Expose ports
EXPOSE 8000 9090

# Switch to non-root user
USER appuser

# Environment variables
ENV SERVICE_NAME=products-service
ENV CONTAINER_ROLE=app

# Start application
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]