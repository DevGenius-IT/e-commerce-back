# 📮 Guide d'Utilisation Collection Postman - Architecture Asynchrone

## 🎯 Vue d'Ensemble

Cette collection Postman est conçue pour tester la **nouvelle architecture fully asynchrone** de la plateforme e-commerce. Tous les services communiquent désormais exclusivement via **RabbitMQ**.

### 🏗️ Architecture Testée
```
Client → Nginx (port 80) → API Gateway (port 8100) → RabbitMQ → Services
```

**Plus aucune communication HTTP directe entre services !**

---

## 🚀 Configuration Rapide

### 1. Importer la Collection
1. Ouvrir Postman
2. Importer `E-commerce Microservices API.postman_collection.json`  
3. Importer `Local Environment.postman_environment.json`
4. Sélectionner l'environnement "Local Environment - Architecture Asynchrone"

### 2. Variables Pré-configurées
- ✅ **base_url**: `http://localhost` (via Nginx)
- ✅ **working_jwt_token**: Token admin valide  
- ✅ **test_product_id**: Produit de test (ID: 1)
- ✅ **test_promo_code**: Code promo `WELCOME10`
- ✅ **paris_latitude/longitude**: Coordonnées pour tests géolocalisation

### 3. Services Disponibles
- ✅ **API Gateway** - Point d'entrée unique
- ✅ **Auth Service** - Authentification JWT
- ✅ **Products Service** - Catalogue produits
- ✅ **Baskets Service** - Paniers et codes promo
- ✅ **Orders Service** - Gestion commandes
- ✅ **Deliveries Service** - Livraisons et tracking
- ✅ **Addresses Service** - Adresses utilisateurs
- ✅ **SAV Service** - Support client
- ✅ **Communication Services** - Newsletters/Contacts

---

## 📋 Structure de la Collection

### 🏗️ Architecture & Monitoring
Endpoints pour diagnostiquer l'infrastructure asynchrone :

| Endpoint | Description | Statut Attendu |
|----------|-------------|----------------|
| `GET /api/health` | Health check API Gateway | 200 - Gateway actif |
| `GET /api/test-rabbitmq` | Test connexion RabbitMQ | 200 - RabbitMQ connected |
| `GET /api/services/status` | État des microservices | 200 - Services disponibles |

### 🔐 Authentication Service
Gestion centralisée de l'authentification JWT :

```javascript
// Après login réussi, le token est automatiquement sauvé
POST /api/auth/login
→ Sauvegarde automatique dans {{jwt_token}}
```

### 🛍️ Services E-commerce
Workflow complet du client :

1. **Navigation** : `GET /api/products/products`
2. **Panier** : `POST /api/baskets/baskets/items`
3. **Promotion** : `POST /api/baskets/baskets/promo-codes`
4. **Commande** : `POST /api/orders/orders/create-from-basket`
5. **Livraison** : `GET /api/deliveries/track/{number}`

### 👨‍💼 Administration
Routes d'administration avec contrôle d'accès JWT :

- **Gestion Produits** : `/api/products/admin/*`
- **Gestion Commandes** : `/api/orders/admin/*`
- **Statistiques Livraisons** : `/api/deliveries/admin/*`
- **Support Client** : `/api/sav/admin/*`

### 🧪 Tests E2E
Workflow complet de bout en bout simulant un parcours client réel.

---

## 🔧 Utilisation Détaillée

### 🚀 Test de l'Architecture

#### 1. Vérifier l'Infrastructure
```bash
# 1. Health check général
GET {{base_url}}/api/health
→ Doit retourner: {"status":"healthy","service":"api-gateway"}

# 2. Connexion RabbitMQ  
GET {{base_url}}/api/test-rabbitmq
→ Doit retourner: {"rabbitmq_connected":true}

# 3. État des services
GET {{base_url}}/api/services/status
→ Liste les services disponibles via RabbitMQ
```

#### 2. Test de Communication Asynchrone
```bash
# Test d'un service via l'architecture asynchrone
GET {{base_url}}/api/products/health
→ Client → Nginx → API Gateway → RabbitMQ → Products Service
```

### 🔐 Authentification

#### 1. Connexion Standard
```json
POST /api/auth/login
{
    "email": "kylian@collect-verything.com", 
    "password": "password"
}
```

Le script de test sauvegarde automatiquement :
- `jwt_token` : Token pour les requêtes authentifiées
- `user_id` : ID utilisateur pour les relations

#### 2. Utilisation du Token
Toutes les requêtes protégées utilisent :
```
Authorization: Bearer {{jwt_token}}
```

### 🛍️ Workflow E-commerce Complet

#### Parcours Client Type
```bash
# 1. Navigation produits (public)
GET /api/products/products

# 2. Connexion client
POST /api/auth/login

# 3. Ajout au panier
POST /api/baskets/baskets/items
{
    "product_id": 1,
    "quantity": 2
}

# 4. Application code promo  
POST /api/baskets/baskets/promo-codes
{
    "code": "WELCOME10"
}

# 5. Création commande
POST /api/orders/orders/create-from-basket
{
    "address_id": 1,
    "payment_method": "credit_card"
}

# 6. Suivi livraison
GET /api/deliveries/track/DLV-20241225-0001
```

### 📊 Tests d'Administration

