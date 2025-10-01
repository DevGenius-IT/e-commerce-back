<?php

namespace App\Http\Controllers\Api;

use App\Models\TicketMessage;
use App\Models\SupportTicket;
use App\Services\SAVNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Shared\Components\Controller;
use Illuminate\Support\Facades\Validator;

class TicketMessageController extends Controller
{
    protected SAVNotificationService $notificationService;

    public function __construct(SAVNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
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

        $messages = TicketMessage::with('attachments')
            ->where('ticket_id', $ticketId)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $messages,
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
            'sender_id' => 'required|integer',
            'sender_type' => 'required|in:customer,agent',
            'message' => 'required|string',
            'is_internal' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $messageData = $validator->validated();
        $messageData['ticket_id'] = $ticketId;

        $message = TicketMessage::create($messageData);

        // Mettre à jour le statut du ticket si nécessaire
        if ($request->sender_type === 'customer' && $ticket->status === 'waiting_customer') {
            $ticket->update(['status' => 'in_progress']);
        }
        
        // Envoyer notification de nouveau message
        $this->notificationService->notifyMessageAdded($message, $ticket->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Message added successfully',
            'data' => $message->load('attachments'),
        ], 201);
    }

    public function show(string $ticketId, string $id): JsonResponse
    {
        $message = TicketMessage::with('attachments')
            ->where('ticket_id', $ticketId)
            ->find($id);

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $message,
        ]);
    }

    public function update(Request $request, string $ticketId, string $id): JsonResponse
    {
        $message = TicketMessage::where('ticket_id', $ticketId)->find($id);

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'sometimes|string',
            'is_internal' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $message->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Message updated successfully',
            'data' => $message->fresh()->load('attachments'),
        ]);
    }

    public function destroy(string $ticketId, string $id): JsonResponse
    {
        $message = TicketMessage::where('ticket_id', $ticketId)->find($id);

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found',
            ], 404);
        }

        $message->delete();

        return response()->json([
            'success' => true,
            'message' => 'Message deleted successfully',
        ]);
    }

    public function markAsRead(string $ticketId, string $id): JsonResponse
    {
        $message = TicketMessage::where('ticket_id', $ticketId)->find($id);

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found',
            ], 404);
        }

        $message->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Message marked as read',
            'data' => $message->fresh(),
        ]);
    }

    public function markAllAsRead(string $ticketId): JsonResponse
    {
        $ticket = SupportTicket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Support ticket not found',
            ], 404);
        }

        TicketMessage::where('ticket_id', $ticketId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All messages marked as read',
        ]);
    }

    public function getUnreadCount(string $ticketId): JsonResponse
    {
        $ticket = SupportTicket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Support ticket not found',
            ], 404);
        }

        $unreadCount = TicketMessage::where('ticket_id', $ticketId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $unreadCount,
            ],
        ]);
    }
}