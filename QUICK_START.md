# 🚀 Guide de démarrage rapide - E-commerce Microservices

Ce guide vous permet de démarrer rapidement l'ensemble de la plateforme e-commerce microservices.

## 🏃‍♂️ Démarrage en 30 secondes

```bash
# Installation complète (première fois)
make install

# Ou démarrage simple
make start
```

## 📋 Services disponibles

| Service | Description | Port | URL |
|---------|-------------|------|-----|
| **api-gateway** | Point d'entrée principal | 80 | http://localhost |
| **auth-service** | Authentification JWT | 8001 | http://localhost/api/auth |
| **addresses-service** | Gestion des adresses | 8009 | http://localhost/api/addresses |
| **products-service** | Catalogue produits | 8003 | http://localhost/api/products |
| **messages-broker** | Communication inter-services | 8002 | - |
| **RabbitMQ** | Queue de messages | 15672 | http://localhost:15672 |

## 🎯 Commandes essentielles

```bash
# Gestion du projet
make install        # Installation complète (première fois)
make start          # Démarrer tous les services
make stop           # Arrêter tous les services
make status         # Voir le statut des services
make health         # Vérifier la santé des services
make logs           # Voir les logs en temps réel

# Développement
make dev            # Mode développement avec surveillance
make shell SERVICE=auth-service    # Accès shell à un service
make test-all       # Exécuter tous les tests

# Base de données
make fresh-all      # Réinitialiser toutes les BDD avec données

# Aide
make help           # Voir toutes les commandes disponibles
```

## 🔧 Test rapide

Une fois le projet démarré :

```bash
# Vérifier que tout fonctionne
make health

# Tester l'API
curl http://localhost/api/products/health
curl http://localhost/api/products/featured
curl http://localhost/api/addresses/countries
```

## 🛠️ Dépannage rapide

```bash
# Si quelque chose ne fonctionne pas
make status         # Voir les services arrêtés
make logs          # Voir les erreurs
make restart       # Redémarrer tout
make clean && make install  # Réinstallation complète
```

## 📊 API Examples

### Catalogue produits
```bash
GET http://localhost/api/products              # Tous les produits
GET http://localhost/api/products/featured     # Produits vedettes
GET http://localhost/api/categories            # Catégories
GET http://localhost/api/products/search?q=iphone
```

### Adresses
```bash
GET http://localhost/api/addresses/countries          # Pays
GET http://localhost/api/addresses/countries/1/regions # Régions
```

### Authentification
```bash
POST http://localhost/api/auth/login
Content-Type: application/json
{
  "email": "user@example.com",
  "password": "password"
}
```

## 🎉 C'est parti !

Votre plateforme e-commerce microservices est maintenant prête. 

- **Interface RabbitMQ** : http://localhost:15672 (admin/admin)
- **API Gateway** : http://localhost
- **Documentation complète** : voir README.md et CLAUDE.md

Bon développement ! 🚀