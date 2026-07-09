<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentApiController extends Controller
{
    /**
     * List all incidents
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [],
            'message' => 'Incidents retrieved successfully'
        ]);
    }

    /**
     * Create new incident
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Incident created'
        ], 201);
    }

    /**
     * Get incident details
     */
    public function show($id): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => ['id' => $id]
        ]);
    }

    /**
     * Update incident
     */
    public function update(Request $request, $id): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Incident updated'
        ]);
    }

    /**
     * Delete incident
     */
    public function destroy($id): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Incident deleted'
        ]);
    }
}
