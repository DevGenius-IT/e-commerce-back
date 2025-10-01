#!/bin/bash

# E-commerce Platform Integration Tests
# Comprehensive end-to-end testing for all platform services

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
NAMESPACE="e-commerce"
BASE_URL="http://localhost:8080"
TEST_USER_EMAIL="test@example.com"
TEST_USER_PASSWORD="testpassword123"

# Test results
TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0
declare -A TEST_DETAILS

# Utility functions
log_test() {
    echo -e "${BLUE}[TEST]${NC} $1"
}

log_pass() {
    echo -e "${GREEN}[PASS]${NC} $1"
    TESTS_PASSED=$((TESTS_PASSED + 1))
    TEST_DETAILS["$1"]="PASS"
}

log_fail() {
    echo -e "${RED}[FAIL]${NC} $1"
    TESTS_FAILED=$((TESTS_FAILED + 1))
    TEST_DETAILS["$1"]="FAIL"
}

run_test() {
    local test_name="$1"
    TESTS_RUN=$((TESTS_RUN + 1))
    log_test "$test_name"
}

# Setup test environment
setup_test_environment() {
    log_test "Setting up test environment..."
    
    # Start port forwarding to api-gateway
    kubectl port-forward svc/api-gateway 8080:80 -n "$NAMESPACE" >/dev/null 2>&1 &
    PF_PID=$!
    
    # Wait for port forward to be ready
    sleep 5
    
    # Verify api-gateway is accessible
    if curl -sf "$BASE_URL/health" >/dev/null 2>&1; then
        log_pass "Test environment setup complete"
    else
        log_fail "Failed to setup test environment - API Gateway not accessible"
        cleanup_test_environment
        exit 1
    fi
}

cleanup_test_environment() {
    log_test "Cleaning up test environment..."
    
    # Kill port forward if it exists
    if [ -n "${PF_PID:-}" ]; then
        kill $PF_PID 2>/dev/null || true
    fi
    
    log_pass "Test environment cleanup complete"
}

# Authentication service tests
test_auth_service_health() {
    run_test "Auth Service Health Check"
    
    if curl -sf "$BASE_URL/auth/health" >/dev/null 2>&1; then
        log_pass "Auth service health check"
    else
        log_fail "Auth service health check"
    fi
}

test_user_registration() {
    run_test "User Registration"
    
    local response=$(curl -s -w "%{http_code}" -X POST "$BASE_URL/auth/register" \
        -H "Content-Type: application/json" \
        -d "{\"email\":\"$TEST_USER_EMAIL\",\"password\":\"$TEST_USER_PASSWORD\",\"name\":\"Test User\"}" \
        -o /tmp/register_response.json)
    
    if [ "$response" = "201" ] || [ "$response" = "422" ]; then  # 422 if user already exists
        log_pass "User registration (status: $response)"
    else
        log_fail "User registration (status: $response)"
    fi
}

test_user_login() {
    run_test "User Login"
    
    local response=$(curl -s -w "%{http_code}" -X POST "$BASE_URL/auth/login" \
        -H "Content-Type: application/json" \
        -d "{\"email\":\"$TEST_USER_EMAIL\",\"password\":\"$TEST_USER_PASSWORD\"}" \
        -o /tmp/login_response.json)
    
    if [ "$response" = "200" ]; then
        # Extract JWT token for further tests
        if command -v jq >/dev/null 2>&1; then
            JWT_TOKEN=$(jq -r '.access_token' /tmp/login_response.json 2>/dev/null || echo "")
        fi
        log_pass "User login (status: $response)"
    else
        log_fail "User login (status: $response)"
        JWT_TOKEN=""
    fi
}

test_protected_endpoint() {
    run_test "Protected Endpoint Access"
    
    if [ -z "${JWT_TOKEN:-}" ]; then
        log_fail "No JWT token available for protected endpoint test"
        return
    fi
    
    local response=$(curl -s -w "%{http_code}" -X GET "$BASE_URL/auth/user" \
        -H "Authorization: Bearer $JWT_TOKEN" \
        -o /tmp/user_response.json)
    
    if [ "$response" = "200" ]; then
        log_pass "Protected endpoint access (status: $response)"
    else
        log_fail "Protected endpoint access (status: $response)"
    fi
}

