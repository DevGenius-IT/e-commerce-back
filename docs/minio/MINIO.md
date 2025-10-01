# MinIO Object Storage - Documentation Technique

## Vue d'ensemble

MinIO est le système de stockage d'objets S3-compatible de la plateforme e-commerce. Il gère les images produits, pièces jointes SAV, et templates newsletters.

## Architecture

### Infrastructure

```
┌─────────────────┐
│   API Gateway   │
└────────┬────────┘
         │
    ┌────┴────┬────────────┬─────────────┐
    ▼         ▼            ▼             
┌─────────┐ ┌─────┐  ┌────────────┐
│Products │ │ SAV │  │Newsletters │
└────┬────┘ └──┬──┘  └─────┬──────┘
     │         │            │
     └─────────┴────────────┘
                 │
            ┌────▼────┐
            │  MinIO  │
            │ Storage │
            └─────────┘
```

### Buckets

| Bucket | Usage | Formats |
|--------|-------|---------|
| `products` | Images produits | JPG, PNG, WebP |
| `sav` | Pièces jointes | PDF, images, ZIP |
| `newsletters` | Templates | HTML, CSS, JS, images |

## Configuration

### Docker Compose

```yaml
minio:
  image: minio/minio:latest
  ports:
    - "9000:9000"   # API
    - "9001:9001"   # Console
  environment:
    MINIO_ROOT_USER: admin
    MINIO_ROOT_PASSWORD: adminpass123
  volumes:
    - minio-data:/data
  command: server /data --console-address ":9001"
  healthcheck:
    test: ["CMD", "curl", "-f", "http://localhost:9000/minio/health/live"]
    interval: 30s
```

### Variables d'environnement

```bash
# Connexion MinIO
MINIO_ENDPOINT=minio:9000
MINIO_ROOT_USER=admin
MINIO_ROOT_PASSWORD=adminpass123
MINIO_USE_SSL=false
MINIO_REGION=us-east-1

# Buckets
MINIO_BUCKET_PRODUCTS=products
MINIO_BUCKET_SAV=sav
MINIO_BUCKET_NEWSLETTERS=newsletters
```

## Implémentation

### Shared MinioService

Service partagé S3-compatible utilisé par tous les microservices:

```php
namespace Shared\Services;

use Aws\S3\S3Client;

class MinioService
{
    private S3Client $client;
    private string $bucket;
    
    public function __construct(string $bucket)
    {
        $this->bucket = $bucket;
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => env('MINIO_REGION', 'us-east-1'),
            'endpoint' => env('MINIO_ENDPOINT', 'http://minio:9000'),
            'credentials' => [
                'key' => env('MINIO_ROOT_USER'),
                'secret' => env('MINIO_ROOT_PASSWORD'),
            ],
            'use_path_style_endpoint' => true,
        ]);
    }
    
    // Upload fichier
    public function uploadFile(string $key, $file, array $metadata = []): array
    
    // Télécharger fichier
    public function getFile(string $key): array
    
    // Supprimer fichier
    public function deleteFile(string $key): bool
    
    // URL temporaire (presigned)
    public function getPresignedUrl(string $key, int $expiration = 3600): string
    
    // Lister fichiers
    public function listFiles(string $prefix = '', int $maxKeys = 1000): array
    
    // Copier fichier
    public function copyFile(string $source, string $destination): bool
    
    // Vérifier existence
    public function fileExists(string $key): bool
    
    // Infos fichier
    public function getFileInfo(string $key): array
}
```

### Products Service - Images

**Controller:** `ProductImagesController`
- Upload images avec génération automatique de thumbnails
- Formats: original + 150x150 + 400x400
- Stockage dans bucket `products`

**Model:** `ProductImage`
```php
protected $fillable = [
    'product_id', 'original_url', 'thumbnail_url', 
    'medium_url', 'filename', 'type', 'alt_text'
];
```

**Endpoints:**
```bash
POST   /api/products/{id}/images      # Upload
GET    /api/products/{id}/images      # List
DELETE /api/products/{id}/images/{id} # Delete
```

### SAV Service - Attachments

**Controller:** `TicketAttachmentController`
- Upload pièces jointes avec presigned URLs
- Formats: PDF, images, ZIP
- Stockage dans bucket `sav`

**Sécurité:**
```php
private function sanitizeFilename(string $filename): string
{
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    return substr($filename, 0, 100);
}
```

**Endpoints:**
```bash
POST   /api/sav/tickets/{id}/attachments        # Upload
GET    /api/sav/tickets/{id}/attachments        # List
GET    /api/sav/tickets/{id}/attachments/{id}   # Download (presigned)
DELETE /api/sav/tickets/{id}/attachments/{id}   # Delete
```

