#!/bin/bash

# E-commerce Microservices Deployment Script
set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
NAMESPACE_BASE="e-commerce"
ENVIRONMENTS=("development" "staging" "production")
REQUIRED_TOOLS=("kubectl" "helm" "kustomize")

# Functions
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
    log_info "Checking prerequisites..."
    
    for tool in "${REQUIRED_TOOLS[@]}"; do
        if ! command -v "$tool" &> /dev/null; then
            log_error "$tool is not installed"
            exit 1
        fi
    done
    
    # Check kubectl connectivity
    if ! kubectl cluster-info &> /dev/null; then
        log_error "kubectl cannot connect to cluster"
        exit 1
    fi
    
    log_success "All prerequisites met"
}

deploy_infrastructure() {
    local environment=$1
    log_info "Deploying infrastructure for $environment..."
    
    # Deploy namespace and base resources
    kubectl apply -k "k8s/overlays/$environment"
    
    # Wait for namespace to be ready
    kubectl wait --for=condition=Ready namespace "${NAMESPACE_BASE}-${environment}" --timeout=60s
    
    log_success "Infrastructure deployed for $environment"
}

deploy_databases() {
    local environment=$1
    log_info "Deploying databases for $environment..."
    
    # Deploy MySQL Operator and cluster
    kubectl apply -f k8s/manifests/databases/mysql-operator.yaml
    
    # Wait for MySQL to be ready
    kubectl wait --for=condition=Ready pod -l mysql.oracle.com/cluster=mysql-cluster -n "${NAMESPACE_BASE}-${environment}" --timeout=300s
    
    log_success "Databases deployed for $environment"
}

deploy_messaging() {
    local environment=$1
    log_info "Deploying messaging infrastructure for $environment..."
    
    # Deploy RabbitMQ cluster
    kubectl apply -f k8s/manifests/messaging/rabbitmq-cluster.yaml
    
    # Wait for RabbitMQ to be ready
    kubectl wait --for=condition=Ready pod -l app.kubernetes.io/name=rabbitmq-cluster -n e-commerce-messaging --timeout=300s
    
    log_success "Messaging infrastructure deployed for $environment"
}

deploy_monitoring() {
    local environment=$1
    log_info "Deploying monitoring stack for $environment..."
    
    # Deploy Prometheus stack
    kubectl apply -f k8s/manifests/monitoring/prometheus-stack.yaml
    kubectl apply -f k8s/manifests/monitoring/grafana-dashboards.yaml
    
    # Wait for monitoring to be ready
    kubectl wait --for=condition=Ready pod -l app.kubernetes.io/name=prometheus -n monitoring --timeout=300s
    kubectl wait --for=condition=Ready pod -l app.kubernetes.io/name=grafana -n monitoring --timeout=300s
    
    log_success "Monitoring stack deployed for $environment"
}

deploy_security() {
    local environment=$1
    log_info "Deploying security components for $environment..."
    
    # Deploy External Secrets Operator
    kubectl apply -f k8s/manifests/security/external-secrets.yaml
    
    # Deploy Network Policies
    kubectl apply -f k8s/manifests/security/network-policies.yaml
    
    log_success "Security components deployed for $environment"
}

