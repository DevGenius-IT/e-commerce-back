# Service Contacts

## Vue d'ensemble du service

**Objectif** : Systeme de gestion contacts pour marketing, CRM, et suivi engagement email.

**Port** : 8008
**Base de donnees** : contacts_db (MySQL 8.0)
**Port externe** : 3315 (pour le debogage)
**Dependances** : Service Auth

## Responsabilites

- Gestion base de donnees contacts (CRM)
- Organisation et segmentation listes contacts
- Etiquetage et categorisation contacts
- Gestion abonnements (newsletter, marketing)
- Suivi engagement email (ouvertures, clics)
- Operations contacts en masse
- Import/export contacts
- Point integration automatisation marketing

## Points de terminaison API

### Verification sante

| Methode | Point de terminaison | Description | Auth requise | Requete/Reponse |
|--------|----------|-------------|---------------|------------------|
| GET | /health | Verification sante du service | Non | {"status":"ok","service":"contacts-service"} |
| GET | /status | Verification statut basique | Non | {"status":"ok","database":"connected"} |

### Points de terminaison proteges (Auth requise)

**Gestion contacts** :

| Methode | Point de terminaison | Description | Requete/Reponse |
|--------|----------|-------------|------------------|
| GET | /contacts | Lister tous contacts | Liste paginee contacts avec filtres |
| POST | /contacts | Creer contact | {"email","first_name","last_name"} -> Contact cree |
| GET | /contacts/{id} | Obtenir details contact | Contact complet avec historique engagement |
| PUT | /contacts/{id} | Mettre a jour contact | Contact mis a jour |
| DELETE | /contacts/{id} | Supprimer contact | Message de succes |
| POST | /contacts/{id}/subscribe | Abonner contact | {"type":"newsletter"} -> Contact mis a jour |
| POST | /contacts/{id}/unsubscribe | Desabonner contact | {"type":"newsletter"} -> Contact mis a jour |
| POST | /contacts/{id}/engagement | Enregistrer engagement | {"type":"opened","campaign_id"} -> Succes |
| POST | /contacts/bulk-action | Operations en masse | {"action","contact_ids",[]} -> Resultats |

**Gestion listes contacts** :

| Methode | Point de terminaison | Description | Requete/Reponse |
|--------|----------|-------------|------------------|
| GET | /lists | Lister listes contacts | [{"id","name","contact_count"}] |
| POST | /lists | Creer liste | {"name","description"} -> Liste creee |
| GET | /lists/{id} | Obtenir details liste | Liste avec contacts |
| PUT | /lists/{id} | Mettre a jour liste | Liste mise a jour |
| DELETE | /lists/{id} | Supprimer liste | Message de succes |
| POST | /lists/{id}/contacts | Ajouter contacts a liste | {"contact_ids":[]} -> Succes |
| DELETE | /lists/{id}/contacts | Retirer contacts | {"contact_ids":[]} -> Succes |
| POST | /lists/{id}/sync | Synchroniser contacts | {"contact_ids":[]} -> Liste synchronisee |
| POST | /lists/{id}/duplicate | Dupliquer liste | Liste dupliquee |
| GET | /lists/{id}/stats | Statistiques liste | Stats engagement et croissance |
| GET | /lists/{id}/export | Exporter contacts | Telechargement CSV/Excel |

**Gestion etiquettes contacts** :

| Methode | Point de terminaison | Description | Requete/Reponse |
|--------|----------|-------------|------------------|
| GET | /tags | Lister toutes etiquettes | [{"id","name","color","contact_count"}] |
| POST | /tags | Creer etiquette | {"name","color"} -> Etiquette creee |
| GET | /tags/popular | Obtenir etiquettes populaires | Etiquettes les plus utilisees |
| GET | /tags/{id} | Obtenir details etiquette | Etiquette avec contacts |
| PUT | /tags/{id} | Mettre a jour etiquette | Etiquette mise a jour |
| DELETE | /tags/{id} | Supprimer etiquette | Message de succes |
| GET | /tags/{id}/contacts | Contacts avec etiquette | Liste contacts |
| POST | /tags/{id}/apply | Appliquer etiquette | {"contact_ids":[]} -> Succes |
| DELETE | /tags/{id}/remove | Retirer etiquette | {"contact_ids":[]} -> Succes |
| POST | /tags/{id}/merge | Fusionner etiquettes | {"target_tag_id"} -> Etiquette fusionnee |
| GET | /tags/{id}/stats | Statistiques etiquette | Stats usage et engagement |

