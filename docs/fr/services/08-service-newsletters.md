# Service Newsletters

## Vue d'ensemble du service

**Objectif** : Gestion des abonnements aux newsletters par email et execution des campagnes avec stockage MinIO pour les modeles d'email.

**Port** : 8006
**Base de donnees** : newsletters_db (MySQL 8.0)
**Port externe** : 3313 (pour le debogage)
**Dependances** : Service Auth, MinIO (stockage de modeles d'email - bucket: newsletters)

## Responsabilites

- Gestion des abonnements aux newsletters
- Creation et execution de campagnes email
- Planification et envoi de campagnes
- Gestion des modeles d'email (stockage MinIO)
- Gestion des listes d'abonnes
- Analyses et suivi des campagnes
- Webhooks de livraison d'email
- Gestion des desabonnements

## Points de terminaison API

### Points de terminaison publics

| Methode | Point de terminaison | Description | Auth requise | Requete/Reponse |
|--------|----------|-------------|---------------|------------------|
| GET | /health | Verification sante du service | Non | {"status":"healthy","service":"newsletters-service"} |
| POST | /newsletters/subscribe | S'abonner a la newsletter | Non | {"email","first_name","last_name"} -> Succes |
| GET | /newsletters/confirm/{token} | Confirmer l'abonnement | Non | Succes de confirmation |
| GET | /newsletters/unsubscribe/{token} | Se desabonner via email | Non | Succes de desabonnement |
| POST | /newsletters/unsubscribe/{token} | Desabonnement POST | Non | Succes de desabonnement |

### Points de terminaison proteges (Auth requise)

| Methode | Point de terminaison | Description | Requete/Reponse |
|--------|----------|-------------|------------------|
| GET | /newsletters | Lister les newsletters | Liste paginee d'abonnes |
| GET | /newsletters/stats | Statistiques d'abonnement | Donnees tableau de bord |
| POST | /newsletters/bulk-import | Importation en masse d'abonnes | Fichier CSV/Excel -> Resultats d'import |
| GET | /newsletters/{id} | Obtenir details newsletter | Objet newsletter |
| PUT | /newsletters/{id} | Mettre a jour newsletter | Newsletter mise a jour |
| DELETE | /newsletters/{id} | Supprimer newsletter | Message de succes |
| GET | /campaigns | Lister les campagnes | [{"id","name","status","stats"}] |
| POST | /campaigns | Creer campagne | Donnees campagne -> Campagne creee |
| GET | /campaigns/{id} | Obtenir details campagne | Objet campagne complet |
| PUT | /campaigns/{id} | Mettre a jour campagne | Campagne mise a jour |
| DELETE | /campaigns/{id} | Supprimer campagne | Message de succes |
| POST | /campaigns/{id}/schedule | Planifier campagne | {"send_at"} -> Campagne planifiee |
| POST | /campaigns/{id}/send | Envoyer campagne maintenant | Statut d'envoi campagne |
| POST | /campaigns/{id}/cancel | Annuler campagne planifiee | Campagne annulee |
| POST | /campaigns/{id}/test-send | Envoyer email de test | {"email"} -> Test envoye |
| POST | /campaigns/{id}/duplicate | Dupliquer campagne | Campagne dupliquee |
| GET | /campaigns/{id}/stats | Statistiques campagne | Ouvertures, clics, rebonds, etc. |
| GET | /campaigns/{id}/analytics | Analyses campagne | Donnees analytiques detaillees |

### Points de terminaison Admin (Auth requise + Role Admin/Gestionnaire Newsletter)

| Methode | Point de terminaison | Description | Requete/Reponse |
|--------|----------|-------------|------------------|
| GET | /admin/system-stats | Statistiques systeme | Sante globale du systeme |
| GET | /admin/export/newsletters | Exporter donnees newsletter | Telechargement CSV/Excel |
| GET | /admin/export/campaigns | Exporter donnees campagnes | Telechargement CSV/Excel |

### Points de terminaison Webhook (Sans Auth - Securise IP/Secret)

| Methode | Point de terminaison | Description | Requete/Reponse |
|--------|----------|-------------|------------------|
| POST | /webhooks/email-delivered | Webhook livraison email | Acquittement de succes |
| POST | /webhooks/email-opened | Suivi ouverture email | Acquittement de succes |
| POST | /webhooks/email-clicked | Suivi clic lien | Acquittement de succes |
| POST | /webhooks/email-bounced | Notification rebond | Acquittement de succes |
| POST | /webhooks/email-complained | Plainte spam | Acquittement de succes |

## Schema de base de donnees

**Tables** :

1. **newsletters** - Abonnes newsletter
   - id (PK)
   - email (unique)
   - first_name
   - last_name
   - language (default: en)
   - status (enum: subscribed, unsubscribed, bounced, complained)
   - subscribed_at (timestamp)
   - unsubscribed_at (timestamp, nullable)
   - confirmation_token (unique)
   - confirmed_at (timestamp, nullable)
   - source (string - formulaire inscription, import, etc.)
   - tags (JSON)
   - custom_fields (JSON)
   - timestamps

2. **campaigns** - Campagnes email
   - id (PK)
   - name
   - subject
   - preview_text
   - from_name
   - from_email
   - reply_to (nullable)
   - template_id (FK, nullable)
   - html_content (text)
   - plain_text_content (text, nullable)
   - status (enum: draft, scheduled, sending, sent, cancelled)
   - scheduled_at (timestamp, nullable)
   - sent_at (timestamp, nullable)
   - total_recipients (integer)
   - total_sent (integer)
   - total_delivered (integer)
   - total_opened (integer)
   - total_clicked (integer)
   - total_bounced (integer)
   - total_complained (integer)
   - settings (JSON - suivi, lien desabonnement, etc.)
   - timestamps

3. **newsletter_campaigns** - Mapping Campagne-Abonne (plusieurs-a-plusieurs)
   - id (PK)
   - newsletter_id (FK)
   - campaign_id (FK)
   - sent_at (timestamp, nullable)
   - delivered_at (timestamp, nullable)
   - opened_at (timestamp, nullable)
   - clicked_at (timestamp, nullable)
   - bounced_at (timestamp, nullable)
   - bounce_reason (text, nullable)
   - unsubscribed_at (timestamp, nullable)
   - timestamps

4. **email_templates** - Modeles d'email reutilisables
   - id (PK)
   - name
   - description
   - template_url (chemin MinIO)
   - thumbnail_url (chemin MinIO, nullable)
   - category
   - is_active (boolean)
   - timestamps

**Relations** :
- Campaign -> Template (appartient a, nullable)
- Campaign -> Newsletters (plusieurs-a-plusieurs via newsletter_campaigns)
- Newsletter -> Campaigns (plusieurs-a-plusieurs via newsletter_campaigns)

## Flux d'abonnement newsletter

1. L'utilisateur soumet son email via formulaire public
2. Enregistrement d'abonnement cree avec statut "pending"
3. Email de confirmation envoye avec token unique
4. L'utilisateur clique sur le lien de confirmation
5. Statut mis a jour vers "subscribed"
6. Email de bienvenue envoye (optionnel)

## Cycle de vie de campagne

**Etats de campagne** :
- **draft** : En cours de creation/edition
- **scheduled** : Planifiee pour envoi futur
- **sending** : En cours d'envoi
- **sent** : Completee
- **cancelled** : Annulee avant envoi

**Processus d'envoi** :
1. Valider la campagne (contenu, destinataires)
2. Recuperer les abonnes actifs
3. Mettre en file d'attente les emails pour envoi (traitement par lot)
4. Suivre livraison, ouvertures, clics via webhooks
5. Mettre a jour les statistiques de campagne
6. Marquer la campagne comme "sent"

## Integration MinIO

**Bucket** : newsletters
**Stockage de modeles** :
```
newsletters/
  templates/
    campaign_123/
      template.html
      assets/
        image_1.jpg
        logo.png
    campaign_124/
      template.html
```

**Fonctionnalites de modeles** :
- Modeles HTML email avec CSS inline
- Placeholders de variables ({{first_name}}, {{unsubscribe_url}})
- Hebergement d'images dans MinIO
- Versionnage de modeles

## Suivi d'email

**Mecanismes de suivi** :
- Suivi d'ouverture : image pixel 1x1
- Suivi de clic : URLs enveloppees avec redirection
- Suivi de desabonnement : liens token unique

**Traitement webhook** :
- Traitement d'evenements en temps reel
- Mise a jour statistiques campagne
- Mise a jour engagement abonne
- Declenchement workflows automatises (futur)

## Integration RabbitMQ

**Evenements consommes** :
- `user.registered` - Auto-abonnement a newsletter (optionnel)
- `order.completed` - Ajout a liste newsletter clients

**Evenements publies** :
- `newsletter.subscribed` - Nouvel abonnement
- `newsletter.unsubscribed` - Desabonnement
- `campaign.sent` - Campagne completee
- `email.bounced` - Rebond dur (pour nettoyage)

**Exemple de format de message** :
```json
{
  "event": "newsletter.subscribed",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "newsletter_id": 123,
    "email": "user@example.com",
    "source": "checkout_optin",
    "language": "en"
  }
}
```

## Variables d'environnement

```bash
# Application
APP_NAME=newsletters-service
APP_ENV=local
APP_PORT=8006

# Base de donnees
DB_CONNECTION=mysql
DB_HOST=newsletters-mysql
DB_PORT=3306
DB_DATABASE=newsletters_db
DB_USERNAME=newsletters_user
DB_PASSWORD=newsletters_password

# Configuration MinIO
MINIO_ENDPOINT=http://minio:9000
MINIO_ACCESS_KEY=admin
MINIO_SECRET_KEY=adminpass123
MINIO_BUCKET=newsletters
MINIO_REGION=us-east-1

# Configuration fournisseur service email (ESP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME=E-Commerce Platform

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=newsletters_exchange
RABBITMQ_QUEUE=newsletters_queue

# URLs de services
AUTH_SERVICE_URL=http://auth-service:8000
```

## Deploiement

**Configuration Docker** :
```yaml
Service: newsletters-service
Port Mapping: 8006:8000
Database: newsletters-mysql (port 3313 externe)
Depends On: newsletters-mysql, rabbitmq, minio
Networks: e-commerce-network
Health Check: point de terminaison /health
```

**Ressources Kubernetes** :
- Deployment: 2 replicas
- CPU Request: 150m, Limit: 400m
- Memory Request: 512Mi, Limit: 1Gi
- Service Type: ClusterIP
- ConfigMap: Configuration fournisseur email
- Secret: Identifiants ESP
- CronJob: Envoyeur campagnes planifiees

**Configuration verification sante** :
- Liveness Probe: GET /health (intervalle 10s)
- Readiness Probe: Connectivite Base de donnees + MinIO
- Startup Probe: timeout 30s

## Optimisation des performances

**Traitement par lot** :
- Envoyer emails par lots (100-500 par lot)
- Traitement base sur files d'attente avec Laravel queues
- Limitation de debit pour eviter throttling ESP
- Logique de reessai pour echecs d'envoi

**Strategie de cache** :
- Listes d'abonnes en cache (TTL 5 min)
- Statistiques campagne en cache (TTL 2 min)
- Metadonnees modeles en cache (TTL 15 min)

**Optimisation base de donnees** :
- Index sur: email, status, subscribed_at
- Partitionnement par date campagne
- Archivage anciennes campagnes (6+ mois)

**Taches planifiees** :
- Envoyeur campagnes planifiees (toutes les 5 minutes)
- Nettoyage rebonds (quotidien)
- Notation engagement (hebdomadaire)
- Nettoyage listes (mensuel - supprimer inactifs)

## Securite et conformite

**Conformite vie privee** :
- Conformite RGPD (droit a l'oubli)
- Conformite CAN-SPAM Act (liens desabonnement)
- Confirmation double opt-in
- Politiques retention donnees
- Exportation donnees utilisateur sur demande

**Mesures de securite** :
- Limitation debit points terminaison abonnement
- Validation et verification email
- Verification signature webhook (futur)
- Authentification email DMARC/SPF/DKIM

## Surveillance et observabilite

**Metriques a suivre** :
- Taux croissance abonnes
- Taux desabonnement
- Taux ouverture email
- Taux clic
- Taux rebond
- Taux envoi campagne
- Vitesse traitement file d'attente

**Journalisation** :
- Evenements abonnement
- Envois campagne
- Statut livraison email
- Evenements webhook
- Erreurs API ESP

## Ameliorations futures

- Tests A/B pour campagnes
- Workflows email automatises (campagnes goutte-a-goutte)
- Segmentation et ciblage
- Moteur personnalisation
- Constructeur modeles email (glisser-deposer)
- Automatisation RSS-vers-email
- Support campagnes SMS
- Integration ESP majeurs (SendGrid, Mailchimp, AWS SES)
- Tableau de bord analyses avancees
- Notation et suivi engagement abonnes
