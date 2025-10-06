#!/bin/bash

###############################################################################
# Script de génération des composer.lock pour tous les services
# Utilise l'image Docker de Composer
###############################################################################

set -e

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}╔═══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   GÉNÉRATION DES COMPOSER.LOCK                                ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════════════╝${NC}"
echo ""

SERVICES=(
    "addresses-service"
    "auth-service"
    "baskets-service"
    "contacts-service"
    "deliveries-service"
    "messages-broker"
    "newsletters-service"
    "orders-service"
    "products-service"
    "questions-service"
    "sav-service"
    "websites-service"
    "api-gateway"
)

GENERATED=0
SKIPPED=0
FAILED=0

for service in "${SERVICES[@]}"; do
    SERVICE_DIR="services/$service"

    if [ ! -f "$SERVICE_DIR/composer.json" ]; then
        echo -e "${YELLOW}⚠️  $service: composer.json not found${NC}"
        continue
    fi

    if [ -f "$SERVICE_DIR/composer.lock" ]; then
        echo -e "${YELLOW}⏭️  $service: composer.lock already exists${NC}"
        ((SKIPPED++))
        continue
    fi

    echo -e "${BLUE}📦 Generating composer.lock for $service...${NC}"

    # Utiliser l'image Docker de Composer
    if docker run --rm \
        -v "$(pwd)/$SERVICE_DIR:/app" \
        -v "$(pwd)/shared:/shared" \
        -w /app \
        composer:latest install --ignore-platform-reqs --no-scripts --no-interaction > /dev/null 2>&1; then

        echo -e "${GREEN}✅ $service done${NC}"
        ((GENERATED++))
    else
        echo -e "${YELLOW}⚠️  $service: failed, trying update...${NC}"

        # Essayer avec update si install échoue
        if docker run --rm \
            -v "$(pwd)/$SERVICE_DIR:/app" \
            -v "$(pwd)/shared:/shared" \
            -w /app \
            composer:latest update --ignore-platform-reqs --no-scripts --no-interaction > /dev/null 2>&1; then

            echo -e "${GREEN}✅ $service done (update)${NC}"
            ((GENERATED++))
        else
            echo -e "${YELLOW}❌ $service: failed${NC}"
            ((FAILED++))
        fi
    fi
done

echo ""
echo -e "${BLUE}╔═══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   RÉSUMÉ                                                      ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════════════╝${NC}"
echo -e "${GREEN}✅ Générés: $GENERATED${NC}"
echo -e "${YELLOW}⏭️  Ignorés: $SKIPPED${NC}"
echo -e "${YELLOW}❌ Échecs: $FAILED${NC}"
echo ""

# Vérifier le total
TOTAL_LOCKS=$(find services -name "composer.lock" -type f | wc -l)
echo -e "${BLUE}📊 Total composer.lock: $TOTAL_LOCKS${NC}"

if [ $FAILED -gt 0 ]; then
    echo ""
    echo -e "${YELLOW}⚠️  Certains services ont échoué. Vérifiez manuellement.${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}✅ Tous les composer.lock sont prêts pour le build Docker${NC}"