### Points de terminaison publics (Sans Auth)

| Methode | Point de terminaison | Description | Requete/Reponse |
|--------|----------|-------------|------------------|
| POST | /public/subscribe | Abonnement public | {"email","first_name","newsletter":true} -> Succes |
| POST | /public/unsubscribe | Desabonnement public | {"email","type":"all"} -> Succes |
| POST | /public/track/{contact}/opened | Suivre ouverture email | Point terminaison pixel suivi -> Succes |
| POST | /public/track/{contact}/clicked | Suivre clic lien | Suivi clic -> Succes |

## Schema de base de donnees

**Tables** :

1. **contacts** - Enregistrements contacts
   - id (PK)
   - email (unique)
   - first_name
   - last_name
   - company (nullable)
   - phone (nullable)
   - language (default: en)
   - timezone (nullable)
   - source (string - provenance contact)
   - status (enum: active, unsubscribed, bounced, complained)
   - newsletter_subscribed (boolean)
   - marketing_subscribed (boolean)
   - subscribed_at (timestamp, nullable)
   - unsubscribed_at (timestamp, nullable)
   - last_email_opened_at (timestamp, nullable)
   - last_email_clicked_at (timestamp, nullable)
   - total_emails_opened (integer, default 0)
   - total_emails_clicked (integer, default 0)
   - engagement_score (integer, nullable - calcule)
   - custom_fields (JSON)
   - timestamps, soft_deletes

2. **contact_lists** - Listes segmentees
   - id (PK)
   - name
   - description (text, nullable)
   - type (enum: static, dynamic)
   - criteria (JSON, nullable - pour listes dynamiques)
   - contact_count (integer, default 0)
   - is_active (boolean)
   - timestamps

3. **contact_list_contacts** - Appartenance liste (plusieurs-a-plusieurs)
   - id (PK)
   - contact_list_id (FK)
   - contact_id (FK)
   - added_at (timestamp)
   - added_by (FK vers users, nullable)

4. **contact_tags** - Etiquettes pour categorisation
   - id (PK)
   - name (unique)
   - slug (unique)
   - color (code couleur hex)
   - description (text, nullable)
   - usage_count (integer, default 0)
   - timestamps

**Tables de jonction** :
- **contact_tag** - Mapping Contact vers Etiquette (plusieurs-a-plusieurs)
  - contact_id (FK)
  - contact_tag_id (FK)
  - tagged_at (timestamp)

**Relations** :
- Contact -> Lists (plusieurs-a-plusieurs via contact_list_contacts)
- Contact -> Tags (plusieurs-a-plusieurs via contact_tag)
- ContactList -> Contacts (plusieurs-a-plusieurs via contact_list_contacts)
- ContactTag -> Contacts (plusieurs-a-plusieurs via contact_tag)

## Suivi engagement contacts

**Metriques engagement** :
- Ouvertures email (pixel suivi)
- Clics liens (URLs enveloppees)
- Comptage engagement total
- Date dernier engagement
- Score engagement (calcule)

**Calcul score engagement** :
```
Score = (ouvertures * 1) + (clics * 3) + (bonus_activite_recente)
- 0-10: Faible engagement
- 11-50: Engagement moyen
- 51+: Fort engagement
```

**Evenements engagement** :
- Email ouvert
- Lien clique
- Formulaire soumis
- Achat effectue (depuis service commandes)

## Types de listes

**Listes statiques** :
- Gerees manuellement
- Contacts ajoutes/retires explicitement
- Utilisees pour campagnes specifiques

**Listes dynamiques** (Futur) :
- Mise a jour automatique selon criteres
- Appartenance basee sur regles
- Exemples : "Achats 30 derniers jours", "Score engagement eleve"

## Gestion abonnements

**Types abonnement** :
- Newsletter : Newsletters regulieres
- Marketing : Emails promotionnels
- Transactionnel : Confirmations commande (toujours active)

**Options desabonnement** :
- Se desabonner d'un type specifique
- Se desabonner de tout marketing
- Mettre a jour preferences

## Integration RabbitMQ

