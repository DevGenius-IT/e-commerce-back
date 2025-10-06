#!/bin/bash

# Complete E-commerce Platform Automation Suite
# Provides unified CLI for Kubernetes infrastructure management

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
CLUSTER_NAME="e-commerce-cluster"
NAMESPACE_ECOMMERCE="e-commerce"
NAMESPACE_MONITORING="monitoring"
NAMESPACE_MESSAGING="messaging"
REGISTRY="your-registry.com"
ENVIRONMENTS=("development" "staging" "production")

# Services list
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

check_prerequisites() {
    local missing_tools=()
    
    command -v kubectl >/dev/null 2>&1 || missing_tools+=("kubectl")
    command -v helm >/dev/null 2>&1 || missing_tools+=("helm")
    command -v docker >/dev/null 2>&1 || missing_tools+=("docker")
    command -v kustomize >/dev/null 2>&1 || missing_tools+=("kustomize")
    
    if [ ${#missing_tools[@]} -ne 0 ]; then
        log_error "Missing required tools: ${missing_tools[*]}"
        exit 1
    fi
    
    log_success "All prerequisites satisfied"
}

wait_for_rollout() {
    local deployment=$1
    local namespace=$2
    local timeout=${3:-300}
    
    log_info "Waiting for rollout of $deployment in namespace $namespace..."
    kubectl rollout status deployment/$deployment -n $namespace --timeout=${timeout}s
}

# Setup Functions
setup_cluster() {
    log_info "Setting up Kubernetes cluster infrastructure..."

    # Create namespaces
    kubectl apply -f k8s/base/namespace.yaml

    # Apply global configurations
    kubectl apply -f k8s/base/configmaps/

    # Setup secrets (auto-apply, assumes they are pre-configured)
    log_info "Applying secrets from k8s/base/secrets/"
    kubectl apply -f k8s/base/secrets/

    # Setup RBAC
    kubectl apply -f k8s/manifests/security/rbac.yaml

    log_success "Basic cluster setup completed"
}

setup_operators() {
    log_info "Installing Kubernetes operators..."

    # Install cert-manager
    kubectl apply -f https://github.com/jetstack/cert-manager/releases/download/v1.13.0/cert-manager.yaml
    kubectl wait --for=condition=available --timeout=300s deployment/cert-manager -n cert-manager

    # Install External Secrets Operator (check if already installed)
    helm repo add external-secrets https://charts.external-secrets.io
    helm repo update
    if helm list -n external-secrets-system | grep -q external-secrets; then
        log_info "External Secrets already installed, upgrading..."
        helm upgrade external-secrets external-secrets/external-secrets -n external-secrets-system
    else
        log_info "Installing External Secrets..."
        helm install external-secrets external-secrets/external-secrets -n external-secrets-system --create-namespace
    fi

    # Install MySQL Operator
    kubectl apply -f https://raw.githubusercontent.com/mysql/mysql-operator/trunk/deploy/deploy-crds.yaml
    kubectl apply -f https://raw.githubusercontent.com/mysql/mysql-operator/trunk/deploy/deploy-operator.yaml

    # Install RabbitMQ Cluster Operator
    kubectl apply -f "https://github.com/rabbitmq/cluster-operator/releases/latest/download/cluster-operator.yml"

    # Install Prometheus Stack (check if already installed)
    helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
    helm repo update
    if helm list -n monitoring | grep -q prometheus-stack; then
        log_info "Prometheus Stack already installed, upgrading..."
        helm upgrade prometheus-stack prometheus-community/kube-prometheus-stack -n monitoring
    else
        log_info "Installing Prometheus Stack..."
        helm install prometheus-stack prometheus-community/kube-prometheus-stack -n monitoring --create-namespace
    fi

    log_success "All operators installed successfully"
}

setup_infrastructure() {
    log_info "Deploying infrastructure components..."

    # Note: MySQL databases are deployed via Kustomize in k8s/base/services/
    # Note: RabbitMQ and MinIO are also deployed via Kustomize
    # This function is kept for future infrastructure components if needed

    log_info "Infrastructure components will be deployed via Helm/Kustomize"

    log_success "Infrastructure setup completed"
}

setup_argocd() {
    log_info "Setting up ArgoCD for GitOps..."

    # Install ArgoCD (ignore errors if already exists)
    kubectl create namespace argocd --dry-run=client -o yaml | kubectl apply -f - || true
    kubectl apply -n argocd -f k8s/manifests/argocd/argocd-install.yaml || true

    # Wait for ArgoCD server (optional)
    if kubectl get deployment argocd-server -n argocd >/dev/null 2>&1; then
        kubectl wait --for=condition=available --timeout=300s deployment/argocd-server -n argocd || true
    fi

    # Apply ArgoCD applications (ignore errors for invalid fields)
    kubectl apply -f k8s/manifests/argocd/applications.yaml || log_warning "ArgoCD applications have some errors, continuing anyway"

    log_info "ArgoCD setup completed (some components may need manual configuration)"
}

# Build Functions
build_service() {
    local service=$1
    local tag=${2:-latest}
    local push=${3:-false}

    log_info "Building $service with tag $tag..."

    # Build from project root with service-specific Dockerfile
    if [ -f "services/$service/Dockerfile" ]; then
        # Use project root as context, specify Dockerfile path
        docker build -t e-commerce-back-$service:$tag -f services/$service/Dockerfile .
    else
        docker build -t e-commerce-back-$service:$tag -f docker/Dockerfile.microservice --build-arg SERVICE_NAME=$service .
    fi

    if [ "$push" = "true" ]; then
        docker push e-commerce-back-$service:$tag
        log_success "Pushed $service:$tag to registry"
    fi

    log_success "Built e-commerce-back-$service:$tag"
}

build_all_services() {
    local tag=${1:-latest}
    local push=${2:-false}
    
    log_info "Building all microservices..."
    
    for service in "${SERVICES[@]}"; do
        build_service "$service" "$tag" "$push"
    done
    
    log_success "All services built successfully"
}

# Deployment Functions
deploy_service() {
    local service=$1
    local environment=${2:-development}
    local tag=${3:-latest}
    
    log_info "Deploying $service to $environment environment..."
    
    # Update image tag in values
    helm upgrade --install $service helm/ \
        --namespace $NAMESPACE_ECOMMERCE \
        --set services.$service.image.tag=$tag \
        --values helm/values-$environment.yaml
    
    wait_for_rollout $service $NAMESPACE_ECOMMERCE
    log_success "$service deployed successfully to $environment"
}

deploy_environment() {
    local environment=$1
    local tag=${2:-latest}
    
    log_info "Deploying all services to $environment environment..."
    
    # Apply environment-specific kustomization
    kubectl apply -k k8s/overlays/$environment
    
    # Deploy with Helm
    helm upgrade --install e-commerce-platform helm/ \
        --namespace $NAMESPACE_ECOMMERCE \
        --values helm/values-$environment.yaml \
        --set global.image.tag=$tag
    
    # Wait for all deployments
    for service in "${SERVICES[@]}"; do
        wait_for_rollout $service $NAMESPACE_ECOMMERCE
    done
    
    log_success "Environment $environment deployed successfully"
}

# Testing Functions
run_health_checks() {
    local environment=${1:-development}
    
    log_info "Running health checks for $environment environment..."
    
    local failed_services=()
    
    for service in "${SERVICES[@]}"; do
        log_info "Checking health of $service..."
        
        if kubectl get deployment $service -n $NAMESPACE_ECOMMERCE >/dev/null 2>&1; then
            local ready_replicas=$(kubectl get deployment $service -n $NAMESPACE_ECOMMERCE -o jsonpath='{.status.readyReplicas}')
            local desired_replicas=$(kubectl get deployment $service -n $NAMESPACE_ECOMMERCE -o jsonpath='{.spec.replicas}')
            
            if [ "$ready_replicas" = "$desired_replicas" ] && [ "$ready_replicas" != "" ]; then
                log_success "$service is healthy ($ready_replicas/$desired_replicas replicas ready)"
            else
                log_error "$service is unhealthy ($ready_replicas/$desired_replicas replicas ready)"
                failed_services+=("$service")
            fi
        else
            log_warning "$service not found in $environment"
        fi
    done
    
    if [ ${#failed_services[@]} -eq 0 ]; then
        log_success "All services are healthy"
        return 0
    else
        log_error "Failed services: ${failed_services[*]}"
        return 1
    fi
}

run_integration_tests() {
    log_info "Running integration tests..."
    
    # Port forward to services
    kubectl port-forward svc/api-gateway 8000:80 -n $NAMESPACE_ECOMMERCE &
    local pf_pid=$!
    
    sleep 5
    
    # Run basic API tests
    local test_results=()
    
    # Test auth service
    if curl -f http://localhost:8000/auth/health >/dev/null 2>&1; then
        test_results+=("auth:PASS")
    else
        test_results+=("auth:FAIL")
    fi
    
    # Test addresses service
    if curl -f http://localhost:8000/addresses/health >/dev/null 2>&1; then
        test_results+=("addresses:PASS")
    else
        test_results+=("addresses:FAIL")
    fi
    
    # Kill port forward
    kill $pf_pid 2>/dev/null || true
    
    # Report results
    for result in "${test_results[@]}"; do
        if [[ $result == *"PASS"* ]]; then
            log_success "Integration test $result"
        else
            log_error "Integration test $result"
        fi
    done
}

run_performance_tests() {
    log_info "Running performance tests..."
    
    # Use Apache Bench for basic load testing
    kubectl port-forward svc/api-gateway 8000:80 -n $NAMESPACE_ECOMMERCE &
    local pf_pid=$!
    
    sleep 5
    
    if command -v ab >/dev/null 2>&1; then
        log_info "Running load test on API Gateway..."
        ab -n 100 -c 10 http://localhost:8000/auth/health
    else
        log_warning "Apache Bench (ab) not found, skipping performance tests"
    fi
    
    kill $pf_pid 2>/dev/null || true
}

# Monitoring Functions
show_monitoring_dashboard() {
    log_info "Opening monitoring dashboards..."
    
    log_info "Starting port forwards for monitoring..."
    kubectl port-forward svc/prometheus-stack-grafana 3000:80 -n monitoring &
    local grafana_pid=$!
    
    kubectl port-forward svc/prometheus-stack-kube-prom-prometheus 9090:9090 -n monitoring &
    local prometheus_pid=$!
    
    log_success "Monitoring dashboards available at:"
    log_info "Grafana: http://localhost:3000 (admin/prom-operator)"
    log_info "Prometheus: http://localhost:9090"
    
    read -p "Press enter to stop port forwards..."
    kill $grafana_pid $prometheus_pid 2>/dev/null || true
}

show_logs() {
    local service=${1:-api-gateway}
    local lines=${2:-100}
    
    log_info "Showing logs for $service (last $lines lines)..."
    kubectl logs -f deployment/$service -n $NAMESPACE_ECOMMERCE --tail=$lines
}

show_metrics() {
    log_info "Showing resource metrics..."
    
    echo "=== Node Metrics ==="
    kubectl top nodes
    
    echo -e "\n=== Pod Metrics (e-commerce) ==="
    kubectl top pods -n $NAMESPACE_ECOMMERCE
    
    echo -e "\n=== Pod Metrics (monitoring) ==="
    kubectl top pods -n monitoring
}

# Migration Functions
migrate_service() {
    local service=$1
    local environment=${2:-development}
    
    log_info "Migrating $service from Docker Compose to Kubernetes..."
    
    # Build and push image
    build_service "$service" "migrate-$(date +%Y%m%d)" true
    
    # Deploy to Kubernetes
    deploy_service "$service" "$environment" "migrate-$(date +%Y%m%d)"
    
    # Verify deployment
    if run_health_checks; then
        log_success "$service migration completed successfully"
    else
        log_error "$service migration failed"
        return 1
    fi
}

migrate_progressive() {
    log_info "Starting progressive migration from Docker Compose to Kubernetes..."
    
    # Migration order (critical services first)
    local migration_order=(
        "auth-service"
        "messages-broker"
        "api-gateway"
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
    
    for service in "${migration_order[@]}"; do
        log_info "Migrating $service..."
        migrate_service "$service" "development"
        
        read -p "Continue with next service? (y/n): " -r
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            log_info "Migration paused. Resume with: $0 migrate-progressive"
            break
        fi
    done
    
    log_success "Progressive migration completed"
}

# Cleanup Functions
cleanup_environment() {
    local environment=$1
    
    log_warning "This will delete all resources in $environment environment"
    read -p "Are you sure? (y/N): " -r
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_info "Cleanup cancelled"
        return
    fi
    
    log_info "Cleaning up $environment environment..."
    
    # Delete Helm releases
    helm uninstall e-commerce-platform -n $NAMESPACE_ECOMMERCE || true
    
    # Delete environment-specific resources
    kubectl delete -k k8s/overlays/$environment || true
    
    log_success "Environment $environment cleaned up"
}

cleanup_cluster() {
    log_warning "This will delete the entire cluster and all data"
    read -p "Are you sure? Type 'DELETE' to confirm: " -r
    if [ "$REPLY" != "DELETE" ]; then
        log_info "Cleanup cancelled"
        return
    fi
    
    log_info "Cleaning up entire cluster..."
    
    # Delete applications
    kubectl delete -f k8s/manifests/argocd/applications.yaml || true
    
    # Delete infrastructure
    kubectl delete -f k8s/manifests/ || true
    
    # Delete namespaces
    kubectl delete -f k8s/base/namespace.yaml || true
    
    log_success "Cluster cleanup completed"
}

# Status Functions
show_status() {
    log_info "E-commerce Platform Status"
    echo "=========================="
    
    echo -e "\n🏗️  Infrastructure Status:"
    kubectl get nodes -o wide
    
    echo -e "\n📦 Namespace Status:"
    kubectl get namespaces | grep -E "(e-commerce|monitoring|messaging|argocd)"
    
    echo -e "\n🚀 Service Status:"
    kubectl get deployments -n $NAMESPACE_ECOMMERCE
    
    echo -e "\n💾 Database Status:"
    kubectl get pods -n e-commerce | grep mysql
    
    echo -e "\n📨 Messaging Status:"
    kubectl get pods -n e-commerce-messaging | grep rabbitmq
    
    echo -e "\n📊 Monitoring Status:"
    kubectl get pods -n monitoring | grep -E "(prometheus|grafana|alertmanager)"
    
    echo -e "\n🔄 ArgoCD Status:"
    kubectl get pods -n argocd | grep argocd
    
    echo -e "\n🗄️  MinIO Status:"
    kubectl get pods -n e-commerce-messaging | grep "^minio-" | grep -v setup
}

show_endpoints() {
    log_info "Service Endpoints"
    echo "================"
    
    echo "🔗 External Services:"
    kubectl get svc -n $NAMESPACE_ECOMMERCE -o wide | grep LoadBalancer
    kubectl get ingress -n $NAMESPACE_ECOMMERCE
    
    echo -e "\n📊 Monitoring Endpoints:"
    echo "Grafana: kubectl port-forward svc/prometheus-stack-grafana 3000:80 -n monitoring"
    echo "Prometheus: kubectl port-forward svc/prometheus-stack-kube-prom-prometheus 9090:9090 -n monitoring"
    echo "AlertManager: kubectl port-forward svc/prometheus-stack-kube-prom-alertmanager 9093:9093 -n monitoring"
    
    echo -e "\n🔄 ArgoCD Endpoint:"
    echo "ArgoCD: kubectl port-forward svc/argocd-server 8080:443 -n argocd"
}

show_help() {
    cat << EOF
E-commerce Platform Automation Suite
===================================

SETUP COMMANDS:
  setup-cluster        Setup basic cluster infrastructure
  setup-operators      Install Kubernetes operators
  setup-infrastructure Deploy databases, messaging, monitoring
  setup-argocd         Setup GitOps with ArgoCD
  setup-all           Run complete setup (all above commands)

BUILD COMMANDS:
  build <service> [tag] [push]  Build specific service
  build-all [tag] [push]        Build all services

DEPLOYMENT COMMANDS:
  deploy-service <service> [env] [tag]  Deploy specific service
  deploy-env <environment> [tag]        Deploy entire environment
  
TESTING COMMANDS:
  health-check [environment]    Run health checks
  integration-test             Run integration tests
  performance-test             Run performance tests
  test-all                     Run all tests

MONITORING COMMANDS:
  monitoring                   Open monitoring dashboards
  logs <service> [lines]       Show service logs
  metrics                      Show resource metrics

MIGRATION COMMANDS:
  migrate-service <service> [env]  Migrate single service
  migrate-progressive              Progressive migration

CLEANUP COMMANDS:
  cleanup-env <environment>    Clean up environment
  cleanup-cluster             Clean up entire cluster

STATUS COMMANDS:
  status                       Show platform status
  endpoints                    Show service endpoints

EXAMPLES:
  $0 setup-all                 # Complete platform setup
  $0 build-all latest true     # Build and push all services
  $0 deploy-env development    # Deploy to development
  $0 health-check              # Check all services
  $0 monitoring                # Open Grafana/Prometheus
  $0 migrate-progressive       # Start migration process

EOF
}

# Main execution
main() {
    case "${1:-help}" in
        "setup-cluster")
            check_prerequisites
            setup_cluster
            ;;
        "setup-operators")
            check_prerequisites
            setup_operators
            ;;
        "setup-infrastructure")
            check_prerequisites
            setup_infrastructure
            ;;
        "setup-argocd")
            check_prerequisites
            setup_argocd
            ;;
        "setup-all")
            check_prerequisites
            setup_cluster
            setup_operators
            setup_infrastructure
            setup_argocd
            log_success "Complete platform setup finished!"
            ;;
        "build")
            check_prerequisites
            build_service "${2:-api-gateway}" "${3:-latest}" "${4:-false}"
            ;;
        "build-all")
            check_prerequisites
            build_all_services "${2:-latest}" "${3:-false}"
            ;;
        "deploy-service")
            check_prerequisites
            deploy_service "${2:-api-gateway}" "${3:-development}" "${4:-latest}"
            ;;
        "deploy-env")
            check_prerequisites
            deploy_environment "${2:-development}" "${3:-latest}"
            ;;
        "health-check")
            check_prerequisites
            run_health_checks "${2:-development}"
            ;;
        "integration-test")
            check_prerequisites
            run_integration_tests
            ;;
        "performance-test")
            check_prerequisites
            run_performance_tests
            ;;
        "test-all")
            check_prerequisites
            run_health_checks "${2:-development}"
            run_integration_tests
            run_performance_tests
            ;;
        "monitoring")
            check_prerequisites
            show_monitoring_dashboard
            ;;
        "logs")
            check_prerequisites
            show_logs "${2:-api-gateway}" "${3:-100}"
            ;;
        "metrics")
            check_prerequisites
            show_metrics
            ;;
        "migrate-service")
            check_prerequisites
            migrate_service "${2:-api-gateway}" "${3:-development}"
            ;;
        "migrate-progressive")
            check_prerequisites
            migrate_progressive
            ;;
        "cleanup-env")
            check_prerequisites
            cleanup_environment "${2:-development}"
            ;;
        "cleanup-cluster")
            check_prerequisites
            cleanup_cluster
            ;;
        "status")
            check_prerequisites
            show_status
            ;;
        "endpoints")
            check_prerequisites
            show_endpoints
            ;;
        "help"|*)
            show_help
            ;;
    esac
}

# Execute main function with all arguments
main "$@"