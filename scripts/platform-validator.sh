#!/bin/bash

# E-commerce Platform Validation Suite
# Comprehensive testing and verification for the Kubernetes platform

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Configuration
NAMESPACE_ECOMMERCE="e-commerce"
NAMESPACE_MONITORING="monitoring"
NAMESPACE_MESSAGING="messaging"
NAMESPACE_ARGOCD="argocd"

# Services to validate
SERVICES=(
    "api-gateway"
    "auth-service"
    "messages-broker"
    "addresses-service"
    "products-service"
    "baskets-service"
    "orders-service"
    "deliveries-service"
)

# Test results tracking
declare -A TEST_RESULTS
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Utility functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_test() {
    echo -e "${PURPLE}[TEST]${NC} $1"
}

record_test_result() {
    local test_name=$1
    local result=$2
    local details=${3:-""}
    
    TEST_RESULTS["$test_name"]="$result:$details"
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    if [ "$result" = "PASS" ]; then
        PASSED_TESTS=$((PASSED_TESTS + 1))
        log_success "✅ $test_name"
        [ -n "$details" ] && echo "   Details: $details"
    else
        FAILED_TESTS=$((FAILED_TESTS + 1))
        log_error "❌ $test_name"
        [ -n "$details" ] && echo "   Details: $details"
    fi
}

# Infrastructure validation tests
test_cluster_connectivity() {
    log_test "Testing cluster connectivity..."
    
    if kubectl cluster-info >/dev/null 2>&1; then
        local cluster_info=$(kubectl cluster-info | head -1)
        record_test_result "cluster_connectivity" "PASS" "$cluster_info"
    else
        record_test_result "cluster_connectivity" "FAIL" "Cannot connect to Kubernetes cluster"
    fi
}

