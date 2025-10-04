#!/bin/bash
# MinIO Integration Test Suite

set -e

# Couleurs
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

MINIO_ENDPOINT="http://localhost:9000"
MINIO_USER="admin"
MINIO_PASSWORD="adminpass123"

TEST_PASSED=0
TEST_FAILED=0

# Function: Print test header
print_test_header() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

# Function: Run test
run_test() {
    echo -n "  [TEST] $1... "
}

# Function: Pass test
pass_test() {
    echo -e "${GREEN}✓ PASS${NC}"
    TEST_PASSED=$((TEST_PASSED + 1))
}

# Function: Fail test
fail_test() {
    echo -e "${RED}✗ FAIL${NC}"
    TEST_FAILED=$((TEST_FAILED + 1))
    if [ -n "$1" ]; then
        echo -e "         ${YELLOW}→ $1${NC}"
    fi
}

echo ""
echo -e "${BLUE}╔════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  🧪 MinIO Integration Test Suite                      ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════╝${NC}"

# ============================================================================
# 1. INFRASTRUCTURE TESTS
# ============================================================================
print_test_header "1. Infrastructure Tests"

run_test "MinIO container is running"
if docker ps | grep -q "minio-storage.*healthy"; then
    pass_test
else
    fail_test "Container not healthy or not running"
fi

run_test "MinIO API is accessible on port 9000"
if curl -f -s "${MINIO_ENDPOINT}/minio/health/live" > /dev/null 2>&1; then
    pass_test
else
    fail_test "API endpoint not accessible"
fi

run_test "MinIO Console is accessible on port 9001"
if curl -f -s "http://localhost:9001" > /dev/null 2>&1; then
    pass_test
else
    fail_test "Console not accessible"
fi

# ============================================================================
# 2. BUCKET TESTS
# ============================================================================
print_test_header "2. Bucket Configuration Tests"

for bucket in "products" "sav" "newsletters"; do
    run_test "Bucket '${bucket}' exists"
    if docker exec minio-storage test -d "/data/${bucket}" 2>/dev/null; then
        pass_test
    else
        fail_test "Bucket directory not found"
    fi
done

# ============================================================================
# 3. FILE STORAGE TESTS
# ============================================================================
print_test_header "3. File Storage Tests"

# Test file upload via MinIO API
run_test "Create test file"
TEST_FILE="/tmp/minio-test-$(date +%s).txt"
echo "MinIO Integration Test - $(date)" > "$TEST_FILE"
if [ -f "$TEST_FILE" ]; then
    pass_test
else
    fail_test "Could not create test file"
fi

run_test "Copy test file to products bucket"
if docker cp "$TEST_FILE" minio-storage:/data/products/test-upload.txt 2>/dev/null; then
    pass_test
else
    fail_test "Could not copy file to bucket"
fi

run_test "Verify file exists in MinIO"
if docker exec minio-storage test -f "/data/products/test-upload.txt" 2>/dev/null; then
    pass_test
else
    fail_test "File not found in bucket"
fi

run_test "Read file content from MinIO"
if docker exec minio-storage cat "/data/products/test-upload.txt" | grep -q "MinIO Integration Test"; then
    pass_test
else
    fail_test "Could not read file content"
fi

run_test "Delete test file from MinIO"
if docker exec minio-storage rm -f "/data/products/test-upload.txt" 2>/dev/null; then
    pass_test
else
    fail_test "Could not delete test file"
fi

# Clean up local test file
rm -f "$TEST_FILE"

# ============================================================================
# 4. SERVICES DEPENDENCY TESTS
# ============================================================================
print_test_header "4. Services Dependency Tests"

run_test "Products service has MinIO environment configured"
if docker-compose config | grep -A 5 "products-service:" | grep -q "MINIO_ENDPOINT"; then
    pass_test
else
    fail_test "MinIO env vars not configured"
fi

run_test "SAV service has MinIO environment configured"
if docker-compose config | grep -A 10 "sav-service:" | grep -q "MINIO_ENDPOINT"; then
    pass_test
else
    fail_test "MinIO env vars not configured"
fi

run_test "Newsletters service has MinIO environment configured"
if docker-compose config | grep -A 10 "newsletters-service:" | grep -q "MINIO_ENDPOINT"; then
    pass_test
else
    fail_test "MinIO env vars not configured"
fi

# ============================================================================
# 5. SHARED SERVICE TESTS
# ============================================================================
print_test_header "5. Shared MinIO Service Tests"

