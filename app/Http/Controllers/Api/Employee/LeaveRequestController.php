<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveRequest\StoreLeaveRequestRequest;
use App\Http\Requests\LeaveRequest\UpdateLeaveRequestRequest;
use App\Services\LeaveRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class LeaveRequestController extends Controller
{
    public function __construct(private readonly LeaveRequestService $leaveRequestService)
    {
    }

    /**
     * GET /me/leave-requests
     */
    public function myLeaveRequests(Request $request)
    {
        try {
            $employee = $request->user()->employee;
            if (!$employee) {
                return response()->json(['message' => 'Profil employé introuvable.'], 404);
            }

            return response()->json($this->leaveRequestService->myRequests($employee));
        } catch (Throwable $e) {
            Log::error('Erreur dans myLeaveRequests(): ' . $e->getMessage());
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

    /**
     * GET /me/leave-requests/{id}
     */
    public function showMine(Request $request, int $id)
    {
        try {
            $employee = $request->user()->employee;
            if (!$employee) {
                return response()->json(['message' => 'Profil employé introuvable.'], 404);
            }

            $leaveRequest = $this->leaveRequestService->findMine($employee, $id);
            if (!$leaveRequest) {
                return response()->json(['message' => 'Demande introuvable.'], 404);
            }

            return response()->json($leaveRequest);
        } catch (Throwable $e) {
            Log::error('Erreur dans showMine(): ' . $e->getMessage());
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

    /**
     * PUT /me/leave-requests/{id}
     */
    public function updateMine(UpdateLeaveRequestRequest $request, int $id)
    {
        try {
            $employee = $request->user()->employee;
            if (!$employee) {
                return response()->json(['message' => 'Profil employé introuvable.'], 404);
            }

            $leaveRequest = $this->leaveRequestService->findMine($employee, $id);
            if (!$leaveRequest) {
                return response()->json(['message' => 'Demande introuvable.'], 404);
            }

            $leaveRequest = $this->leaveRequestService->updateMine($leaveRequest, $request->validated());

            return response()->json(['message' => 'Demande mise à jour avec succès.', 'request' => $leaveRequest]);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('Erreur dans updateMine(): ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la modification.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /leave-requests
     */
    public function store(StoreLeaveRequestRequest $request)
    {
        try {
            $employee = $request->user()->employee;
            if (!$employee) {
                return response()->json(['message' => 'Profil employé non trouvé.'], 404);
            }

            $leaveRequest = $this->leaveRequestService->store($employee, $request->validated());

            return response()->json([
                'message' => "Demande de congé soumise avec succès et en attente d'approbation.",
                'request' => $leaveRequest,
            ], 201);
        } catch (Throwable $e) {
            Log::error('Erreur dans LeaveRequestController@store: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la soumission de la demande.', 'error' => $e->getMessage()], 500);
        }
    }
}
