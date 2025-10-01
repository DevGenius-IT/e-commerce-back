# 📋 Plan de Développement E-commerce - Architecture Moderne avec Minio

## 🏗️ Architecture Microservices Fully Asynchrone + Stockage

### 🎯 **ARCHITECTURE COMPLÈTE : RabbitMQ + MinIO**

Cette plateforme e-commerce utilise une **architecture entièrement asynchrone** avec stockage de fichiers distribué via **MinIO**.

#### 🚀 **Flux de Communication et Stockage**
```
Client → Nginx → API Gateway → RabbitMQ → Services
                                    ↓
                            MinIO Storage
                        (Images, Documents, Assets)
```

**Architecture hybride : Communication async + Stockage centralisé**

---

## 🗂️ INTÉGRATION MINIO - STOCKAGE DE FICHIERS

### 📦 Configuration MinIO Container

#### Architecture de Stockage
```
Services → MinIO Container → Persistent Storage
├── products-service    → Images produits, catalogues PDF
├── newsletters-service → Templates HTML, attachements emails
├── sav-service        → Documents joints tickets
├── contacts-service   → Formulaires avec fichiers
├── websites-service   → Assets, logos, images sites
└── shared-assets      → Ressources communes
```

#### Configuration Docker Compose
```yaml
# docker-compose.yml - Ajout MinIO
services:
  minio:
    image: minio/minio:latest
    container_name: minio-storage
    environment:
      MINIO_ROOT_USER: admin
      MINIO_ROOT_PASSWORD: adminpass123
      MINIO_DEFAULT_BUCKETS: "products,newsletters,support,contacts,websites,shared"
    ports:
      - "9000:9000"      # API MinIO
      - "9001:9001"      # Console Web
    volumes:
      - minio-data:/data
    command: server /data --console-address ":9001"
    networks:
      - microservices-network
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:9000/minio/health/live"]
      interval: 30s
      timeout: 20s
      retries: 3

  # Services existants avec accès MinIO
  products-service:
    environment:
      - MINIO_ENDPOINT=http://minio:9000
      - MINIO_BUCKET=products
    depends_on:
      - minio
      
  sav-service:
    environment:
      - MINIO_ENDPOINT=http://minio:9000
      - MINIO_BUCKET=support
    depends_on:
      - minio

volumes:
  minio-data:
    driver: local
```

#### Buckets par Service
```bash
# Structure des buckets MinIO
products/        → Images produits, catalogues, fiches techniques
├── images/      → Photos produits haute résolution
├── thumbnails/  → Miniatures optimisées
├── catalogs/    → PDF catalogues
└── specs/       → Fiches techniques

newsletters/     → Templates et assets emails
├── templates/   → Templates HTML
├── assets/      → Images, logos emails
└── attachments/ → Documents joints

support/         → Documents SAV
├── tickets/     → Attachements tickets
├── kb/          → Base de connaissances
└── forms/       → Formulaires PDF

contacts/        → Documents formulaires
├── attachments/ → Fichiers joints contacts
└── forms/       → Données formulaires

websites/        → Assets sites web
├── logos/       → Logos sites
├── images/      → Images génériques
├── documents/   → Documents téléchargeables
└── themes/      → Thèmes/templates

shared/          → Ressources communes
├── icons/       → Icônes réutilisables
├── fonts/       → Polices personnalisées
└── templates/   → Templates partagés
```

### 🔧 Shared MinIO Service