# Addresses service tests
test_addresses_service_health() {
    run_test "Addresses Service Health Check"
    
    if curl -sf "$BASE_URL/addresses/health" >/dev/null 2>&1; then
        log_pass "Addresses service health check"
    else
        log_fail "Addresses service health check"
    fi
}

test_address_creation() {
    run_test "Address Creation"
    
    if [ -z "${JWT_TOKEN:-}" ]; then
        log_fail "No JWT token available for address creation test"
        return
    fi
    
    local response=$(curl -s -w "%{http_code}" -X POST "$BASE_URL/addresses" \
        -H "Authorization: Bearer $JWT_TOKEN" \
        -H "Content-Type: application/json" \
        -d "{\"street\":\"123 Test St\",\"city\":\"Test City\",\"postal_code\":\"12345\",\"country\":\"Test Country\"}" \
        -o /tmp/address_response.json)
    
    if [ "$response" = "201" ]; then
        log_pass "Address creation (status: $response)"
    else
        log_fail "Address creation (status: $response)"
    fi
}

test_address_listing() {
    run_test "Address Listing"
    
    if [ -z "${JWT_TOKEN:-}" ]; then
        log_fail "No JWT token available for address listing test"
        return
    fi
    
    local response=$(curl -s -w "%{http_code}" -X GET "$BASE_URL/addresses" \
        -H "Authorization: Bearer $JWT_TOKEN" \
        -o /tmp/addresses_list_response.json)
    
    if [ "$response" = "200" ]; then
        log_pass "Address listing (status: $response)"
    else
        log_fail "Address listing (status: $response)"
    fi
}

# Messages broker tests
test_messages_broker_health() {
    run_test "Messages Broker Health Check"
    
    if curl -sf "$BASE_URL/messages/health" >/dev/null 2>&1; then
        log_pass "Messages broker health check"
    else
        log_fail "Messages broker health check"
    fi
}

# Products service tests
test_products_service_health() {
    run_test "Products Service Health Check"
    
    if curl -sf "$BASE_URL/products/health" >/dev/null 2>&1; then
        log_pass "Products service health check"
    else
        log_fail "Products service health check"
    fi
}

test_product_listing() {
    run_test "Product Listing"
    
    local response=$(curl -s -w "%{http_code}" -X GET "$BASE_URL/products" \
        -o /tmp/products_response.json)
    
    if [ "$response" = "200" ]; then
        log_pass "Product listing (status: $response)"
    else
        log_fail "Product listing (status: $response)"
    fi
}

# Baskets service tests
test_baskets_service_health() {
    run_test "Baskets Service Health Check"
    
    if curl -sf "$BASE_URL/baskets/health" >/dev/null 2>&1; then
        log_pass "Baskets service health check"
    else
        log_fail "Baskets service health check"
    fi
}

test_basket_operations() {
    run_test "Basket Operations"
    
    if [ -z "${JWT_TOKEN:-}" ]; then
        log_fail "No JWT token available for basket operations test"
        return
    fi
    
    # Get current basket
    local response=$(curl -s -w "%{http_code}" -X GET "$BASE_URL/baskets" \
        -H "Authorization: Bearer $JWT_TOKEN" \
        -o /tmp/basket_response.json)
    
    if [ "$response" = "200" ]; then
        log_pass "Basket operations (status: $response)"
    else
        log_fail "Basket operations (status: $response)"
    fi
}

# Orders service tests
test_orders_service_health() {
    run_test "Orders Service Health Check"
    
    if curl -sf "$BASE_URL/orders/health" >/dev/null 2>&1; then
        log_pass "Orders service health check"
    else
        log_fail "Orders service health check"
    fi
}

# Performance tests
test_api_response_time() {
    run_test "API Response Time"
    
    local start_time=$(date +%s%3N)
    curl -sf "$BASE_URL/auth/health" >/dev/null 2>&1
    local end_time=$(date +%s%3N)
    local response_time=$((end_time - start_time))
    
    if [ "$response_time" -lt 1000 ]; then  # Less than 1 second
        log_pass "API response time: ${response_time}ms"
    else
        log_fail "API response time too slow: ${response_time}ms"
    fi
}

