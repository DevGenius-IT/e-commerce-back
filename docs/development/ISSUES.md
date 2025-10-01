# 🐛 Issues de Développement - E-commerce Microservices

**Généré le**: 2025-09-10
**Basé sur**: TEST_REPORT.md et PROJECT_ROADMAP.md

---

# 🔥 Issues Critiques - À Corriger Immédiatement

## #001 - Infrastructure Docker Non Fonctionnelle
- **Priorité**: 🚨 URGENT
- **Type**: `infrastructure`
- **Effort**: 15 minutes
- **Assigné**: DevOps/Infrastructure

### Problème
Docker daemon arrêté, application complètement inaccessible. Erreur: `Cannot connect to the Docker daemon at unix:///Users/kbrdn1/.orbstack/run/docker.sock`

### Solution
1. Démarrer Docker/OrbStack
2. Vérifier la connectivité
3. Tester `docker-compose ps`

### Acceptance Criteria
- [ ] Docker daemon démarré et fonctionnel
- [ ] `docker-compose ps` affiche les services
- [ ] Pas d'erreurs de connectivité

---

## #002 - Variables d'Environnement Manquantes
- **Priorité**: 🚨 URGENT  
- **Type**: `configuration`
- **Effort**: 10 minutes
- **Assigné**: Backend Developer

### Problème
Variables de configuration manquantes dans `.env` causant des warnings Docker Compose:
```
DB_MESSAGES_BROKER_HOST (non défini)
DB_MESSAGES_BROKER_PORT (non défini)
DB_ADDRESSES_HOST (non défini)  
DB_ADDRESSES_PORT (non défini)
```

### Solution
Ajouter les variables manquantes dans `.env`:
```bash
DB_MESSAGES_BROKER_HOST=messages-broker-db
DB_MESSAGES_BROKER_PORT=3306
DB_ADDRESSES_HOST=addresses-db  
DB_ADDRESSES_PORT=3306
```

### Acceptance Criteria
- [ ] Variables ajoutées dans .env
- [ ] Plus de warnings Docker Compose
- [ ] Services peuvent se connecter aux bases de données

---

## #003 - Incohérences de Configuration des Ports
- **Priorité**: 🔴 HIGH
- **Type**: `configuration`
- **Effort**: 30 minutes
- **Assigné**: DevOps

### Problème
Incohérences entre docker-compose.yml, .env, et nginx.conf:
- Messages-broker: Docker (9000) ≠ .env (8002)
- Nginx upstream vs ports réels

### Solution
Harmoniser la configuration des ports dans tous les fichiers

### Acceptance Criteria
- [ ] Ports cohérents entre docker-compose.yml et .env
- [ ] Nginx upstream correctement configuré
- [ ] Services accessibles via les bons ports

---

# ⚡ Issues Haute Priorité - Services Incomplets

## #004 - API Gateway Incomplet
- **Priorité**: 🔴 HIGH
- **Type**: `feature`
- **Effort**: 4-6 heures
- **Sprint**: Sprint 1.1
- **Assigné**: Backend Developer

### Problème
L'API Gateway n'implémente pas sa fonction principale de routage vers les microservices:
- Logique de gateway incomplète
- Middleware d'authentification commenté
- Pas de routing automatique vers services
- Routes products commentées

### Solution
1. Implémenter le routing automatique vers tous les services
2. Activer et configurer les middlewares d'authentification
3. Intégrer avec auth-service pour validation JWT
4. Ajouter les routes pour tous les services actifs

### Tasks
- [ ] Créer le middleware de routing automatique
- [ ] Implémenter la validation JWT via auth-service
- [ ] Décommenter et configurer les routes products
- [ ] Ajouter routes pour addresses-service et messages-broker
- [ ] Tests d'intégration du gateway