#### Service MinIO Partagé
```php
<?php
// shared/Services/MinioService.php

namespace Shared\Services;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Illuminate\Http\UploadedFile;

class MinioService
{
    private S3Client $client;
    private string $bucket;
    private string $endpoint;
    
    public function __construct(string $bucket)
    {
        $this->bucket = $bucket;
        $this->endpoint = env('MINIO_ENDPOINT', 'http://minio:9000');
        
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => $this->endpoint,
            'credentials' => [
                'key' => env('MINIO_ACCESS_KEY', 'admin'),
                'secret' => env('MINIO_SECRET_KEY', 'adminpass123'),
            ],
            'use_path_style_endpoint' => true,
        ]);
    }
    
    /**
     * Upload un fichier avec métadonnées
     */
    public function uploadFile(string $key, $file, array $metadata = []): array
    {
        try {
            $body = $file instanceof UploadedFile 
                ? fopen($file->getRealPath(), 'r')
                : $file;
                
            $result = $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => $body,
                'Metadata' => $metadata,
                'ContentType' => $this->getMimeType($file)
            ]);
            
            return [
                'url' => $this->getPublicUrl($key),
                'etag' => $result['ETag'],
                'key' => $key,
                'bucket' => $this->bucket,
                'size' => $this->getFileSize($file)
            ];
            
        } catch (AwsException $e) {
            throw new \Exception('Upload failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Télécharger un fichier
     */
    public function getFile(string $key): array
    {
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $key
            ]);
            
            return [
                'content' => $result['Body']->getContents(),
                'metadata' => $result['Metadata'] ?? [],
                'size' => $result['ContentLength'],
                'content_type' => $result['ContentType'],
                'last_modified' => $result['LastModified']
            ];
            
        } catch (AwsException $e) {
            throw new \Exception('Download failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Supprimer un fichier
     */
    public function deleteFile(string $key): bool
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key
            ]);
            return true;
            
        } catch (AwsException $e) {
            \Log::error('MinIO delete failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Générer URL présignée pour accès temporaire
     */
    public function getPresignedUrl(string $key, int $expiration = 3600): string
    {
        $cmd = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key' => $key
        ]);
        
        $request = $this->client->createPresignedRequest($cmd, "+{$expiration} seconds");
        return (string) $request->getUri();
    }
    
    /**
     * Lister les fichiers d'un dossier
     */
    public function listFiles(string $prefix = '', int $maxKeys = 1000): array
    {
        try {
            $result = $this->client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
                'MaxKeys' => $maxKeys
            ]);
            
            $files = [];
            foreach ($result['Contents'] ?? [] as $object) {
                $files[] = [
                    'key' => $object['Key'],
                    'size' => $object['Size'],
                    'last_modified' => $object['LastModified'],
                    'etag' => $object['ETag'],
                    'url' => $this->getPublicUrl($object['Key'])
                ];
            }
            
            return $files;
            
        } catch (AwsException $e) {
            throw new \Exception('List files failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Copier un fichier vers un autre emplacement
     */
    public function copyFile(string $sourceKey, string $destinationKey): bool
    {
        try {
            $this->client->copyObject([
                'Bucket' => $this->bucket,
                'Key' => $destinationKey,
                'CopySource' => $this->bucket . '/' . $sourceKey
            ]);
            return true;
            
        } catch (AwsException $e) {
            return false;
        }
    }
    
    /**
     * Obtenir URL publique
     */
    public function getPublicUrl(string $key): string
    {
        return $this->endpoint . '/' . $this->bucket . '/' . $key;
    }
    
    /**
     * Vérifier si un fichier existe
     */
    public function fileExists(string $key): bool
    {
        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key
            ]);
            return true;
            
        } catch (AwsException $e) {
            return false;
        }
    }
    
    /**
     * Obtenir informations fichier
     */
    public function getFileInfo(string $key): array
    {
        try {
            $result = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key
            ]);
            
            return [
                'size' => $result['ContentLength'],
                'content_type' => $result['ContentType'],
                'last_modified' => $result['LastModified'],
                'metadata' => $result['Metadata'] ?? [],
                'etag' => $result['ETag']
            ];
            
        } catch (AwsException $e) {
            throw new \Exception('File info failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Helpers privés
     */
    private function getMimeType($file): string
    {
        if ($file instanceof UploadedFile) {
            return $file->getMimeType();
        }
        
        return 'application/octet-stream';
    }
    
    private function getFileSize($file): int
    {
        if ($file instanceof UploadedFile) {
            return $file->getSize();
        }
        
        return 0;
    }
}
```

### 🛍️ Products Service - Intégration Images

