# =============================================================================
# E-commerce Platform - Unified Makefile
# =============================================================================
# Makefile unifié pour Docker Compose et Kubernetes
# Supporte la migration progressive et les opérations multi-environnement
# =============================================================================

# Variables de configuration
COMPOSE_FILE = docker-compose.yml
SERVICES = api-gateway auth-service messages-broker addresses-service products-service baskets-service orders-service deliveries-service newsletters-service sav-service contacts-service websites-service questions-service
DB_SERVICES = auth-db messages-broker-db addresses-db products-db baskets-db orders-db deliveries-db newsletters-db sav-db contacts-db websites-db questions-db
NETWORK = e-commerce-back_microservices-network

# Kubernetes Configuration
PLATFORM_CONTROL = ./platform-control.sh
AUTOMATION_SCRIPT = ./scripts/complete-automation.sh
VALIDATOR_SCRIPT = ./scripts/platform-validator.sh
VERIFIER_SCRIPT = ./scripts/deployment-verifier.sh
INTEGRATION_TESTS = ./tests/integration/platform-integration-tests.sh
K8S_ENVIRONMENT ?= development
SERVICE_NAME ?= api-gateway

# Couleurs pour l'affichage
GREEN = \033[0;32m
YELLOW = \033[1;33m
BLUE = \033[0;34m
RED = \033[0;31m
PURPLE = \033[0;35m
NC = \033[0m # No Color

.DEFAULT_GOAL := help

# =============================================================================
# 🚀 COMMANDES PRINCIPALES - PLATEFORME UNIFIÉE
# =============================================================================

## 🎛️ Tableau de bord interactif de la plateforme
dashboard:
	@echo "$(GREEN)🎛️ Ouverture du tableau de bord de la plateforme...$(NC)"
	@$(PLATFORM_CONTROL)

## 🚀 Installation complète (Docker Compose + Kubernetes ready)
install-complete: banner
	@echo "$(GREEN)🚀 Installation complète de la plateforme e-commerce...$(NC)"
	@echo "$(YELLOW)Phase 1: Installation Docker Compose...$(NC)"
	@$(MAKE) docker-install
	@echo "$(YELLOW)Phase 2: Préparation Kubernetes...$(NC)"
	@$(MAKE) k8s-prepare
	@echo "$(GREEN)✅ Installation complète terminée!$(NC)"
	@echo "$(BLUE)💡 Utilisez 'make k8s-deploy' pour déployer sur Kubernetes$(NC)"

## 🔄 Migration progressive vers Kubernetes
migrate-to-k8s: banner
	@echo "$(PURPLE)🔄 Démarrage de la migration progressive vers Kubernetes...$(NC)"
	@$(AUTOMATION_SCRIPT) migrate-progressive

# =============================================================================
# 🐳 COMMANDES DOCKER COMPOSE (LEGACY)
# =============================================================================

## 🚀 Démarrer avec Docker Compose
docker-start: banner
	@echo "$(GREEN)🚀 Démarrage de tous les services Docker...$(NC)"
	@docker-compose up -d
	@$(MAKE) docker-status
	@echo "$(GREEN)✅ Services Docker démarrés!$(NC)"
	@$(MAKE) docker-endpoints

## 🏗️ Installation Docker Compose (première installation)
docker-install: banner
	@echo "$(GREEN)🏗️ Installation Docker Compose...$(NC)"
	@echo "$(YELLOW)1. Construction des images Docker...$(NC)"
	@docker-compose build
	@echo "$(YELLOW)2. Démarrage des services...$(NC)"
	@docker-compose up -d
	@echo "$(YELLOW)3. Attente de la disponibilité des bases de données...$(NC)"
	@sleep 15
	@echo "$(YELLOW)4. Exécution des migrations et seeds...$(NC)"
	@$(MAKE) migrate-all
	@$(MAKE) seed-all
	@echo "$(GREEN)✅ Installation Docker terminée!$(NC)"
	@$(MAKE) docker-status

## 📊 Statut des services Docker
docker-status: 
	@echo "$(BLUE)📊 Statut des services Docker:$(NC)"
	@docker-compose ps

## ⏹️ Arrêter Docker Compose
docker-stop:
	@echo "$(YELLOW)⏹️ Arrêt de tous les services Docker...$(NC)"
	@docker-compose stop

## 🛑 Arrêter et supprimer Docker Compose
docker-down:
	@echo "$(YELLOW)🛑 Arrêt et suppression des services Docker...$(NC)"
	@docker-compose down
	@echo "$(GREEN)✅ Services Docker arrêtés et supprimés$(NC)"

## 📦 Exporter les fichiers .env en ZIP
export-env:
	@echo "$(YELLOW)📦 Export des fichiers .env...$(NC)"
	@mkdir -p exports
	@zip -r exports/env-backup-$$(date +%Y%m%d-%H%M%S).zip \
		.env \
		services/api-gateway/.env \
		services/auth-service/.env \
		services/messages-broker/.env \
		services/addresses-service/.env \
		services/products-service/.env \
		services/baskets-service/.env \
		services/orders-service/.env \
		services/deliveries-service/.env \
		services/newsletters-service/.env \
		services/sav-service/.env \
		services/contacts-service/.env \
		services/questions-service/.env \
		services/websites-service/.env
	@echo "$(GREEN)✅ Export créé dans exports/$(NC)"
	@ls -lh exports/*.zip | tail -1

## 🗑️ Nettoyer Docker Compose
docker-clean:
	@echo "$(RED)🧹 Nettoyage Docker Compose...$(NC)"
	@docker-compose down -v --rmi all
	@docker system prune -f

## 🚨 Arrêt d'urgence Docker (force)
docker-kill:
	@echo "$(RED)🚨 Arrêt d'urgence de tous les services Docker...$(NC)"
	@docker-compose kill
	@docker-compose down --remove-orphans
	@echo "$(GREEN)✅ Arrêt d'urgence terminé$(NC)"

## 🌐 Points d'accès Docker
docker-endpoints:
	@echo "$(YELLOW)📋 Accès aux services Docker:$(NC)"
	@echo "  - API Gateway: http://localhost"
	@echo "  - RabbitMQ Management: http://localhost:15672 (admin/admin)"
	@echo "  - Services disponibles via: http://localhost/api/{service}/"

# =============================================================================
# ☸️ COMMANDES KUBERNETES
# =============================================================================

## 🏗️ Configuration complète de Kubernetes
k8s-setup: banner
	@echo "$(GREEN)🏗️ Configuration complète de l'infrastructure Kubernetes...$(NC)"
	@$(AUTOMATION_SCRIPT) setup-all

## 📦 Déployer sur Kubernetes
k8s-deploy: banner
	@echo "$(GREEN)📦 Déploiement sur Kubernetes (environnement: $(K8S_ENVIRONMENT))...$(NC)"
	@$(AUTOMATION_SCRIPT) deploy-env $(K8S_ENVIRONMENT)
	@echo ""
	@echo "$(GREEN)🌐 Activation du port-forward API Gateway...$(NC)"
	@echo "$(YELLOW)Environnement: $(K8S_ENVIRONMENT)$(NC)"
	@echo "$(GREEN)URL d'accès: http://localhost:8100$(NC)"
	@echo "$(YELLOW)Appuyez sur Ctrl+C pour arrêter$(NC)"
	@echo ""
	@kubectl port-forward -n e-commerce svc/api-gateway 8100:80

## 🔨 Construire toutes les images pour Kubernetes
k8s-build:
	@echo "$(YELLOW)🔨 Construction des images pour Kubernetes...$(NC)"
	@$(AUTOMATION_SCRIPT) build-all latest false

## 🏥 Vérifier la santé Kubernetes
k8s-health:
	@echo "$(BLUE)🏥 Vérification de la santé Kubernetes...$(NC)"
	@$(AUTOMATION_SCRIPT) health-check

## 📊 Statut de la plateforme Kubernetes
k8s-status:
	@echo "$(BLUE)📊 Statut de la plateforme Kubernetes...$(NC)"
	@$(AUTOMATION_SCRIPT) status

## 📈 Monitoring Kubernetes
k8s-monitoring:
	@echo "$(PURPLE)📈 Ouverture des tableaux de bord de monitoring...$(NC)"
	@$(AUTOMATION_SCRIPT) monitoring

## 🌐 Port-forward API Gateway (port 8100)
k8s-port-forward:
	@echo "$(BLUE)🌐 Activation du port-forward API Gateway...$(NC)"
	@echo "$(YELLOW)Environnement: $(K8S_ENVIRONMENT)$(NC)"
	@echo "$(GREEN)URL d'accès: http://localhost:8100$(NC)"
	@echo "$(YELLOW)Appuyez sur Ctrl+C pour arrêter$(NC)"
	@echo ""
	@kubectl port-forward -n e-commerce svc/api-gateway 8100:80

## 📝 Logs Kubernetes
k8s-logs:
	@echo "$(BLUE)📝 Logs du service $(SERVICE_NAME)...$(NC)"
	@$(AUTOMATION_SCRIPT) logs $(SERVICE_NAME) 100

## 🌐 Points d'accès Kubernetes
k8s-endpoints:
	@echo "$(YELLOW)🌐 Points d'accès Kubernetes:$(NC)"
	@$(AUTOMATION_SCRIPT) endpoints

## ⏹️ Arrêter l'environnement Kubernetes
k8s-stop:
	@echo "$(YELLOW)⏹️ Arrêt de l'environnement $(K8S_ENVIRONMENT)...$(NC)"
	@if [ "$(K8S_ENVIRONMENT)" = "monitoring" ]; then \
		echo "$(BLUE)Arrêt des services de monitoring...$(NC)"; \
		kubectl get deployments -n monitoring 2>/dev/null | tail -n +2 | awk '{print $$1}' | xargs -I {} kubectl scale deployment {} --replicas=0 -n monitoring 2>/dev/null || true; \
	else \
		echo "$(BLUE)Mise à l'échelle des déploiements microservices à 0 répliques...$(NC)"; \
		kubectl get deployments -n e-commerce 2>/dev/null | tail -n +2 | awk '{print $$1}' | xargs -I {} kubectl scale deployment {} --replicas=0 -n e-commerce 2>/dev/null || true; \
		echo "$(BLUE)Arrêt des services dans l'environnement de monitoring...$(NC)"; \
		kubectl get deployments -n e-commerce-monitoring 2>/dev/null | tail -n +2 | awk '{print $$1}' | xargs -I {} kubectl scale deployment {} --replicas=0 -n e-commerce-monitoring 2>/dev/null || true; \
	fi
	@echo "$(GREEN)✅ Environnement $(K8S_ENVIRONMENT) arrêté (déploiements mis à l'échelle 0)$(NC)"

## 🛑 Supprimer l'environnement Kubernetes
k8s-down:
	@echo "$(YELLOW)🛑 Suppression de l'environnement $(K8S_ENVIRONMENT)...$(NC)"
	@kubectl delete namespace e-commerce 2>/dev/null || true
	@kubectl delete namespace e-commerce-monitoring 2>/dev/null || true
	@kubectl delete namespace e-commerce-messaging 2>/dev/null || true
	@echo "$(GREEN)✅ Environnement $(K8S_ENVIRONMENT) supprimé$(NC)"

## 🧹 Nettoyer l'environnement Kubernetes
k8s-clean:
	@echo "$(RED)🧹 Nettoyage de l'environnement $(K8S_ENVIRONMENT)...$(NC)"
	@$(AUTOMATION_SCRIPT) cleanup-env $(K8S_ENVIRONMENT)

## 🚨 Arrêt d'urgence Kubernetes (tout)
k8s-kill:
	@echo "$(RED)🚨 Arrêt d'urgence de tous les environnements Kubernetes...$(NC)"
	@kubectl delete namespace e-commerce 2>/dev/null || true
	@kubectl delete namespace e-commerce-monitoring 2>/dev/null || true
	@kubectl delete namespace e-commerce-messaging 2>/dev/null || true
	@kubectl delete namespace argocd 2>/dev/null || true
	@echo "$(GREEN)✅ Arrêt d'urgence Kubernetes terminé$(NC)"

## ☸️ Préparer la migration Kubernetes
k8s-prepare:
	@echo "$(YELLOW)☸️ Préparation pour Kubernetes...$(NC)"
	@chmod +x $(PLATFORM_CONTROL)
	@chmod +x $(AUTOMATION_SCRIPT)
	@chmod +x $(VALIDATOR_SCRIPT)
	@chmod +x $(VERIFIER_SCRIPT)
	@chmod +x $(INTEGRATION_TESTS)
	@echo "$(GREEN)✅ Scripts Kubernetes préparés$(NC)"

# =============================================================================
# 🛑 COMMANDES D'ARRÊT GLOBAL
# =============================================================================

## 🛑 Arrêter tout (Docker + Kubernetes)
stop-all:
	@echo "$(YELLOW)🛑 Arrêt de tous les services (Docker + Kubernetes)...$(NC)"
	@$(MAKE) docker-stop
	@$(MAKE) k8s-stop
	@echo "$(GREEN)✅ Tous les services arrêtés$(NC)"

## 🗑️ Supprimer tout (Docker + Kubernetes)
down-all:
	@echo "$(YELLOW)🗑️ Suppression de tous les services (Docker + Kubernetes)...$(NC)"
	@$(MAKE) docker-down
	@$(MAKE) k8s-down
	@echo "$(GREEN)✅ Tous les services supprimés$(NC)"

## 🧹 Nettoyer tout (Docker + Kubernetes)
clean-all:
	@echo "$(RED)🧹 Nettoyage complet (Docker + Kubernetes)...$(NC)"
	@$(MAKE) docker-clean
	@$(MAKE) k8s-clean
	@echo "$(GREEN)✅ Nettoyage complet terminé$(NC)"

## 🚨 Arrêt d'urgence complet (TOUT)
kill-all:
	@echo "$(RED)🚨 ARRÊT D'URGENCE COMPLET - Tous les services...$(NC)"
	@echo "$(RED)⚠️  Ceci va arrêter et supprimer TOUS les services Docker et Kubernetes$(NC)"
	@echo "$(YELLOW)Appuyez sur Ctrl+C dans les 5 secondes pour annuler...$(NC)"
	@sleep 5
	@$(MAKE) docker-kill
	@$(MAKE) k8s-kill
	@echo "$(RED)🔥 ARRÊT D'URGENCE COMPLET TERMINÉ$(NC)"

# =============================================================================
# 🧪 TESTS ET VALIDATION
# =============================================================================

## 🔍 Validation complète de la plateforme
validate-platform:
	@echo "$(PURPLE)🔍 Validation complète de la plateforme...$(NC)"
	@$(VALIDATOR_SCRIPT) all

## 🔍 Validation rapide
validate-quick:
	@echo "$(BLUE)🔍 Validation rapide...$(NC)"
	@$(VALIDATOR_SCRIPT) quick

## ✅ Vérification du déploiement
verify-deployment:
	@echo "$(GREEN)✅ Vérification du déploiement...$(NC)"
	@$(VERIFIER_SCRIPT) full

## ✅ Vérification rapide du déploiement
verify-quick:
	@echo "$(BLUE)✅ Vérification rapide du déploiement...$(NC)"
	@$(VERIFIER_SCRIPT) quick

## 🧪 Tests d'intégration complets
test-integration:
	@echo "$(PURPLE)🧪 Tests d'intégration complets...$(NC)"
	@$(INTEGRATION_TESTS) all

## 🧪 Tests d'intégration santé
test-health:
	@echo "$(BLUE)🧪 Tests de santé...$(NC)"
	@$(INTEGRATION_TESTS) health

## 🧪 Tests d'authentification
test-auth:
	@echo "$(YELLOW)🧪 Tests d'authentification...$(NC)"
	@$(INTEGRATION_TESTS) auth

## 🧪 Tests de performance
test-performance:
	@echo "$(RED)🧪 Tests de performance...$(NC)"
	@$(INTEGRATION_TESTS) performance

## 🧪 Tests de sécurité
test-security:
	@echo "$(RED)🧪 Tests de sécurité...$(NC)"
	@$(INTEGRATION_TESTS) security

## 🎯 Suite de tests complète (validation + déploiement + intégration)
test-all: validate-platform verify-deployment test-integration
	@echo "$(GREEN)🎯 Suite de tests complète terminée!$(NC)"

# =============================================================================
# 🗄️ GESTION DES BASES DE DONNÉES (DOCKER)
# =============================================================================

## 🗄️ Exécuter toutes les migrations
migrate-all:
	@echo "$(YELLOW)🗄️ Exécution des migrations...$(NC)"
	@for service in auth-service addresses-service products-service baskets-service orders-service deliveries-service newsletters-service sav-service contacts-service questions-service; do \
		echo "Migration: $$service"; \
		docker-compose exec $$service php artisan migrate --force || true; \
	done
	@echo "$(GREEN)✅ Migrations terminées$(NC)"

## 🌱 Exécuter tous les seeders
seed-all:
	@echo "$(YELLOW)🌱 Exécution des seeders...$(NC)"
	@for service in auth-service addresses-service products-service baskets-service orders-service deliveries-service newsletters-service sav-service contacts-service questions-service; do \
		echo "Seeding: $$service"; \
		docker-compose exec $$service php artisan db:seed --force || true; \
	done
	@echo "$(GREEN)✅ Seeders terminés$(NC)"

## 🔄 Réinitialiser toutes les bases de données
fresh-all:
	@echo "$(RED)🔄 Réinitialisation complète des bases de données...$(NC)"
	@for service in auth-service addresses-service products-service baskets-service orders-service deliveries-service newsletters-service sav-service contacts-service questions-service; do \
		echo "Fresh migration: $$service"; \
		docker-compose exec $$service php artisan migrate:fresh --seed --force || true; \
	done
	@echo "$(GREEN)✅ Bases de données réinitialisées$(NC)"

# =============================================================================
# 🧪 TESTS DOCKER (LEGACY)
# =============================================================================

## 🧪 Tests Laravel Docker
test-docker:
	@echo "$(YELLOW)🧪 Exécution des tests Laravel...$(NC)"
	@for service in auth-service addresses-service products-service baskets-service orders-service deliveries-service newsletters-service sav-service contacts-service questions-service; do \
		echo "Testing: $$service"; \
		docker-compose exec $$service php artisan test || true; \
	done
	@echo "$(GREEN)✅ Tests Docker terminés$(NC)"

## 🧪 Test d'un service spécifique
test-service:
	@echo "$(YELLOW)🧪 Test du service $(SERVICE_NAME)...$(NC)"
	@docker-compose exec $(SERVICE_NAME) php artisan test

# =============================================================================
# 🛠️ UTILITAIRES DE DÉVELOPPEMENT
# =============================================================================

## 🐚 Shell d'un service Docker
shell:
	@echo "$(BLUE)🐚 Accès au shell du service $(SERVICE_NAME)...$(NC)"
	@docker-compose exec $(SERVICE_NAME) bash

## 🔧 Installation des dépendances Composer
composer-install:
	@echo "$(YELLOW)🔧 Installation Composer pour $(SERVICE_NAME)...$(NC)"
	@docker-compose exec $(SERVICE_NAME) composer install

## 🔄 Reset complet Composer (composer.lock + vendor/)
reset-composer:
	@echo "$(RED)🔄 Reset complet des dépendances Composer...$(NC)"
	@./scripts/reset-composer.sh

## 📋 Vérifier la santé des services Docker
health-docker:
	@echo "$(BLUE)🏥 Vérification de la santé des services Docker:$(NC)"
	@for service in auth addresses products baskets orders deliveries newsletters sav questions; do \
		echo "$(YELLOW)$$service Service:$(NC)"; \
		curl -s http://localhost/api/$$service/health | jq . || echo "❌ Non disponible"; \
	done

## 🧹 Nettoyer les caches Laravel
clear-cache:
	@echo "$(YELLOW)🧹 Nettoyage des caches Laravel...$(NC)"
	@for service in auth-service addresses-service products-service baskets-service orders-service deliveries-service newsletters-service sav-service contacts-service questions-service; do \
		echo "Clearing cache: $$service"; \
		docker-compose exec $$service php artisan cache:clear || true; \
	done
	@echo "$(GREEN)✅ Caches nettoyés$(NC)"

## 🔥 Mode développement avec surveillance
dev:
	@echo "$(GREEN)🔥 Démarrage en mode développement...$(NC)"
	@docker-compose up --watch

## 📈 Statistiques des ressources
stats:
	@echo "$(BLUE)📈 Utilisation des ressources:$(NC)"
	@docker stats --no-stream

# =============================================================================
# 📧 NEWSLETTERS SPÉCIFIQUES
# =============================================================================

## 📧 Traiter les campagnes programmées
newsletters-process:
	@echo "$(YELLOW)📧 Traitement des campagnes programmées...$(NC)"
	@docker-compose exec newsletters-service php artisan newsletters:process-scheduled
	@echo "$(GREEN)✅ Campagnes traitées$(NC)"

## 📊 Statistiques des newsletters
newsletters-stats:
	@echo "$(BLUE)📊 Statistiques des newsletters:$(NC)"
	@curl -s http://localhost/api/newsletters/stats | jq . || echo "❌ Service non disponible"

# =============================================================================
# 💾 SAUVEGARDE ET RESTAURATION
# =============================================================================

## 💾 Sauvegarder les bases de données Docker
backup-docker:
	@echo "$(YELLOW)💾 Sauvegarde des bases de données Docker...$(NC)"
	@mkdir -p ./backups
	@timestamp=$$(date +%Y%m%d_%H%M%S); \
	for db in auth-db addresses-db products-db baskets-db orders-db deliveries-db newsletters-db sav-db contacts-db questions-db; do \
		echo "Sauvegarde: $$db"; \
		docker-compose exec $$db mysqldump -u root -proot $${db%%-*}_service_db > ./backups/$${db}_$$timestamp.sql || true; \
	done
	@echo "$(GREEN)✅ Sauvegardes créées dans ./backups/$(NC)"

# =============================================================================
# 💾 MINIO - OBJECT STORAGE
# =============================================================================

## 🗄️ Démarrer MinIO
minio-start:
	@echo "$(GREEN)🗄️ Démarrage de MinIO...$(NC)"
	@docker-compose up -d minio
	@sleep 5
	@$(MAKE) minio-health

## 🏥 Vérifier la santé de MinIO
minio-health:
	@echo "$(BLUE)🏥 Vérification de la santé MinIO...$(NC)"
	@./scripts/minio-health-check.sh || echo "$(YELLOW)MinIO en cours de démarrage...$(NC)"

## 📦 Créer les buckets MinIO
minio-setup:
	@echo "$(YELLOW)📦 Configuration des buckets MinIO...$(NC)"
	@./scripts/minio-setup-buckets.sh
	@echo "$(GREEN)✅ Buckets MinIO créés$(NC)"

## 🔧 Installer AWS SDK dans les services
minio-install-sdk:
	@echo "$(YELLOW)🔧 Installation AWS SDK...$(NC)"
	@./scripts/install-aws-sdk.sh
	@echo "$(GREEN)✅ AWS SDK installé$(NC)"

## 🧪 Tester l'intégration MinIO
minio-test:
	@echo "$(BLUE)🧪 Tests d'intégration MinIO...$(NC)"
	@./scripts/test-minio-integration.sh

## ✅ Valider Phase 1 MinIO
minio-validate:
	@echo "$(PURPLE)✅ Validation Phase 1 MinIO...$(NC)"
	@./scripts/validate-phase1-minio.sh

## 🌐 Ouvrir la console MinIO
minio-console:
	@echo "$(BLUE)🌐 Ouverture console MinIO...$(NC)"
	@echo "URL: http://localhost:9001"
	@echo "User: admin"
	@echo "Pass: adminpass123"
	@open http://localhost:9001 2>/dev/null || xdg-open http://localhost:9001 2>/dev/null || echo "Ouvrez manuellement: http://localhost:9001"

## 📊 Statistiques MinIO
minio-stats:
	@echo "$(YELLOW)📊 Statistiques MinIO:$(NC)"
	@docker exec minio-storage mc admin info local 2>/dev/null || echo "$(RED)MinIO non disponible$(NC)"

## 🗑️ Nettoyer les buckets MinIO
minio-clean:
	@echo "$(RED)🗑️ Nettoyage des buckets MinIO...$(NC)"
	@docker exec minio-storage sh -c "rm -rf /data/products/* /data/sav/* /data/newsletters/*" 2>/dev/null || true
	@echo "$(GREEN)✅ Buckets MinIO nettoyés$(NC)"

## 🛑 Arrêter MinIO
minio-stop:
	@echo "$(YELLOW)🛑 Arrêt de MinIO...$(NC)"
	@docker-compose stop minio
	@echo "$(GREEN)✅ MinIO arrêté$(NC)"

## 🔄 Workflow MinIO complet
minio-workflow:
	@echo "$(PURPLE)🔄 Workflow MinIO complet:$(NC)"
	@$(MAKE) minio-start
	@$(MAKE) minio-setup
	@$(MAKE) minio-validate
	@$(MAKE) minio-test
	@echo "$(GREEN)✅ Workflow MinIO terminé!$(NC)"
	@echo "$(BLUE)Console: http://localhost:9001 (admin/adminpass123)$(NC)"

# =============================================================================
# 🔧 WORKFLOWS DE DÉPLOIEMENT
# =============================================================================

## 🚀 Déploiement complet (build + deploy + verify + test)
deploy-complete:
	@echo "$(GREEN)🚀 Déploiement complet sur Kubernetes...$(NC)"
	@$(MAKE) k8s-build
	@$(MAKE) k8s-deploy
	@$(MAKE) verify-deployment
	@$(MAKE) test-integration
	@echo "$(GREEN)✅ Déploiement complet terminé!$(NC)"
	@echo ""
	@echo "$(YELLOW)🌐 Pour accéder à l'API Gateway, exécutez:$(NC)"
	@echo "$(BLUE)kubectl port-forward -n e-commerce svc/api-gateway 8100:80$(NC)"

## 🎯 Workflow de développement
dev-workflow:
	@echo "$(BLUE)🎯 Workflow de développement:$(NC)"
	@echo "1. $(YELLOW)Démarrage Docker pour développement...$(NC)"
	@$(MAKE) docker-start
	@echo "2. $(YELLOW)Tests de santé...$(NC)"
	@$(MAKE) health-docker
	@echo "3. $(YELLOW)Prêt pour le développement!$(NC)"

## 🎯 Workflow de production
prod-workflow:
	@echo "$(RED)🎯 Workflow de production:$(NC)"
	@echo "1. $(YELLOW)Tests de validation...$(NC)"
	@$(MAKE) validate-platform
	@echo "2. $(YELLOW)Déploiement...$(NC)"
	@$(MAKE) deploy-complete
	@echo "3. $(YELLOW)Monitoring...$(NC)"
	@$(MAKE) k8s-monitoring

## 🔄 Workflow de migration
migration-workflow:
	@echo "$(PURPLE)🔄 Workflow de migration:$(NC)"
	@echo "1. $(YELLOW)Validation Docker existant...$(NC)"
	@$(MAKE) health-docker
	@echo "2. $(YELLOW)Préparation Kubernetes...$(NC)"
	@$(MAKE) k8s-setup
	@echo "3. $(YELLOW)Migration progressive...$(NC)"
	@$(MAKE) migrate-to-k8s
	@echo "4. $(YELLOW)Validation finale...$(NC)"
	@$(MAKE) test-all

# =============================================================================
# ℹ️ AIDE ET INFORMATIONS
# =============================================================================

## 🎨 Afficher la bannière du projet
banner:
	@echo "$(BLUE)"
	@echo "╔══════════════════════════════════════════════════════════════════╗"
	@echo "║                    E-COMMERCE PLATFORM                          ║"
	@echo "║              Docker Compose ↔ Kubernetes                        ║"
	@echo "║                   Unified Management Tool                        ║"
	@echo "╚══════════════════════════════════════════════════════════════════╝"
	@echo "$(NC)"

## ❓ Afficher l'aide complète
help: banner
	@echo "$(GREEN)🎛️ PLATEFORME UNIFIÉE:$(NC)"
	@echo "  $(BLUE)dashboard$(NC)              Tableau de bord interactif"
	@echo "  $(BLUE)install-complete$(NC)       Installation complète (Docker + K8s ready)"
	@echo "  $(BLUE)migrate-to-k8s$(NC)         Migration progressive vers Kubernetes"
	@echo ""
	@echo "$(YELLOW)☸️ KUBERNETES:$(NC)"
	@echo "  $(BLUE)k8s-setup$(NC)              Configuration infrastructure Kubernetes"
	@echo "  $(BLUE)k8s-deploy$(NC)             Déployer sur Kubernetes"
	@echo "  $(BLUE)k8s-build$(NC)              Construire images pour Kubernetes"
	@echo "  $(BLUE)k8s-health$(NC)             Vérifier santé Kubernetes"
	@echo "  $(BLUE)k8s-status$(NC)             Statut plateforme Kubernetes"
	@echo "  $(BLUE)k8s-monitoring$(NC)         Ouvrir monitoring Kubernetes"
	@echo "  $(BLUE)k8s-port-forward$(NC)       Port-forward API Gateway (8100)"
	@echo "  $(BLUE)k8s-stop$(NC)               Arrêter environnement Kubernetes"
	@echo "  $(BLUE)k8s-down$(NC)               Supprimer environnement Kubernetes"
	@echo "  $(BLUE)k8s-clean$(NC)              Nettoyer environnement Kubernetes"
	@echo "  $(BLUE)k8s-kill$(NC)               Arrêt d'urgence tous environnements"
	@echo ""
	@echo "$(YELLOW)🐳 DOCKER COMPOSE:$(NC)"
	@echo "  $(BLUE)docker-start$(NC)           Démarrer services Docker"
	@echo "  $(BLUE)docker-install$(NC)         Installation Docker Compose"
	@echo "  $(BLUE)docker-status$(NC)          Statut services Docker"
	@echo "  $(BLUE)docker-stop$(NC)            Arrêter services Docker"
	@echo "  $(BLUE)docker-down$(NC)            Arrêter et supprimer services Docker"
	@echo "  $(BLUE)docker-clean$(NC)           Nettoyer Docker Compose"
	@echo "  $(BLUE)docker-kill$(NC)            Arrêt d'urgence Docker"
	@echo ""
	@echo "$(YELLOW)💾 MINIO - OBJECT STORAGE:$(NC)"
	@echo "  $(BLUE)minio-start$(NC)            Démarrer MinIO"
	@echo "  $(BLUE)minio-setup$(NC)            Créer buckets MinIO"
	@echo "  $(BLUE)minio-health$(NC)           Vérifier santé MinIO"
	@echo "  $(BLUE)minio-console$(NC)          Ouvrir console MinIO"
	@echo "  $(BLUE)minio-test$(NC)             Tester intégration MinIO"
	@echo "  $(BLUE)minio-validate$(NC)         Valider Phase 1 MinIO"
	@echo "  $(BLUE)minio-workflow$(NC)         Workflow MinIO complet"
	@echo ""
	@echo "$(YELLOW)🛑 ARRÊT GLOBAL:$(NC)"
	@echo "  $(BLUE)stop-all$(NC)               Arrêter tout (Docker + Kubernetes)"
	@echo "  $(BLUE)down-all$(NC)               Supprimer tout (Docker + Kubernetes)"
	@echo "  $(BLUE)clean-all$(NC)              Nettoyer tout (Docker + Kubernetes)"
	@echo "  $(BLUE)kill-all$(NC)               Arrêt d'urgence complet (TOUT)"
	@echo ""
	@echo "$(YELLOW)🧪 TESTS & VALIDATION:$(NC)"
	@echo "  $(BLUE)validate-platform$(NC)      Validation complète plateforme"
	@echo "  $(BLUE)verify-deployment$(NC)      Vérification déploiement"
	@echo "  $(BLUE)test-integration$(NC)       Tests d'intégration complets"
	@echo "  $(BLUE)test-all$(NC)               Suite de tests complète"
	@echo ""
	@echo "$(YELLOW)🎯 WORKFLOWS:$(NC)"
	@echo "  $(BLUE)deploy-complete$(NC)        Déploiement complet K8s"
	@echo "  $(BLUE)dev-workflow$(NC)           Workflow développement"
	@echo "  $(BLUE)prod-workflow$(NC)          Workflow production"
	@echo "  $(BLUE)migration-workflow$(NC)     Workflow migration complète"
	@echo ""
	@echo "$(GREEN)💡 EXEMPLES:$(NC)"
	@echo "  make dashboard                    # Interface interactive"
	@echo "  make install-complete            # Installation complète"
	@echo "  make K8S_ENVIRONMENT=staging k8s-deploy  # Déployer staging"
	@echo "  make K8S_ENVIRONMENT=staging k8s-port-forward  # Port-forward 8100"
	@echo "  make SERVICE_NAME=auth-service k8s-logs  # Logs service"
	@echo ""
	@echo "$(GREEN)📖 Documentation: README.md | PLATFORM_INTEGRATION_COMPLETE.md$(NC)"

## ℹ️ Informations système et environnement
info: banner
	@echo "$(BLUE)ℹ️ Informations système:$(NC)"
	@echo "Docker: $(shell docker --version 2>/dev/null || echo 'Non installé')"
	@echo "Docker Compose: $(shell docker-compose --version 2>/dev/null || echo 'Non installé')"
	@echo "Kubectl: $(shell kubectl version --client --short 2>/dev/null || echo 'Non installé')"
	@echo "Helm: $(shell helm version --short 2>/dev/null || echo 'Non installé')"
	@echo "Système: $(shell uname -s -r)"
	@echo ""
	@echo "$(YELLOW)📁 Structure du projet:$(NC)"
	@echo "├── services/                 # Services microservices"
	@echo "├── k8s/                     # Manifestes Kubernetes"
	@echo "├── helm/                    # Charts Helm"
	@echo "├── scripts/                 # Scripts d'automation"
	@echo "├── tests/integration/       # Tests d'intégration"
	@echo "├── docker/                  # Configuration Docker"
	@echo "├── shared/                  # Code partagé"
	@echo "├── platform-control.sh      # Contrôle unifié"
	@echo "├── docker-compose.yml       # Orchestration Docker"
	@echo "└── Makefile                 # Ce fichier"
	@echo ""
	@echo "$(YELLOW)🎯 Environnement actuel:$(NC)"
	@echo "K8S_ENVIRONMENT: $(K8S_ENVIRONMENT)"
	@echo "SERVICE_NAME: $(SERVICE_NAME)"

## 📋 Vérifier l'état des outils requis
check-tools:
	@echo "$(BLUE)🔍 Vérification des outils requis:$(NC)"
	@echo -n "Docker: "; docker --version 2>/dev/null && echo "$(GREEN)✅$(NC)" || echo "$(RED)❌$(NC)"
	@echo -n "Docker Compose: "; docker-compose --version 2>/dev/null && echo "$(GREEN)✅$(NC)" || echo "$(RED)❌$(NC)"
	@echo -n "Kubectl: "; kubectl version --client 2>/dev/null | head -1 && echo "$(GREEN)✅$(NC)" || echo "$(RED)❌$(NC)"
	@echo -n "Helm: "; helm version --short 2>/dev/null && echo "$(GREEN)✅$(NC)" || echo "$(RED)❌$(NC)"
	@echo -n "jq: "; jq --version 2>/dev/null && echo "$(GREEN)✅$(NC)" || echo "$(RED)❌$(NC)"
	@echo -n "curl: "; curl --version 2>/dev/null | head -1 && echo "$(GREEN)✅$(NC)" || echo "$(RED)❌$(NC)"

.PHONY: dashboard install-complete migrate-to-k8s docker-start docker-install docker-status docker-stop docker-down docker-clean docker-kill docker-endpoints k8s-setup k8s-deploy k8s-build k8s-health k8s-status k8s-monitoring k8s-logs k8s-endpoints k8s-stop k8s-down k8s-clean k8s-kill k8s-prepare stop-all down-all clean-all kill-all validate-platform validate-quick verify-deployment verify-quick test-integration test-health test-auth test-performance test-security test-all migrate-all seed-all fresh-all test-docker test-service shell composer-install health-docker clear-cache dev stats newsletters-process newsletters-stats backup-docker deploy-complete dev-workflow prod-workflow migration-workflow banner help info check-tools
# Kubernetes Database Management
.PHONY: k8s-migrate k8s-migrate-clean k8s-seed k8s-create-admin

k8s-migrate: ## Run database migrations on all services
	@echo "$(CYAN)Running database migrations on all services...$(NC)"
	@echo "$(YELLOW)Migrating auth-service...$(NC)"
	@kubectl exec -n e-commerce deployment/auth-service -- php artisan migrate --force
	@echo "$(YELLOW)Migrating products-service...$(NC)"
	@kubectl exec -n e-commerce deployment/products-service -- php artisan migrate --force
	@echo "$(YELLOW)Migrating addresses-service...$(NC)"
	@kubectl exec -n e-commerce deployment/addresses-service -- php artisan migrate --force 2>&1 | grep -E "(INFO|DONE|Nothing)" || true
	@echo "$(YELLOW)Migrating baskets-service...$(NC)"
	@kubectl exec -n e-commerce deployment/baskets-service -- php artisan migrate --force 2>&1 | grep -E "(INFO|DONE|Nothing)" || true
	@echo "$(YELLOW)Migrating orders-service...$(NC)"
	@kubectl exec -n e-commerce deployment/orders-service -- php artisan migrate --force 2>&1 | grep -E "(INFO|DONE|Nothing)" || true
	@echo "$(YELLOW)Migrating deliveries-service...$(NC)"
	@kubectl exec -n e-commerce deployment/deliveries-service -- php artisan migrate --force 2>&1 | grep -E "(INFO|DONE|Nothing)" || true
	@echo "$(YELLOW)Migrating newsletters-service...$(NC)"
	@kubectl exec -n e-commerce deployment/newsletters-service -- php artisan migrate --force 2>&1 | grep -E "(INFO|DONE|Nothing)" || true
	@echo "$(YELLOW)Migrating sav-service...$(NC)"
	@kubectl exec -n e-commerce deployment/sav-service -- php artisan migrate --force 2>&1 | grep -E "(INFO|DONE|Nothing)" || true
	@echo "$(YELLOW)Migrating contacts-service...$(NC)"
	@kubectl exec -n e-commerce deployment/contacts-service -- php artisan migrate --force 2>&1 | grep -E "(INFO|DONE|Nothing)" || true
	@echo "$(YELLOW)Migrating questions-service...$(NC)"
	@kubectl exec -n e-commerce deployment/questions-service -- php artisan migrate --force 2>&1 | grep -E "(INFO|DONE|Nothing)" || true
	@echo "$(GREEN)✓ All migrations completed$(NC)"

k8s-migrate-clean: ## Clean up migration jobs (deprecated - using direct exec now)
	@echo "$(YELLOW)Note: Migration jobs are no longer used. Using kubectl exec instead.$(NC)"
	@echo "$(GREEN)No cleanup needed$(NC)"

k8s-seed: ## Run database seeders (requires migrations first)
	@echo "$(CYAN)Running database seeders...$(NC)"
	@echo "$(YELLOW)Seeding auth-service...$(NC)"
	@kubectl exec -n e-commerce deployment/auth-service -- php artisan db:seed --force 2>&1 | grep -v "Nothing to seed" || true
	@echo "$(YELLOW)Seeding products-service...$(NC)"
	@kubectl exec -n e-commerce deployment/products-service -- php artisan db:seed --force 2>&1 | grep -v "Nothing to seed" || true
	@echo "$(GREEN)✓ Database seeding completed$(NC)"

k8s-create-admin: ## Create admin user in auth-service
	@echo "$(CYAN)Creating admin user...$(NC)"
	@kubectl exec -n e-commerce deployment/auth-service -- php artisan tinker --execute="\
		\$$user = new \App\Models\User(); \
		\$$user->firstname = 'Admin'; \
		\$$user->lastname = 'User'; \
		\$$user->email = 'admin@test.com'; \
		\$$user->password = bcrypt('password123'); \
		\$$user->save(); \
		echo 'User created: ' . \$$user->email;"
	@echo "$(GREEN)✓ Admin user created: admin@test.com / password123$(NC)"

k8s-test-login: ## Test login endpoint
	@echo "$(CYAN)Testing login endpoint...$(NC)"
	@curl -s -X POST http://localhost:8100/api/v1/login \
		-H "Content-Type: application/json" \
		-d '{"email":"admin@test.com","password":"password123"}' | jq '.'
	@echo "$(GREEN)✓ Login test completed$(NC)"

export-composer-locks: ## Export composer.lock files from all services
	@echo "$(CYAN)Exporting composer.lock files from all services...$(NC)"
	@docker-compose exec api-gateway cat composer.lock > services/api-gateway/composer.lock 2>/dev/null && echo "$(GREEN)✓ api-gateway/composer.lock$(NC)" || echo "$(RED)✗ api-gateway/composer.lock$(NC)"
	@docker-compose exec auth-service cat composer.lock > services/auth-service/composer.lock 2>/dev/null && echo "$(GREEN)✓ auth-service/composer.lock$(NC)" || echo "$(RED)✗ auth-service/composer.lock$(NC)"
	@docker-compose exec messages-broker cat composer.lock > services/messages-broker/composer.lock 2>/dev/null && echo "$(GREEN)✓ messages-broker/composer.lock$(NC)" || echo "$(RED)✗ messages-broker/composer.lock$(NC)"
	@docker-compose exec addresses-service cat composer.lock > services/addresses-service/composer.lock 2>/dev/null && echo "$(GREEN)✓ addresses-service/composer.lock$(NC)" || echo "$(RED)✗ addresses-service/composer.lock$(NC)"
	@docker-compose exec products-service cat composer.lock > services/products-service/composer.lock 2>/dev/null && echo "$(GREEN)✓ products-service/composer.lock$(NC)" || echo "$(RED)✗ products-service/composer.lock$(NC)"
	@docker-compose exec baskets-service cat composer.lock > services/baskets-service/composer.lock 2>/dev/null && echo "$(GREEN)✓ baskets-service/composer.lock$(NC)" || echo "$(RED)✗ baskets-service/composer.lock$(NC)"
	@docker-compose exec orders-service cat composer.lock > services/orders-service/composer.lock 2>/dev/null && echo "$(GREEN)✓ orders-service/composer.lock$(NC)" || echo "$(RED)✗ orders-service/composer.lock$(NC)"
	@docker-compose exec deliveries-service cat composer.lock > services/deliveries-service/composer.lock 2>/dev/null && echo "$(GREEN)✓ deliveries-service/composer.lock$(NC)" || echo "$(RED)✗ deliveries-service/composer.lock$(NC)"
	@docker-compose exec newsletters-service cat composer.lock > services/newsletters-service/composer.lock 2>/dev/null && echo "$(GREEN)✓ newsletters-service/composer.lock$(NC)" || echo "$(RED)✗ newsletters-service/composer.lock$(NC)"
	@docker-compose exec sav-service cat composer.lock > services/sav-service/composer.lock 2>/dev/null && echo "$(GREEN)✓ sav-service/composer.lock$(NC)" || echo "$(RED)✗ sav-service/composer.lock$(NC)"
	@docker-compose exec contacts-service cat composer.lock > services/contacts-service/composer.lock 2>/dev/null && echo "$(GREEN)✓ contacts-service/composer.lock$(NC)" || echo "$(RED)✗ contacts-service/composer.lock$(NC)"
	@docker-compose exec questions-service cat composer.lock > services/questions-service/composer.lock 2>/dev/null && echo "$(GREEN)✓ questions-service/composer.lock$(NC)" || echo "$(RED)✗ questions-service/composer.lock$(NC)"
	@docker-compose exec websites-service cat composer.lock > services/websites-service/composer.lock 2>/dev/null && echo "$(GREEN)✓ websites-service/composer.lock$(NC)" || echo "$(RED)✗ websites-service/composer.lock$(NC)"
	@docker-compose exec shared cat composer.lock > shared/composer.lock 2>/dev/null && echo "$(GREEN)✓ shared/composer.lock$(NC)" || echo "$(RED)✗ shared/composer.lock$(NC)"
	@echo "$(GREEN)✓ All composer.lock files exported$(NC)"
