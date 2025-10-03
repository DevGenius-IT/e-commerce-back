# Service SAV (Support Client)

## Vue d'ensemble du service

**Objectif** : Systeme de gestion des tickets de support client (SAV = Service Apres-Vente).

**Port** : 8007
**Base de donnees** : sav_db (MySQL 8.0)
**Port externe** : 3314 (pour le debogage)
**Dependances** : Service Auth, MinIO (stockage pieces jointes tickets - bucket: sav)

**SAV** signifie "Service Apres-Vente" en francais, equivalent a "Service Apres-Vente" ou Support Client.

## Responsabilites

- Creation et gestion tickets de support
- Threading messages/conversations tickets
- Gestion pieces jointes fichiers (stockage MinIO)
- Attribution tickets aux agents support
- Suivi statut tickets (ouvert, attribue, resolu, ferme)
- Gestion priorite tickets
- Analyses tickets support
- Historique support client

## Points de terminaison API

### Verification sante

| Methode | Point de terminaison | Description | Auth requise | Requete/Reponse |
|--------|----------|-------------|---------------|------------------|
| GET | /health | Verification sante du service | Non | {"status":"ok","service":"sav-service"} |
| GET | /status | Verification statut basique | Non | {"status":"ok","database":"connected"} |

### Points de terminaison proteges (Auth requise)

| Methode | Point de terminaison | Description | Requete/Reponse |
|--------|----------|-------------|------------------|
| GET | /tickets | Lister tickets utilisateur | Liste paginee tickets |
| POST | /tickets | Creer ticket support | {"subject","description","priority"} -> Ticket cree |
| GET | /tickets/statistics | Statistiques tickets | Donnees tableau de bord |
| GET | /tickets/{id} | Obtenir details ticket | Ticket complet avec messages et pieces jointes |
| PUT | /tickets/{id} | Mettre a jour ticket | Ticket mis a jour |
| DELETE | /tickets/{id} | Supprimer ticket | Message de succes |
| POST | /tickets/{id}/assign | Attribuer ticket | {"agent_id"} -> Ticket attribue |
| POST | /tickets/{id}/resolve | Resoudre ticket | {"resolution"} -> Ticket resolu |
| POST | /tickets/{id}/close | Fermer ticket | Ticket ferme |
| GET | /tickets/{ticketId}/messages | Lister messages ticket | Fil de messages |
| POST | /tickets/{ticketId}/messages | Ajouter message | {"content","is_internal"} -> Message cree |
| GET | /tickets/{ticketId}/messages/unread-count | Comptage non lus | {"count":5} |
| POST | /tickets/{ticketId}/messages/mark-all-read | Marquer tous lus | Succes |
| GET | /tickets/{ticketId}/messages/{id} | Obtenir message | Objet message |
| PUT | /tickets/{ticketId}/messages/{id} | Mettre a jour message | Message mis a jour |
| DELETE | /tickets/{ticketId}/messages/{id} | Supprimer message | Succes |
| POST | /tickets/{ticketId}/messages/{id}/mark-read | Marquer comme lu | Succes |
| GET | /tickets/{ticketId}/attachments | Lister pieces jointes | Liste pieces jointes |
| POST | /tickets/{ticketId}/attachments | Telecharger piece jointe | Upload fichier -> Objet piece jointe |
| POST | /tickets/{ticketId}/attachments/multiple | Telecharger multiples | Fichiers -> Liste pieces jointes |
| GET | /tickets/{ticketId}/attachments/{id} | Obtenir piece jointe | Metadonnees piece jointe |
| GET | /tickets/{ticketId}/attachments/{id}/download | Telecharger fichier | Telechargement fichier (URL presignee) |
| DELETE | /tickets/{ticketId}/attachments/{id} | Supprimer piece jointe | Succes |
| GET | /tickets/{ticketId}/attachments/message/{messageId} | Obtenir par message | Pieces jointes pour message specifique |

### Points de terminaison publics (Sans Auth - Pour demandes support anonymes)

| Methode | Point de terminaison | Description | Requete/Reponse |
|--------|----------|-------------|------------------|
| POST | /public/tickets | Creer ticket public | {"email","subject","description"} -> Ticket avec token acces |
| GET | /public/tickets/{ticketNumber} | Recherche ticket public | Details ticket (messages publics uniquement) |
| POST | /public/tickets/{ticketId}/messages | Message public | {"content"} -> Message cree |