#### Controller Gestion Images Produits
```php
<?php
// services/products-service/app/Http/Controllers/API/ProductImagesController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Shared\Services\MinioService;
use Intervention\Image\Facades\Image;

class ProductImagesController extends Controller
{
    private MinioService $minioService;
    
    public function __construct()
    {
        $this->minioService = new MinioService('products');
    }
    
    /**
     * Upload image produit avec génération miniatures
     */
    public function uploadImage(Request $request, int $productId)
    {
        $request->validate([
            'image' => 'required|image|max:5120', // 5MB max
            'type' => 'required|in:main,gallery,thumbnail',
            'alt_text' => 'string|max:255',
            'position' => 'integer|min:0'
        ]);
        
        $product = Product::findOrFail($productId);
        $file = $request->file('image');
        
        try {
            // Générer nom unique
            $timestamp = now()->timestamp;
            $extension = $file->getClientOriginalExtension();
            $filename = "images/{$productId}/original_{$timestamp}.{$extension}";
            
            // Upload image originale
            $uploadResult = $this->minioService->uploadFile(
                $filename,
                $file,
                [
                    'product_id' => (string) $productId,
                    'type' => $request->type,
                    'alt_text' => $request->alt_text ?? '',
                    'uploaded_by' => auth()->id() ?? 'system',
                    'original_name' => $file->getClientOriginalName()
                ]
            );
            
            // Générer miniatures
            $thumbnails = $this->generateThumbnails($file, $productId, $timestamp);
            
            // Sauvegarder en base
            $productImage = ProductImage::create([
                'product_id' => $productId,
                'original_url' => $uploadResult['url'],
                'thumbnail_url' => $thumbnails['thumbnail'] ?? null,
                'medium_url' => $thumbnails['medium'] ?? null,
                'filename' => $filename,
                'type' => $request->type,
                'alt_text' => $request->alt_text,
                'position' => $request->position ?? 0,
                'size' => $uploadResult['size'],
                'mime_type' => $file->getMimeType()
            ]);
            
            return response()->json([
                'success' => true,
                'image' => $productImage,
                'urls' => [
                    'original' => $uploadResult['url'],
                    'thumbnail' => $thumbnails['thumbnail'] ?? null,
                    'medium' => $thumbnails['medium'] ?? null
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Upload failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Récupérer images d'un produit
     */
    public function getProductImages(int $productId)
    {
        $product = Product::findOrFail($productId);
        
        $images = ProductImage::where('product_id', $productId)
            ->orderBy('position')
            ->orderBy('created_at')
            ->get()
            ->map(function ($image) {
                return [
                    'id' => $image->id,
                    'type' => $image->type,
                    'urls' => [
                        'original' => $image->original_url,
                        'medium' => $image->medium_url,
                        'thumbnail' => $image->thumbnail_url
                    ],
                    'alt_text' => $image->alt_text,
                    'position' => $image->position,
                    'created_at' => $image->created_at
                ];
            });
            
        return response()->json([
            'product_id' => $productId,
            'images' => $images
        ]);
    }
    
    /**
     * Supprimer une image
     */
    public function deleteImage(int $productId, int $imageId)
    {
        $image = ProductImage::where('product_id', $productId)
            ->where('id', $imageId)
            ->firstOrFail();
            
        try {
            // Supprimer de MinIO
            $this->minioService->deleteFile($image->filename);
            
            // Supprimer miniatures
            if ($image->thumbnail_url) {
                $thumbnailKey = $this->extractKeyFromUrl($image->thumbnail_url);
                $this->minioService->deleteFile($thumbnailKey);
            }
            
            if ($image->medium_url) {
                $mediumKey = $this->extractKeyFromUrl($image->medium_url);
                $this->minioService->deleteFile($mediumKey);
            }
            
            // Supprimer de la base
            $image->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Delete failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Générer miniatures
     */
    private function generateThumbnails($file, int $productId, int $timestamp): array
    {
        $thumbnails = [];
        
        try {
            $image = Image::make($file->getRealPath());
            
            // Thumbnail 150x150
            $thumbnail = clone $image;
            $thumbnail->fit(150, 150);
            $thumbnailKey = "images/{$productId}/thumbnail_{$timestamp}.jpg";
            
            $thumbnailResult = $this->minioService->uploadFile(
                $thumbnailKey,
                $thumbnail->encode('jpg', 80)->getEncoded(),
                ['type' => 'thumbnail', 'generated_from' => 'original']
            );
            $thumbnails['thumbnail'] = $thumbnailResult['url'];
            
            // Medium 400x400
            $medium = clone $image;
            $medium->fit(400, 400);
            $mediumKey = "images/{$productId}/medium_{$timestamp}.jpg";
            
            $mediumResult = $this->minioService->uploadFile(
                $mediumKey,
                $medium->encode('jpg', 85)->getEncoded(),
                ['type' => 'medium', 'generated_from' => 'original']
            );
            $thumbnails['medium'] = $mediumResult['url'];
            
        } catch (\Exception $e) {
            \Log::error('Thumbnail generation failed: ' . $e->getMessage());
        }
        
        return $thumbnails;
    }
    
    private function extractKeyFromUrl(string $url): string
    {
        $parts = parse_url($url);
        return ltrim($parts['path'], '/products/');
    }
}
```

