# Service Sites Web

## Vue d'ensemble du service

**Objectif** : Configuration multi-sites et gestion locataires pour supporter plusieurs sites e-commerce depuis une seule plateforme.

**Port** : 8010
**Base de donnees** : websites_db (MySQL 8.0)
**Port externe** : 3317 (pour le debogage)
**Dependances** : Service Auth (pour operations admin)

## Responsabilites

- Gestion configuration sites web/locataires
- Support multi-sites (ex: differentes marques, regions)
- Parametres specifiques sites web
- Gestion domaines
- Configuration themes et branding
- Parametres locale et devise
- Analyses et metriques sites web

## Points de terminaison API

### Verification sante

| Methode | Point de terminaison | Description | Auth requise | Requete/Reponse |
|--------|----------|-------------|---------------|------------------|
| GET | /health | Verification sante du service | Non | {"status":"ok","service":"websites-service"} |

### Points de terminaison publics

| Methode | Point de terminaison | Description | Auth requise | Requete/Reponse |
|--------|----------|-------------|---------------|------------------|
| GET | /websites | Lister sites web actifs | Non | [{"id","name","domain","locale"}] |
| GET | /websites/search | Rechercher sites web | Non | Requete: q -> Sites web correspondants |
| GET | /websites/{id} | Obtenir details site web | Non | Configuration complete site web |

### Points de terminaison proteges (Auth requise)

| Methode | Point de terminaison | Description | Requete/Reponse |
|--------|----------|-------------|------------------|
| POST | /websites | Creer site web | Donnees site web -> Site web cree |
| PUT | /websites/{id} | Mettre a jour site web | Site web mis a jour |
| DELETE | /websites/{id} | Supprimer site web | Message de succes |

## Schema de base de donnees

**Tables** :

1. **websites** - Configurations sites web/locataires
   - id (PK)
   - name
   - slug (unique)
   - domain (unique - ex: shop.example.com)
   - description (text, nullable)
   - logo_url (nullable)
   - favicon_url (nullable)
   - status (enum: active, inactive, maintenance)
   - locale (default: en_US)
   - timezone (default: UTC)
   - currency (default: USD)
   - theme (string, nullable - identifiant theme)
   - settings (JSON - configuration specifique site web)
   - contact_email
   - support_email (nullable)
   - phone (nullable)
   - address (JSON, nullable)
   - social_media (JSON - liens vers profils sociaux)
   - analytics_id (string, nullable - Google Analytics, etc.)
   - is_default (boolean)
   - created_by (FK vers users, nullable)
   - timestamps, soft_deletes

**Structure JSON settings** :
```json
{
  "features": {
    "enable_wishlist": true,
    "enable_reviews": true,
    "enable_chat": false
  },
  "checkout": {
    "guest_checkout": true,
    "require_phone": true
  },
  "shipping": {
    "free_shipping_threshold": 50.00,
    "allow_pickup": true
  },
  "payment": {
    "methods": ["credit_card", "paypal", "bank_transfer"]
  },
  "branding": {
    "primary_color": "#007bff",
    "secondary_color": "#6c757d",
    "font_family": "Roboto"
  }
}
```

## Architecture multi-locataires

**Cas d'usage** :
- Plusieurs marques sous une plateforme
- Sites web regionaux (US, EU, Asie)
- Solutions marque blanche pour partenaires
- Environnements test et staging

**Isolation locataires** :
- Donnees segreguees par website_id
- Catalogue produits partage (optionnel)
- Bases utilisateurs independantes (optionnel)
- Analyses et rapports separes

## Configuration site web

**Parametres principaux** :
- Informations basiques (nom, domaine, description)
- Branding (logo, favicon, couleurs, polices)
- Localisation (langue, fuseau horaire, devise)
- Informations contact
- Toggles fonctionnalites

**Feature flags** :
- Activer/desactiver fonctionnalites par site web
- Support tests A/B
- Capacite deploiement progressif

## Integration RabbitMQ

**Evenements consommes** :
- `user.activity` - Suivre activite par site web
- `order.completed` - Analyses specifiques site web

**Evenements publies** :
- `website.created` - Nouveau site web configure
- `website.updated` - Configuration modifiee
- `website.status.changed` - Mise a jour statut (actif, maintenance, etc.)

**Exemple de format de message** :
```json
{
  "event": "website.created",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "website_id": 123,
    "name": "Ma Boutique En Ligne",
    "domain": "shop.example.com",
    "locale": "en_US",
    "currency": "USD"
  }
}
```

## Variables d'environnement

```bash
# Application
APP_NAME=websites-service
APP_ENV=local
APP_PORT=8010

# Base de donnees
DB_CONNECTION=mysql
DB_HOST=websites-mysql
DB_PORT=3306
DB_DATABASE=websites_db
DB_USERNAME=websites_user
DB_PASSWORD=websites_password

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=websites_exchange
RABBITMQ_QUEUE=websites_queue

# URLs de services
AUTH_SERVICE_URL=http://auth-service:8000
```

## Deploiement

**Configuration Docker** :
```yaml
Service: websites-service
Port Mapping: 8010:8000
Database: websites-mysql (port 3317 externe)
Depends On: websites-mysql, rabbitmq
Networks: e-commerce-network
Health Check: point de terminaison /health
```

**Ressources Kubernetes** :
- Deployment: 2 replicas
- CPU Request: 50m, Limit: 150m
- Memory Request: 128Mi, Limit: 256Mi
- Service Type: ClusterIP

**Configuration verification sante** :
- Liveness Probe: GET /health (intervalle 10s)
- Readiness Probe: Connectivite base de donnees
- Startup Probe: timeout 30s

## Optimisation des performances

**Strategie de cache** :
- Configurations sites web en cache (TTL 30 min)
- Liste sites web actifs en cache (TTL 15 min)
- Recherche site web par domaine en cache (TTL 30 min)

**Optimisation base de donnees** :
- Index sur: slug, domain, status
- Configurations frequemment accedees en cache

## Surveillance et observabilite

**Metriques a suivre** :
- Total sites web actifs
- Sites web par statut
- Changements configuration
- Problemes resolution domaine

**Journalisation** :
- Operations CRUD sites web
- Changements configuration
- Transitions statut

## Ameliorations futures

- Support multi-domaines par site web
- Marketplace themes
- Modeles sites web
- Provisionnement automatise sites web
- Surveillance performance sites web
- Gestion configuration SEO
- Gestion SSL domaines personnalises
