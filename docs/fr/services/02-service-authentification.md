# Service Authentification

## Presentation du Service

**Objectif** : Service centralise d'authentification et d'autorisation utilisant des jetons JWT et controle d'acces base sur les roles (RBAC).

**Port** : 8000
**Base de donnees** : auth_db (MySQL 8.0)
**Port Externe** : 3307 (pour debogage)
**Dependances** : Aucune (service fondamental)

**Methode d'Authentification** : Jetons JWT via tymon/jwt-auth
**Framework d'Autorisation** : Spatie Laravel Permission (RBAC)

## Responsabilites

- Enregistrement et authentification utilisateurs
- Generation, validation et rafraichissement jetons JWT
- Controle d'acces base sur les roles (RBAC)
- Gestion des permissions
- Gestion du profil utilisateur
- Hachage des mots de passe et securite

## Points de Terminaison API

| Methode | Point de Terminaison | Description | Auth Requis | Requete/Reponse |
|---------|----------------------|-------------|-------------|-----------------|
| GET | /health | Verification sante service | Non | {"status":"healthy","service":"auth-service"} |
| POST | /register | Enregistrer nouvel utilisateur | Non | {"email","password","name"} -> jeton JWT |
| POST | /login | Authentifier utilisateur | Non | {"email","password"} -> jeton JWT |
| POST | /validate-token | Valider jeton JWT | Non | {"token"} -> {"valid":true,"user":{}} |
| POST | /logout | Invalider jeton actuel | Oui | Message succes |
| POST | /refresh | Rafraichir jeton JWT | Oui | Nouveau jeton JWT |
| GET | /me | Obtenir utilisateur authentifie | Oui | Objet utilisateur avec roles/permissions |
| GET | /roles | Lister tous les roles | Oui | [{"id","name","permissions":[]}] |
| POST | /roles | Creer nouveau role | Oui | {"name","guard_name"} -> Objet role |
| GET | /roles/{id} | Obtenir details role | Oui | {"id","name","permissions":[]} |
| PUT | /roles/{id} | Mettre a jour role | Oui | {"name"} -> Role mis a jour |
| DELETE | /roles/{id} | Supprimer role | Oui | Message succes |
| POST | /roles/{id}/permissions | Assigner permissions au role | Oui | {"permissions":[]} -> Role mis a jour |
| GET | /permissions | Lister toutes permissions | Oui | [{"id","name","guard_name"}] |
| POST | /permissions | Creer permission | Oui | {"name","guard_name"} -> Permission |
| GET | /permissions/{id} | Obtenir details permission | Oui | Objet permission |
| PUT | /permissions/{id} | Mettre a jour permission | Oui | Permission mise a jour |
| DELETE | /permissions/{id} | Supprimer permission | Oui | Message succes |
| POST | /users/{id}/roles | Assigner role a utilisateur | Oui | {"role"} -> Utilisateur mis a jour |
| DELETE | /users/{id}/roles/{role} | Retirer role d'utilisateur | Oui | Message succes |
| POST | /users/{id}/permissions | Assigner permission a utilisateur | Oui | {"permission"} -> Utilisateur mis a jour |
| DELETE | /users/{id}/permissions/{permission} | Retirer permission d'utilisateur | Oui | Message succes |

## Schema de Base de Donnees

**Tables** :

1. **users** - Comptes utilisateurs
   - id (PK)
   - name
   - email (unique)
   - password (hache)
   - email_verified_at
   - remember_token
   - timestamps

2. **roles** - Roles systeme (Spatie)
   - id (PK)
   - name (unique par garde)
   - guard_name
   - timestamps

3. **permissions** - Permissions systeme (Spatie)
   - id (PK)
   - name (unique par garde)
   - guard_name
   - timestamps

4. **model_has_roles** - Assignations utilisateur-role
   - role_id (FK)
   - model_type
   - model_uuid

5. **model_has_permissions** - Assignations directes utilisateur-permission
   - permission_id (FK)
   - model_type
   - model_uuid

6. **role_has_permissions** - Assignations role-permission
   - permission_id (FK)
   - role_id (FK)

7. **cache**, **jobs** - Tables infrastructure Laravel

**Relations** :
- User -> Roles (plusieurs-a-plusieurs via model_has_roles)
- User -> Permissions (plusieurs-a-plusieurs via model_has_permissions)
- Role -> Permissions (plusieurs-a-plusieurs via role_has_permissions)

## Integration RabbitMQ

**Evenements Consommes** :
- `auth.validate.request` - Requetes validation jeton depuis autres services
- `user.create.request` - Creation utilisateur depuis autres services

**Evenements Publies** :
- `auth.validated` - Resultat validation jeton
- `user.created` - Evenement nouvel enregistrement utilisateur
- `user.logged_in` - Evenement connexion utilisateur
- `user.logged_out` - Evenement deconnexion utilisateur
- `role.assigned` - Role assigne a utilisateur
- `permission.assigned` - Permission assignee a utilisateur/role