## Schema de base de donnees

**Tables** :

1. **support_tickets** - En-tetes tickets support
   - id (PK)
   - ticket_number (unique, genere - ex: TICKET-20240115-001)
   - user_id (FK, nullable - pour utilisateurs authentifies)
   - assigned_to (FK, nullable - user_id agent support)
   - subject
   - description (text)
   - status (enum: open, assigned, in_progress, waiting_customer, resolved, closed)
   - priority (enum: low, normal, high, urgent)
   - category (string, nullable - facturation, technique, produit, etc.)
   - source (enum: web, email, phone, chat)
   - contact_email (pour tickets anonymes)
   - contact_name (pour tickets anonymes)
   - first_response_at (timestamp, nullable)
   - resolved_at (timestamp, nullable)
   - closed_at (timestamp, nullable)
   - resolution_notes (text, nullable)
   - internal_notes (text, nullable - personnel uniquement)
   - satisfaction_rating (integer, nullable - 1-5)
   - satisfaction_comment (text, nullable)
   - tags (JSON)
   - metadata (JSON)
   - timestamps, soft_deletes

2. **ticket_messages** - Fil de conversation
   - id (PK)
   - ticket_id (FK)
   - user_id (FK, nullable)
   - author_name (string - pour affichage)
   - author_email (string)
   - content (text)
   - is_internal (boolean - notes personnel non visibles client)
   - is_read (boolean)
   - read_at (timestamp, nullable)
   - timestamps

3. **ticket_attachments** - Pieces jointes fichiers (MinIO)
   - id (PK)
   - ticket_id (FK)
   - message_id (FK, nullable - piece jointe liee au message)
   - user_id (FK, nullable)
   - file_name
   - file_path (chemin MinIO)
   - file_url (URL presignee MinIO)
   - file_type
   - file_size (octets)
   - mime_type
   - timestamps

**Relations** :
- Ticket -> User (appartient a, nullable)
- Ticket -> Assigned Agent (appartient a User, nullable)
- Ticket -> Messages (a plusieurs)
- Ticket -> Attachments (a plusieurs)
- Message -> Ticket (appartient a)
- Message -> Attachments (a plusieurs)
- Attachment -> Ticket (appartient a)
- Attachment -> Message (appartient a, nullable)

## Cycle de vie ticket

**Flux de statut** :
```
open -> assigned -> in_progress -> waiting_customer -> resolved -> closed
   |        |            |                |
   +--------+------------+----------------+-> closed (peut fermer depuis n'importe quel statut)
```

**Definitions statuts** :
- **open** : Nouveau ticket, en attente attribution
- **assigned** : Attribue a agent support
- **in_progress** : Agent travaille activement sur ticket
- **waiting_customer** : En attente reponse client
- **resolved** : Probleme resolu, en attente confirmation
- **closed** : Ticket ferme (etat final)

**Actions automatiques** :
- Fermeture automatique tickets resolus apres 7 jours inactivite
- Escalade automatique tickets urgents si pas de reponse en 2 heures
- Attribution automatique basee sur distribution charge travail

## Integration MinIO

**Bucket** : sav
**Stockage pieces jointes** :
```
sav/
  tickets/
    {ticket_id}/
      {attachment_id}/
        original_filename.ext
```

**Gestion fichiers** :
- Taille maximale fichier : 10MB par fichier
- Types autorises : Images, PDFs, documents, logs
- Analyse antivirus avant stockage (futur)
- Validation automatique type fichier
- URLs presignees pour telechargements securises (expiration 1 heure)

## Acces tickets publics

