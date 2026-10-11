<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Presence;
use App\Services\PresenceService;
use Illuminate\Http\Request;
use Throwable;

class PresenceController extends Controller
{
    public function __construct(private readonly PresenceService $presenceService)
    {
    }

    public function destroy(Request $request, Presence $presence)
    {
        $this->presenceService->delete($presence, $request->user());
        return response()->json(null, 204);
    }

    /**
     * GET /presences/export-data?from=&to= — données de la fiche de présence PDF
     * (le PDF lui-même est généré dans le navigateur, avec le logo et la protection).
     */
    public function exportData(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        try {
            $result = $this->presenceService->exportRows($data['from'] ?? null, $data['to'] ?? null);

            return response()->json($result + [
                'generated_at' => now()->toIso8601String(),
                'generated_by' => $request->user()->name,
                'period' => ['from' => $data['from'] ?? null, 'to' => $data['to'] ?? null],
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => "Erreur lors de la préparation de l'export"], 500);
        }
    }

    public function export()
    {
        try {
            $filename = 'export_presences_' . date('d-m-Y') . '.csv';

            return response()->stream($this->presenceService->exportCsvCallback(), 200, [
                'Content-type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=$filename",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => "Erreur lors de l'export : " . $e->getMessage()], 500);
        }
    }
}
