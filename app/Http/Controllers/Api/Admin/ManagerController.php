<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\StoreManagerRequest;
use App\Http\Requests\Manager\UpdateManagerRequest;
use App\Models\Manager;
use App\Services\ManagerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ManagerController extends Controller
{
    public function __construct(private readonly ManagerService $managerService)
    {
    }

    public function index(Request $request)
    {
        try {
            return response()->json($this->managerService->paginated($request));
        } catch (Throwable $e) {
            Log::error('Erreur dans ManagerController@index: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur interne lors du chargement.'], 500);
        }
    }

    public function store(StoreManagerRequest $request)
    {
        try {
            $manager = $this->managerService->create($request->validated());
            return response()->json([
                'data' => $manager,
                'message' => 'Manager créé et rôle utilisateur mis à jour avec succès',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (Throwable $e) {
            Log::error('Erreur dans ManagerController@store: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la création du manager.'], 500);
        }
    }

    public function show(Manager $manager)
    {
        try {
            $manager->load(['employee', 'department']);
            return response()->json(['data' => $manager]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur lors du chargement.'], 500);
        }
    }

    public function update(UpdateManagerRequest $request, Manager $manager)
    {
        try {
            $manager = $this->managerService->update($manager, $request->validated());
            return response()->json(['data' => $manager, 'message' => 'Manager mis à jour avec succès']);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur lors de la mise à jour.'], 500);
        }
    }

    public function destroy(Manager $manager)
    {
        try {
            $this->managerService->delete($manager);
            return response()->json(null, 204);
        } catch (Throwable $e) {
            Log::error('Erreur dans ManagerController@destroy: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la suppression.'], 500);
        }
    }
}
