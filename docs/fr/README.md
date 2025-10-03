# Documentation Technique - Collect & Verything E-commerce

Documentation complete en francais de la plateforme e-commerce microservices.

## Structure de la Documentation

### Base de Donnees

Documentation complete de l'architecture des 11 bases de donnees MySQL:

- [Architecture Globale Base de Donnees](database/00-architecture-globale-base-de-donnees.md) - Vue d'ensemble des 11 bases de donnees, 66 tables
- [Relations Base de Donnees](database/01-relations-base-de-donnees.md) - Catalogue evenements RabbitMQ, diagrammes workflow

#### Services Base de Donnees

- [Service Authentification](database/services/auth-service-base-de-donnees.md) - 7 tables (utilisateurs, roles, permissions, sessions)
- [Service Produits](database/services/products-service-base-de-donnees.md) - 15 tables (produits, categories, marques, variantes, stock)
- [Service Paniers](database/services/baskets-service-base-de-donnees.md) - Gestion panier achats
- [Service Commandes](database/services/orders-service-base-de-donnees.md) - Machine a etats commandes, calculs TTC/HT
- [Service Adresses](database/services/addresses-service-base-de-donnees.md) - Stockage adresses
- [Service Livraisons](database/services/deliveries-service-base-de-donnees.md) - Suivi livraisons
- [Service Newsletters](database/services/newsletters-service-base-de-donnees.md) - Campagnes email
- [Service SAV](database/services/sav-service-base-de-donnees.md) - Tickets support
- [Service Contacts](database/services/contacts-service-base-de-donnees.md) - Formulaires contact
- [Service Questions](database/services/questions-service-base-de-donnees.md) - Systeme FAQ
- [Service Sites Web](database/services/websites-service-base-de-donnees.md) - Configuration multi-sites

### Infrastructure

Documentation architecture complete production et developpement:

- [Architecture Docker Compose](infrastructure/01-architecture-docker-compose.md) - 27 conteneurs, hot-reload, configuration reseau
- [Architecture Infrastructure Complete](infrastructure/02-architecture-infrastructure-complete.md) - Architecture production (67000+ caracteres)
- [Architecture Simplifiee](infrastructure/03-architecture-simplifiee.md) - Vue stakeholders (36000+ caracteres)
- [Architecture Reseau](infrastructure/04-architecture-reseau.md) - Politiques reseau, decouverte services, DNS
- [Architecture Securite](infrastructure/05-architecture-securite.md) - RBAC, Network Policies, Pod Security, conformite

### Business B2B

Documentation architecture multi-tenant et tarification:

- [Architecture Multi-Tenant](business/01-architecture-multi-tenant.md) - Isolation namespace, base par tenant, buckets MinIO
- [Workflow Provisionnement](business/02-workflow-provisionnement.md) - Provisionnement automatise 8-11 minutes
- [Niveaux Tarification](business/03-niveaux-tarification.md) - Standard/Premium/Business avec quotas ressources

### Services

Documentation complete des 13 microservices:

- [Passerelle API](services/01-passerelle-api.md) - Port 8100, routage via RabbitMQ
- [Service Authentification](services/02-service-authentification.md) - Port 8000, JWT avec Spatie RBAC
- [Service Produits](services/03-service-produits.md) - Port 8001, catalogue produits
- [Service Paniers](services/04-service-paniers.md) - Port 8002, gestion paniers
- [Service Commandes](services/05-service-commandes.md) - Port 8003, traitement commandes
- [Service Adresses](services/06-service-adresses.md) - Port 8004, gestion adresses
- [Service Livraisons](services/07-service-livraisons.md) - Port 8005, suivi livraisons
- [Service Newsletters](services/08-service-newsletters.md) - Port 8006, campagnes email
- [Service SAV](services/09-service-sav.md) - Port 8007, support client
- [Service Contacts](services/10-service-contacts.md) - Port 8008, formulaires contact
- [Service Questions](services/11-service-questions.md) - Port 8009, systeme FAQ
- [Service Sites Web](services/12-service-sites-web.md) - Port 8010, configuration multi-sites
- [Courtier Messages](services/13-courtier-messages.md) - Port 8011, coordination RabbitMQ

## Stack Technique

### Backend
- PHP 8.3 avec Laravel 12
- Authentification JWT avec Spatie Laravel Permission
- APIs RESTful

### Infrastructure
- Docker Compose pour developpement (27 conteneurs)
- Kubernetes pour production avec Kustomize
- RabbitMQ 3.12 (ports 5672, 15672)
- MinIO S3-compatible (ports 9000, 9001)
- Nginx reverse proxy
- MySQL 8.0 par service

### Monitoring & Operations
- Prometheus + Grafana metriques
- Jaeger tracing distribue
- FluentBit agregation logs
- ArgoCD deployments GitOps
- External Secrets Operator

## Architecture Multi-Tenant

- Isolation namespace-par-tenant
- Base-de-donnees-par-tenant dans cluster MySQL partage
- Bucket-par-tenant dans MinIO
- Virtual host par tenant dans RabbitMQ
- Routage ingress base sur domaine
- Provisionnement/deprovisionnement automatise

## Niveaux Tarification

### Standard (49.99 EUR/mois)
- 1 site web
- 2 CPU, 4 GB RAM
- 50 GB stockage
- Support email

### Premium (124.99 EUR/mois)
- 3 sites web
- 6 CPU, 12 GB RAM
- 150 GB stockage
- Support prioritaire

### Business (149.99+ EUR/mois)
- 10 sites web
- 12+ CPU, 24+ GB RAM
- 300+ GB stockage
- Support dedie

## Demarrage Rapide

### Prerequis
- Docker et Docker Compose
- Make

### Installation

```bash
# Configuration premiere fois
make docker-install

# Developpement quotidien
make dev

# Verification sante services
make health-docker

# Voir statut tous services
make docker-status
```

### Commandes Principales

```bash
# Operations Docker
make docker-start          # Demarrer services
make docker-stop           # Arreter services
make docker-clean          # Nettoyage complet

# Gestion base de donnees
make migrate-all           # Executer migrations
make seed-all              # Executer seeders
make fresh-all             # Migration fraiche avec seeds

# Tests
make test-docker           # Tests tous services
make test-service SERVICE_NAME=auth-service

# Kubernetes
make k8s-deploy            # Deployer vers Kubernetes
make k8s-status            # Verifier statut deploiement
```

## Statistiques Cles

- **13 microservices** independants
- **11 bases de donnees** MySQL (database-per-service)
- **66 tables** au total
- **40+ evenements** RabbitMQ
- **27 conteneurs** Docker
- **3 niveaux** tarification (Standard/Premium/Business)
- **8-11 minutes** provisionnement automatise tenant

## Documentation Anglaise

Toute la documentation est egalement disponible en anglais dans le repertoire parent `/docs/`.

## Support

Pour questions techniques ou contributions, voir:
- CLAUDE.md - Guide developpement
- Makefile - Commandes disponibles
- docker-compose.yml - Configuration services
