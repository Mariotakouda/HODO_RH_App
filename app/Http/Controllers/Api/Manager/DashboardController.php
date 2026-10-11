<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    /**
     * GET /manager/dashboard-summary
     * "Vue d'ensemble sur le statut de son département" (cahier des charges).
     */
    public function summary(Request $request)
    {
        try {
            $employee = $request->user()->employee;

            if (!$employee || !$employee->department_id) {
                return response()->json(['message' => "Vous n'avez pas de département assigné."], 200);
            }

            return response()->json($this->dashboardService->departmentSummary($employee));
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }
}
