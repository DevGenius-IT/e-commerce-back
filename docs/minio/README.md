# MinIO Object Storage

MinIO fournit un stockage d'objets S3-compatible pour la plateforme e-commerce.

## Architecture

```
API Gateway → Services → MinIO Storage
                         ├── products (images)
                         ├── sav (attachments)
                         └── newsletters (templates)
```

## Configuration

### Démarrage

```bash
# Workflow complet
make minio-workflow

# Ou étape par étape
make minio-start      # Démarrer MinIO
make minio-setup      # Créer buckets
make minio-health     # Vérifier santé
```

### Console Web

- **URL:** http://localhost:9001
- **User:** admin
- **Pass:** adminpass123

### Variables d'environnement

```bash
MINIO_ENDPOINT=minio:9000
MINIO_ROOT_USER=admin
MINIO_ROOT_PASSWORD=adminpass123
MINIO_USE_SSL=false
```

## Utilisation

### Shared Service

```php
use Shared\Services\MinioService;

$minio = new MinioService('products'); // ou 'sav', 'newsletters'

// Upload
$minio->uploadFile('path/file.jpg', $file, ['meta' => 'value']);

// Download
$data = $minio->getFile('path/file.jpg');

// Presigned URL (1h)
$url = $minio->getPresignedUrl('path/file.jpg', 3600);

// Delete
$minio->deleteFile('path/file.jpg');
```

### API Endpoints

**Products Service** (images + auto-thumbnails)
```bash
POST /api/products/{id}/images
GET  /api/products/{id}/images
DELETE /api/products/{id}/images/{imageId}
```

**SAV Service** (pièces jointes)
```bash
POST /api/sav/tickets/{id}/attachments
GET  /api/sav/tickets/{id}/attachments
GET  /api/sav/tickets/{id}/attachments/{attachmentId}
DELETE /api/sav/tickets/{id}/attachments/{attachmentId}
```

**Newsletters Service** (templates)
```bash
POST /api/newsletters/templates/assets
GET  /api/newsletters/templates
DELETE /api/newsletters/templates/{id}
```

## Commandes Make

```bash
# Gestion
make minio-workflow       # Workflow complet
make minio-start          # Démarrer
make minio-stop           # Arrêter
make minio-setup          # Créer buckets

# Monitoring
make minio-health         # Health check
make minio-console        # Console web
make minio-stats          # Statistiques

# Tests
make minio-validate       # Validation
make minio-test           # Tests intégration

# Maintenance
make minio-clean          # Nettoyer buckets
```

## Sécurité

- Sanitization des noms de fichiers
- Presigned URLs (accès temporaire 1h)
- Validation types MIME et tailles
- Buckets isolés par service
- Protection path traversal

**Production:** Changer credentials, activer SSL/TLS, configurer CDN et backups.

## Troubleshooting

### MinIO ne démarre pas
```bash
docker-compose logs minio
docker-compose restart minio
```

### Buckets manquants
```bash
./scripts/minio-setup-buckets.sh
```

### Erreur connexion
```bash
docker-compose exec products-service curl http://minio:9000/minio/health/live
```

## Documentation Technique

**Guide complet:** [MINIO.md](MINIO.md)

**Scripts:**
- `scripts/minio-health-check.sh` - Health monitoring
- `scripts/minio-setup-buckets.sh` - Bucket setup
- `scripts/validate-phase1-minio.sh` - Validation
- `scripts/test-minio-integration.sh` - Tests
