<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class AnnouncementService
{
    public function paginatedFor(User $user, ?string $search)
    {
        $query = Announcement::with([
            'creator:id,name',
            'department:id,name',
            'employee:id,first_name,last_name',
        ])
            ->select('id', 'title', 'message', 'department_id', 'employee_id', 'user_id', 'is_general', 'created_at')
            ->latest();

        if (!$user->isAdmin()) {
            $deptId = $user->employee->department_id ?? null;
            $employeeId = $user->employee->id ?? null;

            $query->where(function ($q) use ($deptId, $employeeId) {
                $q->where('is_general', true);
                if ($deptId) {
                    $q->orWhere('department_id', $deptId);
                }
                if ($employeeId) {
                    $q->orWhere('employee_id', $employeeId);
                }
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "{$search}%")
                    ->orWhere('message', 'LIKE', "%{$search}%");
            });
        }

        return $query->paginate(12);
    }

    public function forMyDashboard(User $user)
    {
        $deptId = $user->employee->department_id ?? null;
        $employeeId = $user->employee->id ?? null;

        return Announcement::with(['creator', 'department'])
            ->where(function ($q) use ($deptId, $employeeId) {
                $q->where('is_general', true);
                if ($deptId) {
                    $q->orWhere('department_id', $deptId);
                }
                if ($employeeId) {
                    $q->orWhere('employee_id', $employeeId);
                }
            })
            ->latest()
            ->limit(5)
            ->get();
    }

    /**
     * @throws AuthorizationException si l'annonce n'est pas visible par l'utilisateur
     */
    public function assertVisible(User $user, Announcement $announcement): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $deptId = $user->employee->department_id ?? null;
        $empId = $user->employee->id ?? null;

        $canView = $announcement->is_general
            || $announcement->department_id === $deptId
            || $announcement->employee_id === $empId;

        if (!$canView) {
            throw new AuthorizationException('Accès refusé.');
        }
    }

    /**
     * BUGFIX (voir cahier des charges) : avant, un manager pouvait publier une
     * annonce GÉNÉRALE (en laissant department_id/employee_id vides) ou cibler
     * un employé en dehors de son département — seul le cas "autre département
     * explicitement renseigné" était bloqué. Un manager ne peut maintenant
     * QUE cibler son propre département (jamais le général, jamais un autre
     * département, jamais un employé hors de son équipe).
     *
     * @throws AuthorizationException si un manager tente de sortir de son département
     */
    public function store(User $creator, array $validated): Announcement
    {
        if (!$creator->isAdmin()) {
            $managerDeptId = $creator->employee->department_id ?? null;

            if (!$managerDeptId) {
                throw new AuthorizationException("Vous n'avez pas de département assigné.");
            }

            if (!empty($validated['employee_id'])) {
                $target = Employee::find($validated['employee_id']);
                if (!$target || (int) $target->department_id !== (int) $managerDeptId) {
                    throw new AuthorizationException("Vous ne pouvez cibler qu'un employé de votre département.");
                }
                // Message pour UN employé précis : pas de department_id, sinon tout
                // le département verrait un message qui ne lui était pas destiné.
                $validated['department_id'] = null;
            } else {
                // Pas d'employé ciblé -> message pour tout le département du manager
                // (forcé côté serveur : un manager ne choisit pas un autre département).
                $validated['department_id'] = $managerDeptId;
            }
            $validated['is_general'] = false;
        } else {
            $validated['is_general'] = empty($validated['department_id']) && empty($validated['employee_id']);
        }

        $announcement = Announcement::create([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'department_id' => $validated['department_id'] ?? null,
            'employee_id' => $validated['employee_id'] ?? null,
            'is_general' => $validated['is_general'],
            'user_id' => $creator->id,
        ]);

        return $announcement->load(['creator', 'department', 'employee']);
    }

    /**
     * @throws AuthorizationException si l'utilisateur ne peut pas modifier cette annonce
     */
    public function update(User $editor, Announcement $announcement, array $validated): Announcement
    {
        $this->assertCanManage($editor, $announcement, 'modifier que vos annonces');

        $announcement->update($validated);

        return $announcement->load(['creator', 'department', 'employee']);
    }

    /**
     * @throws AuthorizationException si l'utilisateur ne peut pas supprimer cette annonce
     */
    public function delete(User $editor, Announcement $announcement): void
    {
        $this->assertCanManage($editor, $announcement, 'action non autorisée');
        $announcement->delete();
    }

    private function assertCanManage(User $user, Announcement $announcement, string $message): void
    {
        // Chacun ne modifie / supprime que SES annonces : ni l'admin ne touche à
        // celles d'un manager, ni un manager à celles de l'admin ou d'un autre manager.
        if ((int) $announcement->user_id !== (int) $user->id) {
            throw new AuthorizationException(ucfirst($message) . '.');
        }
    }
}
