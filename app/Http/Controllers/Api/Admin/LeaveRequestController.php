<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Services\LeaveRequestService;
use Illuminate\Support\Facades\Log;
use Throwable;

class LeaveRequestController extends Controller
{
    public function __construct(private readonly LeaveRequestService $leaveRequestService)
    {
    }

    public function destroy(LeaveRequest $leaveRequest)
    {
        try {
            $this->leaveRequestService->delete($leaveRequest);
            return response()->json(null, 204);
        } catch (Throwable $e) {
            Log::error('Erreur suppression: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la suppression'], 500);
        }
    }
}
