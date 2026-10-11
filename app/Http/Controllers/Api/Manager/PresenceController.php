<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Services\PresenceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Throwable;

class PresenceController extends Controller
{
    public function __construct(private readonly PresenceService $presenceService)
    {
    }

    /**
     * GET /presences
     */
    public function index(Request $request)
    {
        try {
            return response()->json($this->presenceService->listForStaff($request->user(), $request));
        } catch (AuthorizationException $e) {
            return response()->json(['data' => [], 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur lors de la récupération'], 500);
        }
    }

    /**
     * GET /presences/logs — journal des pointages (admin : tout ; manager : son département).
     */
    public function logs(Request $request)
    {
        try {
            return response()->json($this->presenceService->logsForStaff($request->user(), $request));
        } catch (AuthorizationException $e) {
            return response()->json(['data' => [], 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur lors de la récupération du journal'], 500);
        }
    }
}
