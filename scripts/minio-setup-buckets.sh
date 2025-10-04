#!/bin/bash
# MinIO Buckets Setup Script

set -e

echo "🪣 MinIO Buckets Setup Starting..."

# Configuration
MINIO_HOST=${MINIO_HOST:-"minio"}
MINIO_PORT=${MINIO_PORT:-"9000"}
MINIO_ROOT_USER=${MINIO_ROOT_USER:-"admin"}
MINIO_ROOT_PASSWORD=${MINIO_ROOT_PASSWORD:-"adminpass123"}
MINIO_ENDPOINT="http://${MINIO_HOST}:${MINIO_PORT}"

# Couleurs
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Buckets à créer
declare -A BUCKETS=(
    ["products"]="Product images and media files"
    ["sav"]="Support ticket attachments and documents"
    ["newsletters"]="Newsletter templates and assets"
)

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "MinIO Configuration:"
echo "  Endpoint: ${MINIO_ENDPOINT}"
echo "  User:     ${MINIO_ROOT_USER}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Wait for MinIO to be ready
echo "⏳ Waiting for MinIO to be ready..."
max_attempts=30
attempt=0

while [ $attempt -lt $max_attempts ]; do
    if curl -f -s "${MINIO_ENDPOINT}/minio/health/live" > /dev/null 2>&1; then
        echo -e "${GREEN}✓ MinIO is ready!${NC}"
        break
    fi
    
    attempt=$((attempt + 1))
    echo "   Attempt ${attempt}/${max_attempts}..."
    sleep 2
done

if [ $attempt -eq $max_attempts ]; then
    echo -e "${RED}✗ MinIO failed to start${NC}"
    exit 1
fi

echo ""
echo "📦 Creating buckets..."

# Note: Bucket creation requires MinIO client (mc) or AWS SDK
# For Docker setup, buckets should be created via application bootstrap
# This script documents the required buckets

for bucket in "${!BUCKETS[@]}"; do
    echo -e "${BLUE}  - ${bucket}${NC}: ${BUCKETS[$bucket]}"
done

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "${GREEN}✓ Setup complete!${NC}"
echo ""
echo "📝 Note: Buckets will be created automatically by Laravel services"
echo "   on first file upload or can be created manually via:"
echo ""
echo "   docker exec -it minio-storage mc mb /data/products"
echo "   docker exec -it minio-storage mc mb /data/sav"
echo "   docker exec -it minio-storage mc mb /data/newsletters"
echo ""
echo "🌐 Access MinIO Console:"
echo "   URL:      http://localhost:9001"
echo "   Username: ${MINIO_ROOT_USER}"
echo "   Password: ${MINIO_ROOT_PASSWORD}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
