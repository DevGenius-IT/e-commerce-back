# SAV Service - Service Après-Vente

## Vue d'ensemble

Le service SAV (Service Après-Vente) est un microservice Laravel dédié à la gestion des tickets de support client, des messages associés et des pièces jointes. Il fait partie de l'architecture microservices e-commerce et communique via RabbitMQ avec les autres services.

## Fonctionnalités

### 🎫 Gestion des Tickets de Support
- **Création de tickets** avec numéro unique automatique
- **Catégorisation** et **priorisation** des demandes
- **Assignation** aux agents de support
- **Suivi du cycle de vie** : ouvert → en cours → résolu → fermé
- **Recherche et filtrage** avancés
- **Statistiques** en temps réel

### 💬 Système de Messages
- **Messages clients/agents** avec historique complet
- **Messages internes** pour coordination équipe
- **Statut de lecture** et compteurs non lus
- **Réponses en temps réel**

### 📎 Gestion des Pièces Jointes
- **Upload de fichiers** (images, documents)
- **Validation de type** et taille
- **Stockage sécurisé** avec accès contrôlé
- **Upload multiple** en une fois

### 🔔 Notifications Intelligentes
- **Notifications temps réel** via RabbitMQ
- **Emails automatiques** pour les clients
- **Alertes agents** pour nouveaux tickets/messages
- **Intégration** avec autres services (auth, orders)

## Architecture Technique

### Base de Données
```
support_tickets
├── id (PK)
├── ticket_number (unique)
├── user_id
├── subject
├── description
├── priority (low/medium/high/urgent)
├── status (open/in_progress/waiting_customer/resolved/closed)
├── category
├── assigned_to
├── order_id (nullable)
├── metadata (JSON)
├── resolved_at
├── closed_at
└── timestamps

ticket_messages
├── id (PK)
├── ticket_id (FK)
├── sender_id
├── sender_type (customer/agent)
├── message
├── is_internal
├── read_at
└── timestamps

ticket_attachments
├── id (PK)
├── ticket_id (FK)
├── message_id (FK, nullable)
├── original_name
├── filename
├── file_path
├── mime_type
├── file_size
└── timestamps
```

### API Endpoints

#### 🎫 Tickets
```
GET    /api/tickets                 # Liste des tickets (avec filtres)
POST   /api/tickets                 # Créer un ticket
GET    /api/tickets/{id}            # Détails d'un ticket
PUT    /api/tickets/{id}            # Mettre à jour un ticket
DELETE /api/tickets/{id}            # Supprimer un ticket
POST   /api/tickets/{id}/assign     # Assigner un ticket
POST   /api/tickets/{id}/resolve    # Marquer comme résolu
POST   /api/tickets/{id}/close      # Fermer un ticket
GET    /api/tickets/statistics      # Statistiques globales
```

#### 💬 Messages
```
GET    /api/tickets/{id}/messages                 # Messages d'un ticket
POST   /api/tickets/{id}/messages                 # Ajouter un message
GET    /api/tickets/{id}/messages/{msgId}         # Détail message
PUT    /api/tickets/{id}/messages/{msgId}         # Modifier message
DELETE /api/tickets/{id}/messages/{msgId}         # Supprimer message
POST   /api/tickets/{id}/messages/{msgId}/mark-read    # Marquer lu
POST   /api/tickets/{id}/messages/mark-all-read        # Tout marquer lu
GET    /api/tickets/{id}/messages/unread-count         # Compte non lus
```

#### 📎 Pièces Jointes
```
GET    /api/tickets/{id}/attachments              # Liste des PJ
POST   /api/tickets/{id}/attachments              # Upload PJ
POST   /api/tickets/{id}/attachments/multiple     # Upload multiple
GET    /api/tickets/{id}/attachments/{attId}      # Détail PJ
GET    /api/tickets/{id}/attachments/{attId}/download  # Télécharger
DELETE /api/tickets/{id}/attachments/{attId}      # Supprimer PJ
GET    /api/tickets/{id}/attachments/message/{msgId}   # PJ par message
```

#### 🌍 Public (sans auth)
```
POST   /api/public/tickets                        # Création ticket public
GET    /api/public/tickets/{ticketNumber}         # Consultation publique
POST   /api/public/tickets/{id}/messages          # Réponse client
```

### Filtres et Recherche

#### Filtres Disponibles
- `status` : Filtrer par statut
- `priority` : Filtrer par priorité
- `user_id` : Tickets d'un utilisateur
- `assigned_to` : Tickets assignés à un agent
- `category` : Filtrer par catégorie
- `search` : Recherche texte (sujet/description/numéro)

#### Tri et Pagination
- `sort_by` : Champ de tri (défaut: created_at)
- `sort_direction` : Direction (asc/desc, défaut: desc)
- `per_page` : Éléments par page (défaut: 15)

### Notifications RabbitMQ

