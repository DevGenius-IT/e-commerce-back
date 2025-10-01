# 🛍️ E-commerce Platform API - Postman Collections v2.0

## 📋 Overview

Cette collection Postman v2.0 a été complètement retravaillée pour refléter l'architecture microservices moderne de la plateforme e-commerce avec communication asynchrone via RabbitMQ.

## 🏗️ Architecture

```
Client → Nginx → API Gateway → RabbitMQ → Target Service → Response
```

### Points clés :
- **Point d'entrée unique** : API Gateway via Nginx
- **Communication asynchrone** : RabbitMQ pour tous les services
- **Authentification JWT** : Centralisée avec gestion automatique des tokens
- **13 Microservices** : Architecture domain-driven

## 📁 Fichiers de Collection

### Collections
- **`Complete E-commerce API v2.postman_collection.json`** - Collection complète avec tous les services *(RECOMMANDÉ)*
- **`E-commerce Platform API v2.postman_collection.json`** - Collection de base (en développement)

### Environnements
- **`Development Environment.postman_environment.json`** - Docker Compose local
- **`Staging Environment.postman_environment.json`** - Kubernetes staging
- **`Production Environment.postman_environment.json`** - Kubernetes production

## 🚀 Quick Start

### 1. Import dans Postman
```bash
# Importer la collection principale
File > Import > Complete E-commerce API v2.postman_collection.json

# Importer l'environnement souhaité
File > Import > Development Environment.postman_environment.json
```

### 2. Configuration de l'environnement
1. Sélectionner l'environnement approprié dans Postman
2. Vérifier les variables d'environnement :
   - `base_url` : URL de base de l'API
   - `admin_email` / `admin_password` : Credentials admin
   - Autres variables de test

### 3. Authentification
1. Aller dans **🔐 Authentication & Authorization > Login**
2. Exécuter la requête
3. Le JWT token sera automatiquement sauvé
4. Toutes les requêtes authentifiées utiliseront ce token

### 4. Tests de santé
1. Exécuter **🏥 Health & Monitoring > API Gateway Health**
2. Vérifier **RabbitMQ Connection Test**
3. Contrôler **All Services Status**

## 🌍 Environnements

### Development (Docker Compose)
```json
{
  "base_url": "http://localhost",
  "environment_name": "development",
  "rabbitmq_management": "http://localhost:15672"
}
```

**Usage** :
- Développement local avec Docker Compose
- Accès direct aux services via ports mappés
- RabbitMQ Management UI disponible
- Debug mode activé

### Staging (Kubernetes)
```json
{
  "base_url": "https://staging-api.yourdomain.com",
  "environment_name": "staging",
  "ssl_verify": "true"
}
```

**Usage** :
- Tests d'intégration pré-production
- Environnement Kubernetes avec HTTPS
- Credentials depuis CI/CD secrets
- Rate limiting appliqué

### Production (Kubernetes)
```json
{
  "base_url": "https://api.yourdomain.com",
  "environment_name": "production",
  "monitoring_enabled": "true"
}
```

**Usage** :
- Tests en production (avec précaution)
- Monitoring actif des requêtes
- Credentials depuis vault sécurisé
- Accès limité et contrôlé

## 📚 Services Disponibles

### Core Services
| Service | Description | Endpoints principaux |
|---------|-------------|---------------------|
| **auth-service** | Authentification & autorisation | `/login`, `/logout`, `/me` |
| **products-service** | Catalogue produits | `/products`, `/categories`, `/brands` |
| **baskets-service** | Panier d'achat | `/baskets/current`, `/baskets/items` |
| **orders-service** | Gestion commandes | `/orders`, `/orders/create-from-basket` |
| **deliveries-service** | Livraisons & tracking | `/track/{id}`, `/sale-points` |

### Support Services
| Service | Description | Endpoints principaux |
|---------|-------------|---------------------|
| **addresses-service** | Gestion adresses | `/addresses`, `/countries` |
| **sav-service** | Support client | `/tickets`, `/public/tickets` |
| **questions-service** | FAQ & knowledge base | `/public/questions`, `/public/search` |
| **contacts-service** | Formulaires contact | `/contact` |
| **newsletters-service** | Newsletters | `/subscribe`, `/stats` |
| **websites-service** | Configuration sites | `/config` |

## 🧪 Tests Automatiques

### Tests Globaux
- **Response time** : < 5000ms (configurable par environnement)
- **Content-type** : Validation JSON
- **JWT expiration** : Vérification automatique
- **Status codes** : Validation appropriée

### Tests Spécifiques
- **Authentication** : Token auto-save, validation user data
- **Products** : Product ID extraction pour tests chaînés
- **Orders** : Order ID sauvegarde pour tracking
- **Health checks** : Validation complète des services

