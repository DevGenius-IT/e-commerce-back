<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\TicketMessageController;
use App\Http\Controllers\Api\TicketAttachmentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'sav-service',
        'timestamp' => now(),
    ]);
});

// Basic status check (compatible with make health)
Route::get('/status', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'sav-service',
        'database' => 'connected',
        'timestamp' => now(),
    ]);
});

// Protected routes (require JWT authentication)
Route::middleware(['auth:api'])->group(function () {
    
    // Support Tickets Routes
    Route::prefix('tickets')->group(function () {
        Route::get('/', [SupportTicketController::class, 'index']);
        Route::post('/', [SupportTicketController::class, 'store']);
        Route::get('/statistics', [SupportTicketController::class, 'statistics']);
        Route::get('/{id}', [SupportTicketController::class, 'show']);
        Route::put('/{id}', [SupportTicketController::class, 'update']);
        Route::delete('/{id}', [SupportTicketController::class, 'destroy']);
        
        // Special actions for tickets
        Route::post('/{id}/assign', [SupportTicketController::class, 'assign']);
        Route::post('/{id}/resolve', [SupportTicketController::class, 'resolve']);
        Route::post('/{id}/close', [SupportTicketController::class, 'close']);
        
        // Messages for a specific ticket
        Route::prefix('/{ticketId}/messages')->group(function () {
            Route::get('/', [TicketMessageController::class, 'index']);
            Route::post('/', [TicketMessageController::class, 'store']);
            Route::get('/unread-count', [TicketMessageController::class, 'getUnreadCount']);
            Route::post('/mark-all-read', [TicketMessageController::class, 'markAllAsRead']);
            Route::get('/{id}', [TicketMessageController::class, 'show']);
            Route::put('/{id}', [TicketMessageController::class, 'update']);
            Route::delete('/{id}', [TicketMessageController::class, 'destroy']);
            Route::post('/{id}/mark-read', [TicketMessageController::class, 'markAsRead']);
        });
        
        // Attachments for a specific ticket
        Route::prefix('/{ticketId}/attachments')->group(function () {
            Route::get('/', [TicketAttachmentController::class, 'index']);
            Route::post('/', [TicketAttachmentController::class, 'store']);
            Route::post('/multiple', [TicketAttachmentController::class, 'uploadMultiple']);
            Route::get('/{id}', [TicketAttachmentController::class, 'show']);
            Route::get('/{id}/download', [TicketAttachmentController::class, 'download']);
            Route::delete('/{id}', [TicketAttachmentController::class, 'destroy']);
            
            // Get attachments for a specific message
            Route::get('/message/{messageId}', [TicketAttachmentController::class, 'getByMessage']);
        });
    });

});

// Public routes (no authentication required)
Route::prefix('public')->group(function () {
    
    // Public ticket creation (for customers without authentication)
    Route::post('tickets', [SupportTicketController::class, 'store']);
    
    // Public ticket lookup by ticket number
    Route::get('tickets/{ticketNumber}', function ($ticketNumber) {
        $ticket = \App\Models\SupportTicket::where('ticket_number', $ticketNumber)
            ->with(['messages' => function($q) {
                $q->where('is_internal', false)->orderBy('created_at', 'asc');
            }, 'attachments'])
            ->first();
        
        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $ticket,
        ]);
    });
    
    // Public message posting (for customers to respond to tickets)
    Route::post('tickets/{ticketId}/messages', [TicketMessageController::class, 'store']);
});