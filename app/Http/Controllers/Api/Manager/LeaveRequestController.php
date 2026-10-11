<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveRequest\DecideLeaveRequestRequest;
use App\Models\LeaveRequest;
use App\Services\LeaveRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class LeaveRequestController extends Controller
{
    public function __construct(private readonly LeaveRequestService $leaveRequestService)
    {
    }

    /**
     * GET /leave-requests
     */
    public function index(Request $request)
    {
        try {
            return response()->json($this->leaveRequestService->listForStaff($request->user()));
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (Throwable $e) {
            Log::error('Erreur indexAdmin: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors du chargement des demandes.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /leave-requests/{leaveRequest}/approve
     */
    public function approve(DecideLeaveRequestRequest $request, LeaveRequest $leaveRequest)
    {
        $leaveRequest = $this->leaveRequestService->approve($leaveRequest, $request->input('admin_comment'));
        return response()->json(['message' => 'Approuvée', 'request' => $leaveRequest]);
    }

    /**
     * PUT /leave-requests/{leaveRequest}/reject
     */
    public function reject(DecideLeaveRequestRequest $request, LeaveRequest $leaveRequest)
    {
        $leaveRequest = $this->leaveRequestService->reject($leaveRequest, $request->input('admin_comment'));
        return response()->json(['message' => 'Rejetée', 'request' => $leaveRequest]);
    }
}
