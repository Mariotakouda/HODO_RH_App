<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\ArchiveEmployeeRequest;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\TransferEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $employeeService)
    {
    }

    public function store(StoreEmployeeRequest $request)
    {
        try {
            $employee = $this->employeeService->create($request->validated());

            $sent = (bool) $employee->getAttribute('invitation_sent');

            return response()->json([
                'message' => $sent
                    ? "Employé créé. Un email d'invitation (lien de création du mot de passe) a été envoyé."
                    : "Employé créé, mais l'email d'invitation n'a pas pu être envoyé. Utilisez « Renvoyer l'invitation » depuis son dossier.",
                'invitation_sent' => $sent,
                'employee' => $employee,
            ], 201);
        } catch (Throwable $e) {
            Log::error('Erreur création employé: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la création', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        try {
            $employee = $this->employeeService->update($employee, $request->validated());
            return response()->json($employee);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

    /** POST /employees/{employee}/transfer */
    public function transfer(TransferEmployeeRequest $request, Employee $employee)
    {
        try {
            $data = $request->validated();
            $employee = $this->employeeService->transfer(
                $employee,
                (int) $data['department_id'],
                $data['reason'] ?? null,
                $data['effective_date'] ?? null
            );

            return response()->json(['message' => 'Employé transféré.', 'employee' => $employee]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('Erreur transfert employé: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors du transfert'], 500);
        }
    }

    /** POST /employees/{employee}/archive */
    public function archive(ArchiveEmployeeRequest $request, Employee $employee)
    {
        try {
            $employee = $this->employeeService->archive($employee, $request->validated());
            return response()->json(['message' => 'Employé archivé.', 'employee' => $employee]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('Erreur archivage employé: ' . $e->getMessage());
            return response()->json(['message' => "Erreur lors de l'archivage"], 500);
        }
    }

    /** POST /employees/{employee}/restore */
    public function restore(Employee $employee)
    {
        try {
            $employee = $this->employeeService->restore($employee);
            return response()->json(['message' => 'Employé réactivé.', 'employee' => $employee]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('Erreur restauration employé: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la réactivation'], 500);
        }
    }

    /** GET /archives/employees */
    public function archived()
    {
        try {
            return response()->json(['data' => $this->employeeService->listArchived()]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

    /** POST /employees/{employee}/resend-invitation */
    public function resendInvitation(Employee $employee)
    {
        try {
            $sent = $this->employeeService->resendInvitation($employee);

            return response()->json([
                'message' => $sent
                    ? 'Invitation renvoyée. Les anciens identifiants temporaires sont désormais invalides.'
                    : "L'email n'a pas pu être envoyé (vérifiez la configuration SMTP).",
                'invitation_sent' => $sent,
            ], $sent ? 200 : 502);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('Erreur renvoi invitation: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

    public function destroy(Employee $employee)
    {
        try {
            $this->employeeService->delete($employee);
            return response()->json(['message' => 'Employé supprimé']);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur suppression'], 500);
        }
    }
}
