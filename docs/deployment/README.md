# Deployment Guide

## Docker Compose (Development)

### Quick Start

```bash
# Installation complète
make docker-install

# Démarrage quotidien
make docker-start

# Status
make docker-status
```

### Commandes Principales

```bash
# Gestion services
make docker-start          # Démarrer
make docker-stop           # Arrêter
make docker-down           # Arrêter + supprimer
make docker-clean          # Nettoyage complet

# Base de données
make migrate-all           # Migrations
make seed-all              # Seeders
make fresh-all             # Reset complet

# Tests
make test-docker           # Tests Laravel
make health-docker         # Health checks

# MinIO
make minio-workflow        # Workflow complet
make minio-console         # Console web

# Utilitaires
make backup-docker         # Backup DBs
make clear-cache           # Clear caches
```

### Structure Docker

```yaml
services:
  - nginx (80, 443)
  - api-gateway (8100)
  - 12 microservices
  - rabbitmq (5672, 15672)
  - minio (9000, 9001)
  - 12 mysql databases
```

## Kubernetes (Production)

### Quick Start

```bash
# Setup complet
make k8s-setup

# Déploiement
make k8s-deploy K8S_ENVIRONMENT=production

# Status
make k8s-status
```

### Environnements

- **development** - Dev local K8s
- **staging** - Pre-production
- **production** - Production

### Commandes Principales

```bash
# Infrastructure
make k8s-setup             # Config infrastructure
make k8s-build             # Build images

# Déploiement
make k8s-deploy            # Deploy env
make deploy-complete       # Build + deploy + verify

# Monitoring
make k8s-health            # Health checks
make k8s-monitoring        # Dashboards
make k8s-status            # Status plateforme

# Maintenance
make k8s-stop              # Arrêter env
make k8s-down              # Supprimer env
make k8s-clean             # Nettoyer env
```

### Migration Docker → Kubernetes

```bash
# Validation Docker
make health-docker

# Préparation K8s
make k8s-prepare

# Migration progressive
make migrate-to-k8s

# Validation finale
make test-all
```

## Configuration

### Variables d'environnement

Fichier `.env` à la racine contient:
- Connexions databases par service
- RabbitMQ credentials
- MinIO configuration
- JWT secrets
- Service URLs

### Secrets Production

```bash
# Kubernetes secrets
kubectl create secret generic app-secrets \
  --from-literal=jwt-secret=xxx \
  --from-literal=db-password=xxx \
  -n production-microservices
```

## Health Checks

### Endpoints

```bash
# API Gateway
curl http://localhost/api/health

# Services individuels
curl http://localhost/api/{service}/health

# RabbitMQ
http://localhost:15672

# MinIO
http://localhost:9001
```

### Monitoring

```bash
# Docker
make stats
make docker-status

# Kubernetes
make k8s-health
make k8s-monitoring
```

## Troubleshooting

### Docker

**Services ne démarrent pas:**
```bash
docker-compose logs -f {service}
docker-compose build --no-cache {service}
```

**DB connection failed:**
```bash
docker-compose ps
make fresh-all
```

**Port conflicts:**
```bash
lsof -i :80
# Modifier ports dans .env
```

### Kubernetes

**Pods crashloop:**
```bash
kubectl logs -f {pod} -n {namespace}
kubectl describe pod {pod} -n {namespace}
```

**Service unreachable:**
```bash
kubectl get svc -n {namespace}
kubectl port-forward svc/{service} 8080:80 -n {namespace}
```

## Documentation

- **Docker Details:** [DOCKER_QUICKSTART.md](DOCKER_QUICKSTART.md) (à créer si besoin)
- **Kubernetes Setup:** [KUBERNETES_COMPLETE_SETUP.md](KUBERNETES_COMPLETE_SETUP.md)
- **Kubernetes Quick:** [KUBERNETES_QUICKSTART.md](KUBERNETES_QUICKSTART.md)
