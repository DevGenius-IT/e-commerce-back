# Service Courtier Messages

## Vue d'ensemble du service

**Objectif** : Service de coordination RabbitMQ et gestion files d'attente pour communication inter-services.

**Port** : 8011
**Base de donnees** : messages_broker_db (MySQL 8.0, pour suivi messages echoues)
**Port externe** : 3318 (pour le debogage)
**Dependances** : RabbitMQ (dependance principale)

## Responsabilites

- Gestion et coordination connexions RabbitMQ
- Orchestration publication et consommation messages
- Suivi messages echoues et logique reessai
- Surveillance sante files d'attente
- Gestion file d'attente lettres mortes (DLQ)
- Coordination routage messages
- Statistiques et analyses files d'attente

## Points de terminaison API

### Verification sante

| Methode | Point de terminaison | Description | Auth requise | Requete/Reponse |
|--------|----------|-------------|---------------|------------------|
| GET | /api/health | Verification sante du service | Non | {"status":"healthy","service":"messages-broker"} |

### Gestion messages

| Methode | Point de terminaison | Description | Auth requise | Requete/Reponse |
|--------|----------|-------------|---------------|------------------|
| POST | /api/messages/publish | Publier message vers file d'attente | Oui | {"exchange","routing_key","message"} -> Succes |
| GET | /api/messages/failed | Lister messages echoues | Oui | Liste paginee messages echoues |
| POST | /api/messages/retry/{id} | Reessayer message echoue | Oui | Resultat reessai |
| DELETE | /api/messages/failed/{id} | Supprimer message echoue | Oui | Message de succes |

### Gestion files d'attente

| Methode | Point de terminaison | Description | Auth requise | Requete/Reponse |
|--------|----------|-------------|---------------|------------------|
| GET | /api/queues | Lister toutes files d'attente | Oui | [{"name","message_count","consumer_count"}] |
| GET | /api/queues/{queue}/stats | Statistiques file d'attente | Oui | Metriques et statistiques file d'attente |
| POST | /api/queues/{queue}/purge | Purger file d'attente | Oui | Message de succes (utiliser avec precaution) |

## Schema de base de donnees

**Tables** :

1. **failed_messages** - Suivi messages echoues
   - id (PK)
   - exchange
   - routing_key
   - payload (JSON)
   - error_message (text)
   - retry_count (integer, default 0)
   - max_retries (integer, default 3)
   - status (enum: pending, retrying, failed, resolved)
   - failed_at (timestamp)
   - last_retry_at (timestamp, nullable)
   - resolved_at (timestamp, nullable)
   - timestamps

2. **message_logs** - Piste audit messages (optionnel)
   - id (PK)
   - message_id (unique)
   - exchange
   - routing_key
   - payload (JSON)
   - status (enum: published, delivered, failed)
   - published_at (timestamp)
   - delivered_at (timestamp, nullable)
   - metadata (JSON)
   - timestamps

## Architecture RabbitMQ

**Types exchanges** :
- **Direct Exchange** : Routage point-a-point
- **Topic Exchange** : Routage base sur motifs
- **Fanout Exchange** : Diffusion vers toutes files d'attente

**Exchanges et files d'attente services** :

```
auth_exchange -> auth_queue
products_exchange -> products_queue
baskets_exchange -> baskets_queue
orders_exchange -> orders_queue
addresses_exchange -> addresses_queue
deliveries_exchange -> deliveries_queue
newsletters_exchange -> newsletters_queue
sav_exchange -> sav_queue
contacts_exchange -> contacts_queue
questions_exchange -> questions_queue
websites_exchange -> websites_queue
```

**Dead Letter Exchange (DLX)** :
- Messages echoues routes vers DLQ
- Logique reessai avec backoff exponentiel
- Intervention manuelle pour echecs persistants

## Coordination flux messages

**Flux publication** :
1. Service publie message via RabbitMQClientService
2. Message route vers exchange approprie
3. Exchange route vers file(s) d'attente cible
4. Consommateurs traitent messages
5. Acquittement ou rejet

**Gestion echecs** :
1. Traitement message echoue
2. Message rejete (nack)
3. Route vers file d'attente lettres mortes
4. Enregistre dans table failed_messages
5. Tentatives reessai avec backoff
6. Resolution manuelle si reessais max depasses

## Motifs messages

**Motif requete-reponse** :
```
Gateway -> Publish(request) -> Service Queue
Service -> Process -> Publish(response) -> Gateway Queue
Gateway -> Consume(response) -> Return to client
```

**Diffusion evenements** :
```
Service A -> Publish(event) -> Fanout Exchange
  -> Service B Queue
  -> Service C Queue
  -> Service D Queue
```

**Motif workflow** :
```
Order Created -> Inventory Check -> Payment Processing -> Shipping Creation
```

## Configuration files d'attente

**Parametres files d'attente standard** :
- Durable: true (persistance redemarrages RabbitMQ)
- Auto-delete: false
- Message TTL: 24 heures (configurable)
- Max length: 10000 messages (configurable)
- Comportement debordement: reject-publish

**Parametres file d'attente lettres mortes** :
- TTL: 7 jours
- Max reessais: 3
- Delai reessai: Backoff exponentiel (1min, 5min, 30min)

## Surveillance et gestion

**Metriques files d'attente** :
- Comptage messages par file d'attente
- Comptage consommateurs
- Taux messages (publication/livraison)
- Messages non acquittes
- Comptage messages echoues