deploy_services() {
    local environment=$1
    local services=("$@")
    
    if [ ${#services[@]} -eq 1 ]; then
        log_info "Deploying all services for $environment..."
        services=("api-gateway" "auth-service" "products-service" "baskets-service" "orders-service" "addresses-service" "deliveries-service" "newsletters-service" "sav-service" "questions-service" "contacts-service" "websites-service")
    else
        log_info "Deploying specific services for $environment: ${services[*]:1}"
        services=("${services[@]:1}")
    fi
    
    for service in "${services[@]}"; do
        log_info "Deploying $service..."
        
        helm upgrade --install "${service}-${environment}" ./helm \
            --namespace "${NAMESPACE_BASE}-${environment}" \
            --create-namespace \
            --set environment="$environment" \
            --set services."$service".enabled=true \
            --set global.imageRegistry="${IMAGE_REGISTRY:-localhost}" \
            --set global.imageTag="${IMAGE_TAG:-latest}" \
            --wait --timeout=300s
        
        log_success "$service deployed successfully"
    done
}

verify_deployment() {
    local environment=$1
    log_info "Verifying deployment for $environment..."
    
    local namespace="${NAMESPACE_BASE}-${environment}"
    
    # Check pod status
    log_info "Checking pod status..."
    kubectl get pods -n "$namespace" -o wide
    
    # Check service endpoints
    log_info "Checking service endpoints..."
    kubectl get svc -n "$namespace"
    
    # Check ingress routes
    log_info "Checking ingress routes..."
    kubectl get ingressroute -n "$namespace" 2>/dev/null || log_warning "No ingress routes found"
    
    # Health check services
    log_info "Performing health checks..."
    local failed_services=0
    
    for service in api-gateway auth-service products-service; do
        if kubectl wait --for=condition=Ready pod -l app="$service" -n "$namespace" --timeout=30s &>/dev/null; then
            log_success "$service is ready"
        else
            log_error "$service is not ready"
            ((failed_services++))
        fi
    done
    
    if [ $failed_services -eq 0 ]; then
        log_success "All services are healthy in $environment"
    else
        log_error "$failed_services services failed health check in $environment"
        return 1
    fi
}

cleanup_environment() {
    local environment=$1
    log_warning "Cleaning up $environment environment..."
    
    read -p "Are you sure you want to delete the $environment environment? (yes/no): " -r
    if [[ $REPLY == "yes" ]]; then
        kubectl delete namespace "${NAMESPACE_BASE}-${environment}" --ignore-not-found=true
        log_success "$environment environment cleaned up"
    else
        log_info "Cleanup cancelled"
    fi
}

show_help() {
    cat << EOF
E-commerce Microservices Deployment Script

Usage: $0 [COMMAND] [ENVIRONMENT] [OPTIONS]

Commands:
    deploy          Deploy the full stack or specific services
    verify          Verify deployment health
    cleanup         Clean up an environment
    help            Show this help message

Environments:
    development     Development environment
    staging         Staging environment  
    production      Production environment
    all             All environments (for deploy command)

Options:
    --services      Comma-separated list of services to deploy
    --skip-infra    Skip infrastructure deployment
    --skip-db       Skip database deployment
    --skip-monitoring Skip monitoring deployment

Examples:
    $0 deploy development
    $0 deploy staging --services api-gateway,auth-service
    $0 verify production
    $0 cleanup development

Environment Variables:
    IMAGE_REGISTRY  Container registry (default: localhost)
    IMAGE_TAG       Image tag (default: latest)
    KUBECONFIG      Path to kubeconfig file
EOF
}

main() {
    local command=${1:-help}
    local environment=${2:-}
    
    case $command in
        deploy)
            if [[ -z $environment ]]; then
                log_error "Environment is required for deploy command"
                show_help
                exit 1
            fi
            
            check_prerequisites
            
            if [[ $environment == "all" ]]; then
                for env in "${ENVIRONMENTS[@]}"; do
                    log_info "Deploying to $env environment..."
                    deploy_infrastructure "$env"
                    
                    if [[ ! " $* " =~ " --skip-infra " ]]; then
                        deploy_databases "$env"
                        deploy_messaging "$env"
                        deploy_security "$env"
                    fi
                    
                    if [[ ! " $* " =~ " --skip-monitoring " ]]; then
                        deploy_monitoring "$env"
                    fi
                    
                    deploy_services "$env" "$@"
                    verify_deployment "$env"
                done
            else
                deploy_infrastructure "$environment"
                
                if [[ ! " $* " =~ " --skip-infra " ]]; then
                    deploy_databases "$environment"
                    deploy_messaging "$environment"
                    deploy_security "$environment"
                fi
                
                if [[ ! " $* " =~ " --skip-monitoring " ]]; then
                    deploy_monitoring "$environment"
                fi
                
                deploy_services "$environment" "$@"
                verify_deployment "$environment"
            fi
            ;;
        verify)
            if [[ -z $environment ]]; then
                log_error "Environment is required for verify command"
                exit 1
            fi
            
            check_prerequisites
            verify_deployment "$environment"
            ;;
        cleanup)
            if [[ -z $environment ]]; then
                log_error "Environment is required for cleanup command"
                exit 1
            fi
            
            check_prerequisites
            cleanup_environment "$environment"
            ;;
        help|--help|-h)
            show_help
            ;;
        *)
            log_error "Unknown command: $command"
            show_help
            exit 1
            ;;
    esac
}

# Run main function with all arguments
main "$@"