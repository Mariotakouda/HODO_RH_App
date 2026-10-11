<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Presence;
use App\Services\PresenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PresenceController extends Controller
{
    public function __construct(private readonly PresenceService $presenceService)
    {
    }

    /**
     * GET /me/presences
     */
    public function myPresences(Request $request)
    {
        try {
            $employee = $request->user()->employee;
            if (!$employee) {
                return response()->json(['message' => 'Profil employé introuvable.'], 404);
            }

            return response()->json($this->presenceService->myPresences($employee));
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

    /**
     * GET /me/presences/stats
     */
    public function stats(Request $request)
    {
        try {
            $employee = $request->user()->employee;
            if (!$employee) {
                return response()->json(['message' => 'Profil employé introuvable.'], 404);
            }

            return response()->json($this->presenceService->monthlyStats($employee));
        } catch (Throwable $e) {
            return response()->json(['error' => 'Erreur stats'], 500);
        }
    }

    /**
     * POST /presences/check-in
     */
    public function store(Request $request)
    {
        try {
            $employee = $request->user()->employee;
            if (!$employee) {
                return response()->json(['message' => 'Profil employé non trouvé'], 404);
            }

            $presence = $this->presenceService->checkIn($employee, $request);
            return response()->json($presence, 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            Log::error('Erreur check-in: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors du pointage'], 500);
        }
    }

    /**
     * PUT /presences/{presence}/check-out
     */
    public function update(Request $request, Presence $presence)
    {
        try {
            $employee = $request->user()->employee;
            if (!$employee) {
                return response()->json(['message' => 'Profil employé non trouvé'], 404);
            }

            $presence = $this->presenceService->checkOut($employee, $presence, $request);
            return response()->json($presence);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            Log::error('Erreur update presence: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la mise à jour'], 500);
        }
    }
}
