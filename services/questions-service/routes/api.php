<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\QuestionController;
use App\Http\Controllers\API\AnswerController;

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
Route::get('/health', [QuestionController::class, 'health']);

// Basic status check (compatible with make health)
Route::get('/status', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'questions-service',
        'database' => 'connected',
        'timestamp' => now(),
    ]);
});

// Protected routes (require JWT authentication)
Route::middleware(['auth:api'])->group(function () {
    
    // Questions Routes
    Route::prefix('questions')->group(function () {
        Route::get('/', [QuestionController::class, 'index']);
        Route::post('/', [QuestionController::class, 'store']);
        Route::get('/{question}', [QuestionController::class, 'show']);
        Route::put('/{question}', [QuestionController::class, 'update']);
        Route::delete('/{question}', [QuestionController::class, 'destroy']);
        
        // Answers Routes (nested under questions)
        Route::prefix('{question}/answers')->group(function () {
            Route::get('/', [AnswerController::class, 'index']);
            Route::post('/', [AnswerController::class, 'store']);
            Route::get('/{answer}', [AnswerController::class, 'show']);
            Route::put('/{answer}', [AnswerController::class, 'update']);
            Route::delete('/{answer}', [AnswerController::class, 'destroy']);
        });
    });
});

// Public routes (no authentication required)
Route::prefix('public')->group(function () {
    
    // Public FAQ access (read-only)
    Route::get('questions', [QuestionController::class, 'index']);
    Route::get('questions/{question}', [QuestionController::class, 'show']);
    Route::get('questions/{question}/answers', [AnswerController::class, 'index']);
    Route::get('questions/{question}/answers/{answer}', [AnswerController::class, 'show']);
    
    // Public search
    Route::get('search', function (Request $request) {
        $query = $request->query('q');
        
        if (!$query) {
            return response()->json([
                'success' => false,
                'message' => 'Search query is required'
            ], 400);
        }
        
        try {
            $questions = \App\Models\Question::with('answers')
                ->where('title', 'LIKE', "%{$query}%")
                ->orWhere('body', 'LIKE', "%{$query}%")
                ->orWhereHas('answers', function ($q) use ($query) {
                    $q->where('body', 'LIKE', "%{$query}%");
                })
                ->paginate(10);
                
            return response()->json([
                'success' => true,
                'data' => $questions,
                'message' => 'Search results retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed: ' . $e->getMessage()
            ], 500);
        }
    });
});

// Get authenticated user info
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});