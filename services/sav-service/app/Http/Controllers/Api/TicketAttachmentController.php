<?php

namespace App\Http\Controllers\Api;

use App\Models\SupportTicket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Shared\Components\Controller;
use Shared\Services\MinioService;
use Illuminate\Support\Facades\Validator;

class TicketAttachmentController extends Controller
{
    private MinioService $minioService;

    public function __construct()
    {
        $this->minioService = new MinioService('sav');
    }

    public function index(string $ticketId): JsonResponse
    {
        $ticket = SupportTicket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Support ticket not found',
            ], 404);
        }

        $attachments = TicketAttachment::where('ticket_id', $ticketId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attachments,
        ]);
    }

    public function store(Request $request, string $ticketId): JsonResponse
    {
        $ticket = SupportTicket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Support ticket not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // Max 10MB
            'message_id' => 'sometimes|exists:ticket_messages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        
        // Générer chemin sécurisé pour MinIO
        $filename = "tickets/{$ticketId}/" . uniqid() . '_' . $this->sanitizeFilename($originalName);
        
        // Upload vers MinIO
        $uploadResult = $this->minioService->uploadFile(
            $filename,
            $file,
            [
                'ticket_id' => (string) $ticketId,
                'uploaded_by' => auth()->id() ?? 'system',
                'original_name' => $originalName,
            ]
        );

        $attachment = TicketAttachment::create([
            'ticket_id' => $ticketId,
            'message_id' => $request->message_id ?? null,
            'original_name' => $originalName,
            'filename' => $filename,
            'file_path' => $uploadResult['url'],
            'mime_type' => $file->getMimeType(),
            'file_size' => $uploadResult['size'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File uploaded successfully',
            'data' => $attachment,
        ], 201);
    }

    public function show(string $ticketId, string $id): JsonResponse
    {
        $attachment = TicketAttachment::where('ticket_id', $ticketId)->find($id);

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $attachment,
        ]);
    }

    public function download(string $ticketId, string $id)
    {
        $attachment = TicketAttachment::where('ticket_id', $ticketId)->find($id);

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found',
            ], 404);
        }

        try {
            // Générer URL présignée (valide 1h)
            $presignedUrl = $this->minioService->getPresignedUrl($attachment->filename, 3600);
            
            return redirect($presignedUrl);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'File not found on storage',
            ], 404);
        }
    }

    public function destroy(string $ticketId, string $id): JsonResponse
    {
        $attachment = TicketAttachment::where('ticket_id', $ticketId)->find($id);

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found',
            ], 404);
        }

        try {
            // Supprimer de MinIO
            $this->minioService->deleteFile($attachment->filename);
            
            // Supprimer de la base
            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment',
            ], 500);
        }
    }

    public function getByMessage(string $ticketId, string $messageId): JsonResponse
    {
        $ticket = SupportTicket::find($ticketId);
        $message = TicketMessage::find($messageId);

        if (!$ticket || !$message || $message->ticket_id != $ticketId) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket or message not found',
            ], 404);
        }

        $attachments = TicketAttachment::where('ticket_id', $ticketId)
            ->where('message_id', $messageId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attachments,
        ]);
    }

    public function uploadMultiple(Request $request, string $ticketId): JsonResponse
    {
        $ticket = SupportTicket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Support ticket not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'files' => 'required|array|max:5',
            'files.*' => 'file|max:10240', // Max 10MB per file
            'message_id' => 'sometimes|exists:ticket_messages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $attachments = [];
        $errors = [];

        foreach ($request->file('files') as $file) {
            try {
                $originalName = $file->getClientOriginalName();
                
                // Générer chemin sécurisé pour MinIO
                $filename = "tickets/{$ticketId}/" . uniqid() . '_' . $this->sanitizeFilename($originalName);
                
                // Upload vers MinIO
                $uploadResult = $this->minioService->uploadFile(
                    $filename,
                    $file,
                    [
                        'ticket_id' => (string) $ticketId,
                        'uploaded_by' => auth()->id() ?? 'system',
                        'original_name' => $originalName,
                    ]
                );

                $attachment = TicketAttachment::create([
                    'ticket_id' => $ticketId,
                    'message_id' => $request->message_id ?? null,
                    'original_name' => $originalName,
                    'filename' => $filename,
                    'file_path' => $uploadResult['url'],
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $uploadResult['size'],
                ]);

                $attachments[] = $attachment;
            } catch (\Exception $e) {
                $errors[] = "Failed to upload {$file->getClientOriginalName()}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => count($errors) === 0,
            'message' => count($errors) === 0 
                ? 'All files uploaded successfully' 
                : 'Some files failed to upload',
            'data' => $attachments,
            'errors' => $errors,
        ], count($errors) === 0 ? 201 : 207);
    }

    /**
     * Sanitize filename pour sécurité MinIO
     */
    private function sanitizeFilename(string $filename): string
    {
        // Supprimer caractères dangereux
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        return substr($filename, 0, 100); // Limiter longueur
    }
}