<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\IncidentApiController;
use App\Http\Controllers\Api\CaseApiController;
use App\Http\Controllers\Api\ReportApiController;

Route::middleware('api')->group(function () {
    Route::prefix('v1')->group(function () {
        // Incidents API
        Route::resource('incidents', IncidentApiController::class);
        
        // Cases API
        Route::resource('cases', CaseApiController::class);
        
        // Reports API
        Route::resource('reports', ReportApiController::class);
        Route::post('reports/export', [ReportApiController::class, 'export']);
        
        // Health check
        Route::get('health', fn() => response()->json(['status' => 'ok']));
    });
});
