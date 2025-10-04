# Service Passerelle API

## Presentation du Service

**Objectif** : Point d'entree unique pour toutes les requetes clientes. Route les requetes vers les microservices appropries via le courtier de messages RabbitMQ.

**Port** : 8100
**Base de donnees** : Aucune (service de routage sans etat)
**Dependances** : RabbitMQ, tous les microservices backend

**Modele architectural** : Passerelle API avec routage asynchrone base sur les messages

## Responsabilites

- Accepter les requetes HTTP des clients (Nginx les transmet a la passerelle)
- Router les requetes vers les services backend appropries via RabbitMQ
- Coordonner la communication service-a-service
- Fournir la decouverte de services et la surveillance de sante
- Gerer les points de terminaison d'authentification herites (retrocompatibilite)

## Points de Terminaison API

| Methode | Point de Terminaison | Description | Auth Requis | Requete/Reponse |
|---------|----------------------|-------------|-------------|-----------------|
| GET | /health | Verification sante passerelle | Non | {"status":"healthy","service":"api-gateway"} |
| GET | /simple-health | Sonde de sante textuelle | Non | "healthy" (text/plain) |
| GET | /test-rabbitmq | Test connexion RabbitMQ | Non | {"status":"success","rabbitmq_connected":true} |
| GET | /v1/services/status | Liste services disponibles | Non | {"services":[...]} |
| POST | /v1/login | Point terminaison login direct herite | Non | Reponse token JWT |
| ANY | /v1/{service}/{path} | Route vers microservice | Variable | Reponse specifique service |
| GET | /services/status | Statut services herite | Non | {"services":[...]} |
| POST | /login | Login herite (retrocompat) | Non | Reponse token JWT |
| ANY | /{service}/{path} | Modele routage herite | Variable | Reponse specifique service |

## Flux de Requete

1. Le client envoie une requete HTTP a Nginx (port 80/443)
2. Nginx transmet a la Passerelle API (port 8100)
3. La passerelle valide la requete et determine le service cible
4. La passerelle publie un message vers l'echange RabbitMQ
5. Le service cible consomme le message de sa file d'attente
6. Le service traite la requete et publie la reponse
7. La passerelle recoit la reponse et la retourne au client

## Integration RabbitMQ

**Configuration de Connexion** :
- Hote : conteneur rabbitmq
- Port : 5672
- Hote virtuel : /
- Identifiants : guest/guest

**Modele de Message** :
- La passerelle publie vers des echanges specifiques aux services
- Chaque service a une file d'attente dediee pour les requetes
- Modele reponse-replique pour comportement synchrone
- IDs de correlation pour le couplage requete-reponse

**Logique de Routage** (GatewayRouterService) :
```
/v1/auth/... -> echange auth-service
/v1/products/... -> echange products-service
/v1/baskets/... -> echange baskets-service
/v1/orders/... -> echange orders-service
/v1/addresses/... -> echange addresses-service
/v1/deliveries/... -> echange deliveries-service
/v1/newsletters/... -> echange newsletters-service
/v1/sav/... -> echange sav-service
/v1/contacts/... -> echange contacts-service
/v1/questions/... -> echange questions-service
/v1/websites/... -> echange websites-service
```

## Variables d'Environnement

```bash
# Application
APP_NAME=api-gateway
APP_ENV=local
APP_PORT=8100

# Configuration RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/

# URLs de Services (pour verification sante et decouverte)
AUTH_SERVICE_URL=http://auth-service:8000
PRODUCTS_SERVICE_URL=http://products-service:8001
BASKETS_SERVICE_URL=http://baskets-service:8002
ORDERS_SERVICE_URL=http://orders-service:8003
ADDRESSES_SERVICE_URL=http://addresses-service:8004
DELIVERIES_SERVICE_URL=http://deliveries-service:8005
NEWSLETTERS_SERVICE_URL=http://newsletters-service:8006
SAV_SERVICE_URL=http://sav-service:8007
CONTACTS_SERVICE_URL=http://contacts-service:8008
QUESTIONS_SERVICE_URL=http://questions-service:8009
WEBSITES_SERVICE_URL=http://websites-service:8010
MESSAGES_BROKER_URL=http://messages-broker:8011
```

## Deploiement

**Configuration Docker** :
```yaml
Service: api-gateway
Port Mapping: 8100:8000
Depends On: rabbitmq, messages-broker
Networks: e-commerce-network
Health Check: /health endpoint
```

**Ressources Kubernetes** :
- Deployment : 2 replicas minimum
- CPU Request : 100m, Limit : 200m
- Memory Request : 128Mi, Limit : 256Mi
- Service Type : ClusterIP
- Ingress : Route les chemins /api/ et /v1/

**Configuration Health Check** :
- Liveness Probe : GET /simple-health (intervalle 5s, 3 echecs)
- Readiness Probe : GET /health (intervalle 10s)
- Startup Probe : GET /health (timeout 30s)

## Composants Cles

**GatewayController** : Controleur de routage principal
- Recoit toutes les requetes entrantes
- Determine le service cible
- Delegue a RabbitMQClientService

**GatewayRouterService** : Decouverte de services et logique de routage
- Mappe les chemins URL vers les noms de services
- Maintient le registre de services
- Fournit la liste des services disponibles

**AuthController** : Point de terminaison authentification herite
- Support login direct (retrocompatibilite)
- Sera deprecie une fois clients migres vers /v1/auth/login

**RabbitMQClientService** (Partage) : Client courtier de messages
- Publie les requetes vers les files de services
- Gere la correlation des reponses
- Gestion de connexion et logique de reessai

## Considerations de Performance

- Conception sans etat permet mise a l'echelle horizontale
- RabbitMQ fournit equilibrage de charge naturel via files d'attente
- Timeout de reponse : 30 secondes (configurable)
- Pooling de connexions pour connexions RabbitMQ
- Pas de requetes base de donnees - logique de routage pure

## Surveillance et Observabilite

**Metriques a Suivre** :
- Taux de requetes par service
- Percentiles temps de reponse (p50, p95, p99)
- Sante connexion RabbitMQ
- Tentatives de routage echouees
- Statut disponibilite services

**Journalisation** :
- Toutes requetes journalisees avec en-tete X-Request-ID
- Decisions de routage de service
- Evenements connexion RabbitMQ
- Evenements erreur et timeout

## Ameliorations Futures

- Limitation et regulation du taux de requetes
- Modele disjoncteur pour services defaillants
- Mise en cache requete/reponse
- Support versioning API
- Support federation GraphQL
- Routage WebSocket pour fonctionnalites temps reel
