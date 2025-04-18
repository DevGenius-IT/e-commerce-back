#!/bin/sh

# Démarrage de PHP-FPM
php-fpm83 &

# Attente pour s'assurer que PHP-FPM est démarré
sleep 2

# Installation des dépendances PHP
if [ ! -d "/var/www/api-gateway/vendor" ]; then
  composer install --no-interaction --no-plugins --no-scripts --working-dir=/var/www/api-gateway
fi

# Démarrage des services Laravel en arrière-plan
cd /var/www/api-gateway && php artisan serve --host=0.0.0.0 --port=8000 &

# Attente pour s'assurer que l'API Gateway est démarré
sleep 2

# Installation des dépendances PHP
if [ ! -d "/var/www/auth-service/vendor" ]; then
  composer install --no-interaction --no-plugins --no-scripts --working-dir=/var/www/auth-service
fi

cd /var/www/auth-service && php artisan serve --host=0.0.0.0 --port=8001 &

# Attente pour s'assurer que le service d'authentification est démarré
sleep 2

# Démarrage de Nginx en premier plan
nginx -g 'daemon off;'