# Service Produits

## Presentation du Service

**Objectif** : Gestion du catalogue produits incluant produits, categories, marques, types, catalogues et inventaire.

**Port** : 8001
**Base de donnees** : products_db (MySQL 8.0)
**Port Externe** : 3308 (pour debogage)
**Dependances** : Service Auth (pour operations admin), MinIO (pour images produits)

**Integration Stockage** : Stockage objet MinIO pour images produits (bucket : products)

## Responsabilites

- Gestion catalogue produits (operations CRUD)
- Gestion hierarchie categories
- Classification marques et types
- Attributs et caracteristiques produits
- Stockage images produits (integration MinIO)
- Suivi inventaire
- Gestion taux TVA
- Recherche et filtrage produits
- Organisation catalogues

## Points de Terminaison API

### Points de Terminaison Publics (Pas d'Auth Requis)

| Methode | Point de Terminaison | Description | Requete/Reponse |
|---------|----------------------|-------------|-----------------|
| GET | /health | Verification sante service | {"status":"healthy","service":"products-service"} |
| GET | /products | Lister tous produits | Params requete : page, per_page, category, brand, type |
| GET | /products/search | Rechercher produits | Requete : q, filters |
| GET | /products/{id} | Obtenir details produit | Objet produit avec relations |
| GET | /categories | Lister categories | Structure arbre categories |
| GET | /categories/{id} | Obtenir details categorie | Categorie avec comptage produits |
| GET | /categories/{id}/products | Produits dans categorie | Liste produits paginee |
| GET | /brands | Lister marques | [{"id","name","logo"}] |
| GET | /brands/{id} | Obtenir details marque | Marque avec metadonnees |
| GET | /brands/{id}/products | Produits par marque | Liste produits paginee |
| GET | /types | Lister types produits | [{"id","name","description"}] |
| GET | /types/{id} | Obtenir details type | Type avec caracteristiques |
| GET | /types/{id}/products | Produits par type | Liste produits paginee |
| GET | /catalogs | Lister catalogues | [{"id","name","description"}] |
| GET | /catalogs/{id} | Obtenir details catalogue | Catalogue avec produits |
| GET | /catalogs/{id}/products | Produits dans catalogue | Liste produits paginee |
| GET | /vat | Lister taux TVA | [{"id","rate","country"}] |
| GET | /vat/{id} | Obtenir details taux TVA | Objet taux TVA |

### Points de Terminaison Admin (Auth Requis)

| Methode | Point de Terminaison | Description | Requete/Reponse |
|---------|----------------------|-------------|-----------------|
| POST | /admin/products | Creer produit | Donnees produit -> Produit cree |
| PUT | /admin/products/{id} | Mettre a jour produit | Donnees produit -> Produit mis a jour |
| PATCH | /admin/products/{id} | Mise a jour partielle | Donnees partielles -> Produit mis a jour |
| DELETE | /admin/products/{id} | Supprimer produit | Message succes |
| POST | /admin/products/{id}/stock | Mettre a jour stock | {"quantity","operation"} -> Stock mis a jour |
| POST | /admin/categories | Creer categorie | Donnees categorie -> Categorie creee |
| PUT | /admin/categories/{id} | Mettre a jour categorie | Donnees categorie -> Categorie mise a jour |
| DELETE | /admin/categories/{id} | Supprimer categorie | Message succes (verification cascade) |
| POST | /admin/brands | Creer marque | Donnees marque -> Marque creee |
| PUT | /admin/brands/{id} | Mettre a jour marque | Donnees marque -> Marque mise a jour |
| DELETE | /admin/brands/{id} | Supprimer marque | Message succes |
| POST | /admin/types | Creer type | Donnees type -> Type cree |
| PUT | /admin/types/{id} | Mettre a jour type | Donnees type -> Type mis a jour |
| DELETE | /admin/types/{id} | Supprimer type | Message succes |
| POST | /admin/catalogs | Creer catalogue | Donnees catalogue -> Catalogue cree |
| PUT | /admin/catalogs/{id} | Mettre a jour catalogue | Donnees catalogue -> Catalogue mis a jour |
| DELETE | /admin/catalogs/{id} | Supprimer catalogue | Message succes |
| POST | /admin/vat | Creer taux TVA | Donnees TVA -> TVA creee |
| PUT | /admin/vat/{id} | Mettre a jour taux TVA | Donnees TVA -> TVA mise a jour |
| DELETE | /admin/vat/{id} | Supprimer taux TVA | Message succes |

