# Documentation Microservices E-Commerce

Documentation complete pour les 13 microservices de la plateforme e-commerce.

## Vue d'ensemble architecture

Cette plateforme implemente une architecture microservices entierement asynchrone avec :
- **Communication** : Courtier messages RabbitMQ pour toute communication inter-services
- **Stockage** : Stockage objet distribue MinIO pour fichiers et images
- **Authentification** : Authentification JWT centralisee via auth-service
- **Gateway** : Gateway API unique comme point entree pour toutes requetes client

## Documentation services

### Services infrastructure

1. **[Gateway API](01-gateway-api.md)** - Port 8100
   - Point entree unique pour toutes requetes
   - Route vers microservices via RabbitMQ
   - Decouverte services et surveillance sante

2. **[Courtier Messages](13-courtier-messages.md)** - Port 8011
   - Coordination et gestion RabbitMQ
   - Suivi messages echoues et logique reessai
   - Surveillance et statistiques files d'attente

### Services principaux

3. **[Service Auth](02-service-auth.md)** - Port 8000
   - Authentification JWT et gestion tokens
   - Controle acces base sur roles (RBAC) avec Spatie Laravel Permission
   - Gestion utilisateurs et autorisation

4. **[Service Produits](03-service-produits.md)** - Port 8001
   - Gestion catalogue produits
   - Categories, marques, types et catalogues
   - Suivi inventaire
   - Integration MinIO pour images produits

5. **[Service Paniers](04-service-paniers.md)** - Port 8002
   - Gestion panier d'achat
   - Gestion codes promotionnels
   - Calculs prix et totaux

6. **[Service Commandes](05-service-commandes.md)** - Port 8003
   - Traitement commandes et gestion cycle de vie
   - Machine a etats pour transitions statuts commandes
   - Suivi statut paiement

7. **[Service Adresses](06-service-adresses.md)** - Port 8004
   - Gestion et validation adresses
   - Adresses livraison et facturation
   - Donnees reference pays et regions

8. **[Service Livraisons](07-service-livraisons.md)** - Port 8005
   - Suivi livraisons et gestion expeditions
   - Gestion points vente (lieux retrait)
   - Coordination integration transporteurs

### Services marketing et support

9. **[Service Newsletters](08-service-newsletters.md)** - Port 8006
   - Gestion abonnements newsletters email
   - Creation et execution campagnes
   - Integration MinIO pour modeles email
   - Suivi livraison email et analyses

10. **[Service SAV](09-service-sav.md)** - Port 8007 (Support Client)
    - Systeme gestion tickets support
    - Threading messages et conversations
    - Integration MinIO pour pieces jointes tickets
    - Cycle de vie et attribution tickets

11. **[Service Contacts](10-service-contacts.md)** - Port 8008
    - Gestion base de donnees contacts (CRM)
    - Segmentation listes et etiquetage
    - Suivi engagement email
    - Integration automatisation marketing

12. **[Service Questions](11-service-questions.md)** - Port 8009
    - Systeme gestion FAQ
    - Gestion questions et reponses
    - Acces FAQ public avec recherche
    - Suivi utilite

### Services configuration

13. **[Service Sites Web](12-service-sites-web.md)** - Port 8010
    - Configuration multi-sites et gestion locataires
    - Parametres et branding specifiques sites web
    - Gestion domaines et locale

## Reference ports services

| Service | Port interne | Port DB externe | Nom base de donnees |
|---------|---------------|------------------|---------------|
| Gateway API | 8100 | N/A | Aucune (sans etat) |
| Service Auth | 8000 | 3307 | auth_db |
| Service Produits | 8001 | 3308 | products_db |
| Service Paniers | 8002 | 3309 | baskets_db |
| Service Commandes | 8003 | 3310 | orders_db |
| Service Adresses | 8004 | 3311 | addresses_db |
| Service Livraisons | 8005 | 3312 | deliveries_db |
| Service Newsletters | 8006 | 3313 | newsletters_db |
| Service SAV | 8007 | 3314 | sav_db |
| Service Contacts | 8008 | 3315 | contacts_db |
| Service Questions | 8009 | 3316 | questions_db |
| Service Sites Web | 8010 | 3317 | websites_db |
| Courtier Messages | 8011 | 3318 | messages_broker_db |

## Composants infrastructure

### RabbitMQ
- **Ports** : 5672 (AMQP), 15672 (Interface gestion)
- **Interface gestion** : http://localhost:15672
- **Identifiants** : guest/guest
- **Objectif** : Courtier messages asynchrone pour toute communication inter-services

### Stockage objet MinIO
- **Ports** : 9000 (API), 9001 (Console)
- **Console** : http://localhost:9001
- **Identifiants** : admin/adminpass123
- **Buckets** :
  - `products` - Images produits
  - `sav` - Pieces jointes tickets support
  - `newsletters` - Modeles email