#### Events Émis
```
sav.ticket.created      # Nouveau ticket créé
sav.ticket.updated      # Ticket modifié
sav.ticket.assigned     # Ticket assigné
sav.ticket.resolved     # Ticket résolu
sav.ticket.closed       # Ticket fermé
sav.message.added       # Nouveau message
```

#### Intégrations
- **Auth Service** : Récupération infos utilisateur
- **Orders Service** : Liens avec commandes
- **Email Service** : Notifications email
- **Messages Broker** : Communication inter-services

## Installation et Configuration

### 1. Variables d'Environnement
```env
# Base de données
DB_SAV_HOST=sav-db
DB_SAV_DATABASE=sav_service_db
DB_SAV_USERNAME=root
DB_SAV_PASSWORD=root

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_EXCHANGE=sav_exchange
RABBITMQ_QUEUE=sav_queue

# Services
AUTH_SERVICE_URL=http://auth-service:8001
ORDERS_SERVICE_URL=http://orders-service:8006
```

### 2. Installation
```bash
# Migration et seeders
docker-compose exec sav-service php artisan migrate
docker-compose exec sav-service php artisan db:seed

# Tests
docker-compose exec sav-service php artisan test
```

### 3. Démarrage
```bash
# Via Docker Compose
docker-compose up sav-service

# Service accessible sur le port 8008
curl http://localhost/v1/sav/health
```

## Utilisation

### Exemple: Créer un Ticket
```bash
curl -X POST http://localhost/v1/sav/tickets \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "user_id": 1,
    "subject": "Problème de livraison",
    "description": "Ma commande n'\''est pas arrivée",
    "priority": "medium",
    "category": "Delivery Issue",
    "order_id": 123
  }'
```

### Exemple: Ajouter un Message
```bash
curl -X POST http://localhost/v1/sav/tickets/1/messages \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "sender_id": 2,
    "sender_type": "agent",
    "message": "Nous vérifions le statut de votre commande.",
    "is_internal": false
  }'
```

### Exemple: Upload de Fichier
```bash
curl -X POST http://localhost/v1/sav/tickets/1/attachments \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "file=@receipt.pdf"
```

## Tests

### Tests Unitaires
```bash
# Tous les tests
php artisan test

# Tests spécifiques
php artisan test --filter SupportTicketTest
php artisan test --filter TicketMessageTest
```

### Coverage des Tests
- ✅ CRUD complet tickets
- ✅ Gestion messages
- ✅ Statuts et transitions
- ✅ Validation des données
- ✅ Gestion d'erreurs
- ✅ Filtres et recherche
- ✅ Statistiques

## Monitoring et Logs

### Métriques Importantes
- Nombre de tickets par statut
- Temps de réponse moyen
- Tickets non assignés
- Messages non lus
- Taux de résolution

### Logs
```bash
# Logs du service
docker-compose logs -f sav-service

# Logs spécifiques notifications
tail -f storage/logs/laravel.log | grep "SAV"
```

## Sécurité

### Authentification
- JWT tokens requis pour toutes les opérations protégées
- Validation des permissions via middleware partagé
- Routes publiques limitées pour création tickets clients

### Validation
- Validation stricte des entrées
- Sanitisation des fichiers uploadés
- Contrôle des types MIME autorisés
- Limites de taille de fichiers (10MB max)

### Stockage
- Fichiers stockés hors web root
- Accès contrôlé par l'API uniquement
- Nettoyage automatique à la suppression

## Performance

### Optimisations
- Index sur colonnes fréquemment recherchées
- Pagination automatique des résultats
- Cache des statistiques (à implémenter)
- Lazy loading des relations

### Limites
- Upload: 10MB par fichier, 5 fichiers simultanés
- Messages: pagination par 50 max
- Recherche: optimisée pour < 100K tickets

## Évolutions Futures

### 🚀 Roadmap
- [ ] **Templates de réponses** pré-définies
- [ ] **SLA tracking** avec alertes
- [ ] **Escalation automatique** par priorité
- [ ] **Intégration chat** temps réel
- [ ] **Analytics avancés** et reporting
- [ ] **API webhooks** pour intégrations externes
- [ ] **Import/Export** tickets en masse
- [ ] **Satisfaction client** avec ratings

### 🔧 Améliorations Techniques
- [ ] **Cache Redis** pour performances
- [ ] **Elasticsearch** pour recherche avancée  
- [ ] **File queues** pour traitement async
- [ ] **Rate limiting** par utilisateur
- [ ] **Audit trail** complet
- [ ] **Multi-tenancy** support

## Support et Maintenance

### Contacts
- **Équipe Dev**: dev-team@company.com
- **Documentation**: [Wiki interne]
- **Issues**: GitHub Issues

### Versions
- **v1.0.0**: Version initiale avec fonctionnalités de base
- **Compatibilité**: Laravel 12.x, PHP 8.3+, MySQL 8.0+