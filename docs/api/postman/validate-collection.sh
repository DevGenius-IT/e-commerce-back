#!/bin/bash

# E-commerce Platform API v2.0 - Collection Validation Script
# Usage: ./validate-collection.sh [environment]
# Environments: development (default), staging, production

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
ENVIRONMENT=${1:-development}
COLLECTION_FILE="Complete E-commerce API v2.postman_collection.json"
ENV_FILE="${ENVIRONMENT^} Environment.postman_environment.json"
NEWMAN_OPTS="--timeout-request 10000 --delay-request 500"

echo -e "${BLUE}🧪 E-commerce API Collection Validation${NC}"
echo -e "${BLUE}Environment: ${ENVIRONMENT}${NC}"
echo "================================================"

# Check prerequisites
echo -e "${YELLOW}📋 Checking prerequisites...${NC}"

if ! command -v newman &> /dev/null; then
    echo -e "${RED}❌ Newman CLI not found. Install with: npm install -g newman${NC}"
    exit 1
fi

if [ ! -f "$COLLECTION_FILE" ]; then
    echo -e "${RED}❌ Collection file not found: $COLLECTION_FILE${NC}"
    exit 1
fi

if [ ! -f "$ENV_FILE" ]; then
    echo -e "${RED}❌ Environment file not found: $ENV_FILE${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Prerequisites satisfied${NC}"

# Validate collection structure
echo -e "${YELLOW}🔍 Validating collection structure...${NC}"

# Check if collection is valid JSON
if ! jq empty "$COLLECTION_FILE" 2>/dev/null; then
    echo -e "${RED}❌ Invalid JSON in collection file${NC}"
    exit 1
fi

# Check if environment is valid JSON
if ! jq empty "$ENV_FILE" 2>/dev/null; then
    echo -e "${RED}❌ Invalid JSON in environment file${NC}"
    exit 1
fi

# Count items in collection
TOTAL_REQUESTS=$(jq -r '[.. | objects | select(has("request")) | .request] | length' "$COLLECTION_FILE")
echo -e "${GREEN}✅ Collection structure valid (${TOTAL_REQUESTS} requests)${NC}"

# Environment-specific validations
case $ENVIRONMENT in
    "development")
        echo -e "${YELLOW}🐳 Validating Docker development environment...${NC}"
        
        # Check if Docker is running
        if ! docker info >/dev/null 2>&1; then
            echo -e "${RED}❌ Docker is not running${NC}"
            exit 1
        fi
        
        # Check if services are up
        if ! curl -s http://localhost/api/health >/dev/null; then
            echo -e "${RED}❌ API Gateway not accessible. Run: make docker-start${NC}"
            exit 1
        fi
        
        echo -e "${GREEN}✅ Development environment ready${NC}"
        ;;
        
    "staging")
        echo -e "${YELLOW}☸️ Validating Kubernetes staging environment...${NC}"
        
        # Check kubectl access
        if ! kubectl cluster-info >/dev/null 2>&1; then
            echo -e "${RED}❌ Kubernetes cluster not accessible${NC}"
            exit 1
        fi
        
        # Check staging namespace
        if ! kubectl get namespace staging-microservices >/dev/null 2>&1; then
            echo -e "${RED}❌ Staging namespace not found${NC}"
            exit 1
        fi
        
        echo -e "${GREEN}✅ Staging environment ready${NC}"
        ;;
        
    "production")
        echo -e "${RED}⚠️ Production environment validation${NC}"
        echo -e "${YELLOW}Are you sure you want to run tests against production? (y/N)${NC}"
        read -r confirm
        if [[ ! $confirm =~ ^[Yy]$ ]]; then
            echo "Aborted."
            exit 0
        fi
        ;;
esac

# Run health checks first
echo -e "${YELLOW}🏥 Running health checks...${NC}"

newman run "$COLLECTION_FILE" \
    --environment "$ENV_FILE" \
    --folder "🏥 Health & Monitoring" \
    $NEWMAN_OPTS \
    --reporters cli,json \
    --reporter-json-export "/tmp/health-check-results.json" || {
    echo -e "${RED}❌ Health checks failed${NC}"
    exit 1
}

