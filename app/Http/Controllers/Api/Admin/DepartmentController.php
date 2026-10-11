<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class DepartmentController extends Controller
{
    public function __construct(private readonly DepartmentService $departmentService)
    {
    }

    /**
     * GET /departments/list — liste simple pour les dropdowns
     */
    public function list()
    {
        try {
            return response()->json(['data' => $this->departmentService->simpleList()]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur lors de la récupération de la liste des départements.', 'error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            return response()->json($this->departmentService->paginated($request));
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur lors de la récupération des départements.', 'error' => $e->getMessage()], 500);
        }
    }

    public function store(StoreDepartmentRequest $request)
    {
        try {
            $department = $this->departmentService->create($request->validated());
            return response()->json(['data' => $department], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $e->errors()], 422);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur création', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(Department $department)
    {
        try {
            $department->load(['employees', 'manager']);
            return response()->json(['data' => $department]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur récupération', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        try {
            $department = $this->departmentService->update($department, $request->validated());
            return response()->json(['data' => $department]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erreur validation', 'errors' => $e->errors()], 422);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur mise à jour', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Department $department)
    {
        try {
            $this->departmentService->delete($department);
            return response()->json(null, 204);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur suppression', 'error' => $e->getMessage()], 500);
        }
    }
}