### Proxy inverse Nginx
- **Ports** : 80 (HTTP), 443 (HTTPS)
- **Routes** : Tout trafic `/api/` et `/v1/` vers Gateway API
- **En-tetes** : X-Request-ID pour tracage requetes

### Bases de donnees MySQL
- **Version** : 8.0
- **Modele** : Isolation base de donnees par service
- **Ports externes** : 3307-3318 (pour debogage)

## Motifs communication

### Flux requete-reponse
```
Client -> Nginx -> Gateway API -> RabbitMQ -> Microservice
                                     ^              |
                                     |              v
                                  Response <--- Process
```

### Publication evenements
```
Service A -> Event -> RabbitMQ Exchange -> Multiples files d'attente
                                             |  |  |
                                             v  v  v
                                          Service B, C, D
```

### Evenements courants

**Flux commande** :
```
basket.checkout -> order.created -> inventory.reserve -> payment.process
-> order.confirmed -> delivery.create -> delivery.shipped -> order.delivered
```

**Inscription utilisateur** :
```
user.registered -> contact.created -> newsletter.subscribe (optionnel)
```

**Mises a jour produit** :
```
product.updated -> basket.price.sync -> order.price.validate
```

## Workflow developpement

### Demarrage rapide
```bash
make docker-install    # Configuration premiere fois
make dev              # Developpement quotidien
make health-docker    # Verifier sante services
```

### Travailler avec services
```bash
# Acceder shell service
docker-compose exec <service-name> bash

# Executer migrations
docker-compose exec <service-name> php artisan migrate

# Executer tests
docker-compose exec <service-name> php artisan test

# Voir journaux
docker-compose logs -f <service-name>
```

### Gestion base de donnees
```bash
make migrate-all      # Executer migrations sur tous services
make seed-all         # Executer seeders
make fresh-all        # Migration fraiche avec seeds
make backup-docker    # Sauvegarder toutes bases de donnees
```

## Tests

### Tests niveau service
Chaque service a sa propre suite tests :
```bash
# Executer tests pour service specifique
make test-service SERVICE_NAME=auth-service

# Executer tous tests services
make test-docker
```

### Tests API
Collections Postman disponibles dans `docs/api/postman/` :
- Collection complete E-commerce API v2
- Fichiers environnement (Development, Staging, Production)
- Tests automatises et validation

## Surveillance et verifications sante

### Points de terminaison sante
Tous services exposent :
- `GET /health` - Reponse sante JSON
- `GET /simple-health` ou `/status` - Reponse texte pour sondes

### Outils surveillance
```bash
make docker-status     # Vue d'ensemble statut services
make stats            # Utilisation ressources
make docker-endpoints # URLs services
```

## Considerations securite

### Authentification
- Tokens JWT (TTL 60 minutes)
- Tokens rafraichissement (TTL 2 semaines)
- Permissions basees sur roles (RBAC)
- Middleware validation JWT partage

### Protection donnees
- Isolation base de donnees par service
- Mots de passe chiffres (bcrypt)
- Stockage fichiers securise (MinIO avec URLs presignees)
- HTTPS en production

### Controle acces
- Points de terminaison publics (pas d'auth)
- Points de terminaison utilisateur (JWT requis)
- Points de terminaison admin (JWT + role admin)

## Objectifs performance

- Temps reponse : < 500ms pour appels API
- Debit : 1000 req/s par service
- Disponibilite : 99.9% uptime
- Auto-scaling active sur Kubernetes

## Deploiement

### Docker (Developpement)
```bash
docker-compose up --watch
```

### Kubernetes (Production)
```bash
make k8s-setup       # Configuration initiale
make k8s-deploy      # Deployer services
make k8s-status      # Verifier deploiement
make k8s-monitoring  # Ouvrir tableaux de bord
```

## Documentation additionnelle

- **Architecture** : `docs/architecture/`
- **Documentation API** : `docs/api/postman/`
- **Guides deploiement** : `docs/deployment/`
- **Developpement** : `docs/development/`
- **Maintenance** : `docs/maintenance/`

## Contribuer

Lors ajout nouvelles fonctionnalites ou modification services :
1. Mettre a jour documentation service dans ce repertoire
2. Mettre a jour collections Postman API
3. Executer tests et s'assurer qu'ils passent
4. Mettre a jour diagrammes architecturaux pertinents
5. Suivre conventions commits (Gitmoji + Conventional Commits)

## Support

Pour questions ou problemes :
- Consulter documentation service individuelle
- Revoir interface gestion RabbitMQ pour problemes flux messages
- Verifier journaux service : `docker-compose logs -f <service>`
- Revoir sections depannage dans docs services
