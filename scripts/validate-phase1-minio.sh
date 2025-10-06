#!/bin/bash
# Phase 1 MinIO Deployment Validation Script

set -e

# Couleurs
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

FAILED_CHECKS=0
TOTAL_CHECKS=0

# Function: Print section header
print_header() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

# Function: Run check
run_check() {
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
    echo -n "  [$TOTAL_CHECKS] $1... "
}

# Function: Pass check
pass_check() {
    echo -e "${GREEN}✓ PASS${NC}"
}

# Function: Fail check
fail_check() {
    echo -e "${RED}✗ FAIL${NC}"
    FAILED_CHECKS=$((FAILED_CHECKS + 1))
    if [ -n "$1" ]; then
        echo -e "      ${YELLOW}→ $1${NC}"
    fi
}

# ============================================================================
# PHASE 1 VALIDATION CHECKS
# ============================================================================

echo ""
echo -e "${BLUE}╔════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  🚀 Phase 1: MinIO Deployment Validation              ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════╝${NC}"

# 1. Docker Compose Configuration
print_header "1. Docker Compose Configuration"

run_check "MinIO service defined in docker-compose.yml"
if grep -q "minio:" docker-compose.yml 2>/dev/null; then
    pass_check
else
    fail_check "MinIO service not found in docker-compose.yml"
fi

run_check "MinIO volume configured"
if grep -q "minio-data:" docker-compose.yml 2>/dev/null; then
    pass_check
else
    fail_check "MinIO volume not configured"
fi

run_check "Products service has MinIO environment variables"
if grep -q "MINIO_ENDPOINT.*minio:9000" docker-compose.yml 2>/dev/null; then
    pass_check
else
    fail_check "Products service missing MinIO config"
fi

run_check "SAV service has MinIO environment variables"
if grep -A 20 "sav-service:" docker-compose.yml | grep -q "MINIO_ENDPOINT" 2>/dev/null; then
    pass_check
else
    fail_check "SAV service missing MinIO config"
fi

run_check "Newsletters service has MinIO environment variables"
if grep -A 20 "newsletters-service:" docker-compose.yml | grep -q "MINIO_ENDPOINT" 2>/dev/null; then
    pass_check
else
    fail_check "Newsletters service missing MinIO config"
fi

# 2. Shared MinIO Service
print_header "2. Shared MinIO Service"

run_check "MinioService exists in shared/"
if [ -f "shared/Services/MinioService.php" ]; then
    pass_check
else
    fail_check "shared/Services/MinioService.php not found"
fi

run_check "MinioService has uploadFile method"
if [ -f "shared/Services/MinioService.php" ] && grep -q "function uploadFile" shared/Services/MinioService.php 2>/dev/null; then
    pass_check
else
    fail_check "uploadFile method not found"
fi

run_check "MinioService has deleteFile method"
if [ -f "shared/Services/MinioService.php" ] && grep -q "function deleteFile" shared/Services/MinioService.php 2>/dev/null; then
    pass_check
else
    fail_check "deleteFile method not found"
fi

run_check "MinioService has getPresignedUrl method"
if [ -f "shared/Services/MinioService.php" ] && grep -q "function getPresignedUrl" shared/Services/MinioService.php 2>/dev/null; then
    pass_check
else
    fail_check "getPresignedUrl method not found"
fi

# 3. Products Service Integration
print_header "3. Products Service Integration"

run_check "ProductImagesController exists"
if [ -f "services/products-service/app/Http/Controllers/API/ProductImagesController.php" ]; then
    pass_check
else
    fail_check "ProductImagesController not found"
fi

run_check "ProductImage model exists"
if [ -f "services/products-service/app/Models/ProductImage.php" ]; then
    pass_check
else
    fail_check "ProductImage model not found"
fi