## Schema de Base de Donnees

**Tables Principales** :

1. **products** - Table produits principale
   - id (PK)
   - name
   - slug (unique)
   - description (text)
   - short_description
   - price (decimal)
   - sale_price (decimal, nullable)
   - cost_price (decimal)
   - sku (unique)
   - barcode
   - quantity (inventaire)
   - weight
   - dimensions (JSON)
   - is_active (boolean)
   - is_featured (boolean)
   - brand_id (FK)
   - vat_id (FK)
   - meta_title, meta_description, meta_keywords
   - timestamps, soft_deletes

2. **categories** - Categories hierarchiques
   - id (PK)
   - parent_id (FK, nullable - auto-reference)
   - name
   - slug (unique)
   - description
   - image_url
   - order (integer)
   - is_active (boolean)
   - timestamps

3. **brands** - Marques produits
   - id (PK)
   - name
   - slug (unique)
   - description
   - logo_url
   - website_url
   - is_active (boolean)
   - timestamps

4. **types** - Types/classifications produits
   - id (PK)
   - name
   - slug (unique)
   - description
   - is_active (boolean)
   - timestamps

5. **catalogs** - Groupements catalogues produits
   - id (PK)
   - name
   - slug (unique)
   - description
   - is_active (boolean)
   - start_date, end_date (nullable)
   - timestamps

6. **vat** - Taux TVA/taxe
   - id (PK)
   - name
   - rate (decimal)
   - country_code
   - is_default (boolean)
   - timestamps

7. **product_images** - References images produits (MinIO)
   - id (PK)
   - product_id (FK)
   - image_url (chemin MinIO)
   - thumbnail_url
   - alt_text
   - order (integer)
   - is_primary (boolean)
   - timestamps

8. **attributes** - Attributs produits (taille, couleur, etc.)
   - id (PK)
   - attribute_group_id (FK)
   - name
   - value
   - timestamps

9. **characteristics** - Caracteristiques/specs produits
   - id (PK)
   - characteristic_group_id (FK)
   - name
   - value
   - unit
   - timestamps

10. **attribute_groups**, **characteristic_groups** - Tables groupement
    - id (PK)
    - name
    - description
    - timestamps

**Tables de Jonction** :
- **product_types** - Produit vers Type (plusieurs-a-plusieurs)
- **product_categories** - Produit vers Categorie (plusieurs-a-plusieurs)
- **product_catalogs** - Produit vers Catalogue (plusieurs-a-plusieurs)
- **related_characteristics** - Produit vers Caracteristiques

## Integration MinIO

**Bucket** : products
**Modele Stockage Images** :
```
products/
  {product_id}/
    original/
      image_1.jpg
      image_2.jpg
    thumbnails/
      image_1_thumb.jpg
      image_2_thumb.jpg
```

**Flux Upload Image** :
1. Admin telecharge image produit via API
2. Service valide image (type, taille)
3. Image stockee dans bucket products MinIO
4. Generation vignette (job async)
5. Stockage URLs dans table product_images
6. Retour URLs presignees pour acces

**Generation URL Presignee** :
- Expiration : 1 heure pour listing produits
- Regeneree a chaque requete
- Mise en cache dans Redis pour performance

## Integration RabbitMQ

**Evenements Consommes** :
- `product.stock.check` - Verification disponibilite stock depuis services panier/commande
- `product.price.request` - Requete information prix
- `product.details.request` - Requete details produit complets

**Evenements Publies** :
- `product.created` - Nouveau produit ajoute au catalogue
- `product.updated` - Information produit modifiee
- `product.deleted` - Produit retire du catalogue
- `product.stock.updated` - Niveau inventaire change
- `product.price.changed` - Prix modifie (pour paniers/commandes)
- `product.out_of_stock` - Inventaire produit epuise

**Exemple Format Message** :
```json
{
  "event": "product.stock.updated",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "product_id": 123,
    "sku": "PROD-001",
    "previous_quantity": 50,
    "new_quantity": 45,
    "change": -5,
    "reason": "order_placed"
  }
}
```

## Recherche et Filtrage

