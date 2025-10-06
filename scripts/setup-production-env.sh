#!/bin/bash

###############################################################################
# Script de configuration .env pour production
# E-Commerce Platform - Production Environment Setup
###############################################################################

set -e

SECRETS_FILE="$HOME/secrets-production.txt"
ENV_FILE=".env"

# Couleurs
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}╔═══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   CONFIGURATION .ENV PRODUCTION                               ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Vérifier que le fichier de secrets existe
if [ ! -f "$SECRETS_FILE" ]; then
    echo -e "${YELLOW}⚠️  Fichier de secrets non trouvé: $SECRETS_FILE${NC}"
    echo "Générez d'abord les secrets avec: ./generate-secrets.sh > ~/secrets-production.txt"
    exit 1
fi

# Extraire les secrets du fichier
echo -e "${BLUE}📖 Lecture des secrets...${NC}"
APP_KEY=$(grep "APP_KEY=" "$SECRETS_FILE" | cut -d'=' -f2)
JWT_SECRET=$(grep "JWT_SECRET=" "$SECRETS_FILE" | cut -d'=' -f2)
DB_ROOT_PASSWORD=$(grep "DB_ROOT_PASSWORD=" "$SECRETS_FILE" | cut -d'=' -f2)
AUTH_DB_PASSWORD=$(grep "AUTH_DB_PASSWORD=" "$SECRETS_FILE" | cut -d'=' -f2)
PRODUCTS_DB_PASSWORD=$(grep "PRODUCTS_DB_PASSWORD=" "$SECRETS_FILE" | cut -d'=' -f2)
BASKETS_DB_PASSWORD=$(grep "BASKETS_DB_PASSWORD=" "$SECRETS_FILE" | cut -d'=' -f2)
ORDERS_DB_PASSWORD=$(grep "ORDERS_DB_PASSWORD=" "$SECRETS_FILE" | cut -d'=' -f2)
DELIVERIES_DB_PASSWORD=$(grep "DELIVERIES_DB_PASSWORD=" "$SECRETS_FILE" | cut -d'=' -f2)
ADDRESSES_DB_PASSWORD=$(grep "ADDRESSES_DB_PASSWORD=" "$SECRETS_FILE" | cut -d'=' -f2)
CONTACTS_DB_PASSWORD=$(grep "CONTACTS_DB_PASSWORD=" "$SECRETS_FILE" | cut -d'=' -f2)
NEWSLETTERS_DB_PASSWORD=$(grep "NEWSLETTERS_DB_PASSWORD=" "$SECRETS_FILE" | cut -d'=' -f2)
SAV_DB_PASSWORD=$(grep "SAV_DB_PASSWORD=" "$SECRETS_FILE" | cut -d'=' -f2)
WEBSITES_DB_PASSWORD=$(grep "WEBSITES_DB_PASSWORD=" "$SECRETS_FILE" | cut -d'=' -f2)
QUESTIONS_DB_PASSWORD=$(grep "QUESTIONS_DB_PASSWORD=" "$SECRETS_FILE" | cut -d'=' -f2)
RABBITMQ_PASSWORD=$(grep "RABBITMQ_PASSWORD=" "$SECRETS_FILE" | cut -d'=' -f2)
REDIS_PASSWORD=$(grep "REDIS_PASSWORD=" "$SECRETS_FILE" | cut -d'=' -f2)
MINIO_ROOT_PASSWORD=$(grep "MINIO_ROOT_PASSWORD=" "$SECRETS_FILE" | cut -d'=' -f2)

# Sauvegarder l'ancien .env si existe
if [ -f "$ENV_FILE" ]; then
    cp "$ENV_FILE" "${ENV_FILE}.backup.$(date +%Y%m%d_%H%M%S)"
    echo -e "${GREEN}✅ Ancien .env sauvegardé${NC}"
fi

# Créer le nouveau .env
echo -e "${BLUE}📝 Génération du fichier .env de production...${NC}"

cat > "$ENV_FILE" << EOF
# ═══════════════════════════════════════════════════════════════
# E-COMMERCE PLATFORM - PRODUCTION ENVIRONMENT
# Generated: $(date)
# ═══════════════════════════════════════════════════════════════

# ───────────────────────────────────────────────────────────────
# APPLICATION CONFIGURATION
# ───────────────────────────────────────────────────────────────
APP_ENV=production
APP_DEBUG=false
APP_NAME="E-Commerce Platform"
APP_URL=https://api.demo.collect-n-verything.com
APP_KEY=$APP_KEY

# ───────────────────────────────────────────────────────────────
# JWT AUTHENTICATION
# ───────────────────────────────────────────────────────────────
JWT_SECRET=$JWT_SECRET
JWT_TTL=60
JWT_REFRESH_TTL=20160

# ───────────────────────────────────────────────────────────────
# DATABASE - ROOT
# ───────────────────────────────────────────────────────────────
DB_CONNECTION=mysql
DB_ROOT_PASSWORD=$DB_ROOT_PASSWORD

