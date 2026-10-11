<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Http\Request;
use Throwable;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $employeeService)
    {
    }

    /**
     * GET /employees — liste scoping automatique (admin : tous ; manager : son département).
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->isAdmin()) {
                $employees = $this->employeeService->listAll();
            } else {
                $employee = $user->employee;

                if (!$employee) {
                    return response()->json(['data' => [], 'message' => 'Profil employé introuvable.']);
                }
                if (!$employee->department_id) {
                    return response()->json(['data' => [], 'message' => "Vous n'avez pas de département assigné."]);
                }

                $employees = $this->employeeService->listForManager($employee);
            }

            return response()->json(['data' => $employees]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

    /**
     * GET /employees/{employee}
     */
    public function show(Employee $employee)
    {
        try {
            $employee->load(['department', 'roles', 'tasks', 'presences', 'leaveRequests']);
            return response()->json($employee);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }
}
