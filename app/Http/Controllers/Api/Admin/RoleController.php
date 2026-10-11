<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roleService)
    {
    }

    public function index(Request $request)
    {
        try {
            return response()->json($this->roleService->paginated($request));
        } catch (Throwable $e) {
            Log::error('Erreur lors de la récupération des rôles: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la récupération des rôles.', 'error' => $e->getMessage()], 500);
        }
    }

    public function store(StoreRoleRequest $request)
    {
        try {
            return response()->json($this->roleService->create($request->validated()), 201);
        } catch (ValidationException $e) {
            return response()->json(['erreurs' => $e->errors()], 422);
        } catch (Throwable $e) {
            Log::error('Erreur lors de la création du rôle: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la création du rôle.'], 500);
        }
    }

    public function show(Role $role)
    {
        try {
            $role->loadCount('employees');
            return response()->json($role);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur lors de la récupération du rôle.'], 500);
        }
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        try {
            return response()->json($this->roleService->update($role, $request->validated()));
        } catch (ValidationException $e) {
            return response()->json(['erreurs' => $e->errors()], 422);
        } catch (Throwable $e) {
            Log::error('Erreur lors de la mise à jour du rôle: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la mise à jour du rôle.'], 500);
        }
    }

    public function destroy(Role $role)
    {
        try {
            $this->roleService->delete($role);
            return response()->json(null, 204);
        } catch (Throwable $e) {
            Log::error('Erreur lors de la suppression du rôle: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la suppression du rôle.'], 500);
        }
    }
}
