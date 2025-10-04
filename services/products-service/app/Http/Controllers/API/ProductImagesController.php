<?php

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
            'position' => 'integer|min:0',
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
                    'original_name' => $file->getClientOriginalName(),
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
                'mime_type' => $file->getMimeType(),
            ]);

            return response()->json([
                'success' => true,
                'image' => $productImage,
                'urls' => [
                    'original' => $uploadResult['url'],
                    'thumbnail' => $thumbnails['thumbnail'] ?? null,
                    'medium' => $thumbnails['medium'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Upload failed',
                'message' => $e->getMessage(),
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
                        'thumbnail' => $image->thumbnail_url,
                    ],
                    'alt_text' => $image->alt_text,
                    'position' => $image->position,
                    'created_at' => $image->created_at,
                ];
            });

        return response()->json([
            'product_id' => $productId,
            'images' => $images,
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
                'message' => 'Image deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Delete failed',
                'message' => $e->getMessage(),
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
            \Log::error('Thumbnail generation failed: '.$e->getMessage());
        }

        return $thumbnails;
    }

    private function extractKeyFromUrl(string $url): string
    {
        $parts = parse_url($url);

        return ltrim($parts['path'], '/products/');
    }
}
