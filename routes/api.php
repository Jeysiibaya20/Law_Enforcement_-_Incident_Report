<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\IncidentApiController;
use App\Http\Controllers\Api\CaseApiController;
use App\Http\Controllers\Api\ReportApiController;

Route::middleware('api')->group(function () {
    Route::prefix('v1')->group(function () {
        // Incidents API
        Route::resource('incidents', IncidentApiController::class)->names('api.v1.incidents');
        
        // Cases API
        Route::resource('cases', CaseApiController::class)->names('api.v1.cases');
        
        // Reports API
        Route::resource('reports', ReportApiController::class)->names('api.v1.reports');
        Route::post('reports/export', [ReportApiController::class, 'export'])->name('api.v1.reports.export');
        
        // Health check
        Route::get('health', fn() => response()->json(['status' => 'ok']));
    });
});
