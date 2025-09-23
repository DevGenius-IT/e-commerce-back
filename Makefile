# =============================================================================
# E-commerce Microservices - Makefile
# =============================================================================
# Ce Makefile facilite la gestion de l'ensemble du projet microservices
# =============================================================================

# Variables de configuration
COMPOSE_FILE = docker-compose.yml
SERVICES = api-gateway auth-service messages-broker addresses-service products-service
DB_SERVICES = auth-db messages-broker-db addresses-db products-db
NETWORK = e-commerce-back_microservices-network

# Couleurs pour l'affichage
GREEN = \033[0;32m
YELLOW = \033[1;33m
BLUE = \033[0;34m
RED = \033[0;31m
NC = \033[0m # No Color

.DEFAULT_GOAL := help

# =============================================================================
# Commandes principales
# =============================================================================

## 🚀 Démarrer l'ensemble du projet
start: banner
	@echo "$(GREEN)🚀 Démarrage de tous les services...$(NC)"
	@docker-compose up -d
	@$(MAKE) status
	@echo "$(GREEN)✅ Projet démarré avec succès!$(NC)"
	@echo "$(YELLOW)📋 Accès aux services:$(NC)"
	@echo "  - API Gateway: http://localhost"
	@echo "  - RabbitMQ Management: http://localhost:15672 (admin/admin)"
	@echo "  - Services disponibles via: http://localhost/api/{service}/"

## 🏗️ Construire et démarrer (première installation)
install: banner
	@echo "$(GREEN)🏗️ Installation complète du projet...$(NC)"
	@echo "$(YELLOW)1. Construction des images Docker...$(NC)"
	@docker-compose build
	@echo "$(YELLOW)2. Démarrage des services...$(NC)"
	@docker-compose up -d
	@echo "$(YELLOW)3. Attente de la disponibilité des bases de données...$(NC)"
	@sleep 15
	@echo "$(YELLOW)4. Exécution des migrations et seeds...$(NC)"
	@$(MAKE) migrate-all
	@$(MAKE) seed-all
	@echo "$(GREEN)✅ Installation terminée!$(NC)"
	@$(MAKE) status

## 🔄 Redémarrer tous les services
restart: banner
	@echo "$(GREEN)🔄 Redémarrage de tous les services...$(NC)"
	@docker-compose restart
	@$(MAKE) status

## ⏹️ Arrêter tous les services
stop: banner
	@echo "$(YELLOW)⏹️ Arrêt de tous les services...$(NC)"
	@docker-compose stop
	@echo "$(GREEN)✅ Tous les services sont arrêtés$(NC)"

## 🗑️ Arrêter et supprimer tous les conteneurs
down: banner
	@echo "$(RED)🗑️ Suppression de tous les conteneurs...$(NC)"
	@docker-compose down
	@echo "$(GREEN)✅ Conteneurs supprimés$(NC)"

## 🧹 Nettoyage complet (conteneurs + volumes + images)
clean: banner
	@echo "$(RED)🧹 Nettoyage complet du projet...$(NC)"
	@docker-compose down -v --rmi all
	@docker system prune -f
	@echo "$(GREEN)✅ Nettoyage terminé$(NC)"

## 📊 Afficher le statut de tous les services
status: banner
	@echo "$(BLUE)📊 Statut des services:$(NC)"
	@docker-compose ps

## 📝 Voir les logs de tous les services
logs:
	@docker-compose logs -f

## 📝 Voir les logs d'un service spécifique (make logs-service SERVICE=auth-service)
logs-service:
	@docker-compose logs -f $(SERVICE)

# =============================================================================
# Gestion des bases de données
# =============================================================================

## 🗄️ Exécuter toutes les migrations
migrate-all: banner
	@echo "$(YELLOW)🗄️ Exécution des migrations...$(NC)"
	@docker-compose exec auth-service php artisan migrate --force
	@docker-compose exec addresses-service php artisan migrate --force
	@docker-compose exec products-service php artisan migrate --force
	@echo "$(GREEN)✅ Migrations terminées$(NC)"

## 🌱 Exécuter tous les seeders
seed-all: banner
	@echo "$(YELLOW)🌱 Exécution des seeders...$(NC)"
	@docker-compose exec auth-service php artisan db:seed --force
	@docker-compose exec addresses-service php artisan db:seed --force
	@docker-compose exec products-service php artisan db:seed --force
	@echo "$(GREEN)✅ Seeders terminés$(NC)"

## 🔄 Réinitialiser toutes les bases de données (fresh + seed)
fresh-all: banner
	@echo "$(RED)🔄 Réinitialisation complète des bases de données...$(NC)"
	@docker-compose exec auth-service php artisan migrate:fresh --seed --force
	@docker-compose exec addresses-service php artisan migrate:fresh --seed --force
	@docker-compose exec products-service php artisan migrate:fresh --seed --force
	@echo "$(GREEN)✅ Bases de données réinitialisées$(NC)"

# =============================================================================
# Tests
# =============================================================================

## 🧪 Exécuter tous les tests
test-all: banner
	@echo "$(YELLOW)🧪 Exécution de tous les tests...$(NC)"
	@docker-compose exec auth-service php artisan test
	@docker-compose exec addresses-service php artisan test
	@docker-compose exec products-service php artisan test
	@echo "$(GREEN)✅ Tests terminés$(NC)"

## 🧪 Tester un service spécifique (make test-service SERVICE=auth-service)
test-service:
	@docker-compose exec $(SERVICE) php artisan test

# =============================================================================
# Commandes utilitaires
# =============================================================================

## 🐚 Accéder au shell d'un service (make shell SERVICE=auth-service)
shell:
	@docker-compose exec $(SERVICE) bash

## 📋 Vérifier la santé de tous les services
health: banner
	@echo "$(BLUE)🏥 Vérification de la santé des services:$(NC)"
	@echo "$(YELLOW)API Gateway:$(NC)"
	@curl -s http://localhost/api/health | jq . || echo "❌ Non disponible"
	@echo "$(YELLOW)Auth Service:$(NC)"
	@curl -s http://localhost/api/auth/health | jq . || echo "❌ Non disponible"
	@echo "$(YELLOW)Addresses Service:$(NC)"
	@curl -s http://localhost/api/addresses/health | jq . || echo "❌ Non disponible"
	@echo "$(YELLOW)Products Service:$(NC)"
	@curl -s http://localhost/api/products/health | jq . || echo "❌ Non disponible"

## 🔧 Installer les dépendances Composer pour un service
composer-install:
	@docker-compose exec $(SERVICE) composer install

## 🔧 Mettre à jour les dépendances Composer pour un service
composer-update:
	@docker-compose exec $(SERVICE) composer update

## 📜 Générer la documentation API (si disponible)
docs:
	@echo "$(BLUE)📜 Documentation API disponible sur:$(NC)"
	@echo "  - API Gateway: http://localhost/docs"
	@echo "  - Auth Service: http://localhost/api/auth/docs"
	@echo "  - Addresses Service: http://localhost/api/addresses/docs"
	@echo "  - Products Service: http://localhost/api/products/docs"

# =============================================================================
# Développement
# =============================================================================

## 🔥 Mode développement avec surveillance des fichiers
dev: banner
	@echo "$(GREEN)🔥 Démarrage en mode développement...$(NC)"
	@docker-compose up --watch

## 🔄 Reconstruire et redémarrer un service (make rebuild SERVICE=auth-service)
rebuild:
	@echo "$(YELLOW)🔄 Reconstruction du service $(SERVICE)...$(NC)"
	@docker-compose build $(SERVICE)
	@docker-compose up -d $(SERVICE)

## 🧹 Nettoyer les caches Laravel
clear-cache:
	@echo "$(YELLOW)🧹 Nettoyage des caches...$(NC)"
	@docker-compose exec auth-service php artisan cache:clear
	@docker-compose exec addresses-service php artisan cache:clear
	@docker-compose exec products-service php artisan cache:clear
	@echo "$(GREEN)✅ Caches nettoyés$(NC)"

# =============================================================================
# Monitoring et debug
# =============================================================================

## 📈 Afficher l'utilisation des ressources
stats:
	@docker stats --no-stream

## 🔍 Inspecter le réseau Docker
network:
	@docker network inspect $(NETWORK)

## 📦 Afficher les volumes Docker
volumes:
	@docker volume ls | grep e-commerce-back

## 🔍 Debug: afficher les variables d'environnement
env-check:
	@echo "$(BLUE)🔍 Variables d'environnement importantes:$(NC)"
	@grep -E "(DB_|RABBITMQ_|SERVICE_)" .env

# =============================================================================
# Sauvegarde et restauration
# =============================================================================

## 💾 Sauvegarder toutes les bases de données
backup: banner
	@echo "$(YELLOW)💾 Sauvegarde des bases de données...$(NC)"
	@mkdir -p ./backups
	@docker-compose exec auth-db mysqldump -u root -proot auth_service_db > ./backups/auth_service_$(shell date +%Y%m%d_%H%M%S).sql
	@docker-compose exec addresses-db mysqldump -u root -proot addresses_service > ./backups/addresses_service_$(shell date +%Y%m%d_%H%M%S).sql
	@docker-compose exec products-db mysqldump -u root -proot products_service_db > ./backups/products_service_$(shell date +%Y%m%d_%H%M%S).sql
	@echo "$(GREEN)✅ Sauvegardes créées dans ./backups/$(NC)"

# =============================================================================
# Aide et informations
# =============================================================================

## 🎨 Afficher la bannière du projet
banner:
	@echo "$(BLUE)"
	@echo "╔══════════════════════════════════════════════════════════════════╗"
	@echo "║                    E-COMMERCE MICROSERVICES                      ║"
	@echo "║                         Management Tool                          ║"
	@echo "╚══════════════════════════════════════════════════════════════════╝"
	@echo "$(NC)"

## ❓ Afficher l'aide
help: banner
	@echo "$(YELLOW)🚀 COMMANDES PRINCIPALES:$(NC)"
	@grep -E '^## .*' $(MAKEFILE_LIST) | sed 's/## /  /' | head -10
	@echo ""
	@echo "$(YELLOW)📋 COMMANDES DISPONIBLES:$(NC)"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(BLUE)%-20s$(NC) %s\n", $$1, $$2}'
	@echo ""
	@echo "$(YELLOW)💡 EXEMPLES D'UTILISATION:$(NC)"
	@echo "  make install              # Première installation"
	@echo "  make start                # Démarrer tous les services"
	@echo "  make logs-service SERVICE=auth-service  # Logs d'un service"
	@echo "  make shell SERVICE=products-service     # Accès shell"
	@echo "  make test-service SERVICE=addresses-service # Tests"
	@echo ""
	@echo "$(GREEN)📖 Documentation complète: README.md$(NC)"

## ℹ️ Afficher les informations du système
info: banner
	@echo "$(BLUE)ℹ️ Informations système:$(NC)"
	@echo "Docker version: $(shell docker --version)"
	@echo "Docker Compose version: $(shell docker-compose --version)"
	@echo "Système: $(shell uname -s -r)"
	@echo "Services configurés: $(SERVICES)"
	@echo "Bases de données: $(DB_SERVICES)"
	@echo ""
	@echo "$(YELLOW)📁 Structure du projet:$(NC)"
	@echo "├── services/          # Services microservices"
	@echo "├── docker/            # Configuration Docker"
	@echo "├── shared/            # Code partagé"
	@echo "├── docker-compose.yml # Orchestration"
	@echo "└── Makefile          # Ce fichier"

.PHONY: start install restart stop down clean status logs logs-service migrate-all seed-all fresh-all test-all test-service shell health composer-install composer-update docs dev rebuild clear-cache stats network volumes env-check backup banner help info