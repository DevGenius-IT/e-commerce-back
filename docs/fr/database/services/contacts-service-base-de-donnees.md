# Documentation de la Base de Données du Service Contacts

## Table des Matières
- [Vue d'Ensemble](#vue-densemble)
- [Informations Base de Données](#informations-base-de-données)
- [Diagramme des Relations entre Entités](#diagramme-des-relations-entre-entités)
- [Schémas des Tables](#schémas-des-tables)
- [Conception Système CRM](#conception-système-crm)
- [Gestion Listes et Segmentation](#gestion-listes-et-segmentation)
- [Système d'Étiquettes](#système-détiquettes)
- [Événements Publiés](#événements-publiés)
- [Références Inter-Services](#références-inter-services)
- [Index et Performance](#index-et-performance)
- [Opérations Import et Export](#opérations-import-et-export)
- [Intégration RBAC](#intégration-rbac)
- [Sauvegarde et Maintenance](#sauvegarde-et-maintenance)

## Vue d'Ensemble

La base de données du service contacts (`contacts_service_db`) fournit des fonctionnalités CRM (Gestion de la Relation Client) complètes pour gérer les contacts clients, les organiser en listes et les catégoriser avec des étiquettes. Ce service permet les campagnes marketing, la segmentation client et la gestion des relations contacts.

**Service :** contacts-service
**Base de Données :** contacts_service_db
**Port Externe :** 3323
**Total Tables :** 8 (4 principales, 4 infrastructure Laravel)

**Capacités Clés :**
- Gestion informations contact avec détails entreprise
- Organisation contacts basée listes et segmentation
- Système catégorisation basé étiquettes
- Opérations import/export en masse
- Suivi relations contacts
- Synchronisation événements autres services
- Contrôle accès basé rôles via Spatie Permission

## Informations Base de Données

### Détails Connexion
```bash
Hôte: localhost (dans réseau Docker: contacts-mysql)
Port: 3323 (externe), 3306 (interne)
Base de données: contacts_service_db
Charset: utf8mb4
Collation: utf8mb4_unicode_ci
Moteur: InnoDB
```

### Configuration Environnement
```bash
DB_CONNECTION=mysql
DB_HOST=contacts-mysql
DB_PORT=3306
DB_DATABASE=contacts_service_db
DB_USERNAME=contacts_user
DB_PASSWORD=contacts_password
```

## Diagramme des Relations entre Entités

```mermaid
erDiagram
    contact_lists ||--o{ contact_list_contacts : "contient"
    contacts ||--o{ contact_list_contacts : "appartient à listes"
    contact_tags ||--o{ contact_tag_pivot : "étiqueté sur contacts"
    contacts ||--o{ contact_tag_pivot : "a étiquettes"
    users ||--o{ contacts : "gère"
    roles ||--o{ model_has_roles : "attribué à utilisateurs"
    permissions ||--o{ model_has_permissions : "accordé à utilisateurs"

    contact_lists {
        bigint id PK
        string name UK
        text description
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    contacts {
        bigint id PK
        string email UK
        string first_name
        string last_name
        string company
        string phone
        string country
        string city
        timestamp created_at
        timestamp updated_at
    }

    contact_tags {
        bigint id PK
        string name UK
        string color
        timestamp created_at
        timestamp updated_at
    }

    contact_list_contacts {
        bigint contact_list_id PK_FK
        bigint contact_id PK_FK
        timestamp added_at
    }

    contact_tag_pivot {
        bigint contact_id PK_FK
        bigint contact_tag_id PK_FK
        timestamp created_at
    }

    users {
        bigint id PK
        string email UK
        string firstname
        string lastname
        timestamp created_at
        timestamp updated_at
    }

    roles {
        bigint id PK
        string name UK
        string guard_name
    }

    permissions {
        bigint id PK
        string name UK
        string guard_name
    }

    model_has_roles {
        bigint role_id PK_FK
        string model_type PK
        bigint model_id PK
    }

    model_has_permissions {
        bigint permission_id PK_FK
        string model_type PK
        bigint model_id PK
    }

    jobs {
        bigint id PK
        string queue
        longtext payload
        tinyint attempts
    }

    cache {
        string key PK
        mediumtext value
        int expiration
    }

    cache_locks {
        string key PK
        string owner
        int expiration
    }
```

## Schémas des Tables

### Tables Principales

#### 1. contact_lists
Gestion listes contacts pour segmentation et ciblage campagnes.

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Identifiant liste |
| name | VARCHAR(255) | NOT NULL, UNIQUE | Nom liste (unique système) |
| description | TEXT | NULLABLE | Objectif et description liste |
| is_active | BOOLEAN | NOT NULL, DEFAULT TRUE | Statut actif/archivé |
| created_at | TIMESTAMP | NULLABLE | Horodatage création liste |
| updated_at | TIMESTAMP | NULLABLE | Horodatage dernière mise à jour |

**Index :**
```sql
PRIMARY KEY (id)
UNIQUE KEY contact_lists_name_unique (name)
INDEX contact_lists_is_active_index (is_active)
INDEX contact_lists_created_at_index (created_at)
```

**Règles Métier :**
- Noms listes doivent être uniques dans système entier
- Indicateur is_active permet archivage sans suppression
- Description supporte formatage markdown
- Listes peuvent être vides (pas contacts)
- Plusieurs-à-plusieurs avec contacts via contact_list_contacts

**Fonctionnalités Modèle :**
```php
// Modèle: ContactList.php
$fillable = ['name', 'description', 'is_active'];

$casts = [
    'is_active' => 'boolean',
    'created_at' => 'datetime',
    'updated_at' => 'datetime'
];

// Relations
public function contacts()
{
    return $this->belongsToMany(Contact::class, 'contact_list_contacts')
                ->withPivot('added_at')
                ->withTimestamps();
}
```

**Exemple de Données :**
```json
{
  "id": 1,
  "name": "Abonnés Newsletter",
  "description": "Clients inscrits newsletter hebdomadaire",
  "is_active": true,
  "created_at": "2025-10-03T10:00:00Z",
  "updated_at": "2025-10-03T10:00:00Z"
}
```

**Listes Communes :**
- "Tous Clients" - Base clients complète
- "Abonnés Newsletter" - Opt-ins marketing
- "Clients VIP" - Clients grande valeur
- "Utilisateurs Inactifs" - Pas activité 90+ jours
- "Campagne Saisonnière 2024" - Campagne durée limitée
- "Prospects Lancement Produit" - Liste intérêt pré-lancement

---

#### 2. contacts
Entité contact principale avec informations personnelles et entreprise.

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Identifiant contact |
| email | VARCHAR(255) | NOT NULL, UNIQUE | Adresse email contact |
| first_name | VARCHAR(255) | NOT NULL | Prénom contact |
| last_name | VARCHAR(255) | NOT NULL | Nom famille contact |
| company | VARCHAR(255) | NULLABLE | Nom entreprise/organisation |
| phone | VARCHAR(255) | NULLABLE | Numéro téléphone contact |
| country | VARCHAR(255) | NULLABLE | Pays résidence |
| city | VARCHAR(255) | NULLABLE | Ville résidence |
| created_at | TIMESTAMP | NULLABLE | Horodatage création contact |
| updated_at | TIMESTAMP | NULLABLE | Horodatage dernière mise à jour |

**Index :**
```sql
PRIMARY KEY (id)
UNIQUE KEY contacts_email_unique (email)
INDEX contacts_first_name_index (first_name)
INDEX contacts_last_name_index (last_name)
INDEX contacts_company_index (company)
INDEX contacts_country_index (country)
INDEX contacts_city_index (city)
INDEX contacts_created_at_index (created_at)
```

**Règles Métier :**
- Email doit être unique (identifiant principal)
- first_name et last_name requis
- Champ company pour contacts B2B
- Téléphone stocké chaîne (supporte formats internationaux)
- Pays et ville pour segmentation géographique
- Pas suppressions douces (enregistrements contacts permanents)

**Fonctionnalités Modèle :**
```php
// Modèle: Contact.php
$fillable = [
    'email',
    'first_name',
    'last_name',
    'company',
    'phone',
    'country',
    'city'
];

$casts = [
    'created_at' => 'datetime',
    'updated_at' => 'datetime'
];

// Relations
public function lists()
{
    return $this->belongsToMany(ContactList::class, 'contact_list_contacts')
                ->withPivot('added_at')
                ->withTimestamps();
}

public function tags()
{
    return $this->belongsToMany(ContactTag::class, 'contact_tag_pivot')
                ->withTimestamps();
}

// Portées
public function scopeInCountry($query, $country)
{
    return $query->where('country', $country);
}

public function scopeInCity($query, $city)
{
    return $query->where('city', $city);
}

public function scopeWithCompany($query)
{
    return $query->whereNotNull('company');
}
```

**Exemple de Données :**
```json
{
  "id": 123,
  "email": "john.doe@example.com",
  "first_name": "John",
  "last_name": "Doe",
  "company": "Acme Corporation",
  "phone": "+33612345678",
  "country": "France",
  "city": "Paris",
  "created_at": "2025-10-03T10:30:00Z",
  "updated_at": "2025-10-03T10:30:00Z"
}
```

**Règles Validation :**
```php
// ContactRequest.php
public function rules(): array
{
    return [
        'email' => ['required', 'email', 'max:255', 'unique:contacts,email'],
        'first_name' => ['required', 'string', 'max:255'],
        'last_name' => ['required', 'string', 'max:255'],
        'company' => ['nullable', 'string', 'max:255'],
        'phone' => ['nullable', 'string', 'max:255'],
        'country' => ['nullable', 'string', 'max:255'],
        'city' => ['nullable', 'string', 'max:255']
    ];
}
```

---

#### 3. contact_tags
Système étiquettes pour catégorisation contacts flexible.

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Identifiant étiquette |
| name | VARCHAR(255) | NOT NULL, UNIQUE | Nom étiquette (unique) |
| color | VARCHAR(255) | NULLABLE | Code couleur hexa affichage UI |
| created_at | TIMESTAMP | NULLABLE | Horodatage création étiquette |
| updated_at | TIMESTAMP | NULLABLE | Horodatage dernière mise à jour |

**Index :**
```sql
PRIMARY KEY (id)
UNIQUE KEY contact_tags_name_unique (name)
INDEX contact_tags_created_at_index (created_at)
```

**Règles Métier :**
- Noms étiquettes doivent être uniques
- Couleur stockée code hexa (ex: "#FF5733")
- Étiquettes peuvent être appliquées à plusieurs contacts
- Plusieurs-à-plusieurs avec contacts via contact_tag_pivot
- Pas hiérarchie (structure plate)

**Fonctionnalités Modèle :**
```php
// Modèle: ContactTag.php
$fillable = ['name', 'color'];

$casts = [
    'created_at' => 'datetime',
    'updated_at' => 'datetime'
];

// Relations
public function contacts()
{
    return $this->belongsToMany(Contact::class, 'contact_tag_pivot')
                ->withTimestamps();
}

// Accesseur pour couleur avec défaut
public function getColorAttribute($value)
{
    return $value ?? '#6B7280'; // Gris défaut si non défini
}
```

**Exemple de Données :**
```json
{
  "id": 5,
  "name": "VIP",
  "color": "#FFD700",
  "created_at": "2025-10-03T09:00:00Z",
  "updated_at": "2025-10-03T09:00:00Z"
}
```

**Étiquettes Communes :**
- "VIP" - Clients grande valeur (#FFD700 or)
- "Prospect" - Clients potentiels (#3B82F6 bleu)
- "Inactif" - Pas activité récente (#EF4444 rouge)
- "Newsletter" - Abonnés newsletter (#10B981 vert)
- "B2B" - Clients entreprise (#8B5CF6 violet)
- "Support" - Cas support actifs (#F59E0B ambre)
- "Partenaire" - Partenaires affaires (#EC4899 rose)

---

#### 4. contact_list_contacts (Pivot)
Relation plusieurs-à-plusieurs entre contacts et listes.

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| contact_list_id | BIGINT UNSIGNED | PK, FK | Référence liste (contact_lists.id) |
| contact_id | BIGINT UNSIGNED | PK, FK | Référence contact (contacts.id) |
| added_at | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Horodatage ajout contact à liste |

**Clés Étrangères :**
```sql
FOREIGN KEY (contact_list_id) REFERENCES contact_lists(id) ON DELETE CASCADE
FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
```

**Index :**
```sql
PRIMARY KEY (contact_list_id, contact_id)
INDEX contact_list_contacts_contact_id_index (contact_id)
INDEX contact_list_contacts_added_at_index (added_at)
```

**Règles Métier :**
- Clé primaire composite empêche entrées dupliquées
- Suppression cascade : supprimer contact ou liste supprime relation
- added_at suit quand contact rejoint liste
- Un contact peut être dans plusieurs listes
- Une liste peut contenir plusieurs contacts

**Requêtes Utilisation :**
```sql
-- Obtenir tous contacts dans liste
SELECT c.*
FROM contacts c
INNER JOIN contact_list_contacts clc ON c.id = clc.contact_id
WHERE clc.contact_list_id = 1
ORDER BY clc.added_at DESC;

-- Obtenir toutes listes pour contact
SELECT cl.*
FROM contact_lists cl
INNER JOIN contact_list_contacts clc ON cl.id = clc.contact_list_id
WHERE clc.contact_id = 123
AND cl.is_active = 1;

-- Contacts récemment ajoutés à liste
SELECT c.*, clc.added_at
FROM contacts c
INNER JOIN contact_list_contacts clc ON c.id = clc.contact_id
WHERE clc.contact_list_id = 1
AND clc.added_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY clc.added_at DESC;
```

---

### Tables Infrastructure Laravel

#### 5. users
Cache utilisateur local synchronisé depuis auth-service.

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK | Identifiant utilisateur (depuis auth-service) |
| email | VARCHAR(255) | NOT NULL, UNIQUE | Email utilisateur |
| firstname | VARCHAR(255) | NULLABLE | Prénom utilisateur |
| lastname | VARCHAR(255) | NULLABLE | Nom famille utilisateur |
| created_at | TIMESTAMP | NULLABLE | Horodatage création enregistrement |
| updated_at | TIMESTAMP | NULLABLE | Horodatage dernière synchronisation |

**Index :**
```sql
PRIMARY KEY (id)
UNIQUE KEY users_email_unique (email)
```

**Règles Métier :**
- Synchronisé depuis auth-service via événements RabbitMQ
- id correspond ID utilisateur auth-service
- Utilisé pour propriété contacts et pistes audit
- Pas authentification locale (JWT validé via auth-service)

---

#### 6. roles et permissions
Tables Spatie Laravel Permission pour RBAC.

**roles :**
```sql
CREATE TABLE roles (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY roles_name_guard_name_unique (name, guard_name)
);
```

**permissions :**
```sql
CREATE TABLE permissions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY permissions_name_guard_name_unique (name, guard_name)
);
```

**model_has_roles et model_has_permissions :**
Tables pivot polymorphiques pour attribuer rôles/permissions aux utilisateurs.

**Permissions Standard :**
```sql
INSERT INTO permissions (name, guard_name) VALUES
('contacts.view', 'api'),       -- Voir contacts
('contacts.create', 'api'),     -- Créer nouveaux contacts
('contacts.update', 'api'),     -- Mettre à jour infos contact
('contacts.delete', 'api'),     -- Supprimer contacts
('lists.manage', 'api'),        -- Gérer listes contacts
('tags.manage', 'api'),         -- Gérer étiquettes
('contacts.export', 'api'),     -- Exporter contacts
('contacts.import', 'api');     -- Importer contacts
```

**Rôles Standard :**
```sql
INSERT INTO roles (name, guard_name) VALUES
('contacts_manager', 'api'),    -- Gestion complète contacts
('contacts_viewer', 'api'),     -- Accès lecture seule
('marketing_manager', 'api');   -- Gestion listes et campagnes
```

---

#### 7. jobs
Jobs file pour opérations asynchrones.

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Identifiant job |
| queue | VARCHAR(255) | NOT NULL, INDEXED | Nom file |
| payload | LONGTEXT | NOT NULL | Données job sérialisées |
| attempts | TINYINT UNSIGNED | NOT NULL | Tentatives exécution |
| reserved_at | INT UNSIGNED | NULLABLE | Horodatage réservation |
| available_at | INT UNSIGNED | NOT NULL | Horodatage disponibilité |
| created_at | INT UNSIGNED | NOT NULL | Horodatage création |

**Jobs File Communs :**
- Traitement import contacts (CSV/Excel)
- Génération export contacts
- Opérations listes en masse (ajouter/supprimer plusieurs contacts)
- Validation et enrichissement données contacts
- Synchronisation avec systèmes CRM externes

---

#### 8. cache et cache_locks
Tables cache Laravel pour optimisation performance.

**cache :**
- Stocke listes contacts fréquemment accédées
- Cache associations étiquettes
- Limitation taux pour opérations import/export

**cache_locks :**
- Empêche opérations import concurrentes
- Verrous durant mises à jour listes en masse

---

## Conception Système CRM

### Philosophie Gestion Contacts

Le service contacts implémente système CRM flexible conçu pour :

1. **Création Contacts Multi-Sources :**
   - Saisie manuelle via interface admin
   - Import en masse depuis fichiers CSV/Excel
   - Synchronisation automatisée depuis inscriptions utilisateurs (auth-service)
   - Intégration API depuis systèmes externes
   - Soumissions formulaires contact (contacts-service)

2. **Stratégie Segmentation :**
   - **Listes** : Regroupement basé adhésion (campagnes, newsletters)
   - **Étiquettes** : Catégorisation basée attributs (VIP, Prospect, Inactif)
   - **Filtres** : Requêtes dynamiques (pays, entreprise, plages dates)

3. **Cycle de Vie Contact :**
   ```
   Création → Enrichissement → Segmentation → Engagement → Analyse
   ```

### Architecture Flux Données

```
┌──────────────────────────────────────────────────────┐
│                   Sources Contacts                   │
└──────────────────────────────────────────────────────┘
           │
           ├─ Saisie Manuelle (IU Admin)
           ├─ Import Masse (CSV/Excel)
           ├─ Inscription Utilisateur (événement auth-service)
           ├─ Intégration API (CRM Externe)
           └─ Formulaires Contact (contacts-service)
           │
           ▼
┌──────────────────────────────────────────────────────┐
│              contacts_service_db                     │
│                   (table contacts)                   │
└──────────────────────────────────────────────────────┘
           │
           ├─ Attribution Liste (contact_list_contacts)
           ├─ Application Étiquette (contact_tag_pivot)
           └─ Publication Événement (RabbitMQ)
           │
           ▼
┌──────────────────────────────────────────────────────┐
│                  Services Consommateurs              │
└──────────────────────────────────────────────────────┘
           │
           ├─ newsletters-service (Campagnes email)
           ├─ marketing-service (Publicités ciblées) [futur]
           └─ analytics-service (Rapports) [futur]
```

## Gestion Listes et Segmentation

### Types Listes et Cas d'Usage

#### 1. Listes Statiques
Collections contacts organisées manuellement.

**Caractéristiques :**
- Adhésion fixe
- Ajout/suppression contacts manuel
- Gestion liste explicite

**Exemples :**
```sql
-- Créer liste statique
INSERT INTO contact_lists (name, description, is_active)
VALUES ('Clients VIP', 'Segment clients grande valeur', 1);

-- Ajouter contacts manuellement
INSERT INTO contact_list_contacts (contact_list_id, contact_id, added_at)
SELECT 5, id, NOW()
FROM contacts
WHERE id IN (10, 15, 23, 47, 89);
```

**Cas d'Usage :**
- Listes participants événements
- Groupes testeurs bêta
- Listes contacts partenaires
- Conseils consultatifs clients

---

#### 2. Listes Dynamiques (Basées Requête)
Listes peuplées par critères filtre sauvegardés.

**Implémentation :**
```php
// Requête liste dynamique stockée dans description liste en JSON
$listQuery = [
    'country' => 'France',
    'city' => 'Paris',
    'created_after' => '2024-01-01',
    'tags' => ['VIP']
];

// Exécuter requête dynamique
$contacts = Contact::query()
    ->where('country', $listQuery['country'])
    ->where('city', $listQuery['city'])
    ->where('created_at', '>=', $listQuery['created_after'])
    ->whereHas('tags', function($q) use ($listQuery) {
        $q->whereIn('name', $listQuery['tags']);
    })
    ->get();

// Synchroniser à liste
$list->contacts()->sync($contacts->pluck('id'));
```

**Cas d'Usage :**
- Campagnes ciblage géographique
- Segmentation basée temps (nouveaux clients, utilisateurs inactifs)
- Listes basées comportement (achats, interactions support)
- Filtrage multi-critères

---

#### 3. Listes Campagnes
Listes durée limitée pour campagnes marketing.

**Cycle de Vie :**
```
Création → Population → Exécution Campagne → Analyse → Archive
```

**Exemple :**
```sql
-- Créer liste campagne
INSERT INTO contact_lists (name, description, is_active)
VALUES (
    'Lancement Produit Q4 2024',
    'Contacts ciblés campagne lancement produit Q4',
    1
);

-- Archiver après campagne
UPDATE contact_lists
SET is_active = 0
WHERE name = 'Lancement Produit Q4 2024'
AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

---

### Opérations Listes

#### Ajouter Contacts en Masse
```php
// Ajouter plusieurs contacts à liste
$list->contacts()->attach([101, 102, 103, 104], [
    'added_at' => now()
]);
```

#### Supprimer Contacts en Masse
```php
// Supprimer plusieurs contacts de liste
$list->contacts()->detach([101, 102]);
```

#### Copier Liste
```php
// Dupliquer liste avec tous contacts
$newList = ContactList::create([
    'name' => $originalList->name . ' (Copie)',
    'description' => $originalList->description,
    'is_active' => true
]);

$contactIds = $originalList->contacts()->pluck('contact_id')->toArray();
$newList->contacts()->attach($contactIds, ['added_at' => now()]);
```

#### Fusionner Listes
```php
// Fusionner plusieurs listes en une
$targetList = ContactList::find(1);
$sourceLists = ContactList::whereIn('id', [2, 3, 4])->get();

foreach ($sourceLists as $sourceList) {
    $contactIds = $sourceList->contacts()
        ->whereNotIn('contact_id',
            $targetList->contacts()->pluck('contact_id')
        )
        ->pluck('contact_id')
        ->toArray();

    $targetList->contacts()->attach($contactIds, ['added_at' => now()]);
}
```

#### Intersection Listes
```php
// Trouver contacts dans plusieurs listes
$listIds = [1, 2, 3];
$minOccurrences = 2; // Contact doit être dans au moins 2 listes

$contacts = DB::table('contact_list_contacts')
    ->select('contact_id')
    ->whereIn('contact_list_id', $listIds)
    ->groupBy('contact_id')
    ->havingRaw('COUNT(*) >= ?', [$minOccurrences])
    ->pluck('contact_id');
```

---

## Système d'Étiquettes

### Philosophie Conception Étiquettes

Étiquettes fournissent catégorisation flexible, définie utilisateur, orthogonale à adhésion liste :

- **Listes** = "Où" (quelles campagnes, quels segments)
- **Étiquettes** = "Quoi" (caractéristiques, statut, catégories)

### Catégories Étiquettes (Convention Organisationnelle)

#### 1. Étiquettes Statut
État actuel relation contact.

**Exemples :**
- `Actif` - Actuellement engagé
- `Inactif` - Pas activité récente
- `Prospect` - Client potentiel
- `Client` - Client actif
- `Ancien Client` - Client churné

---

#### 2. Étiquettes Segment
Classification client.

**Exemples :**
- `VIP` - Client grande valeur
- `Entreprise` - Client grande entreprise
- `PME` - Petite/moyenne entreprise
- `Particulier` - Client consommateur

---

#### 3. Étiquettes Canal
Préférences communication et sources.

**Exemples :**
- `Opt-in Email` - Abonné newsletter
- `Opt-in SMS` - Consentement marketing SMS
- `Réseaux Sociaux` - Abonné réseaux sociaux
- `Participant Webinaire` - Participant événement

---

#### 4. Étiquettes Comportement
Catégorisation basée activité.

**Exemples :**
- `Acheteur Fréquent` - Fréquence achat élevée
- `Acheteur Unique` - Achat unique seulement
- `Utilisateur Support` - Engagement support actif
- `Défenseur Produit` - Promoteur NPS

---

### Opérations Étiquettes

#### Appliquer Étiquette à Contact
```php
// Étiquette unique
$contact->tags()->attach($tagId, ['created_at' => now()]);

// Étiquettes multiples
$contact->tags()->attach([1, 2, 3], ['created_at' => now()]);
```

#### Supprimer Étiquette de Contact
```php
// Étiquette unique
$contact->tags()->detach($tagId);

// Toutes étiquettes
$contact->tags()->detach();
```

#### Trouver Contacts par Étiquette
```sql
-- Contacts avec étiquette spécifique
SELECT c.*
FROM contacts c
INNER JOIN contact_tag_pivot ctp ON c.id = ctp.contact_id
INNER JOIN contact_tags ct ON ctp.contact_tag_id = ct.id
WHERE ct.name = 'VIP';

-- Contacts avec N'IMPORTE laquelle des étiquettes spécifiées (logique OU)
SELECT DISTINCT c.*
FROM contacts c
INNER JOIN contact_tag_pivot ctp ON c.id = ctp.contact_id
INNER JOIN contact_tags ct ON ctp.contact_tag_id = ct.id
WHERE ct.name IN ('VIP', 'Actif', 'Newsletter');

-- Contacts avec TOUTES les étiquettes spécifiées (logique ET)
SELECT c.*
FROM contacts c
WHERE (
    SELECT COUNT(DISTINCT ct.name)
    FROM contact_tag_pivot ctp
    INNER JOIN contact_tags ct ON ctp.contact_tag_id = ct.id
    WHERE ctp.contact_id = c.id
    AND ct.name IN ('VIP', 'Actif')
) = 2; -- Compte correspond nombre étiquettes requises
```

#### Opérations Étiquettes en Masse
```php
// Étiqueter plusieurs contacts
Contact::whereIn('id', [1, 2, 3, 4])
    ->each(function($contact) use ($tagId) {
        $contact->tags()->syncWithoutDetaching([$tagId]);
    });

// Supprimer étiquette de tous contacts
$tag = ContactTag::find($tagId);
$tag->contacts()->detach();
```

---

## Événements Publiés

Le service contacts publie événements vers RabbitMQ pour synchronisation inter-services.

### Schéma Événement

Tous événements suivent format message standard :
```json
{
  "event_id": "uuid-v4",
  "event_type": "contact.created",
  "timestamp": "2025-10-03T14:30:00Z",
  "version": "1.0",
  "data": {
    "contact_id": 123,
    "email": "john.doe@example.com"
  },
  "metadata": {
    "correlation_id": "request-uuid",
    "service": "contacts-service"
  }
}
```

### 1. ContactCreated
**File :** contacts.created
**Publié :** Quand nouveau contact créé avec succès

**Charge Utile :**
```json
{
  "event_type": "contact.created",
  "timestamp": "2025-10-03T14:30:00Z",
  "data": {
    "contact_id": 123,
    "email": "john.doe@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "company": "Acme Corp",
    "phone": "+33612345678",
    "country": "France",
    "city": "Paris",
    "created_at": "2025-10-03T14:30:00Z"
  }
}
```

**Consommateurs :**
- newsletters-service : Ajouter au pool abonnés newsletter
- marketing-service : Synchroniser à plateforme automatisation marketing
- analytics-service : Suivre métriques nouveaux contacts

---

### 2. ContactUpdated
**File :** contacts.updated
**Publié :** Quand informations contact modifiées

**Charge Utile :**
```json
{
  "event_type": "contact.updated",
  "timestamp": "2025-10-03T15:45:00Z",
  "data": {
    "contact_id": 123,
    "email": "john.doe@example.com",
    "updated_fields": {
      "company": "New Company Inc",
      "phone": "+33698765432",
      "city": "Lyon"
    },
    "updated_at": "2025-10-03T15:45:00Z"
  }
}
```

**Consommateurs :**
- newsletters-service : Mettre à jour informations abonné
- marketing-service : Synchroniser données mises à jour
- analytics-service : Suivre changements contacts

---

### 3. ContactAddedToList
**File :** contacts.list.added
**Publié :** Quand contact ajouté à liste

**Charge Utile :**
```json
{
  "event_type": "contact.added_to_list",
  "timestamp": "2025-10-03T16:00:00Z",
  "data": {
    "contact_id": 123,
    "list_id": 5,
    "list_name": "Abonnés Newsletter",
    "added_at": "2025-10-03T16:00:00Z"
  }
}
```

**Consommateurs :**
- newsletters-service : S'abonner à campagne newsletter
- marketing-service : Ajouter à audience campagne
- analytics-service : Suivre croissance liste

---

### 4. ContactTagged
**File :** contacts.tagged
**Publié :** Quand étiquette appliquée à contact

**Charge Utile :**
```json
{
  "event_type": "contact.tagged",
  "timestamp": "2025-10-03T16:15:00Z",
  "data": {
    "contact_id": 123,
    "tag_id": 7,
    "tag_name": "VIP",
    "tagged_at": "2025-10-03T16:15:00Z"
  }
}
```

**Consommateurs :**
- newsletters-service : Appliquer templates email VIP
- marketing-service : Déclencher workflows clients VIP
- analytics-service : Suivre changements segments

---

### 5. ContactDeleted
**File :** contacts.deleted
**Publié :** Quand contact supprimé définitivement

**Charge Utile :**
```json
{
  "event_type": "contact.deleted",
  "timestamp": "2025-10-03T17:00:00Z",
  "data": {
    "contact_id": 123,
    "email": "john.doe@example.com",
    "deleted_at": "2025-10-03T17:00:00Z",
    "reason": "user_request"
  }
}
```

**Consommateurs :**
- newsletters-service : Se désinscrire de toutes campagnes
- marketing-service : Supprimer de toutes audiences
- analytics-service : Archiver métriques contacts

**Conformité RGPD :**
```php
// Gérer suppression contact avec nettoyage cascade
public function handleContactDeleted(array $event): void
{
    $contactId = $event['data']['contact_id'];

    // Supprimer de toutes listes
    DB::table('contact_list_contacts')
        ->where('contact_id', $contactId)
        ->delete();

    // Supprimer toutes étiquettes
    DB::table('contact_tag_pivot')
        ->where('contact_id', $contactId)
        ->delete();

    // Anonymiser dans analytique (si nécessaire)
    // Garder métriques agrégées, supprimer PII
}
```

---

## Références Inter-Services

### Référencé PAR Autres Services

#### newsletters-service
```sql
-- table subscribers
CREATE TABLE subscribers (
    contact_id BIGINT UNSIGNED,  -- Référence contacts.id
    email VARCHAR(255),           -- Cache contacts.email
    first_name VARCHAR(255),      -- Cache contacts.first_name
    last_name VARCHAR(255),       -- Cache contacts.last_name
    -- PAS contrainte clé étrangère (base de données différente)
);
```

**Synchronisation :**
- Écouter ContactCreated : Ajouter au pool abonnés
- Écouter ContactUpdated : Mettre à jour données contact cachées
- Écouter ContactAddedToList : S'abonner aux campagnes
- Écouter ContactDeleted : Se désinscrire et supprimer

---

#### marketing-service (futur)
```sql
-- table campaign_audiences
CREATE TABLE campaign_audiences (
    contact_id BIGINT UNSIGNED,  -- Référence contacts.id
    email VARCHAR(255),           -- Cache contacts.email
    -- PAS contrainte clé étrangère (base de données différente)
);
```

**Synchronisation :**
- Écouter ContactTagged : Ajouter à segments ciblés
- Écouter ContactAddedToList : Inclure dans audience campagne

---

### Références VERS Autres Services

#### auth-service
```sql
-- Table users locale cachée depuis auth-service
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY,  -- Depuis auth-service
    email VARCHAR(255) UNIQUE,       -- Depuis auth-service
    -- Synchronisé via événements RabbitMQ
);
```

**Synchronisation :**
- Écouter user.created : Ajouter au cache utilisateurs local
- Écouter user.updated : Mettre à jour données utilisateur cachées
- Écouter user.deleted : Supprimer du cache

---

## Index et Performance

### Index Stratégiques

#### Table contacts
```sql
PRIMARY KEY (id)                  -- Recherche principale
UNIQUE KEY (email)                -- Requêtes basées email
INDEX (first_name)                -- Recherche nom
INDEX (last_name)                 -- Recherche nom
INDEX (company)                   -- Filtrage entreprise
INDEX (country)                   -- Segmentation géographique
INDEX (city)                      -- Segmentation géographique
INDEX (created_at)                -- Requêtes basées temps
```

**Optimisation Requêtes :**
```sql
-- Recherche email rapide
SELECT * FROM contacts WHERE email = 'john.doe@example.com';

-- Filtrage géographique
SELECT * FROM contacts
WHERE country = 'France' AND city = 'Paris';

-- Contacts entreprise
SELECT * FROM contacts
WHERE company IS NOT NULL
ORDER BY company, last_name;

-- Contacts récents
SELECT * FROM contacts
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY created_at DESC;
```

---

#### Table contact_lists
```sql
PRIMARY KEY (id)
UNIQUE KEY (name)                 -- Recherche nom liste
INDEX (is_active)                 -- Filtrage listes actives
INDEX (created_at)                -- Tri par date création
```

**Optimisation Requêtes :**
```sql
-- Listes actives uniquement
SELECT * FROM contact_lists
WHERE is_active = 1
ORDER BY name;

-- Listes récentes
SELECT * FROM contact_lists
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
ORDER BY created_at DESC;
```

---

#### Pivot contact_list_contacts
```sql
PRIMARY KEY (contact_list_id, contact_id)
INDEX (contact_id)                -- Recherche inverse
INDEX (added_at)                  -- Tri basé temps
```

**Optimisation Requêtes :**
```sql
-- Contacts dans liste avec date ajout
SELECT c.*, clc.added_at
FROM contacts c
INNER JOIN contact_list_contacts clc ON c.id = clc.contact_id
WHERE clc.contact_list_id = 5
ORDER BY clc.added_at DESC;

-- Récemment ajoutés à n'importe quelle liste
SELECT c.*, cl.name as list_name, clc.added_at
FROM contacts c
INNER JOIN contact_list_contacts clc ON c.id = clc.contact_id
INNER JOIN contact_lists cl ON clc.contact_list_id = cl.id
WHERE clc.added_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY clc.added_at DESC;
```

---

#### Table contact_tags
```sql
PRIMARY KEY (id)
UNIQUE KEY (name)                 -- Recherche nom étiquette
INDEX (created_at)                -- Tri
```

---

### Recommandations Performance

1. **Requêtes Contacts :**
   - Toujours utiliser colonnes indexées dans clauses WHERE
   - Utiliser email pour recherches uniques (indexé)
   - Considérer recherche texte intégral pour requêtes noms à grande échelle

2. **Opérations Listes :**
   - Cacher listes actives (changent rarement)
   - Utiliser opérations batch pour ajout/suppression en masse
   - Considérer pagination pour grandes listes (10k+ contacts)

3. **Requêtes Étiquettes :**
   - Cacher associations étiquette-contact pour recherches fréquentes
   - Utiliser EXISTS pour vérifications présence étiquette au lieu JOIN
   - Optimiser requêtes multi-étiquettes avec utilisation index appropriée

4. **Opérations Export :**
   - Utiliser jobs file pour grands exports (>1k contacts)
   - Fragmenter requêtes pour éviter problèmes mémoire
   - Générer CSV en streaming au lieu charger toutes données

5. **Opérations Import :**
   - Valider par fragments (500-1000 enregistrements par lot)
   - Utiliser transactions BDD pour atomicité
   - Traitement file pour imports >5k enregistrements

---

## Opérations Import et Export

### Flux de Travail Import CSV

**Processus :**
```
Téléverser CSV → Validation → Job File → Traitement Batch → Confirmation
```

**Implémentation :**
```php
// ContactImportJob.php
public function handle()
{
    $csvPath = $this->csvPath;
    $listId = $this->listId;

    // Lire CSV par fragments
    $chunkSize = 500;
    $imported = 0;
    $errors = [];

    $csv = Reader::createFromPath($csvPath, 'r');
    $csv->setHeaderOffset(0);

    foreach ($csv->chunk($chunkSize) as $chunk) {
        DB::transaction(function() use ($chunk, $listId, &$imported, &$errors) {
            foreach ($chunk as $row) {
                try {
                    // Valider ligne
                    $validated = $this->validateRow($row);

                    // Créer ou mettre à jour contact
                    $contact = Contact::updateOrCreate(
                        ['email' => $validated['email']],
                        $validated
                    );

                    // Ajouter à liste si spécifiée
                    if ($listId) {
                        $contact->lists()->syncWithoutDetaching([
                            $listId => ['added_at' => now()]
                        ]);
                    }

                    $imported++;

                    // Publier événement
                    event(new ContactCreated($contact));

                } catch (ValidationException $e) {
                    $errors[] = [
                        'row' => $row,
                        'error' => $e->getMessage()
                    ];
                }
            }
        });
    }

    return [
        'imported' => $imported,
        'errors' => $errors
    ];
}
```

**Format CSV :**
```csv
email,first_name,last_name,company,phone,country,city
john.doe@example.com,John,Doe,Acme Corp,+33612345678,France,Paris
jane.smith@example.com,Jane,Smith,Tech Inc,+33698765432,France,Lyon
```

**Règles Validation :**
- email : Requis, format valide, unique
- first_name, last_name : Requis
- company, phone, country, city : Optionnels
- Maximum 10 000 lignes par import

---

### Flux de Travail Export CSV

**Processus :**
```
Requête Export → Job File → Générer CSV → Stocker Temporairement → Lien Téléchargement
```

**Implémentation :**
```php
// ContactExportJob.php
public function handle()
{
    $listId = $this->listId;
    $filters = $this->filters;

    // Requêter contacts
    $query = Contact::query();

    if ($listId) {
        $query->whereHas('lists', function($q) use ($listId) {
            $q->where('contact_lists.id', $listId);
        });
    }

    // Appliquer filtres supplémentaires
    if (!empty($filters['country'])) {
        $query->where('country', $filters['country']);
    }

    if (!empty($filters['tags'])) {
        $query->whereHas('tags', function($q) use ($filters) {
            $q->whereIn('name', $filters['tags']);
        });
    }

    // Générer CSV (streaming pour efficacité mémoire)
    $filename = 'contacts_export_' . now()->format('Y-m-d_His') . '.csv';
    $filePath = storage_path('app/exports/' . $filename);

    $csv = Writer::createFromPath($filePath, 'w+');

    // Écrire en-tête
    $csv->insertOne([
        'Email',
        'Prénom',
        'Nom',
        'Entreprise',
        'Téléphone',
        'Pays',
        'Ville',
        'Créé Le'
    ]);

    // Écrire données par fragments
    $query->chunk(1000, function($contacts) use ($csv) {
        foreach ($contacts as $contact) {
            $csv->insertOne([
                $contact->email,
                $contact->first_name,
                $contact->last_name,
                $contact->company,
                $contact->phone,
                $contact->country,
                $contact->city,
                $contact->created_at->toDateTimeString()
            ]);
        }
    });

    // Générer lien téléchargement (expire dans 24 heures)
    $downloadUrl = Storage::temporaryUrl($filePath, now()->addDay());

    return [
        'filename' => $filename,
        'download_url' => $downloadUrl,
        'expires_at' => now()->addDay()->toIso8601String()
    ];
}
```

---

### Opérations en Masse

**Ajouter Plusieurs Contacts à Liste :**
```php
// Insertion en masse efficace
$contactIds = [1, 2, 3, 4, 5, /* ... jusqu'à 10k */];
$listId = 10;

$insertData = [];
$now = now();

foreach ($contactIds as $contactId) {
    $insertData[] = [
        'contact_list_id' => $listId,
        'contact_id' => $contactId,
        'added_at' => $now
    ];
}

// Insertion batch (500 à la fois pour éviter limites taille requête)
collect($insertData)->chunk(500)->each(function($chunk) {
    DB::table('contact_list_contacts')->insertOrIgnore($chunk->toArray());
});
```

**Appliquer Étiquette à Plusieurs Contacts :**
```php
// Application étiquette en masse
$contactIds = [1, 2, 3, 4, 5];
$tagId = 7;

Contact::whereIn('id', $contactIds)
    ->each(function($contact) use ($tagId) {
        $contact->tags()->syncWithoutDetaching([$tagId]);
    });
```

---

## Intégration RBAC

### Contrôle Accès Basé Permissions

Le service contacts utilise Spatie Laravel Permission pour contrôle accès basé rôles.

#### Vérifications Permissions dans Contrôleurs

```php
// ContactController.php
class ContactController extends Controller
{
    public function index()
    {
        $this->authorize('contacts.view');

        return Contact::paginate(50);
    }

    public function store(ContactRequest $request)
    {
        $this->authorize('contacts.create');

        $contact = Contact::create($request->validated());

        event(new ContactCreated($contact));

        return response()->json($contact, 201);
    }

    public function update(ContactRequest $request, Contact $contact)
    {
        $this->authorize('contacts.update');

        $contact->update($request->validated());

        event(new ContactUpdated($contact));

        return response()->json($contact);
    }

    public function destroy(Contact $contact)
    {
        $this->authorize('contacts.delete');

        $contact->delete();

        event(new ContactDeleted($contact));

        return response()->json(null, 204);
    }
}
```

#### Permissions Gestion Listes

```php
// ContactListController.php
public function store(ContactListRequest $request)
{
    $this->authorize('lists.manage');

    $list = ContactList::create($request->validated());

    return response()->json($list, 201);
}

public function addContacts(Request $request, ContactList $list)
{
    $this->authorize('lists.manage');

    $contactIds = $request->input('contact_ids');

    $list->contacts()->syncWithoutDetaching(
        collect($contactIds)->mapWithKeys(function($id) {
            return [$id => ['added_at' => now()]];
        })->toArray()
    );

    foreach ($contactIds as $contactId) {
        event(new ContactAddedToList($contactId, $list->id));
    }

    return response()->json(['message' => 'Contacts ajoutés à liste']);
}
```

#### Permissions Gestion Étiquettes

```php
// ContactTagController.php
public function store(Request $request)
{
    $this->authorize('tags.manage');

    $tag = ContactTag::create($request->validated());

    return response()->json($tag, 201);
}

public function applyToContact(Request $request, Contact $contact)
{
    $this->authorize('tags.manage');

    $tagIds = $request->input('tag_ids');

    $contact->tags()->syncWithoutDetaching($tagIds);

    foreach ($tagIds as $tagId) {
        event(new ContactTagged($contact->id, $tagId));
    }

    return response()->json(['message' => 'Étiquettes appliquées au contact']);
}
```

#### Permissions Export/Import

```php
// ContactExportController.php
public function export(Request $request)
{
    $this->authorize('contacts.export');

    $filters = $request->validated();

    // Mettre job export en file
    $job = new ContactExportJob($filters);
    dispatch($job);

    return response()->json([
        'message' => 'Export mis en file. Vous recevrez lien téléchargement quand prêt.'
    ]);
}

// ContactImportController.php
public function import(Request $request)
{
    $this->authorize('contacts.import');

    $file = $request->file('csv');
    $listId = $request->input('list_id');

    // Mettre job import en file
    $job = new ContactImportJob($file->path(), $listId);
    dispatch($job);

    return response()->json([
        'message' => 'Import mis en file. Traitement commencé.'
    ]);
}
```

---

## Sauvegarde et Maintenance

### Stratégie Sauvegarde

#### Sauvegarde Automatisée Quotidienne
```bash
#!/bin/bash
# scripts/backup-contacts-db.sh

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/contacts-service"
BACKUP_FILE="${BACKUP_DIR}/contacts_service_db_${TIMESTAMP}.sql"

mkdir -p ${BACKUP_DIR}

docker exec contacts-mysql mysqldump \
  --user=contacts_user \
  --password=contacts_password \
  --single-transaction \
  --routines \
  --triggers \
  --databases contacts_service_db \
  > ${BACKUP_FILE}

gzip ${BACKUP_FILE}

# Rétention: Garder 30 jours
find ${BACKUP_DIR} -name "*.sql.gz" -mtime +30 -delete

echo "Sauvegarde complétée: ${BACKUP_FILE}.gz"
```

#### Planning Sauvegarde (cron)
```cron
# Sauvegarde quotidienne à 2h30
30 2 * * * /chemin/vers/scripts/backup-contacts-db.sh

# Sauvegarde complète hebdomadaire (Dimanche 3h30)
30 3 * * 0 /chemin/vers/scripts/full-backup-contacts.sh
```

---

### Maintenance Base de Données

#### Optimiser Tables (Mensuel)
```sql
-- Défragmenter et mettre à jour statistiques
OPTIMIZE TABLE contacts;
OPTIMIZE TABLE contact_lists;
OPTIMIZE TABLE contact_tags;
OPTIMIZE TABLE contact_list_contacts;
OPTIMIZE TABLE contact_tag_pivot;

-- Analyser modèles requêtes
ANALYZE TABLE contacts;
ANALYZE TABLE contact_lists;
ANALYZE TABLE contact_list_contacts;
```

#### Nettoyer Données Expirées
```sql
-- Supprimer contacts emails invalides (optionnel, révision manuelle)
-- Identifier potentiels emails invalides
SELECT * FROM contacts
WHERE email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'
LIMIT 100;

-- Archiver listes inactives (inactives 1+ an)
UPDATE contact_lists
SET is_active = 0
WHERE is_active = 1
AND updated_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);

-- Nettoyer table cache
DELETE FROM cache
WHERE expiration < UNIX_TIMESTAMP(NOW());
```

---

### Requêtes Surveillance

#### Croissance Contacts
```sql
-- Nouveaux contacts quotidiens (30 derniers jours)
SELECT
    DATE(created_at) AS date,
    COUNT(*) AS new_contacts,
    SUM(COUNT(*)) OVER (ORDER BY DATE(created_at)) AS total_contacts
FROM contacts
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;
```

#### Statistiques Listes
```sql
-- Tailles listes et activité
SELECT
    cl.id,
    cl.name,
    cl.is_active,
    COUNT(clc.contact_id) AS contact_count,
    MAX(clc.added_at) AS last_contact_added,
    cl.updated_at AS last_updated
FROM contact_lists cl
LEFT JOIN contact_list_contacts clc ON cl.id = clc.contact_list_id
GROUP BY cl.id, cl.name, cl.is_active, cl.updated_at
ORDER BY contact_count DESC;
```

#### Utilisation Étiquettes
```sql
-- Étiquettes plus populaires
SELECT
    ct.id,
    ct.name,
    ct.color,
    COUNT(ctp.contact_id) AS contact_count
FROM contact_tags ct
LEFT JOIN contact_tag_pivot ctp ON ct.id = ctp.contact_tag_id
GROUP BY ct.id, ct.name, ct.color
ORDER BY contact_count DESC
LIMIT 20;
```

#### Distribution Géographique
```sql
-- Distribution contacts par pays
SELECT
    country,
    COUNT(*) AS contact_count,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM contacts), 2) AS percentage
FROM contacts
WHERE country IS NOT NULL
GROUP BY country
ORDER BY contact_count DESC
LIMIT 20;
```

---

### Vérifications Santé

```php
// HealthCheckController.php
public function check(): JsonResponse
{
    $checks = [
        'database' => $this->checkDatabase(),
        'cache' => $this->checkCache(),
        'queue' => $this->checkQueue(),
        'contacts_count' => $this->getContactsCount(),
        'lists_count' => $this->getListsCount()
    ];

    $healthy = collect($checks)
        ->except(['contacts_count', 'lists_count'])
        ->every(fn($status) => $status === 'ok');

    return response()->json([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'checks' => $checks,
        'timestamp' => now()->toIso8601String()
    ], $healthy ? 200 : 503);
}

private function getContactsCount(): int
{
    return Contact::count();
}

private function getListsCount(): int
{
    return ContactList::where('is_active', true)->count();
}
```

**Point de Terminaison Vérification Santé :**
```
GET /api/health
```

**Réponse :**
```json
{
  "status": "healthy",
  "checks": {
    "database": "ok",
    "cache": "ok",
    "queue": "ok",
    "contacts_count": 15234,
    "lists_count": 42
  },
  "timestamp": "2025-10-03T16:00:00Z"
}
```

---

## Documentation Associée

- [Architecture Globale Base de Données](../00-global-database-architecture.md)
- [Relations Base de Données](../01-database-relationships.md)
- [Guide Message Broker RabbitMQ](../../architecture/rabbitmq-architecture.md)
- [Guide Authentification](../../development/jwt-authentication.md)
- [Documentation API](../../api/README.md)

---

**Version Document :** 1.0
**Dernière Mise à Jour :** 2025-10-03
**Version Base de Données :** MySQL 8.0
**Version Laravel :** 12.x
