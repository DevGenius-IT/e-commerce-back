#!/bin/bash

# =============================================================================
# Reset Composer Dependencies Script
# =============================================================================
# Supprime composer.lock et vendor/ de tous les services
# Puis réinstalle toutes les dépendances avec composer install
# =============================================================================

set -e

# Couleurs
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Liste des services
SERVICES=(
    "api-gateway"
    "auth-service"
    "messages-broker"
    "addresses-service"
    "products-service"
    "baskets-service"
    "orders-service"
    "deliveries-service"
    "newsletters-service"
    "sav-service"
    "contacts-service"
    "questions-service"
    "websites-service"
)

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  Reset Composer Dependencies${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

# Fonction pour nettoyer un service
clean_service() {
    local service=$1
    local service_path="services/$service"

    echo -e "${YELLOW}🧹 Nettoyage de $service...${NC}"

    if [ -d "$service_path" ]; then
        # Supprimer composer.lock
        if [ -f "$service_path/composer.lock" ]; then
            rm -f "$service_path/composer.lock"
            echo -e "  ${GREEN}✓${NC} composer.lock supprimé"
        else
            echo -e "  ${BLUE}ℹ${NC} composer.lock n'existe pas"
        fi

        # Supprimer vendor/
        if [ -d "$service_path/vendor" ]; then
            rm -rf "$service_path/vendor"
            echo -e "  ${GREEN}✓${NC} vendor/ supprimé"
        else
            echo -e "  ${BLUE}ℹ${NC} vendor/ n'existe pas"
        fi
    else
        echo -e "  ${RED}✗${NC} Service directory not found: $service_path"
        return 1
    fi
}

# Fonction pour réinstaller les dépendances
install_service() {
    local service=$1
    local service_path="services/$service"

    echo -e "${YELLOW}📦 Installation des dépendances pour $service...${NC}"

    if [ -d "$service_path" ]; then
        # Composer install dans le conteneur Docker si disponible
        if docker-compose ps | grep -q "$service"; then
            docker-compose exec -T "$service" composer install --no-interaction --prefer-dist --optimize-autoloader
            echo -e "  ${GREEN}✓${NC} Dépendances installées (Docker)"
        else
            # Sinon installation locale
            if [ -f "$service_path/composer.json" ]; then
                cd "$service_path"
                composer install --no-interaction --prefer-dist --optimize-autoloader
                cd ../..
                echo -e "  ${GREEN}✓${NC} Dépendances installées (local)"
            else
                echo -e "  ${RED}✗${NC} composer.json not found"
                return 1
            fi
        fi
    else
        echo -e "  ${RED}✗${NC} Service directory not found: $service_path"
        return 1
    fi
}

# Phase 1: Nettoyage
echo -e "${BLUE}Phase 1: Nettoyage des dépendances existantes${NC}"
echo ""

for service in "${SERVICES[@]}"; do
    clean_service "$service"
    echo ""
done

# Nettoyer shared/ aussi
echo -e "${YELLOW}🧹 Nettoyage de shared/...${NC}"
if [ -f "shared/composer.lock" ]; then
    rm -f shared/composer.lock
    echo -e "  ${GREEN}✓${NC} shared/composer.lock supprimé"
fi
if [ -d "shared/vendor" ]; then
    rm -rf shared/vendor
    echo -e "  ${GREEN}✓${NC} shared/vendor/ supprimé"
fi
echo ""

echo -e "${GREEN}✅ Phase 1 terminée: Nettoyage complet${NC}"
echo ""

# Phase 2: Réinstallation
echo -e "${BLUE}Phase 2: Réinstallation des dépendances${NC}"
echo ""

# Installer shared/ en premier (si les services dépendent de lui)
echo -e "${YELLOW}📦 Installation des dépendances pour shared/...${NC}"
if [ -f "shared/composer.json" ]; then
    cd shared
    composer install --no-interaction --prefer-dist --optimize-autoloader
    cd ..
    echo -e "  ${GREEN}✓${NC} shared/ dépendances installées"
else
    echo -e "  ${BLUE}ℹ${NC} shared/composer.json n'existe pas"
fi
echo ""

# Installer les services
for service in "${SERVICES[@]}"; do
    install_service "$service"
    echo ""
done

echo -e "${GREEN}✅ Phase 2 terminée: Toutes les dépendances réinstallées${NC}"
echo ""

# Résumé
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✅ Reset Composer terminé avec succès!${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "${YELLOW}Résumé:${NC}"
echo -e "  - ${GREEN}13 services${NC} nettoyés et réinstallés"
echo -e "  - ${GREEN}shared/${NC} nettoyé et réinstallé"
echo ""
echo -e "${BLUE}💡 Conseil: Redémarrez les services Docker avec 'make docker-stop && make docker-start'${NC}"
echo ""
