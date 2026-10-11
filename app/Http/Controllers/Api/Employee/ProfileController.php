<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\UploadProfilePhotoRequest;
use App\Services\EmployeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(private readonly EmployeeService $employeeService)
    {
    }

    /**
     * GET /me — profil de l'employé connecté.
     */
    public function show(Request $request)
    {
        try {
            $employee = $request->user()->employee;

            if (!$employee) {
                return response()->json(['message' => 'Aucun profil employé associé.'], 404);
            }

            $employee->load(['department', 'roles']);

            return response()->json([
                'message' => "Profil de l'employé connecté",
                'employee' => $employee,
            ]);
        } catch (Throwable $e) {
            Log::error('Erreur dans ProfileController::show(): ' . $e->getMessage());
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

    /**
     * GET /check-manager-status
     */
    public function managerStatus(Request $request)
    {
        try {
            $user = $request->user();
            $employee = $user->employee;

            return response()->json([
                'is_manager' => $user->isManager(),
                'is_admin' => $user->isAdmin(),
                'department_id' => $employee->department_id ?? null,
                'department_name' => $employee->department->name ?? null,
            ]);
        } catch (Throwable $e) {
            return response()->json(['is_manager' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /me/profile-photo
     */
    public function uploadPhoto(UploadProfilePhotoRequest $request)
    {
        try {
            $employee = $request->user()->employee;

            if (!$employee) {
                return response()->json(['message' => 'Profil employé introuvable.'], 404);
            }

            $employee = $this->employeeService->uploadProfilePhoto($employee, $request->file('profile_photo'));

            return response()->json([
                'message' => 'Photo de profil mise à jour avec succès',
                'url' => $employee->profile_photo_url,
                'path' => $employee->profile_photo,
                'employee' => $employee,
            ]);
        } catch (Throwable $e) {
            Log::error('Erreur upload photo profil : ' . $e->getMessage());
            return response()->json(['message' => "Erreur lors de l'upload", 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /me/profile-photo
     */
    public function deletePhoto(Request $request)
    {
        try {
            $employee = $request->user()->employee;

            if (!$employee) {
                return response()->json(['message' => 'Profil employé introuvable.'], 404);
            }

            $employee = $this->employeeService->deleteProfilePhoto($employee);

            return response()->json([
                'message' => 'Photo de profil supprimée',
                'employee' => $employee,
            ]);
        } catch (Throwable $e) {
            Log::error('Erreur suppression photo : ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la suppression'], 500);
        }
    }
}
