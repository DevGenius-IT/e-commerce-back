<?php

namespace App\Http\Controllers\Api;

use App\Models\SupportTicket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Shared\Components\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TicketAttachmentController extends Controller
{
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
        $filename = time() . '_' . str_replace(' ', '_', $originalName);
        
        // Stocker le fichier
        $filePath = $file->storeAs('sav-attachments', $filename, 'public');

        $attachment = TicketAttachment::create([
            'ticket_id' => $ticketId,
            'message_id' => $request->message_id ?? null,
            'original_name' => $originalName,
            'filename' => $filename,
            'file_path' => $filePath,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
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

        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found on storage',
            ], 404);
        }

        return Storage::disk('public')->download(
            $attachment->file_path,
            $attachment->original_name
        );
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

        // Le fichier sera automatiquement supprimé grâce au boot() dans le modèle
        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attachment deleted successfully',
        ]);
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
                $filename = time() . '_' . str_replace(' ', '_', $originalName);
                
                $filePath = $file->storeAs('sav-attachments', $filename, 'public');

                $attachment = TicketAttachment::create([
                    'ticket_id' => $ticketId,
                    'message_id' => $request->message_id ?? null,
                    'original_name' => $originalName,
                    'filename' => $filename,
                    'file_path' => $filePath,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
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
}