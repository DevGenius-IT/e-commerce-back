# 📚 Documentation E-commerce Platform

Plateforme e-commerce microservices avec communication asynchrone RabbitMQ et stockage distribué MinIO.

## 🚀 Quick Start

```bash
# Installation complète
make install-complete

# Démarrage services
make docker-start

# MinIO object storage
make minio-workflow

# Tests
make test-all
```

**Console:** http://localhost:9001 (admin/adminpass123)  
**RabbitMQ:** http://localhost:15672 (guest/guest)  
**API Gateway:** http://localhost

## 📁 Structure Documentation

```
docs/
├── README.md                    # Ce fichier
│
├── architecture/                # Architecture & design
│   ├── README.md               # Vue d'ensemble architecture
│   ├── ARCHITECTURE_PLAN.md    # Plan technique détaillé
│   └── PROJECT_ROADMAP.md      # Roadmap et phases
│
├── deployment/                  # Guides déploiement
│   ├── README.md               # Guide déploiement
│   ├── KUBERNETES_COMPLETE_SETUP.md
│   └── KUBERNETES_QUICKSTART.md
│
├── development/                 # Guides développeurs
│   ├── README.md               # Guide développement
│   ├── QUICK_START.md          # Démarrage rapide
│   ├── CONTRIBUTING.md         # Guide contribution
│   └── ISSUES.md               # Issues connues
│
├── api/postman/                 # API testing
│   └── README.md               # Collections Postman
│
└── minio/                       # Object storage
    ├── README.md               # Guide MinIO
    └── MINIO.md               # Documentation technique
```

## 🎯 Par Rôle

### 👨‍💻 Développeurs

1. **Démarrage:** [development/QUICK_START.md](development/QUICK_START.md)
2. **Development:** [development/README.md](development/README.md)
3. **API Testing:** [api/postman/README.md](api/postman/README.md)
4. **MinIO:** [minio/README.md](minio/README.md)
5. **Contribution:** [development/CONTRIBUTING.md](development/CONTRIBUTING.md)

### 🏗️ DevOps

1. **Architecture:** [architecture/README.md](architecture/README.md)
2. **Déploiement:** [deployment/README.md](deployment/README.md)
3. **Kubernetes:** [deployment/KUBERNETES_COMPLETE_SETUP.md](deployment/KUBERNETES_COMPLETE_SETUP.md)
4. **MinIO Technique:** [minio/MINIO.md](minio/MINIO.md)

### 📋 Product Owners

1. **Roadmap:** [architecture/PROJECT_ROADMAP.md](architecture/PROJECT_ROADMAP.md)
2. **Architecture Plan:** [architecture/ARCHITECTURE_PLAN.md](architecture/ARCHITECTURE_PLAN.md)

## 🏗️ Architecture

### Services

| Service | Port | Fonction |
|---------|------|----------|
| api-gateway | 8100 | Point d'entrée, routing |
| auth-service | 8001 | JWT authentication |
| products-service | 8003 | Catalogue produits |
| orders-service | 8004 | Commandes |
| baskets-service | 8005 | Paniers |
| deliveries-service | 8006 | Livraisons |
| newsletters-service | 8007 | Email campaigns |
| sav-service | 8008 | Support client |

### Infrastructure

- **RabbitMQ:** Message broker (5672, 15672)
- **MinIO:** Object storage (9000, 9001)
- **MySQL:** Database per service
- **Nginx:** Reverse proxy

## 🛠️ Commandes Essentielles

### Docker

```bash
make docker-start          # Démarrer services
make docker-stop           # Arrêter services
make health-docker         # Health checks
make test-docker           # Tests
```

### MinIO

```bash
make minio-workflow        # Workflow complet
make minio-console         # Console web
make minio-test            # Tests
```

### Kubernetes

```bash
make k8s-setup             # Setup infrastructure
make k8s-deploy            # Déployer
make k8s-health            # Health checks
```

### Database

```bash
make migrate-all           # Migrations
make seed-all              # Seeders
make backup-docker         # Backup
```

## 🔒 Sécurité

- JWT authentication centralisée
- Role-based access control (RBAC)
- Presigned URLs (MinIO)
- File sanitization
- API rate limiting

## 📊 Monitoring

**Health Checks:**
```bash
http://localhost/api/health              # API Gateway
http://localhost/api/{service}/health    # Services
http://localhost:15672                   # RabbitMQ
http://localhost:9001                    # MinIO
```

**Metrics:**
- Response time: <500ms
- Throughput: 1000 req/s
- Availability: 99.9%

## 🧪 Tests

```bash
make test-all              # Tous tests
make test-docker           # Tests Docker
make minio-test            # Tests MinIO
make validate-platform     # Validation complète
```

## 🤝 Contribution

Voir [development/CONTRIBUTING.md](development/CONTRIBUTING.md)

## 📞 Support

**Issues:** [development/ISSUES.md](development/ISSUES.md)  
**API:** [api/postman/README.md](api/postman/README.md)  
**Architecture:** [architecture/README.md](architecture/README.md)

---

**Documentation complète pour une plateforme e-commerce moderne! 🚀**