test_concurrent_requests() {
    run_test "Concurrent Request Handling"
    
    # Send 10 concurrent requests
    local pids=()
    for i in {1..10}; do
        curl -sf "$BASE_URL/auth/health" >/dev/null 2>&1 &
        pids+=($!)
    done
    
    # Wait for all requests to complete
    local success_count=0
    for pid in "${pids[@]}"; do
        if wait $pid; then
            success_count=$((success_count + 1))
        fi
    done
    
    if [ "$success_count" -eq 10 ]; then
        log_pass "Concurrent requests: $success_count/10 successful"
    else
        log_fail "Concurrent requests: $success_count/10 successful"
    fi
}

# Database integration tests
test_database_integration() {
    run_test "Database Integration"
    
    # Test if we can create, read, update data through API
    local test_passed=true
    
    # This would require more complex setup with actual database operations
    # For now, we check if services with database dependencies are responding
    if curl -sf "$BASE_URL/auth/health" >/dev/null 2>&1 && \
       curl -sf "$BASE_URL/addresses/health" >/dev/null 2>&1; then
        log_pass "Database integration (services responding)"
    else
        log_fail "Database integration (services not responding)"
    fi
}

# Message queue integration tests
test_message_queue_integration() {
    run_test "Message Queue Integration"
    
    # Test if message broker is accessible and functional
    if curl -sf "$BASE_URL/messages/health" >/dev/null 2>&1; then
        log_pass "Message queue integration (broker responding)"
    else
        log_fail "Message queue integration (broker not responding)"
    fi
}

# Security tests
test_unauthorized_access() {
    run_test "Unauthorized Access Protection"
    
    local response=$(curl -s -w "%{http_code}" -X GET "$BASE_URL/auth/user" \
        -o /tmp/unauthorized_response.json)
    
    if [ "$response" = "401" ] || [ "$response" = "403" ]; then
        log_pass "Unauthorized access protection (status: $response)"
    else
        log_fail "Unauthorized access protection - should return 401/403, got: $response"
    fi
}

test_cors_headers() {
    run_test "CORS Headers"
    
    local cors_header=$(curl -s -H "Origin: https://example.com" -I "$BASE_URL/auth/health" | grep -i "access-control-allow-origin" || echo "")
    
    if [ -n "$cors_header" ]; then
        log_pass "CORS headers present"
    else
        log_fail "CORS headers missing"
    fi
}

# Generate test report
generate_integration_report() {
    echo
    echo "=============================================="
    echo "E-COMMERCE INTEGRATION TEST REPORT"
    echo "=============================================="
    echo
    echo "Test Summary:"
    echo "  Total Tests: $TESTS_RUN"
    echo "  Passed: $TESTS_PASSED"
    echo "  Failed: $TESTS_FAILED"
    
    if [ $TESTS_RUN -gt 0 ]; then
        echo "  Success Rate: $(( TESTS_PASSED * 100 / TESTS_RUN ))%"
    fi
    
    echo
    
    if [ $TESTS_FAILED -eq 0 ]; then
        echo -e "${GREEN}🎉 ALL INTEGRATION TESTS PASSED!${NC}"
        echo "Your e-commerce platform is working correctly."
    else
        echo -e "${RED}⚠️  Some integration tests failed.${NC}"
        echo "Please review the failed tests and fix issues."
    fi
    
    echo
    echo "Detailed Results:"
    echo "=================="
    
    for test_name in "${!TEST_DETAILS[@]}"; do
        local result="${TEST_DETAILS[$test_name]}"
        if [ "$result" = "PASS" ]; then
            echo -e "✅ ${GREEN}$test_name${NC}"
        else
            echo -e "❌ ${RED}$test_name${NC}"
        fi
    done
    
    echo
    echo "Next Steps:"
    echo "==========="
    
    if [ $TESTS_FAILED -eq 0 ]; then
        echo "• Your platform is ready for production use"
        echo "• Consider running performance tests under load"
        echo "• Set up monitoring and alerting"
        echo "• Implement backup and disaster recovery"
    else
        echo "• Fix failing tests before proceeding to production"
        echo "• Check service logs: kubectl logs <pod-name> -n e-commerce"
        echo "• Verify service configurations"
        echo "• Re-run tests after fixes"
    fi
    
    echo
}