# ───────────────────────────────────────────────────────────────
# AUTH SERVICE
# ───────────────────────────────────────────────────────────────
DB_AUTH_PORT=3306
DB_AUTH_HOST=auth-db
DB_AUTH_DATABASE=auth_service
DB_AUTH_USERNAME=auth_user
DB_AUTH_PASSWORD=$AUTH_DB_PASSWORD
AUTH_SERVICE_URL=https://api.demo.collect-n-verything.com/auth/
AUTH_SERVICE_BASE_URL=https://api.demo.collect-n-verything.com/auth/

# ───────────────────────────────────────────────────────────────
# PRODUCTS SERVICE
# ───────────────────────────────────────────────────────────────
DB_PRODUCTS_PORT=3307
DB_PRODUCTS_HOST=products-db
DB_PRODUCTS_DATABASE=products_service
DB_PRODUCTS_USERNAME=products_user
DB_PRODUCTS_PASSWORD=$PRODUCTS_DB_PASSWORD
PRODUCTS_SERVICE_URL=https://api.demo.collect-n-verything.com/products/
PRODUCTS_SERVICE_BASE_URL=https://api.demo.collect-n-verything.com/products/

# ───────────────────────────────────────────────────────────────
# BASKETS SERVICE
# ───────────────────────────────────────────────────────────────
DB_BASKETS_PORT=3308
DB_BASKETS_HOST=baskets-db
DB_BASKETS_DATABASE=baskets_service
DB_BASKETS_USERNAME=baskets_user
DB_BASKETS_PASSWORD=$BASKETS_DB_PASSWORD
BASKETS_SERVICE_URL=https://api.demo.collect-n-verything.com/baskets/
BASKETS_SERVICE_BASE_URL=https://api.demo.collect-n-verything.com/baskets/

# ───────────────────────────────────────────────────────────────
# ORDERS SERVICE
# ───────────────────────────────────────────────────────────────
DB_ORDERS_PORT=3309
DB_ORDERS_HOST=orders-db
DB_ORDERS_DATABASE=orders_service
DB_ORDERS_USERNAME=orders_user
DB_ORDERS_PASSWORD=$ORDERS_DB_PASSWORD
ORDERS_SERVICE_URL=https://api.demo.collect-n-verything.com/orders/
ORDERS_SERVICE_BASE_URL=https://api.demo.collect-n-verything.com/orders/

# ───────────────────────────────────────────────────────────────
# DELIVERIES SERVICE
# ───────────────────────────────────────────────────────────────
DB_DELIVERIES_PORT=3310
DB_DELIVERIES_HOST=deliveries-db
DB_DELIVERIES_DATABASE=deliveries_service
DB_DELIVERIES_USERNAME=deliveries_user
DB_DELIVERIES_PASSWORD=$DELIVERIES_DB_PASSWORD
DELIVERIES_SERVICE_URL=https://api.demo.collect-n-verything.com/deliveries/
DELIVERIES_SERVICE_BASE_URL=https://api.demo.collect-n-verything.com/deliveries/

# ───────────────────────────────────────────────────────────────
# ADDRESSES SERVICE
# ───────────────────────────────────────────────────────────────
DB_ADDRESSES_PORT=3321
DB_ADDRESSES_HOST=addresses-db
DB_ADDRESSES_DATABASE=addresses_service
DB_ADDRESSES_USERNAME=addresses_user
DB_ADDRESSES_PASSWORD=$ADDRESSES_DB_PASSWORD
ADDRESSES_SERVICE_URL=https://api.demo.collect-n-verything.com/addresses/
ADDRESSES_SERVICE_BASE_URL=https://api.demo.collect-n-verything.com/addresses/

# ───────────────────────────────────────────────────────────────
# CONTACTS SERVICE
# ───────────────────────────────────────────────────────────────
DB_CONTACTS_PORT=3313
DB_CONTACTS_HOST=contacts-db
DB_CONTACTS_DATABASE=contacts_service
DB_CONTACTS_USERNAME=contacts_user
DB_CONTACTS_PASSWORD=$CONTACTS_DB_PASSWORD
CONTACTS_SERVICE_URL=https://api.demo.collect-n-verything.com/contacts/
CONTACTS_SERVICE_BASE_URL=https://api.demo.collect-n-verything.com/contacts/

# ───────────────────────────────────────────────────────────────
# NEWSLETTERS SERVICE
# ───────────────────────────────────────────────────────────────
DB_NEWSLETTERS_PORT=3314
DB_NEWSLETTERS_HOST=newsletters-db
DB_NEWSLETTERS_DATABASE=newsletters_service
DB_NEWSLETTERS_USERNAME=newsletters_user
DB_NEWSLETTERS_PASSWORD=$NEWSLETTERS_DB_PASSWORD
NEWSLETTERS_SERVICE_URL=https://api.demo.collect-n-verything.com/newsletters/
NEWSLETTERS_SERVICE_BASE_URL=https://api.demo.collect-n-verything.com/newsletters/