**Indicateurs sante** :
- Statut connexion RabbitMQ
- Alertes profondeur file d'attente (>80% capacite)
- Retard consommateur
- Taux messages echoues

## Variables d'environnement

```bash
# Application
APP_NAME=messages-broker
APP_ENV=local
APP_PORT=8011

# Base de donnees
DB_CONNECTION=mysql
DB_HOST=messages-broker-mysql
DB_PORT=3306
DB_DATABASE=messages_broker_db
DB_USERNAME=messages_broker_user
DB_PASSWORD=messages_broker_password

# Configuration RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_MANAGEMENT_PORT=15672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/

# Configuration messages
MESSAGE_TTL=86400000
MESSAGE_MAX_RETRIES=3
MESSAGE_RETRY_DELAY=60000
DLQ_TTL=604800000
```

## Deploiement

**Configuration Docker** :
```yaml
Service: messages-broker
Port Mapping: 8011:8000
Database: messages-broker-mysql (port 3318 externe)
Depends On: messages-broker-mysql, rabbitmq
Networks: e-commerce-network
Health Check: point de terminaison /api/health
```

**Ressources Kubernetes** :
- Deployment: 2 replicas
- CPU Request: 100m, Limit: 300m
- Memory Request: 256Mi, Limit: 512Mi
- Service Type: ClusterIP
- ConfigMap: Configuration RabbitMQ

**Configuration verification sante** :
- Liveness Probe: GET /api/health (intervalle 10s)
- Readiness Probe: Connectivite RabbitMQ
- Startup Probe: timeout 30s

## Gestion RabbitMQ

**Interface gestion** :
- URL: http://localhost:15672
- Identifiants: guest/guest
- Fonctionnalites: Surveillance files d'attente, tracage messages, gestion connexions

**Gestion CLI** (via docker exec) :
```bash
docker exec rabbitmq rabbitmqctl list_queues
docker exec rabbitmq rabbitmqctl list_exchanges
docker exec rabbitmq rabbitmqctl list_bindings
```

## Optimisation des performances

**Pooling connexions** :
- Reutilisation connexions entre services
- Pooling canaux pour publication
- Recuperation connexion en cas echec

**Batching messages** :
- Publication par lot quand possible
- Acquittements par lot
- Optimisation comptage prefetch

**Optimisation consommateur** :
- Consommateurs paralleles par file d'attente
- Ajustement limite prefetch
- Auto-scaling consommateur base sur profondeur file d'attente

## Recuperation echecs

**Recuperation automatique** :
- Recuperation connexion avec reessai
- Recuperation canal en cas erreurs
- Recreation consommateur en cas echec

**Intervention manuelle** :
- Tableau de bord messages echoues
- Declenchement reessai manuel
- Outils inspection messages
- Purge files d'attente (urgence)

## Considerations securite

**Controle acces** :
- Permissions utilisateur RabbitMQ par service
- Permissions exchanges et files d'attente
- Chiffrement TLS pour connexions (production)
- Restriction acces interface gestion

**Securite messages** :
- Validation messages
- Limites taille payload
- Limitation debit sur publication
- Detection messages empoisonnes

## Surveillance et observabilite

**Metriques a suivre** :
- Debit messages (pub/sub)
- Profondeur file d'attente par file d'attente
- Retard consommateur
- Taux messages echoues
- Comptage connexions
- Utilisation memoire

**Journalisation** :
- Evenements publication messages
- Evenements consommation
- Messages echoues
- Erreurs connexion
- Tentatives reessai

**Alertes** :
- Profondeur file d'attente depasse seuil
- Alerte retard consommateur
- Pic messages echoues
- Echecs connexion
- Pression memoire

## Ameliorations futures

- Capacite rejeu messages
- Middleware transformation messages
- Validation schema pour messages
- Support versionnage messages
- Regles routage avancees
- Chiffrement messages
- Files d'attente priorite
- Livraison messages differee
- Integration tracage distribue
- Chemin migration Kafka (evolution future)

## Directives integration

**Pour developpeurs services** :

1. **Utiliser client RabbitMQ partage** :
   - `Shared\Services\RabbitMQClientService`
   - Gestion connexion prise en charge
   - Logique reessai incluse

2. **Format message** :
```php
[
    'event' => 'event.name',
    'timestamp' => now()->toISOString(),
    'data' => [
        // Donnees specifiques evenement
    ],
    'metadata' => [
        'service' => 'service-name',
        'version' => '1.0'
    ]
]
```

3. **Gestion erreurs** :
   - Toujours ack/nack messages
   - Journaliser echecs
   - Ne pas bloquer consommateurs
   - Implementer traitement idempotent

4. **Tests** :
   - Utiliser files d'attente test
   - Simuler RabbitMQ dans tests unitaires
   - Tests integration avec vraies files d'attente

## Depannage

**Problemes courants** :

1. **Messages non consommes** :
   - Verifier consommateur en cours execution
   - Verifier bindings files d'attente
   - Verifier format message

2. **Profondeur file d'attente elevee** :
   - Dimensionner consommateurs
   - Verifier performance consommateur
   - Investiguer messages bloques

3. **Accumulation messages echoues** :
   - Verifier journaux erreurs
   - Verifier format message
   - Revoir logique consommateur

4. **Echecs connexion** :
   - Verifier statut RabbitMQ
   - Verifier connectivite reseau
   - Revoir identifiants
