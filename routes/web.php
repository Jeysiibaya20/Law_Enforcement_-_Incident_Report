<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['web'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Incidents
    Route::resource('incidents', IncidentController::class);
    
    // Health check
    Route::get('/up', function () {
        return response()->json(['status' => 'ok'], 200);
    });
});

// Integration with existing PHP routes - fallback
Route::fallback(function () {
    // This will allow requests to existing PHP files to still work
    $path = request()->path();
    $file = base_path($path . '.php');
    if (file_exists($file)) {
        return include($file);
    }
    return response('Not Found', 404);
});
