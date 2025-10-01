<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\NewsletterTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            'description' => 'string|max:500',
        ]);

        $file = $request->file('template');

        try {
            $filename = 'templates/'.Str::slug($request->name).'_'.uniqid().'.'.$file->getClientOriginalExtension();

            // Upload template
            $uploadResult = $this->minioService->uploadFile(
                $filename,
                $file,
                [
                    'name' => $request->name,
                    'category' => $request->category,
                    'created_by' => auth()->id(),
                    'version' => '1.0',
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
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'template' => $template,
                'upload_info' => $uploadResult,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Template upload failed',
                'message' => $e->getMessage(),
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
            'description' => 'string|max:255',
        ]);

        $file = $request->file('asset');
        $folder = $request->folder ?? 'images';

        try {
            $filename = "assets/{$folder}/".uniqid().'_'.$file->getClientOriginalName();

            $uploadResult = $this->minioService->uploadFile(
                $filename,
                $file,
                [
                    'folder' => $folder,
                    'description' => $request->description ?? '',
                    'uploaded_by' => auth()->id(),
                ]
            );

            return response()->json([
                'success' => true,
                'asset' => [
                    'filename' => $filename,
                    'url' => $uploadResult['url'],
                    'public_url' => $this->minioService->getPublicUrl($filename),
                    'size' => $uploadResult['size'],
                    'type' => $file->getMimeType(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Asset upload failed',
                'message' => $e->getMessage(),
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
                'html_preview' => $previewHtml,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Preview generation failed',
                'message' => $e->getMessage(),
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
                    'download_url' => route('api.newsletters.templates.download', $template->id),
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