test_namespaces() {
    log_test "Testing namespace creation..."
    
    local required_namespaces=("$NAMESPACE_ECOMMERCE" "$NAMESPACE_MONITORING" "$NAMESPACE_MESSAGING" "$NAMESPACE_ARGOCD")
    local missing_namespaces=()
    
    for ns in "${required_namespaces[@]}"; do
        if ! kubectl get namespace "$ns" >/dev/null 2>&1; then
            missing_namespaces+=("$ns")
        fi
    done
    
    if [ ${#missing_namespaces[@]} -eq 0 ]; then
        record_test_result "namespaces" "PASS" "All required namespaces exist"
    else
        record_test_result "namespaces" "FAIL" "Missing namespaces: ${missing_namespaces[*]}"
    fi
}

test_secrets() {
    log_test "Testing secrets configuration..."
    
    local required_secrets=("app-secrets" "jwt-secret" "database-secrets" "rabbitmq-secrets")
    local missing_secrets=()
    
    for secret in "${required_secrets[@]}"; do
        if ! kubectl get secret "$secret" -n "$NAMESPACE_ECOMMERCE" >/dev/null 2>&1; then
            missing_secrets+=("$secret")
        fi
    done
    
    if [ ${#missing_secrets[@]} -eq 0 ]; then
        record_test_result "secrets" "PASS" "All required secrets exist"
    else
        record_test_result "secrets" "FAIL" "Missing secrets: ${missing_secrets[*]}"
    fi
}

test_configmaps() {
    log_test "Testing ConfigMaps..."
    
    if kubectl get configmap global-config -n "$NAMESPACE_ECOMMERCE" >/dev/null 2>&1; then
        record_test_result "configmaps" "PASS" "Global configuration found"
    else
        record_test_result "configmaps" "FAIL" "Global ConfigMap not found"
    fi
}

# Service validation tests
test_service_deployments() {
    log_test "Testing service deployments..."
    
    local failed_services=()
    local service_details=()
    
    for service in "${SERVICES[@]}"; do
        if kubectl get deployment "$service" -n "$NAMESPACE_ECOMMERCE" >/dev/null 2>&1; then
            local ready=$(kubectl get deployment "$service" -n "$NAMESPACE_ECOMMERCE" -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
            local desired=$(kubectl get deployment "$service" -n "$NAMESPACE_ECOMMERCE" -o jsonpath='{.spec.replicas}' 2>/dev/null || echo "0")
            
            if [ "$ready" = "$desired" ] && [ "$ready" != "0" ]; then
                service_details+=("$service: $ready/$desired ready")
            else
                failed_services+=("$service: $ready/$desired")
            fi
        else
            failed_services+=("$service: not found")
        fi
    done
    
    if [ ${#failed_services[@]} -eq 0 ]; then
        record_test_result "service_deployments" "PASS" "${#service_details[@]} services running"
    else
        record_test_result "service_deployments" "FAIL" "Failed: ${failed_services[*]}"
    fi
}

test_service_health_endpoints() {
    log_test "Testing service health endpoints..."
    
    # Port forward to api-gateway
    kubectl port-forward svc/api-gateway 8080:80 -n "$NAMESPACE_ECOMMERCE" >/dev/null 2>&1 &
    local pf_pid=$!
    
    sleep 5
    
    local health_results=()
    local health_services=("auth" "addresses")
    
    for svc in "${health_services[@]}"; do
        if curl -sf "http://localhost:8080/$svc/health" >/dev/null 2>&1; then
            health_results+=("$svc: healthy")
        else
            health_results+=("$svc: unhealthy")
        fi
    done
    
    # Clean up port forward
    kill $pf_pid 2>/dev/null || true
    
    local healthy_count=$(echo "${health_results[@]}" | grep -o "healthy" | wc -l || echo "0")
    
    if [ "$healthy_count" -eq ${#health_services[@]} ]; then
        record_test_result "service_health" "PASS" "All health endpoints responding"
    else
        record_test_result "service_health" "FAIL" "${health_results[*]}"
    fi
}

test_service_to_service_communication() {
    log_test "Testing inter-service communication..."
    
    # Test auth service to messages-broker communication
    local test_pod=$(kubectl get pods -n "$NAMESPACE_ECOMMERCE" -l app=auth-service -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [ -n "$test_pod" ]; then
        # Test internal DNS resolution
        if kubectl exec "$test_pod" -n "$NAMESPACE_ECOMMERCE" -- nslookup messages-broker.e-commerce.svc.cluster.local >/dev/null 2>&1; then
            record_test_result "service_communication" "PASS" "Internal DNS resolution working"
        else
            record_test_result "service_communication" "FAIL" "Internal DNS resolution failed"
        fi
    else
        record_test_result "service_communication" "FAIL" "No auth-service pods found for testing"
    fi
}

# Infrastructure component tests
test_mysql_cluster() {
    log_test "Testing MySQL cluster..."
    
    local mysql_pods=$(kubectl get pods -n "$NAMESPACE_ECOMMERCE" -l app=mysql --no-headers 2>/dev/null | wc -l || echo "0")
    local mysql_ready=$(kubectl get pods -n "$NAMESPACE_ECOMMERCE" -l app=mysql --field-selector=status.phase=Running --no-headers 2>/dev/null | wc -l || echo "0")
    
    if [ "$mysql_ready" -gt 0 ]; then
        record_test_result "mysql_cluster" "PASS" "$mysql_ready/$mysql_pods MySQL pods running"
    else
        record_test_result "mysql_cluster" "FAIL" "No MySQL pods running"
    fi
}

test_rabbitmq_cluster() {
    log_test "Testing RabbitMQ cluster..."
    
    local rabbitmq_pods=$(kubectl get pods -n "$NAMESPACE_MESSAGING" -l app.kubernetes.io/name=rabbitmq --no-headers 2>/dev/null | wc -l || echo "0")
    local rabbitmq_ready=$(kubectl get pods -n "$NAMESPACE_MESSAGING" -l app.kubernetes.io/name=rabbitmq --field-selector=status.phase=Running --no-headers 2>/dev/null | wc -l || echo "0")
    
    if [ "$rabbitmq_ready" -gt 0 ]; then
        record_test_result "rabbitmq_cluster" "PASS" "$rabbitmq_ready/$rabbitmq_pods RabbitMQ pods running"
    else
        record_test_result "rabbitmq_cluster" "FAIL" "No RabbitMQ pods running"
    fi
}

test_monitoring_stack() {
    log_test "Testing monitoring stack..."
    
    local monitoring_components=("prometheus" "grafana" "alertmanager")
    local monitoring_status=()
    
    for component in "${monitoring_components[@]}"; do
        local pods=$(kubectl get pods -n "$NAMESPACE_MONITORING" -l app.kubernetes.io/name="$component" --field-selector=status.phase=Running --no-headers 2>/dev/null | wc -l || echo "0")
        if [ "$pods" -gt 0 ]; then
            monitoring_status+=("$component: running")
        else
            monitoring_status+=("$component: not running")
        fi
    done
    
    local running_count=$(echo "${monitoring_status[@]}" | grep -o "running" | wc -l || echo "0")
    
    if [ "$running_count" -eq ${#monitoring_components[@]} ]; then
        record_test_result "monitoring_stack" "PASS" "All monitoring components running"
    else
        record_test_result "monitoring_stack" "FAIL" "${monitoring_status[*]}"
    fi
}

test_argocd() {
    log_test "Testing ArgoCD installation..."
    
    local argocd_pods=$(kubectl get pods -n "$NAMESPACE_ARGOCD" -l app.kubernetes.io/name=argocd-server --field-selector=status.phase=Running --no-headers 2>/dev/null | wc -l || echo "0")
    
    if [ "$argocd_pods" -gt 0 ]; then
        record_test_result "argocd" "PASS" "ArgoCD server running"
    else
        record_test_result "argocd" "FAIL" "ArgoCD server not running"
    fi
}

# Security validation tests
test_network_policies() {
    log_test "Testing network policies..."
    
    local network_policies=$(kubectl get networkpolicies -n "$NAMESPACE_ECOMMERCE" --no-headers 2>/dev/null | wc -l || echo "0")
    
    if [ "$network_policies" -gt 0 ]; then
        record_test_result "network_policies" "PASS" "$network_policies network policies configured"
    else
        record_test_result "network_policies" "FAIL" "No network policies found"
    fi
}

test_rbac() {
    log_test "Testing RBAC configuration..."
    
    local service_accounts=$(kubectl get serviceaccounts -n "$NAMESPACE_ECOMMERCE" --no-headers 2>/dev/null | wc -l || echo "0")
    local role_bindings=$(kubectl get rolebindings -n "$NAMESPACE_ECOMMERCE" --no-headers 2>/dev/null | wc -l || echo "0")
    
    if [ "$service_accounts" -gt 1 ] && [ "$role_bindings" -gt 0 ]; then
        record_test_result "rbac" "PASS" "$service_accounts service accounts, $role_bindings role bindings"
    else
        record_test_result "rbac" "FAIL" "Insufficient RBAC configuration"
    fi
}

# Performance tests
test_resource_limits() {
    log_test "Testing resource limits configuration..."
    
    local pods_with_limits=0
    local total_pods=0
    
    for service in "${SERVICES[@]}"; do
        if kubectl get deployment "$service" -n "$NAMESPACE_ECOMMERCE" >/dev/null 2>&1; then
            total_pods=$((total_pods + 1))
            local limits=$(kubectl get deployment "$service" -n "$NAMESPACE_ECOMMERCE" -o jsonpath='{.spec.template.spec.containers[0].resources.limits}' 2>/dev/null || echo "{}")
            if [ "$limits" != "{}" ] && [ "$limits" != "null" ]; then
                pods_with_limits=$((pods_with_limits + 1))
            fi
        fi
    done
    
    if [ "$pods_with_limits" -eq "$total_pods" ] && [ "$total_pods" -gt 0 ]; then
        record_test_result "resource_limits" "PASS" "All deployments have resource limits"
    else
        record_test_result "resource_limits" "FAIL" "$pods_with_limits/$total_pods deployments have resource limits"
    fi
}

test_horizontal_pod_autoscaler() {
    log_test "Testing Horizontal Pod Autoscaler..."
    
    local hpa_count=$(kubectl get hpa -n "$NAMESPACE_ECOMMERCE" --no-headers 2>/dev/null | wc -l || echo "0")
    
    if [ "$hpa_count" -gt 0 ]; then
        record_test_result "hpa" "PASS" "$hpa_count HPA configurations found"
    else
        record_test_result "hpa" "FAIL" "No HPA configurations found"
    fi
}

# Integration tests
test_database_connectivity() {
    log_test "Testing database connectivity..."
    
    local auth_pod=$(kubectl get pods -n "$NAMESPACE_ECOMMERCE" -l app=auth-service -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [ -n "$auth_pod" ]; then
        # Test database connection from auth service
        if kubectl exec "$auth_pod" -n "$NAMESPACE_ECOMMERCE" -- php artisan migrate:status >/dev/null 2>&1; then
            record_test_result "database_connectivity" "PASS" "Database connection successful"
        else
            record_test_result "database_connectivity" "FAIL" "Database connection failed"
        fi
    else
        record_test_result "database_connectivity" "FAIL" "No auth-service pods available for testing"
    fi
}

test_message_queue_connectivity() {
    log_test "Testing message queue connectivity..."
    
    local broker_pod=$(kubectl get pods -n "$NAMESPACE_ECOMMERCE" -l app=messages-broker -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [ -n "$broker_pod" ]; then
        # Test RabbitMQ connection
        if kubectl exec "$broker_pod" -n "$NAMESPACE_ECOMMERCE" -- php artisan queue:work --once --stop-when-empty >/dev/null 2>&1; then
            record_test_result "message_queue" "PASS" "RabbitMQ connection successful"
        else
            record_test_result "message_queue" "FAIL" "RabbitMQ connection failed"
        fi
    else
        record_test_result "message_queue" "FAIL" "No messages-broker pods available for testing"
    fi
}

# Generate test report
generate_report() {
    echo
    echo "=============================================="
    echo "E-COMMERCE PLATFORM VALIDATION REPORT"
    echo "=============================================="
    echo
    echo "Test Summary:"
    echo "  Total Tests: $TOTAL_TESTS"
    echo "  Passed: $PASSED_TESTS"
    echo "  Failed: $FAILED_TESTS"
    echo "  Success Rate: $(( PASSED_TESTS * 100 / TOTAL_TESTS ))%"
    echo
    
    if [ $FAILED_TESTS -eq 0 ]; then
        echo -e "${GREEN}🎉 ALL TESTS PASSED! Platform is ready for use.${NC}"
    else
        echo -e "${RED}⚠️  Some tests failed. Please review the details above.${NC}"
    fi
    
    echo
    echo "Detailed Results:"
    echo "=================="
    
    for test_name in "${!TEST_RESULTS[@]}"; do
        local result_data="${TEST_RESULTS[$test_name]}"
        local result="${result_data%%:*}"
        local details="${result_data#*:}"
        
        if [ "$result" = "PASS" ]; then
            echo -e "✅ ${GREEN}$test_name${NC}"
        else
            echo -e "❌ ${RED}$test_name${NC}"
        fi
        
        if [ -n "$details" ]; then
            echo "   $details"
        fi
    done
    
    echo
    echo "Next Steps:"
    echo "==========="
    
    if [ $FAILED_TESTS -eq 0 ]; then
        echo "• Your platform is fully operational"
        echo "• Access monitoring: ./platform-control.sh monitoring"
        echo "• Deploy services: ./platform-control.sh deploy-env development"
        echo "• Run health checks: ./platform-control.sh health-check"
    else
        echo "• Review failed tests and fix issues"
        echo "• Check pod logs: kubectl logs <pod-name> -n e-commerce"
        echo "• Verify configurations: kubectl describe <resource-type> <resource-name>"
        echo "• Re-run validation: $0"
    fi
    
    echo
}

# Main test execution
run_all_tests() {
    echo -e "${BLUE}Starting E-commerce Platform Validation...${NC}"
    echo
    
    # Infrastructure tests
    test_cluster_connectivity
    test_namespaces
    test_secrets
    test_configmaps
    
    # Service tests
    test_service_deployments
    test_service_health_endpoints
    test_service_to_service_communication
    
    # Infrastructure component tests
    test_mysql_cluster
    test_rabbitmq_cluster
    test_monitoring_stack
    test_argocd
    
    # Security tests
    test_network_policies
    test_rbac
    
    # Performance tests
    test_resource_limits
    test_horizontal_pod_autoscaler
    
    # Integration tests
    test_database_connectivity
    test_message_queue_connectivity
    
    # Generate final report
    generate_report
}

# Quick tests (subset of all tests)
run_quick_tests() {
    echo -e "${BLUE}Running Quick Platform Validation...${NC}"
    echo
    
    test_cluster_connectivity
    test_namespaces
    test_service_deployments
    test_mysql_cluster
    test_rabbitmq_cluster
    
    generate_report
}

# Help information
show_help() {
    cat << EOF
E-commerce Platform Validation Suite
===================================

USAGE:
  $0 [command]

COMMANDS:
  all       Run all validation tests (default)
  quick     Run quick validation tests
  help      Show this help message

EXAMPLES:
  $0                # Run all tests
  $0 all           # Run all tests
  $0 quick         # Run quick validation
  $0 help          # Show help

TEST CATEGORIES:
  • Infrastructure: Cluster, namespaces, secrets, configs
  • Services: Deployments, health endpoints, communication
  • Components: MySQL, RabbitMQ, monitoring, ArgoCD
  • Security: Network policies, RBAC
  • Performance: Resource limits, autoscaling
  • Integration: Database and message queue connectivity

EOF
}

# Main execution
main() {
    case "${1:-all}" in
        "all")
            run_all_tests
            ;;
        "quick")
            run_quick_tests
            ;;
        "help"|"--help"|"-h")
            show_help
            ;;
        *)
            echo "Unknown command: $1"
            show_help
            exit 1
            ;;
    esac
}

# Execute main with all arguments
main "$@"