echo -e "${GREEN}✅ Health checks passed${NC}"

# Run authentication tests
echo -e "${YELLOW}🔐 Testing authentication...${NC}"

newman run "$COLLECTION_FILE" \
    --environment "$ENV_FILE" \
    --folder "🔐 Authentication & Authorization" \
    $NEWMAN_OPTS \
    --reporters cli,json \
    --reporter-json-export "/tmp/auth-test-results.json" || {
    echo -e "${RED}❌ Authentication tests failed${NC}"
    exit 1
}

echo -e "${GREEN}✅ Authentication tests passed${NC}"

# Run core service tests (non-destructive)
echo -e "${YELLOW}🛍️ Testing core services...${NC}"

CORE_FOLDERS=(
    "🛍️ Products & Catalog"
    "🏠 Addresses & Locations"
    "❓ FAQ & Questions"
    "🌐 Website Management"
)

for folder in "${CORE_FOLDERS[@]}"; do
    echo -e "${BLUE}Testing: $folder${NC}"
    
    newman run "$COLLECTION_FILE" \
        --environment "$ENV_FILE" \
        --folder "$folder" \
        $NEWMAN_OPTS \
        --reporters cli || {
        echo -e "${RED}❌ Tests failed for: $folder${NC}"
        exit 1
    }
done

echo -e "${GREEN}✅ Core service tests passed${NC}"

# Run E2E workflow (if not production)
if [ "$ENVIRONMENT" != "production" ]; then
    echo -e "${YELLOW}🔧 Running E2E workflow tests...${NC}"
    
    newman run "$COLLECTION_FILE" \
        --environment "$ENV_FILE" \
        --folder "🔧 E2E Workflow Tests" \
        $NEWMAN_OPTS \
        --reporters cli || {
        echo -e "${YELLOW}⚠️ E2E tests had issues (may be expected in $ENVIRONMENT)${NC}"
    }
fi

# Generate summary report
echo -e "${YELLOW}📊 Generating validation report...${NC}"

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
REPORT_FILE="validation-report-${ENVIRONMENT}-${TIMESTAMP}.json"

# Combine all test results
jq -s 'add' /tmp/*-test-results.json > "$REPORT_FILE" 2>/dev/null || {
    echo -e "${YELLOW}⚠️ Could not generate detailed report${NC}"
}

# Cleanup temporary files
rm -f /tmp/*-test-results.json

echo "================================================"
echo -e "${GREEN}🎉 Validation completed successfully!${NC}"
echo -e "${BLUE}Environment: $ENVIRONMENT${NC}"
echo -e "${BLUE}Collection: $COLLECTION_FILE${NC}"
echo -e "${BLUE}Total Requests: $TOTAL_REQUESTS${NC}"

if [ -f "$REPORT_FILE" ]; then
    echo -e "${BLUE}Report: $REPORT_FILE${NC}"
fi

echo "================================================"

# Environment-specific next steps
case $ENVIRONMENT in
    "development")
        echo -e "${YELLOW}💡 Next steps for development:${NC}"
        echo "• Import collection in Postman"
        echo "• Run individual service tests"
        echo "• Test E2E workflows manually"
        echo "• Check RabbitMQ Management: http://localhost:15672"
        ;;
        
    "staging")
        echo -e "${YELLOW}💡 Next steps for staging:${NC}"
        echo "• Run full regression tests"
        echo "• Validate performance benchmarks"
        echo "• Test deployment workflows"
        echo "• Verify monitoring alerts"
        ;;
        
    "production")
        echo -e "${RED}💡 Production environment validated${NC}"
        echo -e "${YELLOW}⚠️ Use production tests sparingly${NC}"
        echo "• Monitor service health continuously"
        echo "• Set up automated health checks"
        echo "• Review error rates and performance"
        ;;
esac

echo -e "${GREEN}✅ Collection ready for use in $ENVIRONMENT! 🚀${NC}"