### Newsletters Service - Templates

**Controller:** `TemplateAssetsController`
- Upload templates HTML/ZIP et assets
- Formats: HTML, CSS, JS, images, fonts
- Stockage dans bucket `newsletters`

**Endpoints:**
```bash
POST   /api/newsletters/templates/assets    # Upload
GET    /api/newsletters/templates           # List
POST   /api/newsletters/templates/{id}/preview
DELETE /api/newsletters/templates/{id}
```

## Sécurité

### Mesures implémentées

1. **Sanitization fichiers**
   - Suppression caractères spéciaux (regex)
   - Limite 100 caractères
   - Protection path traversal

2. **Presigned URLs**
   - Accès temporaire (1h par défaut)
   - URLs signées pour downloads sécurisés
   - Pas d'accès public direct

3. **Validation**
   - Types MIME autorisés
   - Tailles limites
   - Buckets isolés par service

### Production

Avant déploiement:
```bash
# Changer credentials
MINIO_ROOT_USER=<strong_username>
MINIO_ROOT_PASSWORD=<strong_password>

# Activer SSL/TLS
MINIO_USE_SSL=true

# Configurer backups et CDN
```

## Performance

| Métrique | Valeur |
|----------|--------|
| Démarrage container | ~30s |
| Health check | <100ms |
| Upload 1MB | <500ms |
| Presigned URL | ~50ms |
| List files | <150ms |

## Tests

### Validation

```bash
make minio-validate    # 26 checks: config, code, intégration
```

### Tests intégration

```bash
make minio-test        # 23 tests: infrastructure, API, storage
```

### Health check

```bash
make minio-health      # Vérifier statut MinIO et buckets
```

## Maintenance

### Scripts disponibles

```bash
scripts/minio-health-check.sh          # Monitoring santé
scripts/minio-setup-buckets.sh         # Création buckets
scripts/validate-phase1-minio.sh       # Validation complète
scripts/test-minio-integration.sh      # Tests intégration
scripts/install-aws-sdk.sh             # Installation SDK
```

### Commandes Make

```bash
# Workflow
make minio-workflow        # start + setup + validate + test

# Gestion
make minio-start          # Démarrer MinIO
make minio-stop           # Arrêter MinIO
make minio-setup          # Créer buckets

# Monitoring
make minio-health         # Health check
make minio-console        # Console web (http://localhost:9001)
make minio-stats          # Statistiques serveur

# Tests
make minio-validate       # Validation complète
make minio-test           # Tests intégration

# Maintenance
make minio-clean          # Nettoyer buckets
make minio-install-sdk    # Installer AWS SDK
```

## Troubleshooting

### MinIO ne démarre pas

```bash
# Vérifier logs
docker-compose logs minio

# Vérifier ports disponibles
lsof -i :9000
lsof -i :9001

# Redémarrer
docker-compose restart minio
```

### Buckets non créés

```bash
# Création manuelle
docker exec minio-storage mkdir -p /data/products /data/sav /data/newsletters

# Ou via script
./scripts/minio-setup-buckets.sh
```

### Erreurs AWS SDK

```bash
# Vérifier installation
docker-compose exec products-service composer show aws/aws-sdk-php

# Réinstaller si nécessaire
docker-compose exec products-service composer require aws/aws-sdk-php
```

### Erreurs de connexion

```bash
# Vérifier endpoint
docker-compose exec products-service env | grep MINIO

# Tester connectivité
docker-compose exec products-service curl http://minio:9000/minio/health/live
```

## Structure des fichiers

### Services

```
services/
├── products-service/
│   ├── app/Http/Controllers/API/ProductImagesController.php
│   ├── app/Models/ProductImage.php
│   └── database/migrations/..._create_product_images_table.php
├── sav-service/
│   └── app/Http/Controllers/API/TicketAttachmentController.php
├── newsletters-service/
│   └── app/Http/Controllers/API/TemplateAssetsController.php
└── shared/
    └── Services/MinioService.php
```

### Configuration

```
docker-compose.yml         # Service MinIO
.env                       # Variables MinIO
Makefile                   # Commandes MinIO
```

### Scripts

```
scripts/
├── minio-health-check.sh
├── minio-setup-buckets.sh
├── validate-phase1-minio.sh
├── test-minio-integration.sh
└── install-aws-sdk.sh
```

## Ressources

- **Console:** http://localhost:9001 (admin/adminpass123)
- **Guide utilisateur:** [README.md](README.md)
- **MinIO Docs:** https://min.io/docs/minio/linux/
- **AWS SDK PHP:** https://docs.aws.amazon.com/sdk-for-php/
