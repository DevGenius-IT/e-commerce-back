FROM nginx:alpine

# Installation des dépendances système
RUN apk add --no-cache \
    php83 \
    php83-fpm \
    php83-pdo \
    php83-pdo_mysql \
    php83-mbstring \
    php83-xml \
    php83-openssl \
    php83-json \
    php83-phar \
    php83-zip \
    php83-session \
    php83-fileinfo \
    php83-tokenizer \
    php83-dom \
    php83-curl \
    php83-ctype \
    php83-sodium \
    composer

# Configuration de Nginx
COPY ./docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# Création des répertoires
RUN mkdir -p /var/www/api-gateway
RUN mkdir -p /var/www/auth-service

# Copie des fichiers des services
COPY ./services/api-gateway /var/www/api-gateway
COPY ./services/auth-service /var/www/auth-service

# Installation des dépendances
WORKDIR /var/www/api-gateway
COPY ./shared ../../shared
RUN composer install --no-dev --optimize-autoloader --no-interaction

WORKDIR /var/www/auth-service
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Permissions pour les répertoires de stockage
RUN chown -R nginx:nginx /var/www/api-gateway \
    && chown -R nginx:nginx /var/www/auth-service \
    && chmod -R 775 /var/www/api-gateway/storage \
    && chmod -R 775 /var/www/auth-service/storage

# Script de démarrage
COPY ./tools/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80 443

CMD ["/start.sh"]