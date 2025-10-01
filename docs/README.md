# 📚 Documentation E-commerce Platform

## 🏗️ Architecture Overview

Cette plateforme e-commerce utilise une **architecture microservices moderne** avec communication asynchrone via RabbitMQ et stockage distribué via MinIO.

### 🎯 Quick Links
- **🚀 [Quick Start](./development/QUICK_START.md)** - Démarrage rapide
- **📋 [Architecture Plan](./architecture/ARCHITECTURE_PLAN.md)** - Plan technique détaillé
- **🛠️ [Service Implementation Guide](./COMPLETE_SERVICE_IMPLEMENTATION.md)** - Guide complet d'implémentation
- **🔧 [API Documentation](./api/postman/README.md)** - Collections Postman et tests

## 📁 Structure Documentation

### 🏗️ Architecture
```
docs/architecture/
├── ARCHITECTURE_PLAN.md       # Plan technique global
├── PROJECT_ROADMAP.md         # Roadmap et phases
└── PLATFORM_INTEGRATION_COMPLETE.md  # Intégration plateforme
```

### 🚀 Deployment
```
docs/deployment/
├── DOCKER_FIXES_FINAL.md               # Corrections Docker
├── KUBERNETES_COMPLETE_SETUP.md        # Setup Kubernetes complet
├── KUBERNETES_MIGRATION_COMPLETE.md    # Migration vers K8s
├── KUBERNETES_QUICKSTART.md           # Démarrage rapide K8s
├── MIGRATION_PROGRESSIVE.md           # Migration progressive
└── MIGRATION_SUMMARY.md              # Résumé migration
```

### 💻 Development
```
docs/development/
├── QUICK_START.md              # Guide démarrage développeur
├── ISSUES.md                   # Issues connues et solutions
├── CONTRIBUTING.md             # Guide contribution
├── MAKEFILE_FIXES_APPLIED.md   # Corrections Makefile
├── MAKEFILE_UPGRADE_SUMMARY.md # Améliorations Makefile
└── SOCKETS_EXTENSION_FIXES.md  # Fixes extension sockets
```

### 🛠️ Maintenance
```
docs/maintenance/
└── AUTOMATION_COMPLETE.md     # Scripts d'automatisation
```

### 🔌 API
```
docs/api/
└── postman/                    # Collections Postman
    ├── README.md               # Guide utilisation API
    ├── Complete E-commerce API v2.postman_collection.json
    ├── Development Environment.postman_environment.json
    ├── Staging Environment.postman_environment.json
    ├── Production Environment.postman_environment.json
    └── validate-collection.sh  # Script validation
```

## 🎯 Workflows Recommandés

### 🚀 Pour les Développeurs
1. **Setup Initial**: [Quick Start](./development/QUICK_START.md)
2. **Nouveau Service**: [Service Implementation](./COMPLETE_SERVICE_IMPLEMENTATION.md)
3. **Tests API**: [API Collections](./api/postman/README.md)
4. **Contribution**: [Contributing Guide](./development/CONTRIBUTING.md)

### 🏗️ Pour les DevOps
1. **Architecture**: [Architecture Plan](./architecture/ARCHITECTURE_PLAN.md)
2. **Docker Setup**: [Docker Fixes](./deployment/DOCKER_FIXES_FINAL.md)
3. **Kubernetes**: [K8s Complete Setup](./deployment/KUBERNETES_COMPLETE_SETUP.md)
4. **Migration**: [Migration Progressive](./deployment/MIGRATION_PROGRESSIVE.md)

### 🔧 Pour les Administrateurs
1. **Monitoring**: [Platform Integration](./architecture/PLATFORM_INTEGRATION_COMPLETE.md)
2. **Maintenance**: [Automation Complete](./maintenance/AUTOMATION_COMPLETE.md)
3. **Roadmap**: [Project Roadmap](./architecture/PROJECT_ROADMAP.md)

## 🛠️ Outils et Scripts