#### Model ProductImage
```php
<?php
// services/products-service/app/Models/ProductImage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'original_url',
        'thumbnail_url', 
        'medium_url',
        'filename',
        'type',
        'alt_text',
        'position',
        'size',
        'mime_type'
    ];
    
    protected $casts = [
        'position' => 'integer',
        'size' => 'integer'
    ];
    
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    
    /**
     * Scope pour images principales
     */
    public function scopeMain($query)
    {
        return $query->where('type', 'main');
    }
    
    /**
     * Scope pour galerie
     */
    public function scopeGallery($query)
    {
        return $query->where('type', 'gallery');
    }
}
```

#### Migration ProductImages
```php
<?php
// services/products-service/database/migrations/create_product_images_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('original_url');
            $table->string('thumbnail_url')->nullable();
            $table->string('medium_url')->nullable();
            $table->string('filename');
            $table->enum('type', ['main', 'gallery', 'thumbnail'])->default('gallery');
            $table->string('alt_text')->nullable();
            $table->integer('position')->default(0);
            $table->bigInteger('size'); // Size in bytes
            $table->string('mime_type');
            $table->timestamps();
            
            $table->index(['product_id', 'type']);
            $table->index(['product_id', 'position']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_images');
    }
};
```

### 🛠️ SAV Service - Attachments

