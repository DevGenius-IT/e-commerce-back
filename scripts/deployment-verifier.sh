#!/bin/bash

# E-commerce Platform Deployment Verifier
# Comprehensive verification of deployment success and readiness

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m'

# Configuration
NAMESPACE_ECOMMERCE="e-commerce"
NAMESPACE_MONITORING="monitoring"
NAMESPACE_MESSAGING="messaging"
NAMESPACE_ARGOCD="argocd"

# Services configuration
CRITICAL_SERVICES=("api-gateway" "auth-service" "messages-broker")
ALL_SERVICES=("api-gateway" "auth-service" "messages-broker" "addresses-service" "products-service" "baskets-service" "orders-service" "deliveries-service")

# Timeouts (in seconds)
DEPLOYMENT_TIMEOUT=300
HEALTH_CHECK_TIMEOUT=60
CONNECTIVITY_TIMEOUT=30

# Verification results
VERIFICATION_PASSED=0
VERIFICATION_FAILED=0
declare -A VERIFICATION_RESULTS

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

log_step() {
    echo -e "${PURPLE}[STEP]${NC} $1"
}

record_verification() {
    local check_name=$1
    local result=$2
    local details=${3:-""}
    
    VERIFICATION_RESULTS["$check_name"]="$result:$details"
    
    if [ "$result" = "PASS" ]; then
        VERIFICATION_PASSED=$((VERIFICATION_PASSED + 1))
        log_success "✅ $check_name"
        [ -n "$details" ] && echo "   $details"
    else
        VERIFICATION_FAILED=$((VERIFICATION_FAILED + 1))
        log_error "❌ $check_name"
        [ -n "$details" ] && echo "   $details"
    fi
}

# Wait for deployment rollout with timeout
wait_for_deployment() {
    local deployment=$1
    local namespace=$2
    local timeout=${3:-$DEPLOYMENT_TIMEOUT}
    
    log_info "Waiting for deployment $deployment in namespace $namespace (timeout: ${timeout}s)..."
    
    if kubectl rollout status deployment/$deployment -n $namespace --timeout=${timeout}s >/dev/null 2>&1; then
        return 0
    else
        return 1
    fi
}

