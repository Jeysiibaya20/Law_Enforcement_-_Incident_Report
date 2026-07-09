<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportApiController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => []]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['status' => 'success'], 201);
    }

    public function show($id): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => ['id' => $id]]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        return response()->json(['status' => 'success']);
    }

    public function destroy($id): JsonResponse
    {
        return response()->json(['status' => 'success']);
    }

    public function export(Request $request): JsonResponse
    {
        return response()->json(['status' => 'success', 'file' => 'report.pdf']);
    }
}
