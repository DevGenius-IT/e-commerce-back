# 🚀 E-Commerce API - Guide Kubernetes Quick Start

Guide rapide pour utiliser la collection Postman avec Kubernetes en local.

## 📦 Fichiers

- **Collection** : `E-Commerce-API-Complete.postman_collection.json`
- **Environnement** : `E-Commerce-Local-Environment.postman_environment.json`

## ⚡ Setup Rapide (5 minutes)

### 1. Démarrer Kubernetes

```bash
# Vérifier que les pods sont running
kubectl get pods -n e-commerce

# Si non démarrés
kubectl apply -k k8s/overlays/development
```

### 2. Port-Forward vers API Gateway

```bash
kubectl port-forward -n e-commerce service/api-gateway 8100:80
```

> ⚠️ **Important** : Laisser cette commande tourner dans un terminal séparé

### 3. Importer dans Postman

1. **Import Collection** :
   - File → Import
   - Sélectionner `E-Commerce-API-Complete.postman_collection.json`

2. **Import Environment** :
   - File → Import
   - Sélectionner `E-Commerce-Local-Environment.postman_environment.json`

3. **Activer l'environnement** :
   - Menu déroulant en haut à droite
   - Sélectionner **"E-Commerce - Local Development"**

### 4. Premier Test

1. Aller dans **Health & Status → API Gateway Health**
2. Cliquer sur **Send**
3. ✅ Vous devriez voir : `{"status":"healthy","service":"api-gateway"}`

### 5. Login

1. Aller dans **Authentication → Login**
2. Cliquer sur **Send**
3. ✅ Le token est automatiquement sauvegardé

**Credentials** :
```json
{
  "email": "admin@example.com",
  "password": "password"
}
```

## 🎯 Endpoints Principaux

### Health Check
```
GET {{base_url}}/api/health
```

### Authentication
```
POST {{base_url}}/api/v1/login
Body: {"email": "admin@example.com", "password": "password"}
```

### Products
```
GET {{base_url}}/api/products/products
Headers: Authorization: Bearer {{auth_token}}
```

### Baskets
```
GET {{base_url}}/api/baskets/baskets
Headers: Authorization: Bearer {{auth_token}}
```

### Orders
```
GET {{base_url}}/api/orders/orders
Headers: Authorization: Bearer {{auth_token}}
```

## 🔧 Variables Automatiques

Après le login, ces variables sont automatiquement remplies :

| Variable | Description |
|----------|-------------|
| `{{auth_token}}` | Token JWT pour l'authentification |
| `{{user_id}}` | ID de l'utilisateur connecté |
| `{{user_email}}` | Email de l'utilisateur |

Toutes les requêtes protégées utilisent automatiquement `{{auth_token}}`.

## 🐛 Dépannage

### Port-forward not working
```bash
# Tuer les anciens port-forwards
pkill -f "port-forward.*api-gateway"

# Redémarrer
kubectl port-forward -n e-commerce service/api-gateway 8100:80
```

### Connection refused
```bash
# Vérifier que le service existe
kubectl get svc -n e-commerce api-gateway

# Vérifier que les pods sont running
kubectl get pods -n e-commerce -l app=api-gateway
```

### 401 Unauthorized
1. Re-exécuter **Authentication → Login**
2. Le token est automatiquement mis à jour

### 500 Server Error
```bash
# Vérifier les logs
kubectl logs -n e-commerce -l app=api-gateway --tail=50
kubectl logs -n e-commerce -l app=auth-service --tail=50
```

## 📚 Structure de la Collection

```
📁 E-Commerce API Complete
├── 📊 Health & Status
│   ├── API Gateway Health
│   ├── Services Status
│   └── Test RabbitMQ
├── 🔐 Authentication
│   ├── Login ⭐ (commence ici)
│   ├── Validate Token
│   ├── Refresh Token
│   ├── Logout
│   └── Get Current User
├── 🛍️ Products
│   ├── List Products
│   ├── Get Product
│   ├── Create Product
│   ├── Update Product
│   └── Delete Product
├── 🛒 Baskets
│   ├── Get My Basket
│   ├── Add Item
│   ├── Update Item
│   └── Remove Item
├── 📦 Orders
│   ├── List My Orders
│   ├── Get Order
│   ├── Create Order
│   └── Process Payment
├── 📍 Addresses
├── 🚚 Deliveries
├── 📧 Newsletters
├── 📞 Contacts
├── 🎫 SAV
└── ❓ Questions
```

## ✅ Checklist de Test

- [ ] Health check fonctionne
- [ ] Login réussit et sauvegarde le token
- [ ] Liste des produits accessible
- [ ] Création de panier fonctionne
- [ ] Création de commande fonctionne

## 🎓 Parcours Recommandé

### Parcours Découverte (10 min)
1. **Health & Status → API Gateway Health**
2. **Authentication → Login**
3. **Products → List Products**
4. **Baskets → Get My Basket**

### Parcours Complet (20 min)
1. **Authentication → Login**
2. **Products → List Products**
3. **Baskets → Add Item**
4. **Baskets → Get My Basket**
5. **Addresses → Create Address**
6. **Orders → Create Order**
7. **Orders → List My Orders**

---

**Base URL** : `http://localhost:8100`
**Auth** : Automatique via token JWT
**Format** : Toutes les requêtes/réponses en JSON
