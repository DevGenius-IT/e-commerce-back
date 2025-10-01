#!/bin/bash

# E-commerce Platform Control Center
# Single entry point for all platform operations

set -euo pipefail

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
AUTOMATION_SCRIPT="$SCRIPT_DIR/scripts/complete-automation.sh"

# Logo and welcome
show_banner() {
    echo -e "${BLUE}"
    cat << 'EOF'
╔══════════════════════════════════════════════════════════════╗
║                                                              ║
║   🛒 E-COMMERCE PLATFORM CONTROL CENTER 🛒                  ║
║                                                              ║
║   Kubernetes-Native Microservices Platform                  ║
║   Complete DevOps Automation Suite                          ║
║                                                              ║
╚══════════════════════════════════════════════════════════════╝
EOF
    echo -e "${NC}"
}

# Platform status dashboard
show_dashboard() {
    echo -e "${GREEN}=== PLATFORM DASHBOARD ===${NC}"
    echo
    
    # Check if cluster is accessible
    if kubectl cluster-info >/dev/null 2>&1; then
        echo -e "🔗 ${GREEN}Cluster Connection: ACTIVE${NC}"
        
        # Check namespaces
        local namespaces=(e-commerce monitoring messaging argocd)
        echo -e "\n📦 ${BLUE}Namespace Status:${NC}"
        for ns in "${namespaces[@]}"; do
            if kubectl get namespace "$ns" >/dev/null 2>&1; then
                echo -e "   ✅ $ns"
            else
                echo -e "   ❌ $ns"
            fi
        done
        
        # Check key services
        echo -e "\n🚀 ${BLUE}Core Services:${NC}"
        local services=(api-gateway auth-service messages-broker addresses-service)
        for svc in "${services[@]}"; do
            if kubectl get deployment "$svc" -n e-commerce >/dev/null 2>&1; then
                local ready=$(kubectl get deployment "$svc" -n e-commerce -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
                local desired=$(kubectl get deployment "$svc" -n e-commerce -o jsonpath='{.spec.replicas}' 2>/dev/null || echo "0")
                if [ "$ready" = "$desired" ] && [ "$ready" != "0" ]; then
                    echo -e "   ✅ $svc ($ready/$desired)"
                else
                    echo -e "   ⚠️  $svc ($ready/$desired)"
                fi
            else
                echo -e "   ❌ $svc (not deployed)"
            fi
        done
        
        # Check infrastructure
        echo -e "\n🏗️  ${BLUE}Infrastructure:${NC}"
        
        # MySQL
        if kubectl get pods -n e-commerce -l app=mysql --no-headers 2>/dev/null | grep -q Running; then
            echo -e "   ✅ MySQL Cluster"
        else
            echo -e "   ❌ MySQL Cluster"
        fi
        
        # RabbitMQ
        if kubectl get pods -n messaging -l app.kubernetes.io/name=rabbitmq --no-headers 2>/dev/null | grep -q Running; then
            echo -e "   ✅ RabbitMQ Cluster"
        else
            echo -e "   ❌ RabbitMQ Cluster"
        fi
        
        # Monitoring
        if kubectl get pods -n monitoring -l app.kubernetes.io/name=prometheus --no-headers 2>/dev/null | grep -q Running; then
            echo -e "   ✅ Prometheus Stack"
        else
            echo -e "   ❌ Prometheus Stack"
        fi
        
        # ArgoCD
        if kubectl get pods -n argocd -l app.kubernetes.io/name=argocd-server --no-headers 2>/dev/null | grep -q Running; then
            echo -e "   ✅ ArgoCD GitOps"
        else
            echo -e "   ❌ ArgoCD GitOps"
        fi
        
    else
        echo -e "❌ ${RED}Cluster Connection: FAILED${NC}"
        echo -e "   Please check your kubectl configuration"
    fi
    
    echo
}

# Quick actions menu
show_quick_actions() {
    echo -e "${YELLOW}=== QUICK ACTIONS ===${NC}"
    echo "1.  🚀 Setup Complete Platform"
    echo "2.  🔨 Build All Services"
    echo "3.  📦 Deploy Development Environment"
    echo "4.  🏥 Run Health Checks"
    echo "5.  📊 Open Monitoring Dashboard"
    echo "6.  📋 Show Platform Status"
    echo "7.  🔄 Progressive Migration"
    echo "8.  🧪 Run All Tests"
    echo "9.  📝 Show Service Logs"
    echo "10. 🌐 Show Service Endpoints"
    echo "11. 🧹 Cleanup Environment"
    echo "12. ❓ Show All Commands"
    echo "13. 🚪 Exit"
    echo
}

# Interactive mode
interactive_mode() {
    while true; do
        clear
        show_banner
        show_dashboard
        show_quick_actions
        
        read -p "Select action (1-13): " choice
        
        case $choice in
            1)
                echo -e "\n${BLUE}Setting up complete platform...${NC}"
                $AUTOMATION_SCRIPT setup-all
                read -p "Press enter to continue..."
                ;;
            2)
                echo -e "\n${BLUE}Building all services...${NC}"
                read -p "Tag (default: latest): " tag
                read -p "Push to registry? (y/N): " push
                tag=${tag:-latest}
                push_flag="false"
                [[ $push =~ ^[Yy]$ ]] && push_flag="true"
                $AUTOMATION_SCRIPT build-all "$tag" "$push_flag"
                read -p "Press enter to continue..."
                ;;
            3)
                echo -e "\n${BLUE}Deploying to development...${NC}"
                $AUTOMATION_SCRIPT deploy-env development
                read -p "Press enter to continue..."
                ;;
            4)
                echo -e "\n${BLUE}Running health checks...${NC}"
                $AUTOMATION_SCRIPT health-check
                read -p "Press enter to continue..."
                ;;
            5)
                echo -e "\n${BLUE}Opening monitoring dashboard...${NC}"
                $AUTOMATION_SCRIPT monitoring
                ;;
            6)
                echo -e "\n${BLUE}Platform status:${NC}"
                $AUTOMATION_SCRIPT status
                read -p "Press enter to continue..."
                ;;
            7)
                echo -e "\n${BLUE}Starting progressive migration...${NC}"
                $AUTOMATION_SCRIPT migrate-progressive
                read -p "Press enter to continue..."
                ;;
            8)
                echo -e "\n${BLUE}Running all tests...${NC}"
                $AUTOMATION_SCRIPT test-all
                read -p "Press enter to continue..."
                ;;
            9)
                echo -e "\n${BLUE}Service logs:${NC}"
                read -p "Service name (default: api-gateway): " service
                read -p "Number of lines (default: 100): " lines
                service=${service:-api-gateway}
                lines=${lines:-100}
                $AUTOMATION_SCRIPT logs "$service" "$lines"
                ;;
            10)
                echo -e "\n${BLUE}Service endpoints:${NC}"
                $AUTOMATION_SCRIPT endpoints
                read -p "Press enter to continue..."
                ;;
            11)
                echo -e "\n${BLUE}Cleanup environment:${NC}"
                read -p "Environment (development/staging/production): " env
                if [ -n "$env" ]; then
                    $AUTOMATION_SCRIPT cleanup-env "$env"
                fi
                read -p "Press enter to continue..."
                ;;
            12)
                echo -e "\n${BLUE}All available commands:${NC}"
                $AUTOMATION_SCRIPT help
                read -p "Press enter to continue..."
                ;;
            13)
                echo -e "\n${GREEN}Goodbye! 👋${NC}"
                exit 0
                ;;
            *)
                echo -e "\n${RED}Invalid selection. Please try again.${NC}"
                sleep 2
                ;;
        esac
    done
}

