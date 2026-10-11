<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Announcement\StoreAnnouncementRequest;
use App\Http\Requests\Announcement\UpdateAnnouncementRequest;
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

    public function store(StoreAnnouncementRequest $request)
    {
        try {
            $announcement = $this->announcementService->store($request->user(), $request->validated());
            return response()->json(['message' => 'Annonce créée avec succès', 'data' => $announcement], 201);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (Throwable $e) {
            Log::error('Erreur création annonce: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la création'], 500);
        }
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement)
    {
        try {
            $announcement = $this->announcementService->update($request->user(), $announcement, $request->validated());
            return response()->json(['message' => 'Annonce mise à jour', 'data' => $announcement]);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (Throwable $e) {
            Log::error('Erreur update announcement: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        try {
            $this->announcementService->delete($request->user(), $announcement);
            return response()->json(['message' => 'Annonce supprimée'], 204);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur suppression'], 500);
        }
    }
}