# Test suites
run_health_tests() {
    echo -e "${BLUE}Running Health Tests...${NC}"
    test_auth_service_health
    test_addresses_service_health
    test_messages_broker_health
    test_products_service_health
    test_baskets_service_health
    test_orders_service_health
}

run_auth_tests() {
    echo -e "${BLUE}Running Authentication Tests...${NC}"
    test_user_registration
    test_user_login
    test_protected_endpoint
    test_unauthorized_access
}

run_api_tests() {
    echo -e "${BLUE}Running API Tests...${NC}"
    test_address_creation
    test_address_listing
    test_product_listing
    test_basket_operations
}

run_performance_tests() {
    echo -e "${BLUE}Running Performance Tests...${NC}"
    test_api_response_time
    test_concurrent_requests
}

run_integration_tests() {
    echo -e "${BLUE}Running Integration Tests...${NC}"
    test_database_integration
    test_message_queue_integration
}

run_security_tests() {
    echo -e "${BLUE}Running Security Tests...${NC}"
    test_unauthorized_access
    test_cors_headers
}

run_all_tests() {
    setup_test_environment
    
    run_health_tests
    run_auth_tests
    run_api_tests
    run_performance_tests
    run_integration_tests
    run_security_tests
    
    cleanup_test_environment
    generate_integration_report
}

# Help information
show_help() {
    cat << EOF
E-commerce Platform Integration Tests
====================================

USAGE:
  $0 [test-suite]

TEST SUITES:
  all           Run all integration tests (default)
  health        Run health check tests only
  auth          Run authentication tests
  api           Run API functionality tests
  performance   Run performance tests
  integration   Run integration tests
  security      Run security tests
  help          Show this help message

EXAMPLES:
  $0            # Run all tests
  $0 all        # Run all tests
  $0 health     # Run only health checks
  $0 auth       # Run only authentication tests

REQUIREMENTS:
  • Platform must be deployed and accessible
  • kubectl access to the cluster
  • curl command available
  • jq command available (optional, for JSON parsing)

NOTES:
  • Tests will automatically port-forward to api-gateway
  • Some tests require user authentication
  • Tests create temporary test data
  • Tests are safe to run multiple times

EOF
}

# Main execution
main() {
    # Check prerequisites
    if ! command -v kubectl >/dev/null 2>&1; then
        echo -e "${RED}Error: kubectl is required but not installed${NC}"
        exit 1
    fi
    
    if ! command -v curl >/dev/null 2>&1; then
        echo -e "${RED}Error: curl is required but not installed${NC}"
        exit 1
    fi
    
    # Create temp directory for responses
    mkdir -p /tmp
    
    case "${1:-all}" in
        "all")
            run_all_tests
            ;;
        "health")
            setup_test_environment
            run_health_tests
            cleanup_test_environment
            generate_integration_report
            ;;
        "auth")
            setup_test_environment
            run_auth_tests
            cleanup_test_environment
            generate_integration_report
            ;;
        "api")
            setup_test_environment
            run_auth_tests  # Need auth for API tests
            run_api_tests
            cleanup_test_environment
            generate_integration_report
            ;;
        "performance")
            setup_test_environment
            run_performance_tests
            cleanup_test_environment
            generate_integration_report
            ;;
        "integration")
            setup_test_environment
            run_integration_tests
            cleanup_test_environment
            generate_integration_report
            ;;
        "security")
            setup_test_environment
            run_security_tests
            cleanup_test_environment
            generate_integration_report
            ;;
        "help"|"--help"|"-h")
            show_help
            ;;
        *)
            echo "Unknown test suite: $1"
            show_help
            exit 1
            ;;
    esac
}

# Cleanup on exit
trap cleanup_test_environment EXIT

# Execute main with all arguments
main "$@"