#### Controller Attachments Tickets
```php
<?php
// services/sav-service/app/Http/Controllers/API/TicketAttachmentsController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\TicketAttachment;
use Illuminate\Http\Request;
use Shared\Services\MinioService;

class TicketAttachmentsController extends Controller
{
    private MinioService $minioService;
    
    public function __construct()
    {
        $this->minioService = new MinioService('support');
    }
    
    /**
     * Upload attachment pour ticket
     */
    public function uploadAttachment(Request $request, int $ticketId)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'description' => 'string|max:255'
        ]);
        
        $ticket = SupportTicket::findOrFail($ticketId);
        
        // Vérifier permissions
        if (!$this->canAccessTicket($ticket)) {
            return response()->json(['error' => 'Access denied'], 403);
        }
        
        $file = $request->file('file');
        
        try {
            // Générer chemin sécurisé
            $filename = "tickets/{$ticketId}/" . uniqid() . '_' . $this->sanitizeFilename($file->getClientOriginalName());
            
            // Upload vers MinIO
            $uploadResult = $this->minioService->uploadFile(
                $filename,
                $file,
                [
                    'ticket_id' => (string) $ticketId,
                    'uploaded_by' => auth()->id(),
                    'description' => $request->description ?? '',
                    'original_name' => $file->getClientOriginalName(),
                    'upload_ip' => request()->ip()
                ]
            );
            
            // Sauvegarder en base
            $attachment = TicketAttachment::create([
                'ticket_id' => $ticketId,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'url' => $uploadResult['url'],
                'size' => $uploadResult['size'],
                'mime_type' => $file->getMimeType(),
                'description' => $request->description,
                'uploaded_by' => auth()->id()
            ]);
            
            // Log activité
            $this->logTicketActivity($ticketId, 'attachment_added', [
                'filename' => $file->getClientOriginalName(),
                'size' => $uploadResult['size']
            ]);
            
            return response()->json([
                'success' => true,
                'attachment' => [
                    'id' => $attachment->id,
                    'filename' => $attachment->original_name,
                    'size' => $attachment->size,
                    'mime_type' => $attachment->mime_type,
                    'description' => $attachment->description,
                    'download_url' => route('api.sav.tickets.attachments.download', [
                        'ticketId' => $ticketId,
                        'attachmentId' => $attachment->id
                    ]),
                    'uploaded_at' => $attachment->created_at
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Upload failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Télécharger attachment
     */
    public function downloadAttachment(int $ticketId, int $attachmentId)
    {
        $ticket = SupportTicket::findOrFail($ticketId);
        $attachment = TicketAttachment::where('ticket_id', $ticketId)
            ->where('id', $attachmentId)
            ->firstOrFail();
            
        if (!$this->canAccessTicket($ticket)) {
            return response()->json(['error' => 'Access denied'], 403);
        }
        
        try {
            // Générer URL présignée (valide 1h)
            $presignedUrl = $this->minioService->getPresignedUrl($attachment->filename, 3600);
            
            // Log téléchargement
            $this->logTicketActivity($ticketId, 'attachment_downloaded', [
                'filename' => $attachment->original_name,
                'downloaded_by' => auth()->id()
            ]);
            
            return redirect($presignedUrl);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Download failed',
                'message' => 'File not found or corrupted'
            ], 404);
        }
    }
    
    /**
     * Lister attachments d'un ticket
     */
    public function listAttachments(int $ticketId)
    {
        $ticket = SupportTicket::findOrFail($ticketId);
        
        if (!$this->canAccessTicket($ticket)) {
            return response()->json(['error' => 'Access denied'], 403);
        }
        
        $attachments = TicketAttachment::where('ticket_id', $ticketId)
            ->with('uploadedBy:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($attachment) use ($ticketId) {
                return [
                    'id' => $attachment->id,
                    'filename' => $attachment->original_name,
                    'size' => $attachment->size,
                    'size_human' => $this->formatBytes($attachment->size),
                    'mime_type' => $attachment->mime_type,
                    'description' => $attachment->description,
                    'uploaded_by' => $attachment->uploadedBy->name ?? 'Unknown',
                    'uploaded_at' => $attachment->created_at,
                    'download_url' => route('api.sav.tickets.attachments.download', [
                        'ticketId' => $ticketId,
                        'attachmentId' => $attachment->id
                    ])
                ];
            });
            
        return response()->json([
            'ticket_id' => $ticketId,
            'attachments' => $attachments,
            'total_count' => $attachments->count(),
            'total_size' => $attachments->sum('size'),
            'total_size_human' => $this->formatBytes($attachments->sum('size'))
        ]);
    }
    
    /**
     * Supprimer attachment
     */
    public function deleteAttachment(int $ticketId, int $attachmentId)
    {
        $ticket = SupportTicket::findOrFail($ticketId);
        $attachment = TicketAttachment::where('ticket_id', $ticketId)
            ->where('id', $attachmentId)
            ->firstOrFail();
            
        if (!$this->canAccessTicket($ticket) || !$this->canDeleteAttachment($attachment)) {
            return response()->json(['error' => 'Access denied'], 403);
        }
        
        try {
            // Supprimer de MinIO
            $this->minioService->deleteFile($attachment->filename);
            
            // Log suppression
            $this->logTicketActivity($ticketId, 'attachment_deleted', [
                'filename' => $attachment->original_name,
                'deleted_by' => auth()->id()
            ]);
            
            // Supprimer de la base
            $attachment->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Delete failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Helpers privés
     */
    private function canAccessTicket(SupportTicket $ticket): bool
    {
        $user = auth()->user();
        
        // Admin ou agent SAV
        if ($user->hasRole(['admin', 'support_agent'])) {
            return true;
        }
        
        // Propriétaire du ticket
        return $ticket->user_id === $user->id;
    }
    
    private function canDeleteAttachment(TicketAttachment $attachment): bool
    {
        $user = auth()->user();
        
        // Admin peut tout supprimer
        if ($user->hasRole('admin')) {
            return true;
        }
        
        // Agent SAV peut supprimer ses uploads
        if ($user->hasRole('support_agent') && $attachment->uploaded_by === $user->id) {
            return true;
        }
        
        // Client peut supprimer ses uploads dans les 24h
        if ($attachment->uploaded_by === $user->id && $attachment->created_at->diffInHours() < 24) {
            return true;
        }
        
        return false;
    }
    
    private function sanitizeFilename(string $filename): string
    {
        // Supprimer caractères dangereux
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        return substr($filename, 0, 100); // Limiter longueur
    }
    
    private function logTicketActivity(int $ticketId, string $action, array $data = []): void
    {
        \App\Models\TicketActivity::create([
            'ticket_id' => $ticketId,
            'user_id' => auth()->id(),
            'action' => $action,
            'data' => $data,
            'ip_address' => request()->ip()
        ]);
    }
    
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
```