### Acceptance Criteria
- [ ] Routes `/api/*` routées vers api-gateway
- [ ] Routes `/auth/*` routées vers auth-service
- [ ] Routes `/addresses/*` routées vers addresses-service
- [ ] Routes `/messages-broker/*` routées vers messages-broker
- [ ] Authentification JWT fonctionnelle
- [ ] Health check du gateway

---

## #005 - Addresses Service Non Implémenté
- **Priorité**: 🔴 HIGH
- **Type**: `feature`
- **Effort**: 1-2 jours
- **Sprint**: Sprint 1.2
- **Assigné**: Backend Developer

### Problème
Service addresses-service a seulement la structure Laravel sans implémentation:
- Pas de routes API définies
- Pas de contrôleurs implémentés
- Pas de migrations
- Pas de models

### Solution
Implémenter complètement le service de gestion des adresses

### Tasks
- [ ] Créer les migrations (addresses, countries, regions)
- [ ] Créer les models avec relations
- [ ] Implémenter les contrôleurs CRUD
- [ ] Définir les routes API REST
- [ ] Ajouter la validation des requêtes
- [ ] Intégration avec shared library
- [ ] Documentation API

### Acceptance Criteria
- [ ] CRUD complet des adresses
- [ ] Validation et géolocalisation des adresses
- [ ] API de recherche d'adresses
- [ ] Tests unitaires et d'intégration
- [ ] Documentation Swagger/OpenAPI

---

# 🚀 Issues Nouvelles Features - Services Core E-commerce

## #006 - Products Service - Catalogue Produits
- **Priorité**: 🟡 MEDIUM-HIGH
- **Type**: `epic` / `feature`
- **Effort**: 2-3 semaines
- **Sprint**: Sprint 2.1-2.2
- **Assigné**: Backend Team

### Problème
Service crucial pour e-commerce non implémenté. Priority #1 des fonctionnalités business.

### Solution
Créer un service complet de gestion du catalogue produits

### Epic Tasks
- [ ] **Setup Infrastructure**
  - [ ] Créer Laravel app complète
  - [ ] Configuration Docker et base de données
  - [ ] Integration dans docker-compose.yml
  
- [ ] **Database Design**
  - [ ] Migration products (name, description, price, sku, stock, etc.)
  - [ ] Migration categories (hiérarchique)
  - [ ] Migration product_images
  - [ ] Migration product_categories (many-to-many)
  
- [ ] **Models & Relationships**
  - [ ] Model Product avec relations
  - [ ] Model Category avec tree structure
  - [ ] Model ProductImage
  
- [ ] **API Implementation**
  - [ ] ProductController CRUD complet
  - [ ] CategoryController CRUD hiérarchique
  - [ ] API de recherche et filtres
  - [ ] API de gestion des images
  
- [ ] **Business Logic**
  - [ ] Service layer pour logique métier
  - [ ] Système d'inventaire basique
  - [ ] Validation prix et stock
  
- [ ] **Integration**
  - [ ] Intégration Messages Broker
  - [ ] Events pour changements de stock
  - [ ] API Gateway routing

### Acceptance Criteria
- [ ] CRUD complet des produits
- [ ] Gestion hiérarchique des catégories  
- [ ] Upload et gestion des images produits
- [ ] API de recherche avec filtres (prix, catégorie, stock)
- [ ] Système d'inventaire avec tracking
- [ ] Events émis lors des changements de stock
- [ ] Tests complets (unit + integration)
- [ ] Documentation API complète

---

## #007 - Baskets Service - Panier d'Achat
- **Priorité**: 🟡 MEDIUM-HIGH
- **Type**: `feature`
- **Effort**: 1-2 semaines
- **Sprint**: Sprint 2.3
- **Assigné**: Backend Developer

### Problème
Service de panier d'achat non implémenté, essentiel pour le workflow e-commerce.

### Tasks
- [ ] Setup Laravel app complet
- [ ] Models: Cart, CartItem
- [ ] API gestion panier (add, remove, update, clear)
- [ ] Calcul automatique des totaux et taxes
- [ ] Persistance panier (session/DB)
- [ ] Validation disponibilité produits
- [ ] Intégration avec Products Service