run_check "ProductImages migration exists"
if ls services/products-service/database/migrations/*create_product_images_table.php 2>/dev/null 1>&2; then
    pass_check
else
    fail_check "ProductImages migration not found"
fi

run_check "ProductImagesController uses MinioService"
if [ -f "services/products-service/app/Http/Controllers/API/ProductImagesController.php" ] && \
   grep -q "MinioService" services/products-service/app/Http/Controllers/API/ProductImagesController.php 2>/dev/null; then
    pass_check
else
    fail_check "ProductImagesController not using MinioService"
fi

# 4. SAV Service Integration
print_header "4. SAV Service Integration"

run_check "TicketAttachmentController exists"
if [ -f "services/sav-service/app/Http/Controllers/API/TicketAttachmentController.php" ]; then
    pass_check
else
    fail_check "TicketAttachmentController not found"
fi

run_check "TicketAttachmentController uses MinioService"
if [ -f "services/sav-service/app/Http/Controllers/API/TicketAttachmentController.php" ] && \
   grep -q "MinioService" services/sav-service/app/Http/Controllers/API/TicketAttachmentController.php 2>/dev/null; then
    pass_check
else
    fail_check "TicketAttachmentController not using MinioService"
fi

run_check "TicketAttachmentController has sanitizeFilename method"
if [ -f "services/sav-service/app/Http/Controllers/API/TicketAttachmentController.php" ] && \
   grep -q "function sanitizeFilename" services/sav-service/app/Http/Controllers/API/TicketAttachmentController.php 2>/dev/null; then
    pass_check
else
    fail_check "sanitizeFilename method not found"
fi

# 5. Newsletters Service Integration
print_header "5. Newsletters Service Integration"

run_check "TemplateAssetsController exists"
if [ -f "services/newsletters-service/app/Http/Controllers/API/TemplateAssetsController.php" ]; then
    pass_check
else
    fail_check "TemplateAssetsController not found"
fi

run_check "TemplateAssetsController uses MinioService"
if [ -f "services/newsletters-service/app/Http/Controllers/API/TemplateAssetsController.php" ] && \
   grep -q "MinioService" services/newsletters-service/app/Http/Controllers/API/TemplateAssetsController.php 2>/dev/null; then
    pass_check
else
    fail_check "TemplateAssetsController not using MinioService"
fi

# 6. Environment Configuration
print_header "6. Environment Configuration"

run_check ".env has MINIO_ENDPOINT configured"
if grep -q "MINIO_ENDPOINT" .env 2>/dev/null; then
    pass_check
else
    fail_check "MINIO_ENDPOINT not in .env"
fi

run_check ".env has MINIO_ROOT_USER configured"
if grep -q "MINIO_ROOT_USER" .env 2>/dev/null; then
    pass_check
else
    fail_check "MINIO_ROOT_USER not in .env"
fi

run_check ".env has MINIO_ROOT_PASSWORD configured"
if grep -q "MINIO_ROOT_PASSWORD" .env 2>/dev/null; then
    pass_check
else
    fail_check "MINIO_ROOT_PASSWORD not in .env"
fi

run_check ".env has bucket configurations"
if grep -q "MINIO_BUCKET" .env 2>/dev/null; then
    pass_check
else
    fail_check "Bucket configurations not in .env"
fi

# 7. Scripts & Tools
print_header "7. Scripts & Validation Tools"

run_check "MinIO health check script exists"
if [ -f "scripts/minio-health-check.sh" ]; then
    pass_check
else
    fail_check "minio-health-check.sh not found"
fi

run_check "MinIO health check script is executable"
if [ -x "scripts/minio-health-check.sh" ]; then
    pass_check
else
    fail_check "minio-health-check.sh not executable"
fi

run_check "MinIO buckets setup script exists"
if [ -f "scripts/minio-setup-buckets.sh" ]; then
    pass_check
else
    fail_check "minio-setup-buckets.sh not found"
fi

run_check "MinIO buckets setup script is executable"
if [ -x "scripts/minio-setup-buckets.sh" ]; then
    pass_check
else
    fail_check "minio-setup-buckets.sh not executable"
fi

# ============================================================================
# RESULTS
# ============================================================================

echo ""
print_header "📊 Validation Results"

echo ""
echo "  Total Checks: $TOTAL_CHECKS"
echo -e "  Passed:       ${GREEN}$((TOTAL_CHECKS - FAILED_CHECKS))${NC}"
echo -e "  Failed:       ${RED}$FAILED_CHECKS${NC}"

if [ $FAILED_CHECKS -eq 0 ]; then
    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║  ✓ Phase 1 Validation: ALL CHECKS PASSED!             ║${NC}"
    echo -e "${GREEN}║                                                        ║${NC}"
    echo -e "${GREEN}║  MinIO deployment is complete and ready for testing   ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo "🚀 Next Steps:"
    echo "   1. Start services: docker-compose up -d"
    echo "   2. Run health check: ./scripts/minio-health-check.sh"
    echo "   3. Access MinIO Console: http://localhost:9001"
    echo "   4. Test file uploads via API endpoints"
    echo ""
    exit 0
else
    echo ""
    echo -e "${RED}╔════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║  ✗ Phase 1 Validation: FAILED                          ║${NC}"
    echo -e "${RED}║                                                        ║${NC}"
    echo -e "${RED}║  Please fix the failed checks before proceeding       ║${NC}"
    echo -e "${RED}╚════════════════════════════════════════════════════════╝${NC}"
    echo ""
    exit 1
fi