**Exemple Format Message** :
```json
{
  "event": "user.created",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "user_id": 123,
    "email": "user@example.com",
    "name": "John Doe",
    "roles": ["customer"]
  }
}
```

## Structure Jeton JWT

**Charge Utile Jeton** :
```json
{
  "iss": "http://auth-service:8000/api/login",
  "iat": 1234567890,
  "exp": 1234571490,
  "nbf": 1234567890,
  "jti": "unique-token-id",
  "sub": "user-id",
  "prv": "hash",
  "role": "admin",
  "email": "user@example.com"
}
```

**Configuration Jeton** :
- Algorithme : HS256
- TTL : 60 minutes (configurable)
- Refresh TTL : 20160 minutes (2 semaines)
- Secret : variable d'environnement JWT_SECRET

## Modele d'Autorisation

**Roles par Defaut** :
- `admin` - Acces complet systeme
- `manager` - Acces operations metier
- `customer` - Fonctionnalites orientees client
- `guest` - Acces lecture limite

**Structure Permission** :
- Format : `resource.action` (ex., `products.create`, `orders.view`)
- Permissions granulaires pour chaque ressource
- Support jokers : `products.*` pour toutes operations produit

**Integration Middleware** :
- Services utilisent Shared\Middleware\JWTAuthMiddleware
- Jeton valide via point de terminaison auth-service /validate-token
- Roles/permissions mis en cache dans charge jeton

## Variables d'Environnement

```bash
# Application
APP_NAME=auth-service
APP_ENV=local
APP_PORT=8000

# Base de donnees
DB_CONNECTION=mysql
DB_HOST=auth-mysql
DB_PORT=3306
DB_DATABASE=auth_db
DB_USERNAME=auth_user
DB_PASSWORD=auth_password

# Configuration JWT
JWT_SECRET=your-secret-key
JWT_TTL=60
JWT_REFRESH_TTL=20160
JWT_ALGO=HS256

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=auth_exchange
RABBITMQ_QUEUE=auth_queue
```

## Deploiement

**Configuration Docker** :
```yaml
Service: auth-service
Port Mapping: 8000:8000
Database: auth-mysql (port 3307 externe)
Depends On: auth-mysql, rabbitmq
Networks: e-commerce-network
Health Check: /health endpoint
```

**Ressources Kubernetes** :
- Deployment : 2 replicas minimum
- CPU Request : 100m, Limit : 300m
- Memory Request : 256Mi, Limit : 512Mi
- Service Type : ClusterIP
- ConfigMap : Configuration JWT
- Secret : JWT_SECRET, identifiants base de donnees

**Configuration Health Check** :
- Liveness Probe : GET /health (intervalle 10s)
- Readiness Probe : Test connexion base de donnees
- Startup Probe : timeout 30s pour migrations

## Considerations de Securite

**Securite Mot de Passe** :
- Hachage Bcrypt (defaut Laravel)
- Longueur minimale mot de passe : 8 caracteres
- Regles validation mot de passe appliquees

**Securite Jeton** :
- Jetons acces courte duree (60 min)
- Rotation jeton rafraichissement
- Liste noire jetons a la deconnexion
- Rotation cle secrete supportee

**Securite API** :
- Limitation taux sur points terminaison login/register
- Configuration CORS
- Validation et assainissement entrees
- Protection injection SQL (Eloquent ORM)

## Optimisation Performance

**Strategie Mise en Cache** :
- Requetes roles et permissions mises en cache
- Resultats validation jeton mis en cache (TTL 30s)
- Pooling connexions base de donnees

**Optimisation Base de Donnees** :
- Index sur colonnes email, name
- Index composites pour tables permission Spatie
- Optimisation requetes pour verifications roles/permissions

## Integration avec Autres Services

Tous les services valident les jetons JWT via :
1. Inclure jeton dans en-tete Authorization : `Bearer {token}`
2. Le middleware extrait et valide le jeton
3. ID utilisateur et roles extraits de charge jeton
4. Les services peuvent effectuer verifications permissions supplementaires si necessaire

**Exemple Flux Validation Jeton** :
```
1. Client -> Passerelle avec jeton Bearer
2. Passerelle -> Service Produits via RabbitMQ
3. Service Produits extrait jeton
4. Service Produits valide structure jeton
5. Service Produits verifie permissions (depuis jeton ou cache)
6. Service Produits traite requete
```

## Surveillance et Observabilite

**Metriques a Suivre** :
- Taux succes/echec connexion
- Debit validation jeton
- Requetes reinitialisation mot de passe
- Modifications roles/permissions
- Performance requetes base de donnees

**Journalisation** :
- Tentatives authentification (succes/echec)
- Evenements generation et rafraichissement jeton
- Modifications roles et permissions
- Evenements securite (activite suspecte)

## Ameliorations Futures

- Authentification multi-facteurs (MFA/2FA)
- Integration OAuth2/OpenID Connect
- Connexion sociale (Google, Facebook, etc.)
- Gestion sessions et suivi appareils
- Application politique mot de passe
- Verrouillage compte apres echecs tentatives
- Journal audit pour tous evenements auth