### Acceptance Criteria
- [ ] Ajout/suppression articles du panier
- [ ] Mise à jour quantités
- [ ] Calcul totaux avec taxes
- [ ] Validation stock en temps réel
- [ ] Persistance multi-sessions

---

## #008 - Orders Service - Gestion des Commandes
- **Priorité**: 🟡 MEDIUM
- **Type**: `feature`
- **Effort**: 2-3 semaines
- **Sprint**: Sprint 3.1-3.2
- **Assigné**: Backend Team

### Problème
Service de commandes non implémenté, nécessaire pour compléter le workflow e-commerce.

### Epic Tasks
- [ ] **Setup & Database**
  - [ ] Setup Laravel app
  - [ ] Models: Order, OrderItem, OrderStatus
  - [ ] Migrations avec relations complètes
  
- [ ] **Workflow Implementation**
  - [ ] États de commande (pending, processing, shipped, delivered, cancelled)
  - [ ] State machine pour transitions d'états
  - [ ] Validation des transitions
  
- [ ] **API & Business Logic**
  - [ ] API création commande depuis panier
  - [ ] API gestion des états
  - [ ] Historique des commandes
  - [ ] API de reporting basique
  
- [ ] **Integrations**
  - [ ] Intégration avec Baskets Service
  - [ ] Intégration avec Products Service (stock)
  - [ ] Events via Messages Broker

### Acceptance Criteria
- [ ] Création commande depuis panier
- [ ] Workflow complet des états
- [ ] Historique et tracking commandes
- [ ] Reporting des ventes
- [ ] Intégration complète avec autres services

---

# 🛠️ Issues Techniques & Infrastructure

## #009 - Tests Automatisés Manquants
- **Priorité**: 🟡 MEDIUM
- **Type**: `testing`
- **Effort**: 1 semaine
- **Sprint**: Sprint 1.3
- **Assigné**: QA/Backend

### Problème
Aucun test PHPUnit implémenté dans les services existants.

### Tasks
- [ ] Setup PHPUnit pour auth-service
- [ ] Setup PHPUnit pour messages-broker
- [ ] Setup PHPUnit pour api-gateway
- [ ] Tests d'intégration inter-services
- [ ] Setup CI/CD avec tests automatiques

### Acceptance Criteria
- [ ] Coverage > 80% pour services critiques
- [ ] Tests d'intégration fonctionnels
- [ ] CI/CD bloque les PR si tests échouent

---

## #010 - Documentation API Manquante
- **Priorité**: 🟡 MEDIUM
- **Type**: `documentation`
- **Effort**: 3-4 jours
- **Sprint**: Sprint 1.4
- **Assigné**: Technical Writer/Backend

### Problème
Pas de documentation API accessible (Swagger/OpenAPI).

### Tasks
- [ ] Setup Swagger/OpenAPI pour tous les services
- [ ] Documenter auth-service API
- [ ] Documenter messages-broker API
- [ ] Documenter api-gateway routes
- [ ] Interface unified documentation

### Acceptance Criteria
- [ ] Documentation accessible via /docs endpoints
- [ ] Tous les endpoints documentés
- [ ] Exemples de requêtes/réponses
- [ ] Documentation auto-générée depuis annotations

---

## #011 - Monitoring et Logs Centralisés
- **Priorité**: 🟡 MEDIUM
- **Type**: `infrastructure`
- **Effort**: 1 semaine
- **Sprint**: Sprint 2.4
- **Assigné**: DevOps

### Problème
Pas de monitoring ni logs centralisés pour debug et performance.

### Tasks
- [ ] Setup logs centralisés (ELK stack ou équivalent)
- [ ] Health checks pour tous les services
- [ ] Métriques de performance
- [ ] Alerting basique
- [ ] Dashboard monitoring