### 📧 Newsletters Service - Templates

#### Controller Templates Newsletters
```php
<?php
// services/newsletters-service/app/Http/Controllers/API/TemplateAssetsController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\NewsletterTemplate;
use Illuminate\Http\Request;
use Shared\Services\MinioService;

class TemplateAssetsController extends Controller
{
    private MinioService $minioService;
    
    public function __construct()
    {
        $this->minioService = new MinioService('newsletters');
    }
    
    /**
     * Upload template newsletter
     */
    public function uploadTemplate(Request $request)
    {
        $request->validate([
            'template' => 'required|file|mimes:html,zip|max:5120',
            'name' => 'required|string|max:255|unique:newsletter_templates,name',
            'category' => 'required|in:promotion,notification,welcome,transactional',
            'description' => 'string|max:500'
        ]);
        
        $file = $request->file('template');
        
        try {
            $filename = "templates/" . Str::slug($request->name) . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            // Upload template
            $uploadResult = $this->minioService->uploadFile(
                $filename,
                $file,
                [
                    'name' => $request->name,
                    'category' => $request->category,
                    'created_by' => auth()->id(),
                    'version' => '1.0'
                ]
            );
            
            // Analyser template si HTML
            $templateData = $this->analyzeTemplate($file);
            
            // Sauvegarder en base
            $template = NewsletterTemplate::create([
                'name' => $request->name,
                'category' => $request->category,
                'description' => $request->description,
                'filename' => $filename,
                'url' => $uploadResult['url'],
                'file_type' => $file->getClientOriginalExtension(),
                'size' => $uploadResult['size'],
                'variables' => $templateData['variables'] ?? [],
                'preview_image' => $templateData['preview'] ?? null,
                'created_by' => auth()->id(),
                'is_active' => true
            ]);
            
            return response()->json([
                'success' => true,
                'template' => $template,
                'upload_info' => $uploadResult
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Template upload failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Upload asset pour template (images, CSS, etc.)
     */
    public function uploadAsset(Request $request)
    {
        $request->validate([
            'asset' => 'required|file|mimes:jpg,jpeg,png,gif,css,js|max:2048',
            'folder' => 'string|in:images,css,js,fonts',
            'description' => 'string|max:255'
        ]);
        
        $file = $request->file('asset');
        $folder = $request->folder ?? 'images';
        
        try {
            $filename = "assets/{$folder}/" . uniqid() . '_' . $file->getClientOriginalName();
            
            $uploadResult = $this->minioService->uploadFile(
                $filename,
                $file,
                [
                    'folder' => $folder,
                    'description' => $request->description ?? '',
                    'uploaded_by' => auth()->id()
                ]
            );
            
            return response()->json([
                'success' => true,
                'asset' => [
                    'filename' => $filename,
                    'url' => $uploadResult['url'],
                    'public_url' => $this->minioService->getPublicUrl($filename),
                    'size' => $uploadResult['size'],
                    'type' => $file->getMimeType()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Asset upload failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Générer aperçu template
     */
    public function generatePreview(int $templateId, Request $request)
    {
        $template = NewsletterTemplate::findOrFail($templateId);
        
        try {
            // Récupérer template depuis MinIO
            $templateContent = $this->minioService->getFile($template->filename);
            
            // Remplacer variables par valeurs de test
            $testData = $request->get('test_data', []);
            $previewHtml = $this->renderTemplatePreview($templateContent['content'], $testData);
            
            // Générer screenshot avec Puppeteer ou retourner HTML
            $previewUrl = $this->generatePreviewScreenshot($previewHtml, $templateId);
            
            return response()->json([
                'success' => true,
                'preview_url' => $previewUrl,
                'html_preview' => $previewHtml
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Preview generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Lister templates disponibles
     */
    public function listTemplates(Request $request)
    {
        $query = NewsletterTemplate::query()
            ->where('is_active', true)
            ->with('createdBy:id,name');
            
        if ($request->category) {
            $query->where('category', $request->category);
        }
        
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
            });
        }
        
        $templates = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'category' => $template->category,
                    'description' => $template->description,
                    'preview_image' => $template->preview_image,
                    'file_type' => $template->file_type,
                    'size' => $template->size,
                    'variables' => $template->variables,
                    'created_by' => $template->createdBy->name ?? 'Unknown',
                    'created_at' => $template->created_at,
                    'download_url' => route('api.newsletters.templates.download', $template->id)
                ];
            });
            
        return response()->json($templates);
    }
    
    /**
     * Helpers privés
     */
    private function analyzeTemplate($file): array
    {
        $data = ['variables' => []];
        
        if ($file->getClientOriginalExtension() === 'html') {
            $content = file_get_contents($file->getRealPath());
            
            // Extraire variables {{ variable }}
            preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $content, $matches);
            $data['variables'] = array_unique($matches[1]);
        }
        
        return $data;
    }
    
    private function renderTemplatePreview(string $template, array $data): string
    {
        // Simple template rendering
        foreach ($data as $key => $value) {
            $template = str_replace("{{ {$key} }}", $value, $template);
        }
        
        // Remplacer variables manquantes par placeholder
        $template = preg_replace('/\{\{\s*(\w+)\s*\}\}/', '[{{ $1 }}]', $template);
        
        return $template;
    }
    
    private function generatePreviewScreenshot(string $html, int $templateId): ?string
    {
        // Ici on pourrait utiliser Puppeteer ou une API de screenshot
        // Pour l'instant, on retourne null
        return null;
    }
}
```