# ───────────────────────────────────────────────────────────────
# SAV SERVICE
# ───────────────────────────────────────────────────────────────
DB_SAV_PORT=3315
DB_SAV_HOST=sav-db
DB_SAV_DATABASE=sav_service
DB_SAV_USERNAME=sav_user
DB_SAV_PASSWORD=$SAV_DB_PASSWORD
SAV_SERVICE_URL=https://api.demo.collect-n-verything.com/sav/
SAV_SERVICE_BASE_URL=https://api.demo.collect-n-verything.com/sav/

# ───────────────────────────────────────────────────────────────
# WEBSITES SERVICE
# ───────────────────────────────────────────────────────────────
DB_WEBSITES_PORT=3316
DB_WEBSITES_HOST=websites-db
DB_WEBSITES_DATABASE=websites_service
DB_WEBSITES_USERNAME=websites_user
DB_WEBSITES_PASSWORD=$WEBSITES_DB_PASSWORD
WEBSITES_SERVICE_URL=https://api.demo.collect-n-verything.com/websites/
WEBSITES_SERVICE_BASE_URL=https://api.demo.collect-n-verything.com/websites/

# ───────────────────────────────────────────────────────────────
# QUESTIONS SERVICE
# ───────────────────────────────────────────────────────────────
DB_QUESTIONS_PORT=3317
DB_QUESTIONS_HOST=questions-db
DB_QUESTIONS_DATABASE=questions_service
DB_QUESTIONS_USERNAME=questions_user
DB_QUESTIONS_PASSWORD=$QUESTIONS_DB_PASSWORD
QUESTIONS_SERVICE_URL=https://api.demo.collect-n-verything.com/questions/
QUESTIONS_SERVICE_BASE_URL=https://api.demo.collect-n-verything.com/questions/

# ───────────────────────────────────────────────────────────────
# MESSAGES BROKER SERVICE
# ───────────────────────────────────────────────────────────────
DB_MESSAGES_BROKER_PORT=3320
DB_MESSAGES_BROKER_HOST=messages-broker-db
MESSAGES_BROKER_SERVICE_URL=https://api.demo.collect-n-verything.com/messages-broker/
MESSAGES_BROKER_SERVICE_BASE_URL=https://api.demo.collect-n-verything.com/messages-broker/

# ───────────────────────────────────────────────────────────────
# RABBITMQ
# ───────────────────────────────────────────────────────────────
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_MANAGEMENT_PORT=15672
RABBITMQ_USER=admin
RABBITMQ_PASSWORD=$RABBITMQ_PASSWORD
RABBITMQ_VHOST=/

# ───────────────────────────────────────────────────────────────
# REDIS
# ───────────────────────────────────────────────────────────────
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=$REDIS_PASSWORD

# ───────────────────────────────────────────────────────────────
# MINIO (Object Storage)
# ───────────────────────────────────────────────────────────────
MINIO_ENDPOINT=minio
MINIO_PORT=9000
MINIO_CONSOLE_PORT=9001
MINIO_ROOT_USER=admin
MINIO_ROOT_PASSWORD=$MINIO_ROOT_PASSWORD
MINIO_USE_SSL=false
MINIO_BUCKET_PRODUCTS=products
MINIO_BUCKET_SAV=sav
MINIO_BUCKET_NEWSLETTERS=newsletters
MINIO_PUBLIC_URL=https://minio.demo.collect-n-verything.com

# ───────────────────────────────────────────────────────────────
# MAIL CONFIGURATION
# ───────────────────────────────────────────────────────────────
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@demo.collect-n-verything.com"
MAIL_FROM_NAME="E-Commerce Platform"

# ───────────────────────────────────────────────────────────────
# LOGGING
# ───────────────────────────────────────────────────────────────
LOG_CHANNEL=stack
LOG_LEVEL=error

# ───────────────────────────────────────────────────────────────
# QUEUE
# ───────────────────────────────────────────────────────────────
QUEUE_CONNECTION=rabbitmq

# ───────────────────────────────────────────────────────────────
# CACHE
# ───────────────────────────────────────────────────────────────
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# ───────────────────────────────────────────────────────────────
# BROADCASTING
# ───────────────────────────────────────────────────────────────
BROADCAST_DRIVER=log

# ═══════════════════════════════════════════════════════════════
# END OF CONFIGURATION
# ═══════════════════════════════════════════════════════════════
EOF

chmod 600 "$ENV_FILE"

echo ""
echo -e "${GREEN}✅ Fichier .env de production créé avec succès!${NC}"
echo ""
echo -e "${BLUE}📋 Prochaines étapes:${NC}"
echo "   1. Vérifiez le fichier: cat .env"
echo "   2. Configurez Nginx: ./scripts/setup-production-nginx.sh"
echo "   3. Déployez: docker compose -f docker-compose.yml -f docker-compose.production.yml up -d"
echo ""
echo -e "${YELLOW}⚠️  N'oubliez pas de configurer MAIL_HOST, MAIL_USERNAME et MAIL_PASSWORD${NC}"
echo ""
