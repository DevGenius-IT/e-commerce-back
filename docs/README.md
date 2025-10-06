# Documentation E-commerce Microservices Platform

**Plateforme** : Collect & Verything - Solution B2B SaaS
**Architecture** : 13 microservices Laravel + Kubernetes
**Date de creation** : 03 Octobre 2025
**Derniere mise a jour** : 03 Octobre 2025
**Version** : 1.0

---

## Table des Matieres

### Base de Donnees
- [00 - Vue d'ensemble Architecture BDD Globale](./database/00-global-database-architecture.md)
- [01 - Relations Inter-Services](./database/01-database-relationships.md)

#### Services Individuels
- [auth-service Database](./database/services/auth-service-database.md)
- [addresses-service Database](./database/services/addresses-service-database.md)
- [products-service Database](./database/services/products-service-database.md)
- [baskets-service Database](./database/services/baskets-service-database.md)
- [orders-service Database](./database/services/orders-service-database.md)
- [deliveries-service Database](./database/services/deliveries-service-database.md)
- [newsletters-service Database](./database/services/newsletters-service-database.md)
- [sav-service Database](./database/services/sav-service-database.md)
- [contacts-service Database](./database/services/contacts-service-database.md)
- [questions-service Database](./database/services/questions-service-database.md)
- [websites-service Database](./database/services/websites-service-database.md)

### Infrastructure
- [01 - Architecture Docker Compose](./infrastructure/01-docker-compose-architecture.md) (Developpement)
- [02 - Architecture Infrastructure Complete](./infrastructure/02-complete-infrastructure-architecture.md) (Production)
- [03 - Architecture Simplifiee](./infrastructure/03-simplified-architecture.md) (Vue Business)
- [04 - Architecture Reseau](./infrastructure/04-networking-architecture.md)
- [05 - Architecture Securite](./infrastructure/05-security-architecture.md)

### Business & Deploiement
- [Plan de Deploiement Collect & Verything](./business/collect-verything-deployment-plan.md)
- [Architecture Multi-Tenant](./business/multi-tenant-architecture.md)
- [Modeles d'Abonnement](./business/pricing-tiers.md)
- [Workflow de Provisioning](./business/provisioning-workflow.md)

---

## Vue d'Ensemble Rapide

### Architecture Globale

La plateforme **Collect & Verything** est une solution B2B SaaS permettant aux entreprises de creer et gerer leur propre site e-commerce via abonnement.

#### Statistiques Cles
- **13 microservices** : architecture decouplee et scalable
- **11 bases de donnees** : isolation complete (database-per-service)
- **3 buckets MinIO** : stockage distribue S3-compatible
- **Communication asynchrone** : RabbitMQ pour evenements inter-services
- **Deploiement** : Docker Compose (dev) + Kubernetes (prod)

#### Stack Technique
- **Backend** : PHP 8.3 + Laravel 12
- **Bases de donnees** : MySQL 8.0
- **Message Broker** : RabbitMQ 3.12
- **Object Storage** : MinIO (S3-compatible)
- **Cache** : Redis
- **Reverse Proxy** : Nginx
- **Orchestration** : Kubernetes
- **Monitoring** : Prometheus + Grafana

---

## Quick Start

### Developpement Local (Docker Compose)
```bash
# Installation complete
make docker-install

# Demarrage avec hot-reload
make dev

# Verifier le statut
make docker-status
```

### Production (Kubernetes)
```bash
# Deploiement complet
make k8s-deploy

# Verifier la sante
make k8s-health

# Voir le statut
make k8s-status
```

---

## Support & Contact

- **Issues** : [GitHub Issues](https://github.com/DevGenius-IT/e-commerce-back/issues)
- **Documentation** : Ce repertoire
- **Projet** : [GitHub Project](https://github.com/orgs/DevGenius-IT/projects/6)

---

Copyright (c) 2024-present DevGenius-IT