### Variables Dynamiques
Les tests sauvegardent automatiquement :
- `jwt_token` : Token d'authentification
- `user_id` : ID utilisateur connecté  
- `test_product_id` : ID produit pour tests
- `test_order_id` : ID commande créée

## 🔧 Workflows E2E

### Purchase Workflow Complet
1. **Login** → Sauvegarde JWT
2. **Add Product to Basket** → Utilise product_id
3. **Create Order** → Sauvegarde order_id
4. **Track Delivery** → Utilise tracking number

### Support Workflow
1. **Create Support Ticket** (public)
2. **Login** → Authentication
3. **List My Tickets** → Historique support
4. **Get Ticket Details** → Suivi ticket

## 🛠️ Configuration Avancée

### Variables d'Environnement Personnalisées
```javascript
// Dans les tests Postman
pm.environment.set('custom_variable', 'value');
pm.environment.get('custom_variable');
```

### Debug Mode
```json
{
  "debug_mode": "true",  // Active les logs détaillés
  "request_timeout_ms": "10000"  // Timeout personnalisé
}
```

### SSL Configuration
```json
{
  "ssl_verify": "true",  // Validation certificats SSL
  "api_gateway_direct": "https://gateway.domain.com"  // Accès direct bypass
}
```

## 📊 Monitoring & Debugging

### Health Checks Disponibles
- **API Gateway** : Point d'entrée principal
- **RabbitMQ** : Test connectivité message broker
- **Services Status** : Vue d'ensemble tous services
- **Individual Services** : Health check par service

### Logs & Debugging
```javascript
// Dans les scripts de test
console.log('🚀 Request URL:', pm.request.url.toString());
console.log('📊 Response Status:', pm.response.code);
console.log('⏱️ Response Time:', pm.response.responseTime + 'ms');
```

### URLs Utiles
- **RabbitMQ Management** : `http://localhost:15672` (dev)
- **API Gateway Direct** : `http://localhost:8000` (dev)
- **Services Status** : `{{base_url}}/api/v1/services/status`

## 🔒 Sécurité

### JWT Token Management
- **Auto-expiration** : Vérification avant chaque requête
- **Auto-clear** : Nettoyage après logout
- **Secure storage** : Variables marquées comme "secret"

### Production Safety
- **Limited endpoints** : Certains endpoints désactivés en prod
- **Safe test data** : IDs de test vérifiés sûrs
- **Rate limiting** : Respect des limites API

### Credentials Management
```bash
# Variables à définir dans l'environnement
STAGING_ADMIN_PASSWORD=xxx
PROD_ADMIN_PASSWORD=xxx
STAGING_RABBITMQ_PASSWORD=xxx
PROD_RABBITMQ_PASSWORD=xxx
```

## 🚨 Troubleshooting

### Problèmes Courants

#### JWT Token Expiré
```javascript
// Solution automatique dans pre-request script
if (token_expired) {
    pm.environment.set('jwt_token', '');
    // Relancer Login request
}
```

#### Services Inaccessibles
1. Vérifier **Health Checks**
2. Contrôler **RabbitMQ Connection**
3. Vérifier **Services Status**

#### Environnement Docker
```bash
# Vérifier les services
make docker-status

# Redémarrer si nécessaire
make docker-stop
make docker-start
```

#### Environnement Kubernetes
```bash
# Vérifier les pods
kubectl get pods -n development-microservices

# Vérifier les services
make k8s-health
```

### Support
- **Documentation** : `/docs` endpoints disponibles
- **Health Status** : Monitoring en temps réel
- **Error Logs** : Console Postman pour debugging

## 📈 Métriques & Performance

### Benchmarks Attendus
- **Authentication** : < 500ms
- **Product Catalog** : < 1000ms
- **Cart Operations** : < 800ms
- **Order Creation** : < 2000ms

### Monitoring
Les environnements Kubernetes incluent :
- **Response time tracking**
- **Error rate monitoring**  
- **Service availability**
- **Request volume metrics**

---

## 📝 Notes de Version

### v2.0 (2025-01-15)
- ✅ Architecture microservices complète
- ✅ Communication asynchrone RabbitMQ
- ✅ JWT authentication automatique
- ✅ 13 services intégrés
- ✅ Tests automatiques avancés
- ✅ Environnements multi-déploiement
- ✅ Workflows E2E complets

### Migration depuis v1.x
1. Remplacer l'ancienne collection
2. Importer les nouveaux environnements
3. Vérifier les nouvelles variables
4. Tester l'authentification
5. Valider les health checks

**Collection prête pour développement, staging et production ! 🚀**