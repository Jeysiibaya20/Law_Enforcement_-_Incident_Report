<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Display the dashboard
     */
    public function index(): View
    {
        return view('dashboard');
    }
}
