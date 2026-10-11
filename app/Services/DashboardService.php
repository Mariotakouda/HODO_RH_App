<?php

namespace App\Services;

use App\Enums\LeaveRequestStatus;
use App\Enums\TaskStatus;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Manager;
use App\Models\Presence;
use App\Models\Role;
use App\Models\Task;

class DashboardService
{
    /**
     * Tableau de bord décisionnel du manager — "vue d'ensemble sur le statut
     * de son département" (cahier des charges, Espace Manager). Contrairement
     * à globalSummary() (admin, toute l'entreprise), tout est filtré sur le
     * département du manager, et le manager lui-même est exclu des effectifs
     * comptés (il pilote l'équipe, il n'en fait pas partie statistiquement).
     */
    public function departmentSummary(Employee $managerEmployee): array
    {
        $deptId = $managerEmployee->department_id;

        // Équipe = employés ACTIFS du département, hors manager
        $teamQuery = fn () => Employee::active()
            ->where('department_id', $deptId)
            ->where('id', '!=', $managerEmployee->id);

        $today = now('Africa/Lome')->toDateString();
        $inTeam = fn ($q) => $q->where('department_id', $deptId)
            ->where('id', '!=', $managerEmployee->id)
            ->whereNull('archived_at');

        $teamSize = $teamQuery()->count();

        $presenceToday = Presence::whereDate('date', $today)
            ->whereHas('employee', $inTeam)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $present = (int) ($presenceToday['present'] ?? 0);
        $late = (int) ($presenceToday['late'] ?? 0);
        $absent = (int) ($presenceToday['absent'] ?? 0);

        $onLeave = LeaveRequest::where('status', LeaveRequestStatus::Approved)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->whereHas('employee', $inTeam)
            ->distinct()
            ->count('employee_id');

        $taskStats = Task::whereHas('employee', fn ($q) => $q->where('department_id', $deptId)->where('id', '!=', $managerEmployee->id))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'department' => $managerEmployee->department?->only(['id', 'name']),
            'stats' => [
                'team_size' => $teamSize,
                // Présents = arrivée enregistrée (à l'heure ou en retard), hors lignes « absent »
                'present_today' => $present + $late,
                'tasks_pending' => $taskStats[TaskStatus::Pending->value] ?? 0,
                'tasks_in_progress' => $taskStats[TaskStatus::InProgress->value] ?? 0,
                'tasks_completed' => $taskStats[TaskStatus::Completed->value] ?? 0,
                'leave_requests_pending' => LeaveRequest::where('status', LeaveRequestStatus::Pending)
                    ->whereHas('employee', fn ($q) => $q->where('department_id', $deptId)->where('id', '!=', $managerEmployee->id))
                    ->count(),
            ],
            // Répartition du jour de l'équipe (alimente le graphique du dashboard manager)
            'today' => [
                'total' => $teamSize,
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'on_leave' => $onLeave,
                'not_checked_in' => max(0, $teamSize - $present - $late - $absent - $onLeave),
            ],
            'recent' => [
                'leave_requests' => LeaveRequest::with('employee:id,first_name,last_name')
                    ->whereHas('employee', fn ($q) => $q->where('department_id', $deptId))
                    ->where('status', LeaveRequestStatus::Pending)
                    ->latest()
                    ->take(5)
                    ->get(['id', 'employee_id', 'type', 'start_date', 'end_date', 'status']),
                'tasks' => Task::with('employee:id,first_name,last_name')
                    ->whereHas('employee', fn ($q) => $q->where('department_id', $deptId)->where('id', '!=', $managerEmployee->id))
                    ->latest()
                    ->take(5)
                    ->get(['id', 'title', 'status', 'employee_id', 'due_date']),
            ],
        ];
    }

    /**
     * Vue d'ensemble admin. Les clés historiques (emp, dep, task, leave, managers, roles,
     * announcements, presences / recent.employees, announcements, presences) sont conservées ;
     * les nouvelles clés (tasks_in_progress, leave_pending, today, recent.tasks,
     * recent.leave_requests) alimentent le tableau de bord moderne.
     */
    public function globalSummary(): array
    {
        $today = now('Africa/Lome')->toDateString();
        $activeEmployee = fn ($q) => $q->whereNull('archived_at');

        $totalEmployees = Employee::active()->count();

        $presenceToday = Presence::whereDate('date', $today)
            ->whereHas('employee', $activeEmployee)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $present = (int) ($presenceToday['present'] ?? 0);
        $late = (int) ($presenceToday['late'] ?? 0);
        $absent = (int) ($presenceToday['absent'] ?? 0);

        $onLeave = LeaveRequest::where('status', LeaveRequestStatus::Approved)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->whereHas('employee', $activeEmployee)
            ->distinct()
            ->count('employee_id');

        return [
            'stats' => [
                'emp' => $totalEmployees,
                'dep' => Department::count(),
                'task' => Task::count(),
                'leave' => LeaveRequest::count(),
                'managers' => Manager::count(),
                'roles' => Role::count(),
                'announcements' => Announcement::count(),
                'presences' => Presence::count(),
                'tasks_in_progress' => Task::where('status', TaskStatus::InProgress)->count(),
                'leave_pending' => LeaveRequest::where('status', LeaveRequestStatus::Pending)->count(),
            ],
            // Répartition du jour (employés actifs uniquement)
            'today' => [
                'total' => $totalEmployees,
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'on_leave' => $onLeave,
                'not_checked_in' => max(0, $totalEmployees - $present - $late - $absent - $onLeave),
            ],
            'recent' => [
                'employees' => Employee::active()->latest()->take(5)->get(['id', 'first_name', 'last_name', 'email', 'created_at']),
                'announcements' => Announcement::latest()->take(5)->get(['id', 'title', 'message', 'created_at']),
                'presences' => Presence::with('employee:id,first_name,last_name')
                    ->latest()
                    ->take(5)
                    ->get(['id', 'employee_id', 'date', 'status']),
                'tasks' => Task::with('employee:id,first_name,last_name')
                    ->where('status', TaskStatus::Completed)
                    ->latest('updated_at')
                    ->take(5)
                    ->get(['id', 'title', 'status', 'employee_id', 'updated_at']),
                'leave_requests' => LeaveRequest::with('employee:id,first_name,last_name')
                    ->latest()
                    ->take(5)
                    ->get(['id', 'employee_id', 'type', 'start_date', 'end_date', 'status', 'created_at']),
            ],
        ];
    }
}
