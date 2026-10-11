<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeeDocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\EmployeeFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dossier employé (admin uniquement : il contient des pièces et données sensibles).
 */
class EmployeeFileController extends Controller
{
    public function __construct(private readonly EmployeeFileService $fileService)
    {
    }

    /** GET /employees/{employee}/file */
    public function show(Employee $employee)
    {
        try {
            return response()->json($this->fileService->build($employee));
        } catch (Throwable $e) {
            Log::error('Erreur dossier employé: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors du chargement du dossier'], 500);
        }
    }

    /** POST /employees/{employee}/documents */
    public function storeDocument(StoreEmployeeDocumentRequest $request, Employee $employee)
    {
        try {
            $document = $this->fileService->addDocument(
                $employee,
                $request->file('file'),
                $request->validated()['type'],
                $request->validated()['title'] ?? null,
                $request->user()
            );

            return response()->json(['message' => 'Pièce ajoutée au dossier.', 'document' => $document], 201);
        } catch (Throwable $e) {
            Log::error('Erreur ajout pièce: ' . $e->getMessage());
            return response()->json(['message' => "Erreur lors de l'ajout de la pièce"], 500);
        }
    }

    /** GET /employees/{employee}/documents/{document}/download */
    public function downloadDocument(Employee $employee, EmployeeDocument $document)
    {
        abort_unless($document->employee_id === $employee->id, 404);

        return $this->fileService->download($document);
    }

    /** DELETE /employees/{employee}/documents/{document} */
    public function destroyDocument(Request $request, Employee $employee, EmployeeDocument $document)
    {
        abort_unless($document->employee_id === $employee->id, 404);

        try {
            $this->fileService->deleteDocument($document);
            return response()->json(['message' => 'Pièce supprimée.']);
        } catch (Throwable $e) {
            Log::error('Erreur suppression pièce: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la suppression'], 500);
        }
    }
}