### 🔒 Sécurité et Configuration

#### Variables d'Environment
```env
# MinIO Configuration
MINIO_ENDPOINT=http://minio:9000
MINIO_ACCESS_KEY=admin
MINIO_SECRET_KEY=adminpass123

# Service-specific buckets
MINIO_BUCKET_PRODUCTS=products
MINIO_BUCKET_SUPPORT=support
MINIO_BUCKET_NEWSLETTERS=newsletters
MINIO_BUCKET_CONTACTS=contacts
MINIO_BUCKET_WEBSITES=websites
MINIO_BUCKET_SHARED=shared

# File Upload Configuration
MAX_FILE_SIZE_MB=10
MAX_IMAGE_SIZE_MB=5
ALLOWED_IMAGE_TYPES=jpg,jpeg,png,gif,webp
ALLOWED_DOCUMENT_TYPES=pdf,doc,docx,txt,zip,html
ALLOWED_ARCHIVE_TYPES=zip,tar,gz

# Security
MINIO_SECURE=false  # true pour HTTPS
MINIO_REGION=us-east-1
```

#### Politique de Sécurité Buckets
```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {"AWS": ["arn:aws:iam::*:user/products-service"]},
      "Action": ["s3:GetObject", "s3:PutObject", "s3:DeleteObject"],
      "Resource": ["arn:aws:s3:::products/*"]
    },
    {
      "Effect": "Allow", 
      "Principal": {"AWS": ["arn:aws:iam::*:user/support-service"]},
      "Action": ["s3:GetObject", "s3:PutObject"],
      "Resource": ["arn:aws:s3:::support/*"]
    },
    {
      "Effect": "Deny",
      "Principal": "*",
      "Action": "s3:*",
      "Resource": ["arn:aws:s3:::support/tickets/*/private/*"]
    }
  ]
}
```