**Evenements consommes** :
- `user.registered` - Creer contact depuis inscription utilisateur
- `order.completed` - Mettre a jour contact avec donnees achat
- `newsletter.subscribed` - Synchroniser abonnement newsletter
- `email.sent` - Enregistrer email envoye au contact

**Evenements publies** :
- `contact.created` - Nouveau contact ajoute
- `contact.updated` - Informations contact modifiees
- `contact.subscribed` - Statut abonnement modifie
- `contact.unsubscribed` - Evenement desabonnement
- `contact.engagement` - Evenement engagement email
- `list.contact.added` - Contact ajoute a liste
- `tag.applied` - Etiquette appliquee au contact

**Exemple de format de message** :
```json
{
  "event": "contact.subscribed",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "contact_id": 123,
    "email": "user@example.com",
    "subscription_type": "newsletter",
    "source": "website_footer",
    "double_optin": true
  }
}
```

## Variables d'environnement

```bash
# Application
APP_NAME=contacts-service
APP_ENV=local
APP_PORT=8008

# Base de donnees
DB_CONNECTION=mysql
DB_HOST=contacts-mysql
DB_PORT=3306
DB_DATABASE=contacts_db
DB_USERNAME=contacts_user
DB_PASSWORD=contacts_password

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=contacts_exchange
RABBITMQ_QUEUE=contacts_queue

# URLs de services
AUTH_SERVICE_URL=http://auth-service:8000

# Configuration contacts
ENGAGEMENT_SCORE_OPEN_WEIGHT=1
ENGAGEMENT_SCORE_CLICK_WEIGHT=3
ENGAGEMENT_SCORE_PURCHASE_WEIGHT=10
```

## Deploiement

**Configuration Docker** :
```yaml
Service: contacts-service
Port Mapping: 8008:8000
Database: contacts-mysql (port 3315 externe)
Depends On: contacts-mysql, rabbitmq
Networks: e-commerce-network
Health Check: point de terminaison /health
```

**Ressources Kubernetes** :
- Deployment: 2 replicas
- CPU Request: 100m, Limit: 300m
- Memory Request: 256Mi, Limit: 512Mi
- Service Type: ClusterIP

**Configuration verification sante** :
- Liveness Probe: GET /health (intervalle 10s)
- Readiness Probe: Connectivite base de donnees
- Startup Probe: timeout 30s

## Optimisation des performances

**Strategie de cache** :
- Listes contacts en cache (TTL 5 min)
- Listes etiquettes en cache (TTL 10 min)
- Etiquettes populaires en cache (TTL 30 min)
- Details contacts en cache (TTL 2 min)

**Optimisation base de donnees** :
- Index sur: email, status, engagement_score
- Recherche full-text sur name et company
- Index composes pour requetes appartenance liste
- Partitionnement par created_at pour grands ensembles donnees

**Operations en masse** :
- Traitement par lot pour actions en masse
- Traitement asynchrone base sur files d'attente
- Suivi progression pour operations longues

**Taches planifiees** :
- Recalcul scores engagement (quotidien)
- Nettoyage contacts inactifs (mensuel)
- Synchronisation listes dynamiques (toutes les heures, futur)
- Mise a jour statistiques listes (toutes les heures)

## Securite et conformite

**Conformite vie privee** :
- Droit RGPD a l'oubli
- Exportation donnees pour utilisateurs
- Gestion consentement
- Lien desabonnement dans tous emails
- Support double opt-in

**Controle acces** :
- Permissions basees sur roles
- Operations en masse admin uniquement
- Journal audit pour operations sensibles

## Surveillance et observabilite

**Metriques a suivre** :
- Total contacts
- Taux abonnement
- Taux desabonnement
- Score engagement moyen
- Taux croissance listes
- Distribution usage etiquettes
- Performance operations en masse

**Journalisation** :
- Operations CRUD contacts
- Changements abonnements
- Operations en masse
- Evenements engagement
- Operations import/export

## Ameliorations futures

- Segmentation avancee (listes dynamiques)
- Notation contacts et qualification leads
- Detection et fusion contacts dupliques
- Etapes cycle vie contacts
- Workflows automatisation marketing
- Constructeur champs personnalises
- Integrations API (Salesforce, HubSpot)
- Profilage progressif
- Analyses predictives
- Services enrichissement contacts
