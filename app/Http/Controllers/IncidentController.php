<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    /**
     * Display a listing of incidents.
     */
    public function index(): JsonResponse
    {
        return response()->json(['message' => 'List of incidents']);
    }

    /**
     * Show the form for creating a new incident.
     */
    public function create()
    {
        return view('incidents.create');
    }

    /**
     * Store a newly created incident in storage.
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Incident created'], 201);
    }

    /**
     * Display the specified incident.
     */
    public function show(string $id): JsonResponse
    {
        return response()->json(['id' => $id, 'message' => 'Incident details']);
    }

    /**
     * Show the form for editing the specified incident.
     */
    public function edit(string $id)
    {
        return view('incidents.edit', ['id' => $id]);
    }

    /**
     * Update the specified incident in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        return response()->json(['message' => 'Incident updated']);
    }

    /**
     * Remove the specified incident from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        return response()->json(['message' => 'Incident deleted']);
    }
}
