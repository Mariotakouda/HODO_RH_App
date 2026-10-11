<?php

namespace App\Services;

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class LeaveRequestService
{
    public function myRequests(Employee $employee)
    {
        return LeaveRequest::where('employee_id', $employee->id)->latest()->get();
    }

    /**
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findMine(Employee $employee, int $id): ?LeaveRequest
    {
        return $employee->leaveRequests()->find($id);
    }

    /**
     * @throws AuthorizationException si la demande a déjà été traitée
     */
    public function updateMine(LeaveRequest $leaveRequest, array $validated): LeaveRequest
    {
        if ($leaveRequest->status !== LeaveRequestStatus::Pending) {
            throw new AuthorizationException('Cette demande a déjà été traitée et ne peut plus être modifiée.');
        }

        $leaveRequest->update($validated);

        return $leaveRequest;
    }

    public function store(Employee $employee, array $validated): LeaveRequest
    {
        return LeaveRequest::create([
            ...$validated,
            'employee_id' => $employee->id,
            'status' => LeaveRequestStatus::Pending,
        ]);
    }

    /**
     * Liste pour admin (toutes) ou manager (son département).
     *
     * @throws AuthorizationException si le manager n'a pas de département
     */
    public function listForStaff(User $user)
    {
        $query = LeaveRequest::with(['employee.department'])->latest();

        if (!$user->isAdmin()) {
            $deptId = $user->employee->department_id ?? null;
            if (!$deptId) {
                throw new AuthorizationException('Département manager introuvable');
            }
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $deptId));
        }

        return $query->get();
    }

    public function approve(LeaveRequest $leaveRequest, ?string $comment): LeaveRequest
    {
        $leaveRequest->update([
            'status' => LeaveRequestStatus::Approved,
            'admin_comment' => $comment,
        ]);

        return $leaveRequest;
    }

    public function reject(LeaveRequest $leaveRequest, ?string $comment): LeaveRequest
    {
        $leaveRequest->update([
            'status' => LeaveRequestStatus::Rejected,
            'admin_comment' => $comment,
        ]);

        return $leaveRequest;
    }

    public function delete(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->delete();
    }
}
