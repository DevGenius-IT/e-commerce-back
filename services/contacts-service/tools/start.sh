#!/bin/bash

# Attendre que la base de données soit prête
until nc -z $DB_HOST 3306; do
    echo "Waiting for database connection..."
    sleep 2
done

# Attendre RabbitMQ
until nc -z $RABBITMQ_HOST 5672; do
    echo "Waiting for RabbitMQ connection..."
    sleep 2
done

echo "Database and RabbitMQ are ready!"

# Installer les dépendances Composer
composer install --no-interaction --optimize-autoloader

# Créer la clé d'application Laravel si elle n'existe pas
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Générer la clé d'application
php artisan key:generate --no-interaction

# Exécuter les migrations
php artisan migrate --force

# Exécuter les seeders
php artisan db:seed --force

# Créer le répertoire de logs s'il n'existe pas
mkdir -p storage/logs

# Changer les permissions
chown -R api-gateway:api-gateway storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Démarrer PHP-FPM
php-fpm --daemonize

# Garder le conteneur actif avec un serveur artisan
php artisan serve --host=0.0.0.0 --port=8010