### 🧪 Tests MinIO Integration

#### Health Checks
```bash
# Vérifier MinIO
curl http://localhost:9000/minio/health/live

# Console Web MinIO
open http://localhost:9001
# Login: admin / adminpass123

# Vérifier buckets
curl -X GET http://localhost:9000/
```

#### Tests API avec fichiers
```bash
# Upload image produit
curl -X POST http://localhost/api/v1/products/1/images \
  -H "Authorization: Bearer {jwt_token}" \
  -F "image=@product.jpg" \
  -F "type=main" \
  -F "alt_text=Image principale du produit"

# Upload document SAV
curl -X POST http://localhost/api/v1/sav/tickets/1/attachments \
  -H "Authorization: Bearer {jwt_token}" \
  -F "file=@facture.pdf" \
  -F "description=Facture d'achat"

# Upload template newsletter
curl -X POST http://localhost/api/v1/newsletters/templates \
  -H "Authorization: Bearer {jwt_token}" \
  -F "template=@newsletter.html" \
  -F "name=Black Friday 2025" \
  -F "category=promotion"

# Upload asset newsletter
curl -X POST http://localhost/api/v1/newsletters/assets \
  -H "Authorization: Bearer {jwt_token}" \
  -F "asset=@logo.png" \
  -F "folder=images" \
  -F "description=Logo de l'entreprise"
```

### 📊 Monitoring et Maintenance

#### Scripts de Maintenance
```bash
#!/bin/bash
# scripts/minio-maintenance.sh

# Nettoyage fichiers temporaires > 7 jours
mc find minio-local/temp --older-than 7d --exec "mc rm {}"

# Archivage tickets résolus > 1 an
mc mirror minio-local/support/resolved/ minio-local/archive/support/

# Compression logs > 30 jours
find /data/newsletters/logs -name "*.log" -mtime +30 -exec gzip {} \;

# Statistiques storage
mc admin info minio-local --json | jq '.info.storage'

# Backup buckets critiques
mc mirror minio-local/products minio-backup/products-$(date +%Y%m%d)
mc mirror minio-local/support minio-backup/support-$(date +%Y%m%d)
```

#### Métriques MinIO
```bash
# Usage par bucket
mc du minio-local

# Objets par bucket
mc ls minio-local --recursive | wc -l

# Taille totale
mc admin info minio-local | grep "Storage Usage"
```

---

## 📋 ARCHITECTURE FINALE

### 🎯 Services avec MinIO intégré
- ✅ **products-service** → Images, catalogues, fiches techniques
- ✅ **sav-service** → Attachements tickets, base de connaissances
- ✅ **newsletters-service** → Templates, assets emails
- ✅ **contacts-service** → Documents joints formulaires
- ✅ **websites-service** → Assets sites, logos, images
- ✅ **shared assets** → Ressources communes

### 🔧 Stack Technologique Complète
```
Frontend → Nginx → API Gateway → RabbitMQ → Microservices
                                     ↓           ↓
                                MinIO Storage ← Files
```

### 📊 Métriques Attendues
- **Storage**: 100GB+ avec auto-scaling
- **Upload**: < 2s pour images, < 5s pour documents
- **Download**: < 1s avec URLs présignées
- **Availability**: 99.9% uptime MinIO
- **Security**: Encryption at rest + in transit

---

## 🚀 NEXT STEPS

### Phase 1 : Déploiement MinIO ✅
- [x] Configuration Docker Compose
- [x] Integration services critiques
- [x] Tests et validation

### Phase 2 : Optimisations
- [ ] CDN intégration pour assets statiques
- [ ] Compression automatique images
- [ ] Versioning fichiers
- [ ] Backup automatique

### Phase 3 : Intelligence
- [ ] Analyse automatique images (IA)
- [ ] Optimisation SEO images
- [ ] Analytics usage fichiers
- [ ] Recommandations stockage

**🎉 Plateforme e-commerce avec stockage distribué opérationnelle ! 🚀**