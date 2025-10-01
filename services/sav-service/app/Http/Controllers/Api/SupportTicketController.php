<?php

namespace App\Http\Controllers\Api;

use App\Models\SupportTicket;
use App\Services\SAVNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Shared\Components\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Builder;

class SupportTicketController extends Controller
{
    protected SAVNotificationService $notificationService;

    public function __construct(SAVNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::query()->with(['messages' => function($q) {
            $q->latest()->limit(1);
        }]);

        // Filtres
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('ticket_number', 'like', "%{$search}%");
            });
        }

        // Tri
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $tickets = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $tickets->items(),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'in:low,medium,high,urgent',
            'category' => 'nullable|string|max:100',
            'order_id' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ticket = SupportTicket::create($validator->validated());
        
        // Envoyer notification de création
        $this->notificationService->notifyTicketCreated($ticket);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket created successfully',
            'data' => $ticket->load('messages'),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $ticket = SupportTicket::with([
            'messages' => function($q) {
                $q->orderBy('created_at', 'asc');
            },
            'attachments'
        ])->find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Support ticket not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $ticket,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Support ticket not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'subject' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'status' => 'sometimes|in:open,in_progress,waiting_customer,resolved,closed',
            'category' => 'sometimes|nullable|string|max:100',
            'assigned_to' => 'sometimes|nullable|integer',
            'metadata' => 'sometimes|nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $originalData = $ticket->toArray();

        // Gestion des timestamps automatiques pour les changements de statut
        if (isset($data['status'])) {
            if ($data['status'] === 'resolved' && $ticket->status !== 'resolved') {
                $data['resolved_at'] = now();
            } elseif ($data['status'] === 'closed' && $ticket->status !== 'closed') {
                $data['closed_at'] = now();
            }
        }

        $ticket->update($data);
        
        // Calculer les changements
        $changes = [];
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $originalData) && $originalData[$key] !== $value) {
                $changes[$key] = [
                    'old' => $originalData[$key],
                    'new' => $value
                ];
            }
        }
        
        // Envoyer notification de mise à jour
        if (!empty($changes)) {
            $this->notificationService->notifyTicketUpdated($ticket->fresh(), $changes);
        }

        return response()->json([
            'success' => true,
            'message' => 'Support ticket updated successfully',
            'data' => $ticket->fresh()->load('messages'),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Support ticket not found',
            ], 404);
        }

        $ticket->delete();

        return response()->json([
            'success' => true,
            'message' => 'Support ticket deleted successfully',
        ]);
    }

    public function assign(Request $request, string $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Support ticket not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'assigned_to' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ticket->assignTo($request->assigned_to);
        
        // Envoyer notification d'assignation
        $this->notificationService->notifyTicketAssigned($ticket->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Ticket assigned successfully',
            'data' => $ticket->fresh(),
        ]);
    }

    public function resolve(string $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Support ticket not found',
            ], 404);
        }

        $ticket->markAsResolved();
        
        // Envoyer notification de résolution
        $this->notificationService->notifyTicketResolved($ticket->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Ticket marked as resolved',
            'data' => $ticket->fresh(),
        ]);
    }

    public function close(string $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Support ticket not found',
            ], 404);
        }

        $ticket->markAsClosed();
        
        // Envoyer notification de fermeture
        $this->notificationService->notifyTicketClosed($ticket->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Ticket closed successfully',
            'data' => $ticket->fresh(),
        ]);
    }

    public function statistics(): JsonResponse
    {
        $stats = [
            'total_tickets' => SupportTicket::count(),
            'open_tickets' => SupportTicket::where('status', 'open')->count(),
            'in_progress_tickets' => SupportTicket::where('status', 'in_progress')->count(),
            'resolved_tickets' => SupportTicket::where('status', 'resolved')->count(),
            'closed_tickets' => SupportTicket::where('status', 'closed')->count(),
            'urgent_tickets' => SupportTicket::where('priority', 'urgent')->count(),
            'high_priority_tickets' => SupportTicket::where('priority', 'high')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}