**Capacites Recherche** :
- Recherche plein texte sur nom, description, SKU
- Filtrage categorie (avec support hierarchie)
- Filtrage marque
- Filtrage type
- Filtrage fourchette prix
- Filtrage base attributs (taille, couleur, etc.)
- Filtrage disponibilite (en stock, en solde, vedette)

**Optimisation Recherche** :
- Index base de donnees sur colonnes recherchables
- Futur : integration Elasticsearch pour recherche avancee
- Mise en cache requetes recherche populaires

## Gestion Inventaire

**Suivi Stock** :
- Mises a jour inventaire temps reel
- Seuil bas stock configurable
- Notifications rupture stock
- Reservation stock pour commandes en attente

**Operations Stock** :
- Increment : Reapprovisionnement, retours
- Decrement : Ventes, dommages, perte
- Definir : Ajustement inventaire manuel
- Reserver : Placement commande (libere a annulation)

## Variables d'Environnement

```bash
# Application
APP_NAME=products-service
APP_ENV=local
APP_PORT=8001

# Base de donnees
DB_CONNECTION=mysql
DB_HOST=products-mysql
DB_PORT=3306
DB_DATABASE=products_db
DB_USERNAME=products_user
DB_PASSWORD=products_password

# Configuration MinIO
MINIO_ENDPOINT=http://minio:9000
MINIO_ACCESS_KEY=admin
MINIO_SECRET_KEY=adminpass123
MINIO_BUCKET=products
MINIO_REGION=us-east-1
MINIO_USE_PATH_STYLE=true

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=products_exchange
RABBITMQ_QUEUE=products_queue

# Service Auth
AUTH_SERVICE_URL=http://auth-service:8000
```

## Deploiement

**Configuration Docker** :
```yaml
Service: products-service
Port Mapping: 8001:8000
Database: products-mysql (port 3308 externe)
Depends On: products-mysql, rabbitmq, minio
Networks: e-commerce-network
Health Check: /health endpoint
```

**Ressources Kubernetes** :
- Deployment : 3 replicas minimum
- CPU Request : 200m, Limit : 500m
- Memory Request : 512Mi, Limit : 1Gi
- Service Type : ClusterIP
- PVC : Aucun (utilise MinIO pour stockage)
- ConfigMap : Configuration MinIO

**Configuration Health Check** :
- Liveness Probe : GET /health (intervalle 10s)
- Readiness Probe : Connectivite base de donnees + MinIO
- Startup Probe : timeout 60s pour migrations et donnees seed

## Optimisation Performance

**Strategie Mise en Cache** :
- Listings produits mis en cache (TTL 5 min)
- Arbre categories mis en cache (TTL 15 min)
- Listes marques et types mises en cache (TTL 30 min)
- Page details produit mise en cache (TTL 2 min)
- URLs presignees MinIO mises en cache (TTL 50 min)

**Optimisation Base de Donnees** :
- Index sur : slug, sku, barcode, brand_id, is_active
- Index composites pour requetes filtrage
- Chargement anticipe pour relations
- Pagination resultats requete

**Optimisation Images** :
- Chargement paresseux pour images produits
- Integration CDN (futur)
- Formats image responsifs (support WebP)
- Generation vignettes pour vues liste

## Integration avec Autres Services

**Service Paniers** :
- Verifications disponibilite produits
- Validation prix
- Recuperation details produit

**Service Commandes** :
- Reservation stock au placement commande
- Liberation stock a annulation commande
- Information produit pour lignes commande

**Service Recherche** (Futur) :
- Indexation produits pour Elasticsearch
- Classement resultats recherche
- Recommandations produits

## Surveillance et Observabilite

**Metriques a Suivre** :
- Taille catalogue produits (total produits)
- Performance requetes recherche
- Taux succes upload image
- Frequence mise a jour stock
- Utilisation stockage MinIO
- Performance requetes base de donnees

**Journalisation** :
- Operations CRUD produits
- Changements niveaux stock
- Evenements upload/suppression image
- Requetes recherche et resultats
- Operations MinIO echouees

## Ameliorations Futures

- Integration Elasticsearch pour recherche avancee
- Moteur recommandations produits
- Suggestions produits associes
- Avis et evaluations produits
- Variantes produits (combinaisons taille, couleur)
- Import/export masse (CSV, Excel)
- Lots et kits produits
- Regles tarification dynamique
- Fonctionnalite comparaison produits
- Integration liste souhaits
