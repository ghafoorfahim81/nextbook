<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'dashboard' => $this->dashboardService->getDashboardData($request->user()),
            'dashboardDataUrl' => route('dashboard.data'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json(
            $this->dashboardService->getDashboardData($request->user())
        );
    }
}