# Check if all pods are ready
check_pod_readiness() {
    local deployment=$1
    local namespace=$2
    
    local ready_replicas=$(kubectl get deployment $deployment -n $namespace -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
    local desired_replicas=$(kubectl get deployment $deployment -n $namespace -o jsonpath='{.spec.replicas}' 2>/dev/null || echo "0")
    
    if [ "$ready_replicas" = "$desired_replicas" ] && [ "$ready_replicas" != "0" ] && [ "$ready_replicas" != "" ]; then
        return 0
    else
        return 1
    fi
}

# Pre-deployment verification
verify_prerequisites() {
    log_step "Verifying deployment prerequisites..."
    
    # Check kubectl connectivity
    if kubectl cluster-info >/dev/null 2>&1; then
        record_verification "kubectl_connectivity" "PASS" "Connected to cluster"
    else
        record_verification "kubectl_connectivity" "FAIL" "Cannot connect to Kubernetes cluster"
        return 1
    fi
    
    # Check required namespaces
    local missing_namespaces=()
    for ns in "$NAMESPACE_ECOMMERCE" "$NAMESPACE_MONITORING" "$NAMESPACE_MESSAGING"; do
        if ! kubectl get namespace "$ns" >/dev/null 2>&1; then
            missing_namespaces+=("$ns")
        fi
    done
    
    if [ ${#missing_namespaces[@]} -eq 0 ]; then
        record_verification "namespaces" "PASS" "All required namespaces exist"
    else
        record_verification "namespaces" "FAIL" "Missing namespaces: ${missing_namespaces[*]}"
    fi
    
    # Check required secrets
    local missing_secrets=()
    local required_secrets=("app-secrets" "jwt-secret" "database-secrets" "rabbitmq-secrets")
    
    for secret in "${required_secrets[@]}"; do
        if ! kubectl get secret "$secret" -n "$NAMESPACE_ECOMMERCE" >/dev/null 2>&1; then
            missing_secrets+=("$secret")
        fi
    done
    
    if [ ${#missing_secrets[@]} -eq 0 ]; then
        record_verification "secrets" "PASS" "All required secrets configured"
    else
        record_verification "secrets" "FAIL" "Missing secrets: ${missing_secrets[*]}"
    fi
    
    # Check ConfigMaps
    if kubectl get configmap global-config -n "$NAMESPACE_ECOMMERCE" >/dev/null 2>&1; then
        record_verification "configmaps" "PASS" "Global configuration found"
    else
        record_verification "configmaps" "FAIL" "Global ConfigMap missing"
    fi
}

# Deployment verification
verify_service_deployments() {
    log_step "Verifying service deployments..."
    
    local deployment_failures=()
    
    for service in "${ALL_SERVICES[@]}"; do
        log_info "Checking deployment: $service"
        
        # Check if deployment exists
        if ! kubectl get deployment "$service" -n "$NAMESPACE_ECOMMERCE" >/dev/null 2>&1; then
            deployment_failures+=("$service: deployment not found")
            continue
        fi
        
        # Wait for deployment rollout
        if wait_for_deployment "$service" "$NAMESPACE_ECOMMERCE"; then
            # Double-check pod readiness
            if check_pod_readiness "$service" "$NAMESPACE_ECOMMERCE"; then
                log_success "Deployment $service is ready"
            else
                deployment_failures+=("$service: pods not ready")
            fi
        else
            deployment_failures+=("$service: rollout timeout")
        fi
    done
    
    if [ ${#deployment_failures[@]} -eq 0 ]; then
        record_verification "service_deployments" "PASS" "All services deployed successfully"
    else
        record_verification "service_deployments" "FAIL" "Failed deployments: ${deployment_failures[*]}"
    fi
}

# Infrastructure verification
verify_infrastructure_components() {
    log_step "Verifying infrastructure components..."
    
    # MySQL verification
    local mysql_pods=$(kubectl get pods -n "$NAMESPACE_ECOMMERCE" -l app=mysql --field-selector=status.phase=Running --no-headers 2>/dev/null | wc -l || echo "0")
    if [ "$mysql_pods" -gt 0 ]; then
        record_verification "mysql_cluster" "PASS" "$mysql_pods MySQL pods running"
    else
        record_verification "mysql_cluster" "FAIL" "No MySQL pods running"
    fi
    
    # RabbitMQ verification
    local rabbitmq_pods=$(kubectl get pods -n "$NAMESPACE_MESSAGING" -l app.kubernetes.io/name=rabbitmq --field-selector=status.phase=Running --no-headers 2>/dev/null | wc -l || echo "0")
    if [ "$rabbitmq_pods" -gt 0 ]; then
        record_verification "rabbitmq_cluster" "PASS" "$rabbitmq_pods RabbitMQ pods running"
    else
        record_verification "rabbitmq_cluster" "FAIL" "No RabbitMQ pods running"
    fi
    
    # Monitoring stack verification
    local prometheus_pods=$(kubectl get pods -n "$NAMESPACE_MONITORING" -l app.kubernetes.io/name=prometheus --field-selector=status.phase=Running --no-headers 2>/dev/null | wc -l || echo "0")
    local grafana_pods=$(kubectl get pods -n "$NAMESPACE_MONITORING" -l app.kubernetes.io/name=grafana --field-selector=status.phase=Running --no-headers 2>/dev/null | wc -l || echo "0")
    
    if [ "$prometheus_pods" -gt 0 ] && [ "$grafana_pods" -gt 0 ]; then
        record_verification "monitoring_stack" "PASS" "Prometheus and Grafana running"
    else
        record_verification "monitoring_stack" "FAIL" "Monitoring components not ready"
    fi
}

# Health check verification
verify_service_health() {
    log_step "Verifying service health endpoints..."
    
    # Port forward to api-gateway for health checks
    kubectl port-forward svc/api-gateway 8080:80 -n "$NAMESPACE_ECOMMERCE" >/dev/null 2>&1 &
    local pf_pid=$!
    
    # Wait for port forward
    sleep 5
    
    local health_failures=()
    local health_services=("auth" "addresses" "messages")
    
    for service in "${health_services[@]}"; do
        log_info "Checking health endpoint: $service"
        
        local health_url="http://localhost:8080/$service/health"
        local retry_count=0
        local max_retries=3
        local health_success=false
        
        while [ $retry_count -lt $max_retries ]; do
            if curl -sf "$health_url" >/dev/null 2>&1; then
                health_success=true
                break
            fi
            retry_count=$((retry_count + 1))
            sleep 2
        done
        
        if [ "$health_success" = true ]; then
            log_success "Health check passed: $service"
        else
            health_failures+=("$service")
        fi
    done
    
    # Clean up port forward
    kill $pf_pid 2>/dev/null || true
    
    if [ ${#health_failures[@]} -eq 0 ]; then
        record_verification "service_health" "PASS" "All health endpoints responding"
    else
        record_verification "service_health" "FAIL" "Health check failures: ${health_failures[*]}"
    fi
}

# Network connectivity verification
verify_network_connectivity() {
    log_step "Verifying network connectivity..."
    
    local connectivity_failures=()
    
    # Test internal service communication
    local test_pod=$(kubectl get pods -n "$NAMESPACE_ECOMMERCE" -l app=auth-service -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [ -n "$test_pod" ]; then
        # Test DNS resolution between services
        local test_services=("messages-broker.e-commerce.svc.cluster.local" "addresses-service.e-commerce.svc.cluster.local")
        
        for service_dns in "${test_services[@]}"; do
            if kubectl exec "$test_pod" -n "$NAMESPACE_ECOMMERCE" -- nslookup "$service_dns" >/dev/null 2>&1; then
                log_success "DNS resolution: $service_dns"
            else
                connectivity_failures+=("$service_dns")
            fi
        done
    else
        connectivity_failures+=("No test pod available")
    fi
    
    if [ ${#connectivity_failures[@]} -eq 0 ]; then
        record_verification "network_connectivity" "PASS" "Internal service communication working"
    else
        record_verification "network_connectivity" "FAIL" "Connectivity issues: ${connectivity_failures[*]}"
    fi
}

# Database connectivity verification
verify_database_connectivity() {
    log_step "Verifying database connectivity..."
    
    local auth_pod=$(kubectl get pods -n "$NAMESPACE_ECOMMERCE" -l app=auth-service -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [ -n "$auth_pod" ]; then
        # Test database connection
        if kubectl exec "$auth_pod" -n "$NAMESPACE_ECOMMERCE" -- php artisan migrate:status >/dev/null 2>&1; then
            record_verification "database_connectivity" "PASS" "Database connection successful"
        else
            record_verification "database_connectivity" "FAIL" "Cannot connect to database"
        fi
    else
        record_verification "database_connectivity" "FAIL" "No auth service pod available for testing"
    fi
}

# Security verification
verify_security_configuration() {
    log_step "Verifying security configuration..."
    
    # Check network policies
    local network_policies=$(kubectl get networkpolicies -n "$NAMESPACE_ECOMMERCE" --no-headers 2>/dev/null | wc -l || echo "0")
    if [ "$network_policies" -gt 0 ]; then
        log_success "Network policies configured: $network_policies"
    else
        log_warning "No network policies found"
    fi
    
    # Check RBAC
    local service_accounts=$(kubectl get serviceaccounts -n "$NAMESPACE_ECOMMERCE" --no-headers 2>/dev/null | wc -l || echo "0")
    local role_bindings=$(kubectl get rolebindings -n "$NAMESPACE_ECOMMERCE" --no-headers 2>/dev/null | wc -l || echo "0")
    
    if [ "$service_accounts" -gt 1 ] && [ "$role_bindings" -gt 0 ]; then
        record_verification "security_rbac" "PASS" "RBAC properly configured"
    else
        record_verification "security_rbac" "FAIL" "RBAC configuration incomplete"
    fi
    
    # Check pod security contexts
    local pods_with_security_context=0
    local total_pods=0
    
    for service in "${CRITICAL_SERVICES[@]}"; do
        if kubectl get deployment "$service" -n "$NAMESPACE_ECOMMERCE" >/dev/null 2>&1; then
            total_pods=$((total_pods + 1))
            local security_context=$(kubectl get deployment "$service" -n "$NAMESPACE_ECOMMERCE" -o jsonpath='{.spec.template.spec.securityContext}' 2>/dev/null || echo "{}")
            if [ "$security_context" != "{}" ] && [ "$security_context" != "null" ]; then
                pods_with_security_context=$((pods_with_security_context + 1))
            fi
        fi
    done
    
    if [ "$pods_with_security_context" -eq "$total_pods" ] && [ "$total_pods" -gt 0 ]; then
        record_verification "pod_security" "PASS" "All critical services have security contexts"
    else
        record_verification "pod_security" "FAIL" "$pods_with_security_context/$total_pods services have security contexts"
    fi
}

# Performance verification
verify_performance_configuration() {
    log_step "Verifying performance configuration..."
    
    # Check resource limits
    local services_with_limits=0
    local total_services=0
    
    for service in "${ALL_SERVICES[@]}"; do
        if kubectl get deployment "$service" -n "$NAMESPACE_ECOMMERCE" >/dev/null 2>&1; then
            total_services=$((total_services + 1))
            local limits=$(kubectl get deployment "$service" -n "$NAMESPACE_ECOMMERCE" -o jsonpath='{.spec.template.spec.containers[0].resources.limits}' 2>/dev/null || echo "{}")
            if [ "$limits" != "{}" ] && [ "$limits" != "null" ]; then
                services_with_limits=$((services_with_limits + 1))
            fi
        fi
    done
    
    if [ "$services_with_limits" -eq "$total_services" ] && [ "$total_services" -gt 0 ]; then
        record_verification "resource_limits" "PASS" "All services have resource limits"
    else
        record_verification "resource_limits" "FAIL" "$services_with_limits/$total_services services have resource limits"
    fi
    
    # Check HPA configuration
    local hpa_count=$(kubectl get hpa -n "$NAMESPACE_ECOMMERCE" --no-headers 2>/dev/null | wc -l || echo "0")
    if [ "$hpa_count" -gt 0 ]; then
        record_verification "autoscaling" "PASS" "$hpa_count HPA configurations found"
    else
        record_verification "autoscaling" "FAIL" "No HPA configurations found"
    fi
}

# ArgoCD verification
verify_argocd_integration() {
    log_step "Verifying ArgoCD integration..."
    
    # Check ArgoCD server
    local argocd_pods=$(kubectl get pods -n "$NAMESPACE_ARGOCD" -l app.kubernetes.io/name=argocd-server --field-selector=status.phase=Running --no-headers 2>/dev/null | wc -l || echo "0")
    
    if [ "$argocd_pods" -gt 0 ]; then
        record_verification "argocd_server" "PASS" "ArgoCD server running"
        
        # Check ArgoCD applications
        local app_count=$(kubectl get applications -n "$NAMESPACE_ARGOCD" --no-headers 2>/dev/null | wc -l || echo "0")
        if [ "$app_count" -gt 0 ]; then
            record_verification "argocd_applications" "PASS" "$app_count applications configured"
        else
            record_verification "argocd_applications" "FAIL" "No ArgoCD applications found"
        fi
    else
        record_verification "argocd_server" "FAIL" "ArgoCD server not running"
        record_verification "argocd_applications" "FAIL" "Cannot verify applications without server"
    fi
}

# Generate comprehensive verification report
generate_verification_report() {
    local total_checks=$((VERIFICATION_PASSED + VERIFICATION_FAILED))
    
    echo
    echo "=============================================="
    echo "E-COMMERCE DEPLOYMENT VERIFICATION REPORT"
    echo "=============================================="
    echo
    echo "Verification Summary:"
    echo "  Total Checks: $total_checks"
    echo "  Passed: $VERIFICATION_PASSED"
    echo "  Failed: $VERIFICATION_FAILED"
    
    if [ $total_checks -gt 0 ]; then
        echo "  Success Rate: $(( VERIFICATION_PASSED * 100 / total_checks ))%"
    fi
    
    echo
    
    if [ $VERIFICATION_FAILED -eq 0 ]; then
        echo -e "${GREEN}🎉 DEPLOYMENT VERIFICATION SUCCESSFUL!${NC}"
        echo "Your e-commerce platform is fully deployed and operational."
    else
        echo -e "${RED}⚠️  Deployment verification failed.${NC}"
        echo "Please address the issues before proceeding to production."
    fi
    
    echo
    echo "Detailed Results:"
    echo "=================="
    
    for check_name in "${!VERIFICATION_RESULTS[@]}"; do
        local result_data="${VERIFICATION_RESULTS[$check_name]}"
        local result="${result_data%%:*}"
        local details="${result_data#*:}"
        
        if [ "$result" = "PASS" ]; then
            echo -e "✅ ${GREEN}$check_name${NC}"
        else
            echo -e "❌ ${RED}$check_name${NC}"
        fi
        
        if [ -n "$details" ]; then
            echo "   $details"
        fi
    done
    
    echo
    echo "Post-Deployment Actions:"
    echo "======================="
    
    if [ $VERIFICATION_FAILED -eq 0 ]; then
        echo "• Run integration tests: ./tests/integration/platform-integration-tests.sh"
        echo "• Access monitoring: ./platform-control.sh monitoring"
        echo "• Set up production configurations"
        echo "• Configure backup and disaster recovery"
        echo "• Set up alerting and notifications"
    else
        echo "• Fix failed verification checks"
        echo "• Check pod logs: kubectl logs <pod-name> -n e-commerce"
        echo "• Verify configurations and secrets"
        echo "• Re-run verification: $0"
    fi
    
    echo
    
    # Return appropriate exit code
    if [ $VERIFICATION_FAILED -eq 0 ]; then
        return 0
    else
        return 1
    fi
}

# Main verification workflow
run_full_verification() {
    log_info "Starting comprehensive deployment verification..."
    echo
    
    # Run all verification steps
    verify_prerequisites
    verify_service_deployments
    verify_infrastructure_components
    verify_service_health
    verify_network_connectivity
    verify_database_connectivity
    verify_security_configuration
    verify_performance_configuration
    verify_argocd_integration
    
    # Generate and display report
    generate_verification_report
}

# Quick verification (critical components only)
run_quick_verification() {
    log_info "Starting quick deployment verification..."
    echo
    
    verify_prerequisites
    verify_service_deployments
    verify_infrastructure_components
    verify_service_health
    
    generate_verification_report
}

# Help information
show_help() {
    cat << EOF
E-commerce Platform Deployment Verifier
======================================

USAGE:
  $0 [command]

COMMANDS:
  full      Run full deployment verification (default)
  quick     Run quick verification (critical components only)
  help      Show this help message

EXAMPLES:
  $0              # Run full verification
  $0 full         # Run full verification
  $0 quick        # Run quick verification

VERIFICATION CATEGORIES:
  • Prerequisites: Cluster connectivity, namespaces, secrets, configs
  • Deployments: Service rollout status and pod readiness
  • Infrastructure: MySQL, RabbitMQ, monitoring stack
  • Health: Service health endpoints and API availability
  • Network: Internal service communication and DNS
  • Database: Database connectivity and migrations
  • Security: Network policies, RBAC, pod security contexts
  • Performance: Resource limits and autoscaling configuration
  • GitOps: ArgoCD server and application configurations

REQUIREMENTS:
  • kubectl access to the target cluster
  • curl command available
  • Services must be deployed before verification

EXIT CODES:
  0 - All verification checks passed
  1 - One or more verification checks failed

EOF
}

# Main execution
main() {
    case "${1:-full}" in
        "full")
            run_full_verification
            ;;
        "quick")
            run_quick_verification
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