#### Utilisation Token Admin
Utilisez `{{working_jwt_token}}` pour les routes admin :

```bash
# Création produit (admin)
POST /api/products/admin/products
Authorization: Bearer {{working_jwt_token}}

# Statistiques livraisons
GET /api/deliveries/admin/deliveries/statistics  
Authorization: Bearer {{working_jwt_token}}
```

---

## 🧪 Tests E2E Automatisés

### Workflow "Complete Purchase"
Séquence automatisée de 6 étapes testant l'architecture complète :

1. **Login** : Authentification + sauvegarde token
2. **Browse** : Navigation produits  
3. **Add to Basket** : Ajout panier
4. **Apply Promo** : Code promotionnel
5. **Create Order** : Commande + sauvegarde order_id
6. **Track** : Suivi commande

### Exécution
1. Sélectionner le folder "🧪 E2E Workflow Tests"
2. Clic droit → "Run collection"
3. Surveiller l'exécution séquentielle

---

## 🔍 Debugging & Troubleshooting

### Problèmes Courants

#### ❌ Connection Refused
**Problème** : `curl: (7) Failed to connect to localhost`
**Solution** : 
```bash
# Vérifier que les services sont démarrés
docker-compose ps

# Vérifier Nginx
curl http://localhost/api/health
```

#### ❌ Service Unavailable (503)
**Problème** : `{"error":"Service unavailable","message":"Error routing..."}`
**Cause** : Problème RabbitMQ RPC (timeout responses)
**Debug** :
```bash
# Vérifier RabbitMQ
curl -u guest:guest http://localhost:15672/api/queues

# Vérifier les consumers actifs
curl -u guest:guest http://localhost:15672/api/queues | jq '.[] | {name, consumers}'
```

#### ❌ Authentication Required (401)
**Problème** : `{"error":"Authentication required"}`
**Solution** :
1. Vérifier que `{{jwt_token}}` est défini
2. Utiliser le login pour générer un nouveau token
3. Vérifier l'expiration du token

#### ❌ Route Not Found (404)
**Problème** : `{"message":"The route api/... could not be found"}`
**Cause** : Problème routage Nginx → API Gateway
**Debug** :
```bash
# Test direct API Gateway (bypass Nginx)
curl http://localhost:8100/v1/health

# Test via Nginx
curl http://localhost/api/health
```

### Variables de Debug

#### Accès Direct Services (Bypass Architecture)
Pour debug uniquement, vous pouvez accéder directement :
- **API Gateway Direct** : `{{api_gateway_direct}}` (http://localhost:8100)
- **RabbitMQ Management** : `{{rabbitmq_management}}` (http://localhost:15672)

⚠️ **Ne pas utiliser en production - architecture asynchrone uniquement !**

---

## 📈 Monitoring RabbitMQ

### Interface Management
- **URL** : http://localhost:15672
- **Credentials** : guest/guest

### Queues Critiques à Surveiller
- `products.requests` - Communications avec Products Service
- `auth.requests` - Communications avec Auth Service  
- `baskets.requests` - Communications avec Baskets Service
- `orders.requests` - Communications avec Orders Service

### Métriques Importantes
- **Consumers** : Nombre de listeners actifs par service
- **Messages** : Throughput des messages RPC
- **Publish/Deliver** : Ratio publication/livraison des messages

---

## 🚀 Bonnes Pratiques

### 🔄 Workflow Recommandé

1. **Toujours commencer par** :
   - Architecture & Monitoring → API Gateway Health
   - Architecture & Monitoring → RabbitMQ Connection Test

2. **Pour les tests fonctionnels** :
   - Utiliser l'authentification avant les routes protégées
   - Vérifier les variables dynamiques (jwt_token, user_id)

3. **Pour les tests admin** :
   - Utiliser `{{working_jwt_token}}` directement
   - Vérifier les permissions via les réponses 403

### ⚡ Performance
- **Tests en parallèle** : Éviter pour les tests E2E (dépendances)
- **Cache token** : Réutiliser `{{jwt_token}}` entre requêtes
- **Timeouts** : Prévoir 10-15s pour les requêtes via RabbitMQ

### 🐛 Debug
- **Logs Docker** : `docker-compose logs -f api-gateway`
- **RabbitMQ UI** : Surveiller les queues en temps réel
- **Nginx logs** : `docker-compose logs nginx`

---

## 🎯 Validation Architecture Asynchrone

### ✅ Critères de Réussite

Cette collection permet de valider que :

1. **✅ Point d'entrée unique** : Toutes les requêtes passent par `/api/`
2. **✅ Routage API Gateway** : Aucun accès direct aux services
3. **✅ Communication RabbitMQ** : Messages publiés/consommés
4. **✅ JWT unifié** : Authentification cohérente tous services  
5. **✅ Workflow complet** : E-commerce fonctionnel de bout en bout

### 📊 Résultats Attendus

- **Health checks** : 200 OK sur tous les services
- **RabbitMQ connected** : `true` sur test connexion
- **E2E workflow** : Parcours client complet sans erreur
- **Admin operations** : Gestion back-office fonctionnelle

### 🎉 Objectif Atteint

La collection valide l'implémentation réussie de l'**architecture fully asynchrone** !

---

*Cette collection Postman est maintenue à jour avec l'évolution de l'architecture microservices asynchrone.* 🚀