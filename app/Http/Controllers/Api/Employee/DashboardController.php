<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Services\EmployeeService;
use Illuminate\Http\Request;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(private readonly EmployeeService $employeeService)
    {
    }

    /**
     * GET /me/dashboard-summary
     */
    public function summary(Request $request)
    {
        try {
            $employee = $request->user()->employee;

            if (!$employee) {
                return response()->json(['message' => 'Profil non trouvé'], 404);
            }

            return response()->json($this->employeeService->dashboardSummary($employee));
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }
}
