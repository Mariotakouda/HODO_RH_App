<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeRole\StoreEmployeeRoleRequest;
use App\Services\EmployeeRoleService;
use Throwable;

class EmployeeRoleController extends Controller
{
    public function __construct(private readonly EmployeeRoleService $employeeRoleService)
    {
    }

    public function index()
    {
        try {
            return response()->json($this->employeeRoleService->all());
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur lors de la récupération des rôles employés.', 'error' => $e->getMessage()], 500);
        }
    }

    public function store(StoreEmployeeRoleRequest $request)
    {
        try {
            $employeeRole = $this->employeeRoleService->create($request->validated());
            return response()->json($employeeRole, 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return response()->json(['message' => "Erreur lors de la création de l'attribution.", 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $employee_id, int $role_id)
    {
        try {
            if ($this->employeeRoleService->delete($employee_id, $role_id)) {
                return response()->json(null, 204);
            }
            return response()->json(['message' => 'Association non trouvée.'], 404);
        } catch (Throwable $e) {
            return response()->json(['message' => "Erreur lors de la suppression de l'attribution.", 'error' => $e->getMessage()], 500);
        }
    }
}
