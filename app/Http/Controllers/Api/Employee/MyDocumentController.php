<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreMyDocumentRequest;
use App\Models\EmployeeDocument;
use App\Services\EmployeeFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * « Mon dossier » : chaque employé (ou manager) dépose ses pièces fichier par fichier
 * et peut télécharger toutes celles de SON dossier. Il ne supprime que ce qu'il a déposé
 * lui-même : les pièces ajoutées par l'administration (contrat...) sont en lecture seule.
 */
class MyDocumentController extends Controller
{
    public function __construct(private readonly EmployeeFileService $fileService)
    {
    }

    /** GET /me/documents */
    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Profil employé non trouvé'], 404);
        }

        $userId = $request->user()->id;

        $documents = $employee->documents()
            ->with('uploader:id,name')
            ->latest()
            ->get()
            ->map(function (EmployeeDocument $doc) use ($userId) {
                $doc->setAttribute('can_delete', (int) $doc->uploaded_by === (int) $userId);
                return $doc;
            });

        return response()->json([
            'data' => $documents,
            'allowed_types' => config('hodo.employee_document_types'),
            'max_documents' => config('hodo.max_documents_per_employee'),
        ]);
    }

    /** POST /me/documents — un fichier à la fois */
    public function store(StoreMyDocumentRequest $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Profil employé non trouvé'], 404);
        }

        if ($employee->documents()->count() >= (int) config('hodo.max_documents_per_employee')) {
            return response()->json(['message' => 'Votre dossier a atteint le nombre maximum de pièces.'], 422);
        }

        try {
            $data = $request->validated();
            $document = $this->fileService->addDocument(
                $employee,
                $request->file('file'),
                $data['type'],
                $data['title'] ?? null,
                $request->user()
            );
            $document->setAttribute('can_delete', true);

            Log::info('Pièce ajoutée par l\'employé', ['employee_id' => $employee->id, 'document_id' => $document->id]);

            return response()->json(['message' => 'Pièce ajoutée à votre dossier.', 'document' => $document], 201);
        } catch (Throwable $e) {
            Log::error('Erreur dépôt pièce employé: ' . $e->getMessage());
            return response()->json(['message' => "Erreur lors de l'ajout de la pièce"], 500);
        }
    }

    /** GET /me/documents/{document}/download */
    public function download(Request $request, EmployeeDocument $document)
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $document->employee_id === $employee->id, 404);

        return $this->fileService->download($document);
    }

    /** DELETE /me/documents/{document} */
    public function destroy(Request $request, EmployeeDocument $document)
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $document->employee_id === $employee->id, 404);

        if ((int) $document->uploaded_by !== (int) $request->user()->id) {
            return response()->json(['message' => "Cette pièce a été ajoutée par l'administration : vous ne pouvez pas la supprimer."], 403);
        }

        try {
            $this->fileService->deleteDocument($document);
            return response()->json(['message' => 'Pièce supprimée.']);
        } catch (Throwable $e) {
            Log::error('Erreur suppression pièce employé: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la suppression'], 500);
        }
    }
}