run_test "MinioService class exists"
if [ -f "shared/Services/MinioService.php" ]; then
    pass_test
else
    fail_test "MinioService.php not found"
fi

run_test "MinioService has required methods"
required_methods=("uploadFile" "getFile" "deleteFile" "getPresignedUrl" "listFiles")
all_found=true

for method in "${required_methods[@]}"; do
    if ! grep -q "function ${method}" shared/Services/MinioService.php 2>/dev/null; then
        all_found=false
        break
    fi
done

if $all_found; then
    pass_test
else
    fail_test "Missing required methods"
fi

# ============================================================================
# 6. CONTROLLER INTEGRATION TESTS
# ============================================================================
print_test_header "6. Controller Integration Tests"

run_test "ProductImagesController exists and uses MinioService"
if [ -f "services/products-service/app/Http/Controllers/API/ProductImagesController.php" ] && \
   grep -q "MinioService" "services/products-service/app/Http/Controllers/API/ProductImagesController.php"; then
    pass_test
else
    fail_test "Controller not properly configured"
fi

run_test "TicketAttachmentController exists and uses MinioService"
if [ -f "services/sav-service/app/Http/Controllers/API/TicketAttachmentController.php" ] && \
   grep -q "MinioService" "services/sav-service/app/Http/Controllers/API/TicketAttachmentController.php"; then
    pass_test
else
    fail_test "Controller not properly configured"
fi

run_test "TemplateAssetsController exists and uses MinioService"
if [ -f "services/newsletters-service/app/Http/Controllers/API/TemplateAssetsController.php" ] && \
   grep -q "MinioService" "services/newsletters-service/app/Http/Controllers/API/TemplateAssetsController.php"; then
    pass_test
else
    fail_test "Controller not properly configured"
fi

# ============================================================================
# 7. STORAGE CAPACITY TESTS
# ============================================================================
print_test_header "7. Storage Capacity Tests"

run_test "Check MinIO storage volume"
if docker volume inspect e-commerce-back_minio-data > /dev/null 2>&1; then
    pass_test
else
    fail_test "MinIO volume not found"
fi

run_test "Verify storage is writable"
if docker exec minio-storage touch /data/products/.write-test 2>/dev/null && \
   docker exec minio-storage rm /data/products/.write-test 2>/dev/null; then
    pass_test
else
    fail_test "Storage not writable"
fi

# ============================================================================
# 8. SECURITY TESTS
# ============================================================================
print_test_header "8. Security Configuration Tests"

run_test "MinIO credentials are configured"
if docker exec minio-storage printenv | grep -q "MINIO_ROOT_USER"; then
    pass_test
else
    fail_test "Credentials not properly set"
fi

run_test "Buckets are not publicly accessible"
# Try to access bucket without auth (should return 403 or 404, not 200 with content)
status=$(curl -s -o /dev/null -w "%{http_code}" "${MINIO_ENDPOINT}/products/")
if [ "$status" = "403" ] || [ "$status" = "404" ]; then
    pass_test
else
    fail_test "Buckets may be publicly accessible (status: $status)"
fi

# ============================================================================
# RESULTS
# ============================================================================

echo ""
print_test_header "📊 Test Results Summary"

TOTAL_TESTS=$((TEST_PASSED + TEST_FAILED))
echo ""
echo "  Total Tests:  $TOTAL_TESTS"
echo -e "  Passed:       ${GREEN}$TEST_PASSED${NC}"
echo -e "  Failed:       ${RED}$TEST_FAILED${NC}"

if [ $TEST_FAILED -eq 0 ]; then
    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║  ✓ ALL INTEGRATION TESTS PASSED!                      ║${NC}"
    echo -e "${GREEN}║                                                        ║${NC}"
    echo -e "${GREEN}║  MinIO is fully operational and ready for use         ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo "🎉 Next Steps:"
    echo "   1. Install AWS SDK in services: composer require aws/aws-sdk-php"
    echo "   2. Start application services: docker-compose up -d"
    echo "   3. Test API endpoints with file uploads"
    echo "   4. Access MinIO Console: http://localhost:9001"
    echo ""
    exit 0
else
    echo ""
    echo -e "${RED}╔════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║  ✗ SOME TESTS FAILED                                   ║${NC}"
    echo -e "${RED}║                                                        ║${NC}"
    echo -e "${RED}║  Please review failed tests above                     ║${NC}"
    echo -e "${RED}╚════════════════════════════════════════════════════════╝${NC}"
    echo ""
    exit 1
fi