# Command-line mode
command_mode() {
    local command=$1
    shift
    
    case $command in
        "dashboard"|"status")
            show_banner
            show_dashboard
            ;;
        "interactive"|"menu")
            interactive_mode
            ;;
        *)
            # Pass through to automation script
            $AUTOMATION_SCRIPT "$command" "$@"
            ;;
    esac
}

# Help for this control script
show_control_help() {
    cat << EOF
E-commerce Platform Control Center
=================================

CONTROL CENTER COMMANDS:
  dashboard          Show platform dashboard
  interactive        Start interactive menu mode
  menu              Alias for interactive mode

DIRECT AUTOMATION (pass-through to automation script):
  All commands from complete-automation.sh are available directly

EXAMPLES:
  $0                    # Start interactive mode
  $0 dashboard         # Show platform status
  $0 setup-all         # Setup complete platform
  $0 deploy-env dev    # Deploy development environment
  $0 health-check      # Run health checks
  $0 monitoring        # Open monitoring dashboards

INTERACTIVE MODE:
  Run without arguments to start the interactive dashboard
  Navigate with numbered menu options
  Real-time platform status display

EOF
}

# Main execution
main() {
    # Check if automation script exists
    if [ ! -f "$AUTOMATION_SCRIPT" ]; then
        echo -e "${RED}Error: Automation script not found at $AUTOMATION_SCRIPT${NC}"
        exit 1
    fi
    
    # Make automation script executable
    chmod +x "$AUTOMATION_SCRIPT"
    
    if [ $# -eq 0 ]; then
        # No arguments - start interactive mode
        interactive_mode
    elif [ "$1" = "help" ] || [ "$1" = "--help" ] || [ "$1" = "-h" ]; then
        show_control_help
    else
        # Command mode
        command_mode "$@"
    fi
}

# Execute main with all arguments
main "$@"