### 📁 Scripts Directory
```
scripts/
├── platform-control.sh        # Contrôle unifié plateforme
└── tools/                     # Outils divers
    ├── complete-automation.sh  # Automatisation complète
    ├── platform-validator.sh   # Validation plateforme
    └── deployment-verifier.sh  # Vérification déploiement
```

### 🎯 Commandes Principales
```bash
# Démarrage rapide Docker
make docker-start

# Déploiement Kubernetes
make k8s-deploy

# Tests complets
make test-all

# Validation plateforme
./scripts/platform-control.sh

# Collections API
cd docs/api/postman && ./validate-collection.sh
```

## 📊 Métriques et Monitoring

### 🏥 Health Checks
- **API Gateway**: `http://localhost/api/health`
- **RabbitMQ**: `http://localhost:15672`
- **MinIO Console**: `http://localhost:9001`
- **Services Status**: `http://localhost/api/v1/services/status`

### 📈 Performance Targets
- **Response Time**: < 500ms (API calls)
- **Throughput**: 1000 req/s par service
- **Availability**: 99.9% uptime
- **Storage**: Auto-scaling MinIO

## 🔒 Sécurité

### 🛡️ Authentication
- **JWT Tokens**: Authentification centralisée
- **Role-based Access**: Permissions granulaires
- **API Rate Limiting**: Protection DDoS
- **File Upload Security**: Validation types/tailles

### 🔐 Secrets Management
```bash
# Development
.env files per service

# Staging/Production
Kubernetes secrets + external vault
```

## 🚀 Architecture Technique

### 🎯 Flow de Communication
```
Client → Nginx → API Gateway → RabbitMQ → Services
                                    ↓
                            MinIO Storage
```

### 📦 Services Actifs
- **api-gateway** (8000) - Point d'entrée unique
- **auth-service** (8001) - Authentication JWT
- **products-service** (8003) - Catalogue produits
- **orders-service** (8004) - Gestion commandes
- **baskets-service** (8005) - Paniers d'achat
- **deliveries-service** (8006) - Livraisons
- **newsletters-service** (8007) - Email marketing
- **sav-service** (8008) - Support client
- **addresses-service** (8009) - Gestion adresses
- **contacts-service** (8010) - Formulaires contact
- **questions-service** (8012) - FAQ système
- **websites-service** (8012) - Configuration sites

### 🗃️ Infrastructure
- **RabbitMQ**: Message broker (port 5672, management 15672)
- **MinIO**: Object storage (port 9000, console 9001)
- **MySQL**: Bases de données per service
- **Nginx**: Reverse proxy et load balancer

## 📝 Contribution

### 🤝 Comment Contribuer
1. Lire [Contributing Guide](./development/CONTRIBUTING.md)
2. Vérifier [Issues](./development/ISSUES.md)
3. Suivre les conventions de commit
4. Tester avec les collections Postman
5. Valider avec `make test-all`

### 🔧 Development Workflow
```bash
# 1. Setup environnement
make docker-start

# 2. Développement service
# Voir: docs/COMPLETE_SERVICE_IMPLEMENTATION.md

# 3. Tests
make test-service SERVICE_NAME=your-service

# 4. Validation
./scripts/platform-validator.sh
```

---

## 📞 Support

### 🆘 Troubleshooting
- **Services**: [Issues Documentation](./development/ISSUES.md)
- **Docker**: [Docker Fixes](./deployment/DOCKER_FIXES_FINAL.md)
- **Kubernetes**: [K8s Troubleshooting](./deployment/KUBERNETES_COMPLETE_SETUP.md)
- **API**: [Postman Collections](./api/postman/README.md)

### 📚 Ressources
- **Architecture**: Fully asynchronous microservices
- **Communication**: RabbitMQ message broker
- **Storage**: MinIO distributed object storage
- **Authentication**: JWT with role-based permissions
- **API**: RESTful with automated testing

**🎉 Documentation complète pour une plateforme e-commerce moderne ! 🚀**