**Creation tickets anonymes** :
- Pas d'authentification requise
- Email et nom captures
- Numero ticket retourne pour suivi
- Acces via numero ticket (pas d'auth necessaire)

**Recherche tickets publics** :
- Acces via numero ticket
- Seuls messages publics visibles
- Notes internes cachees
- Peut repondre via point terminaison public

## Integration RabbitMQ

**Evenements consommes** :
- `order.issue` - Creer ticket depuis probleme commande
- `user.registered` - Lier tickets existants au compte utilisateur
- `product.issue_reported` - Creer ticket lie produit

**Evenements publies** :
- `ticket.created` - Nouveau ticket cree
- `ticket.assigned` - Ticket attribue a agent
- `ticket.resolved` - Ticket marque comme resolu
- `ticket.closed` - Ticket ferme
- `ticket.message.new` - Nouveau message ajoute
- `ticket.escalated` - Ticket escalade (urgent, pas de reponse)
- `ticket.satisfaction.received` - Note satisfaction client

**Exemple de format de message** :
```json
{
  "event": "ticket.created",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "ticket_id": 123,
    "ticket_number": "TICKET-20240115-123",
    "user_id": 456,
    "subject": "Commande non recue",
    "priority": "high",
    "category": "delivery"
  }
}
```

## Variables d'environnement

```bash
# Application
APP_NAME=sav-service
APP_ENV=local
APP_PORT=8007

# Base de donnees
DB_CONNECTION=mysql
DB_HOST=sav-mysql
DB_PORT=3306
DB_DATABASE=sav_db
DB_USERNAME=sav_user
DB_PASSWORD=sav_password

# Configuration MinIO
MINIO_ENDPOINT=http://minio:9000
MINIO_ACCESS_KEY=admin
MINIO_SECRET_KEY=adminpass123
MINIO_BUCKET=sav
MINIO_REGION=us-east-1

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=sav_exchange
RABBITMQ_QUEUE=sav_queue

# URLs de services
AUTH_SERVICE_URL=http://auth-service:8000

# Configuration tickets
TICKET_NUMBER_PREFIX=TICKET
AUTO_CLOSE_RESOLVED_DAYS=7
ESCALATION_TIMEOUT_HOURS=2
MAX_ATTACHMENT_SIZE_MB=10
```

## Deploiement

**Configuration Docker** :
```yaml
Service: sav-service
Port Mapping: 8007:8000
Database: sav-mysql (port 3314 externe)
Depends On: sav-mysql, rabbitmq, minio
Networks: e-commerce-network
Health Check: point de terminaison /health
```

**Ressources Kubernetes** :
- Deployment: 2 replicas
- CPU Request: 100m, Limit: 300m
- Memory Request: 256Mi, Limit: 512Mi
- Service Type: ClusterIP
- ConfigMap: Configuration tickets
- PVC: Aucun (utilise MinIO)

**Configuration verification sante** :
- Liveness Probe: GET /health (intervalle 10s)
- Readiness Probe: Connectivite Base de donnees + MinIO
- Startup Probe: timeout 30s

## Optimisation des performances

**Strategie de cache** :
- Liste tickets utilisateur en cache (TTL 3 min)
- Details tickets en cache (TTL 1 min)
- Statistiques en cache (TTL 5 min)
- Metadonnees pieces jointes en cache (TTL 10 min)

**Optimisation base de donnees** :
- Index sur: ticket_number, user_id, assigned_to, status, priority
- Recherche full-text sur subject et description
- Index composes pour requetes filtrage
- Soft deletes pour historique tickets

**Taches planifiees** :
- Fermeture automatique tickets resolus (quotidien)
- Surveillance escalade (toutes les 30 min)
- Envoi enquetes satisfaction (apres cloture)
- Agregation metriques tickets (toutes les heures)

## Considerations securite

**Controle acces** :
- Utilisateurs voient uniquement leurs propres tickets
- Agents support voient tickets attribues + tous ouverts
- Admin voit tous tickets
- Tickets publics accessibles par numero ticket uniquement

**Protection donnees** :
- Analyse antivirus pieces jointes (futur)
- Application liste blanche types fichiers
- Limites taille sur uploads
- Notes internes jamais exposees aux clients
- Conformite RGPD pour suppression donnees

## Surveillance et observabilite

**Metriques a suivre** :
- Temps reponse moyen (premiere reponse)
- Temps resolution moyen
- Tickets par statut
- Tickets par priorite
- Distribution charge travail agents
- Scores satisfaction client
- Taux escalade

**Journalisation** :
- Creation et cloture tickets
- Transitions statut
- Changements attribution
- Ajouts messages
- Uploads pieces jointes
- Evenements escalade

## Ameliorations futures

- Integration chat en direct
- Chatbot pour questions courantes
- Integration base connaissances
- Reponses predefinies pour agents
- Suivi et application SLA
- Conversion email-vers-ticket
- Support multi-langues
- Enquetes satisfaction client
- Rapports et analyses avances
- Integration systemes helpdesk tiers
- Support appels video pour problemes complexes