### Acceptance Criteria
- [ ] Logs de tous les services centralisés
- [ ] Health checks endpoints pour tous services
- [ ] Dashboard temps réel
- [ ] Alerting en cas de problème

---

# 📦 Issues Futures - Services Optionnels

## #012 - Deliveries Service
- **Priorité**: 🟢 LOW-MEDIUM
- **Type**: `feature`
- **Effort**: 2-3 semaines
- **Sprint**: Sprint 4.1
- **Assigné**: Backend Team

### Problème
Service de gestion des livraisons non implémenté.

### Tasks
- [ ] Setup Laravel app
- [ ] Models: Delivery, Carrier, TrackingEvent
- [ ] API gestion des transporteurs
- [ ] Calcul des frais de port
- [ ] Suivi des colis avec APIs externes
- [ ] Notifications automatiques

---

## #013 - Newsletters Service
- **Priorité**: 🟢 LOW
- **Type**: `feature`
- **Effort**: 1-2 semaines
- **Sprint**: Sprint 4.2
- **Assigné**: Backend Developer

### Tasks
- [ ] Setup Laravel app
- [ ] Models: Newsletter, Subscription, Campaign
- [ ] Gestion des abonnements
- [ ] Templates d'emails
- [ ] Système de campagnes

---

## #014 - SAV Service
- **Priorité**: 🟢 LOW-MEDIUM
- **Type**: `feature`
- **Effort**: 2-3 semaines
- **Sprint**: Sprint 4.3-4.4
- **Assigné**: Backend Team

### Tasks
- [ ] Setup Laravel app
- [ ] Models: Ticket, Return, FAQ
- [ ] Système de tickets complet
- [ ] Gestion des retours produits
- [ ] Base de connaissances

---

## #015 - Contacts Service
- **Priorité**: 🟢 LOW
- **Type**: `feature`
- **Effort**: 1 semaine
- **Sprint**: Sprint 4.5
- **Assigné**: Backend Developer

### Tasks
- [ ] Setup Laravel app
- [ ] Models: Contact, ContactRequest
- [ ] Formulaire de contact
- [ ] Gestion des demandes

---

## #016 - Websites Service
- **Priorité**: 🟢 LOW
- **Type**: `feature`
- **Effort**: 1-2 semaines
- **Sprint**: Sprint 4.6
- **Assigné**: Backend Developer

### Tasks
- [ ] Setup Laravel app
- [ ] Models: Setting, Page, Menu
- [ ] Configuration globale du site
- [ ] Gestion contenu statique

---

# 📋 Planification des Sprints

## Sprint 1 - Infrastructure & Core (2-3 semaines)
- **Focus**: Rendre l'application fonctionnelle
- **Issues**: #001, #002, #003, #004, #005, #009

## Sprint 2 - E-commerce Core (3-4 semaines)  
- **Focus**: Products Service + Baskets
- **Issues**: #006, #007, #010, #011

## Sprint 3 - Workflow E-commerce (2-3 semaines)
- **Focus**: Orders Service + Intégrations
- **Issues**: #008

## Sprint 4 - Services Optionnels (4-6 semaines)
- **Focus**: Deliveries, SAV, autres services
- **Issues**: #012, #013, #014, #015, #016

---

# 🎯 Prochaines Actions

## Cette Semaine
1. **Corriger les issues critiques** (#001, #002, #003)
2. **Démarrer API Gateway** (#004)
3. **Planifier Products Service** (#006)

## Ce Mois
1. **Finaliser l'infrastructure** (Sprint 1)
2. **Commencer Products Service** (Sprint 2)
3. **Setup tests et documentation**

---

# 📞 Support & Ressources

- **Documentation**: Voir CLAUDE.md pour les commandes
- **Architecture**: Voir PROJECT_ROADMAP.md pour la vision
- **Tests**: Voir TEST_REPORT.md pour l'état actuel

Pour toute question ou clarification sur ces issues, référencer les documents de support du projet.