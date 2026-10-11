<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcementService)
    {
    }

    /**
     * GET /announcements
     */
    public function index(Request $request)
    {
        try {
            $paginated = $this->announcementService->paginatedFor($request->user(), $request->query('search', ''));

            return response()->json([
                'data' => $paginated->items(),
                'meta' => [
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Erreur index announcements: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

    /**
     * GET /me/announcements
     */
    public function myAnnouncements(Request $request)
    {
        try {
            return response()->json([
                'status' => 'success',
                'data' => $this->announcementService->forMyDashboard($request->user()),
            ]);
        } catch (Throwable $e) {
            Log::error('Erreur Dashboard Annonces: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur de chargement'], 500);
        }
    }

    /**
     * GET /announcements/{announcement}
     */
    public function show(Request $request, Announcement $announcement)
    {
        try {
            $this->announcementService->assertVisible($request->user(), $announcement);
            $announcement->load(['creator', 'department', 'employee']);
            return response()->json(['data' => $announcement]);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }
}
