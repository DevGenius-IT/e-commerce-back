<?php

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
                'ContentType' => $this->getMimeType($file),
            ]);

            return [
                'url' => $this->getPublicUrl($key),
                'etag' => $result['ETag'],
                'key' => $key,
                'bucket' => $this->bucket,
                'size' => $this->getFileSize($file),
            ];

        } catch (AwsException $e) {
            throw new \Exception('Upload failed: '.$e->getMessage());
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
                'Key' => $key,
            ]);

            return [
                'content' => $result['Body']->getContents(),
                'metadata' => $result['Metadata'] ?? [],
                'size' => $result['ContentLength'],
                'content_type' => $result['ContentType'],
                'last_modified' => $result['LastModified'],
            ];

        } catch (AwsException $e) {
            throw new \Exception('Download failed: '.$e->getMessage());
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
                'Key' => $key,
            ]);

            return true;

        } catch (AwsException $e) {
            \Log::error('MinIO delete failed: '.$e->getMessage());

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
            'Key' => $key,
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
                'MaxKeys' => $maxKeys,
            ]);

            $files = [];
            foreach ($result['Contents'] ?? [] as $object) {
                $files[] = [
                    'key' => $object['Key'],
                    'size' => $object['Size'],
                    'last_modified' => $object['LastModified'],
                    'etag' => $object['ETag'],
                    'url' => $this->getPublicUrl($object['Key']),
                ];
            }

            return $files;

        } catch (AwsException $e) {
            throw new \Exception('List files failed: '.$e->getMessage());
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
                'CopySource' => $this->bucket.'/'.$sourceKey,
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
        return $this->endpoint.'/'.$this->bucket.'/'.$key;
    }

    /**
     * Vérifier si un fichier existe
     */
    public function fileExists(string $key): bool
    {
        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
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
                'Key' => $key,
            ]);

            return [
                'size' => $result['ContentLength'],
                'content_type' => $result['ContentType'],
                'last_modified' => $result['LastModified'],
                'metadata' => $result['Metadata'] ?? [],
                'etag' => $result['ETag'],
            ];

        } catch (AwsException $e) {
            throw new \Exception('File info failed: '.$e->getMessage());
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
