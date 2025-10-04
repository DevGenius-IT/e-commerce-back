#!/bin/bash
# MinIO Health Check Script

set -e

echo "🏥 MinIO Health Check Starting..."

# Configuration
MINIO_ENDPOINT=${MINIO_ENDPOINT:-"http://localhost:9000"}
MINIO_CONSOLE=${MINIO_CONSOLE:-"http://localhost:9001"}
BUCKETS=("products" "sav" "newsletters")

# Couleurs
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function: Check MinIO Server Health
check_server_health() {
    echo -n "Checking MinIO server health... "
    
    if curl -f -s "${MINIO_ENDPOINT}/minio/health/live" > /dev/null 2>&1; then
        echo -e "${GREEN}✓ OK${NC}"
        return 0
    else
        echo -e "${RED}✗ FAILED${NC}"
        return 1
    fi
}

# Function: Check MinIO Console
check_console() {
    echo -n "Checking MinIO console... "
    
    if curl -f -s "${MINIO_CONSOLE}" > /dev/null 2>&1; then
        echo -e "${GREEN}✓ OK${NC}"
        return 0
    else
        echo -e "${RED}✗ FAILED${NC}"
        return 1
    fi
}

# Function: Check Bucket Accessibility
check_buckets() {
    echo "Checking buckets accessibility..."
    
    for bucket in "${BUCKETS[@]}"; do
        echo -n "  - Bucket '${bucket}': "
        
        # Try to access bucket (will get 403 without auth, which is OK)
        status_code=$(curl -s -o /dev/null -w "%{http_code}" "${MINIO_ENDPOINT}/${bucket}/")
        
        if [ "$status_code" = "200" ] || [ "$status_code" = "403" ]; then
            echo -e "${GREEN}✓ Accessible${NC}"
        else
            echo -e "${RED}✗ Not found (${status_code})${NC}"
        fi
    done
}

# Function: Display MinIO Info
display_info() {
    echo ""
    echo "📊 MinIO Information:"
    echo "  Endpoint: ${MINIO_ENDPOINT}"
    echo "  Console:  ${MINIO_CONSOLE}"
    echo "  Buckets:  ${BUCKETS[*]}"
}

# Main execution
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
display_info
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

FAILED=0

check_server_health || FAILED=1
check_console || FAILED=1
check_buckets || FAILED=1

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All health checks passed!${NC}"
    exit 0
else
    echo -e "${RED}✗ Some health checks failed!${NC}"
    exit 1
fi
