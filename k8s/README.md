# Kubernetes Infrastructure - E-commerce Microservices

## 🏗️ Structure du Projet

```
k8s/
├── base/                    # Kustomize base configurations
│   ├── namespace.yaml       # Namespace e-commerce
│   ├── configmaps/         # Configuration globale
│   ├── secrets/            # Secrets templates
│   └── services/           # Services de base
├── overlays/               # Kustomize overlays par environnement
│   ├── development/        # Config dev locale
│   ├── staging/           # Config staging
│   └── production/        # Config production
├── manifests/             # Manifests Kubernetes spécialisés
│   ├── gateway/           # Traefik Ingress Controller
│   ├── monitoring/        # Prometheus Stack
│   ├── messaging/         # RabbitMQ Cluster
│   └── databases/         # MySQL Operators
└── scripts/               # Scripts de déploiement
```

## 🎯 Environments

### Development
- **Namespace**: `e-commerce-dev`
- **Domain**: `dev.api.yourcompany.com`
- **Replicas**: 1 par service
- **Resources**: Limites basses

### Staging
- **Namespace**: `e-commerce-staging`
- **Domain**: `staging.api.yourcompany.com`
- **Replicas**: 2 par service
- **Resources**: Limites moyennes

### Production
- **Namespace**: `e-commerce-prod`
- **Domain**: `api.yourcompany.com`
- **Replicas**: 3+ par service
- **Resources**: Limites élevées + HPA

## 🚀 Déploiement

```bash
# Development
kubectl apply -k k8s/overlays/development

# Staging
kubectl apply -k k8s/overlays/staging

# Production
kubectl apply -k k8s/overlays/production
```

## 📊 Services Déployés

- **api-gateway**: Point d'entrée principal
- **auth-service**: Authentication + RBAC
- **messages-broker**: RabbitMQ + Management
- **addresses-service**: Gestion des adresses
- **products-service**: Catalogue produits
- **baskets-service**: Paniers d'achat
- **orders-service**: Gestion commandes
- **deliveries-service**: Livraisons
- **newsletters-service**: Email marketing
- **sav-service**: Support client
- **questions-service**: FAQ
- **contacts-service**: Gestion contacts
- **websites-service**: Configuration sites

## 🛡️ Sécurité

- **Network Policies**: Deny-all par défaut
- **RBAC**: Permissions minimales
- **Pod Security Standards**: Restricted
- **External Secrets**: Vault integration
- **TLS**: Automatique via cert-manager