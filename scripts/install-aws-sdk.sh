#!/bin/bash
# Install AWS SDK in Services

set -e

# Couleurs
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo ""
echo -e "${BLUE}╔════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  📦 Installing AWS SDK for MinIO Integration          ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════╝${NC}"
echo ""

SERVICES=("products-service" "sav-service" "newsletters-service")

for service in "${SERVICES[@]}"; do
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}  Installing in ${service}...${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    if docker-compose ps | grep -q "${service}.*Up"; then
        echo "  Service is running, installing via docker-compose exec..."
        docker-compose exec -T ${service} composer require aws/aws-sdk-php
    else
        echo -e "${YELLOW}  Service not running, will install on next start${NC}"
        echo -e "${YELLOW}  Add to composer.json manually or start service first${NC}"
    fi
    
    echo ""
done

echo -e "${GREEN}╔════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  ✓ AWS SDK Installation Complete                      ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════╝${NC}"
echo ""
echo "Next steps:"
echo "  1. Restart services: docker-compose restart"
echo "  2. Verify installation: docker-compose exec products-service composer show aws/aws-sdk-php"
echo "  3. Test file uploads via API endpoints"
echo ""
