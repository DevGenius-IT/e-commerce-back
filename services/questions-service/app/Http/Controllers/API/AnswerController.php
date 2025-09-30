<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AnswerController extends Controller
{
    /**
     * Display a listing of answers for a specific question.
     */
    public function index(string $questionId): JsonResponse
    {
        try {
            $question = Question::findOrFail($questionId);
            $answers = $question->answers()->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $answers,
                'message' => 'Answers retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving answers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created answer for a question.
     */
    public function store(Request $request, string $questionId): JsonResponse
    {
        try {
            $question = Question::findOrFail($questionId);
            
            $validated = $request->validate([
                'body' => 'required|string'
            ]);

            $answer = $question->answers()->create($validated);

            return response()->json([
                'success' => true,
                'data' => $answer,
                'message' => 'Answer created successfully'
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating answer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified answer.
     */
    public function show(string $questionId, string $id): JsonResponse
    {
        try {
            $question = Question::findOrFail($questionId);
            $answer = $question->answers()->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $answer,
                'message' => 'Answer retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Answer not found'
            ], 404);
        }
    }

    /**
     * Update the specified answer.
     */
    public function update(Request $request, string $questionId, string $id): JsonResponse
    {
        try {
            $question = Question::findOrFail($questionId);
            $answer = $question->answers()->findOrFail($id);
            
            $validated = $request->validate([
                'body' => 'required|string'
            ]);

            $answer->update($validated);

            return response()->json([
                'success' => true,
                'data' => $answer,
                'message' => 'Answer updated successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating answer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified answer.
     */
    public function destroy(string $questionId, string $id): JsonResponse
    {
        try {
            $question = Question::findOrFail($questionId);
            $answer = $question->answers()->findOrFail($id);
            $answer->delete();

            return response()->json([
                'success' => true,
                'message' => 'Answer deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting answer: ' . $e->getMessage()
            ], 500);
        }
    }
}
