# Service Questions

## Vue d'ensemble du service

**Objectif** : Systeme de gestion FAQ (Foire Aux Questions) et Q&R.

**Port** : 8009
**Base de donnees** : questions_db (MySQL 8.0)
**Port externe** : 3316 (pour le debogage)
**Dependances** : Service Auth (pour operations protegees)

## Responsabilites

- Gestion questions et reponses FAQ
- Categorisation questions
- Acces FAQ public
- Fonctionnalite recherche questions
- Versionnage reponses
- Analyses questions (vues, utilite)
- Support multi-langues (futur)

## Points de terminaison API

### Verification sante

| Methode | Point de terminaison | Description | Auth requise | Requete/Reponse |
|--------|----------|-------------|---------------|------------------|
| GET | /health | Verification sante du service | Non | {"status":"ok","service":"questions-service"} |
| GET | /status | Verification statut basique | Non | {"status":"ok","database":"connected"} |

### Points de terminaison proteges (Auth requise)

| Methode | Point de terminaison | Description | Requete/Reponse |
|--------|----------|-------------|------------------|
| GET | /questions | Lister questions | Liste paginee questions |
| POST | /questions | Creer question | {"title","body","category"} -> Question creee |
| GET | /questions/{id} | Obtenir details question | Question complete avec reponses |
| PUT | /questions/{id} | Mettre a jour question | Question mise a jour |
| DELETE | /questions/{id} | Supprimer question | Message de succes |
| GET | /questions/{id}/answers | Lister reponses | Liste reponses pour question |
| POST | /questions/{id}/answers | Creer reponse | {"body"} -> Reponse creee |
| GET | /questions/{id}/answers/{answerId} | Obtenir details reponse | Objet reponse |
| PUT | /questions/{id}/answers/{answerId} | Mettre a jour reponse | Reponse mise a jour |
| DELETE | /questions/{id}/answers/{answerId} | Supprimer reponse | Message de succes |

### Points de terminaison publics (Sans Auth)

| Methode | Point de terminaison | Description | Requete/Reponse |
|--------|----------|-------------|------------------|
| GET | /public/questions | Liste FAQ publique | Questions publiees uniquement |
| GET | /public/questions/{id} | Vue question publique | Question avec reponses approuvees |
| GET | /public/questions/{id}/answers | Reponses publiques | Reponses approuvees uniquement |
| GET | /public/questions/{id}/answers/{answerId} | Vue reponse publique | Details reponse |
| GET | /public/search | Rechercher FAQ | {"q":"terme recherche"} -> Questions correspondantes |

## Schema de base de donnees

**Tables** :

1. **questions** - Questions FAQ
   - id (PK)
   - user_id (FK, nullable - createur)
   - title
   - slug (unique)
   - body (text)
   - category (string, nullable - General, Produit, Livraison, Paiement, etc.)
   - tags (JSON)
   - status (enum: draft, published, archived)
   - view_count (integer, default 0)
   - helpful_count (integer, default 0)
   - not_helpful_count (integer, default 0)
   - order (integer - ordre affichage)
   - is_featured (boolean)
   - published_at (timestamp, nullable)
   - timestamps, soft_deletes

2. **answers** - Reponses questions
   - id (PK)
   - question_id (FK)
   - user_id (FK, nullable - auteur)
   - body (text)
   - status (enum: draft, published, archived)
   - is_accepted (boolean - marquee comme meilleure reponse)
   - helpful_count (integer, default 0)
   - not_helpful_count (integer, default 0)
   - order (integer - ordre affichage)
   - published_at (timestamp, nullable)
   - timestamps, soft_deletes

**Relations** :
- Question -> User (appartient a, nullable)
- Question -> Answers (a plusieurs)
- Answer -> Question (appartient a)
- Answer -> User (appartient a, nullable)

## Categories questions

**Categories par defaut** :
- General : Demandes generales
- Products : Questions liees produits
- Shipping : Livraison et expedition
- Payment : Paiement et facturation
- Returns : Retours et remboursements
- Account : Gestion compte
- Technical : Support technique

## Fonctionnalite recherche

**Fonctionnalites recherche** :
- Recherche full-text sur titre et corps
- Filtrage categorie
- Filtrage etiquettes
- Filtrage statut (pour admins)
- Tri par pertinence, date, vues, utilite

**Exemple requete recherche** :
```
GET /public/search?q=shipping&category=Shipping
```

## Integration RabbitMQ

**Evenements consommes** :
- `product.issue` - Auto-generer FAQ depuis problemes courants
- `support.ticket.resolved` - Suggerer creation FAQ depuis ticket

**Evenements publies** :
- `question.created` - Nouvelle question ajoutee
- `question.published` - Question rendue publique
- `answer.published` - Nouvelle reponse publiee
- `question.viewed` - Question vue (pour analyses)

**Exemple de format de message** :
```json
{
  "event": "question.published",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "question_id": 123,
    "title": "Combien de temps prend la livraison ?",
    "category": "Shipping",
    "tags": ["delivery", "timeframe"]
  }
}
```

## Variables d'environnement

```bash
# Application
APP_NAME=questions-service
APP_ENV=local
APP_PORT=8009

# Base de donnees
DB_CONNECTION=mysql
DB_HOST=questions-mysql
DB_PORT=3306
DB_DATABASE=questions_db
DB_USERNAME=questions_user
DB_PASSWORD=questions_password

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=questions_exchange
RABBITMQ_QUEUE=questions_queue

# URLs de services
AUTH_SERVICE_URL=http://auth-service:8000
```

## Deploiement

**Configuration Docker** :
```yaml
Service: questions-service
Port Mapping: 8009:8000
Database: questions-mysql (port 3316 externe)
Depends On: questions-mysql, rabbitmq
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
- Questions publiees en cache (TTL 10 min)
- Categories FAQ en cache (TTL 30 min)
- Resultats recherche en cache par requete (TTL 5 min)
- Questions populaires en cache (TTL 15 min)

**Optimisation base de donnees** :
- Index sur: slug, category, status, is_featured
- Index recherche full-text sur titre et corps
- Index composes pour requetes filtrage

## Surveillance et observabilite

**Metriques a suivre** :
- Total questions
- Questions par categorie
- Comptage vues moyen
- Performance requetes recherche
- Evaluations utilite

**Journalisation** :
- Operations CRUD questions/reponses
- Requetes recherche
- Suivi vues
- Votes utilite

## Ameliorations futures

- Support multi-langues
- Editeur texte riche pour reponses
- Systeme votes utilisateurs
- Suggestions questions liees
- Workflow approbation reponses
- Import/export FAQ
- Tableau de bord analyses
- Suggestions reponses alimentees IA
