<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TaskService
{
    private const WITH = ['employee.department', 'creator'];

    public function myTasks(Employee $employee)
    {
        return Task::with(self::WITH)
            ->where('employee_id', $employee->id)
            ->latest()
            ->get();
    }

    /**
     * Tâches de l'équipe du manager (son département, sauf lui-même), groupées par statut.
     *
     * @throws AuthorizationException si le manager n'a pas de département
     */
    public function teamTasks(Employee $managerEmployee): array
    {
        if (!$managerEmployee->department_id) {
            throw new AuthorizationException("Vous n'avez pas de département assigné.");
        }

        $tasks = Task::with(self::WITH)
            ->whereHas('employee', function ($q) use ($managerEmployee) {
                $q->where('department_id', $managerEmployee->department_id)
                    ->where('id', '!=', $managerEmployee->id);
            })
            ->latest()
            ->get();

        $byStatus = collect(TaskStatus::cases())->mapWithKeys(
            fn (TaskStatus $status) => [$status->value => $tasks->where('status', $status)->values()]
        );

        return [
            'data' => $tasks,
            'grouped' => $byStatus,
            'stats' => [
                'total' => $tasks->count(),
                ...$byStatus->map->count(),
            ],
        ];
    }

    /**
     * Liste pour admin (toutes) ou manager (son département uniquement).
     */
    public function listForStaff(User $user)
    {
        $query = Task::with(self::WITH);

        if (!$user->isAdmin()) {
            $deptId = $user->employee->department_id ?? null;
            if (!$deptId) {
                return null; // le contrôleur renverra une liste vide explicite
            }
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $deptId));
        }

        return $query->latest()->paginate(15);
    }

    /**
     * @throws AuthorizationException si un manager tente d'assigner hors de son département
     */
    public function create(User $creator, array $validated, ?UploadedFile $file): Task
    {
        if (!$creator->isAdmin()) {
            $managerDeptId = $creator->employee->department_id ?? null;
            $targetEmployee = Employee::findOrFail($validated['employee_id']);

            if (!$managerDeptId) {
                throw new AuthorizationException("Vous n'avez pas de département assigné.");
            }
            if ((int) $targetEmployee->department_id !== (int) $managerDeptId) {
                throw new AuthorizationException("Vous ne pouvez assigner des tâches qu'aux employés de votre département.");
            }
        }

        $path = $file?->store('task_consignes', 'public');

        $task = Task::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'employee_id' => $validated['employee_id'],
            'due_date' => $validated['due_date'] ?? null,
            'task_file' => $path,
            'creator_id' => $creator->id,
        ]);

        return $task->load(self::WITH);
    }

    /**
     * @throws AuthorizationException si un manager tente de modifier une tâche hors de son département
     */
    public function update(User $editor, Task $task, array $validated, ?UploadedFile $file): Task
    {
        if (!$editor->isAdmin()) {
            $managerDeptId = $editor->employee->department_id ?? null;
            if (!$managerDeptId || (int) $task->employee->department_id !== (int) $managerDeptId) {
                throw new AuthorizationException('Accès refusé');
            }
        }

        if ($file) {
            if ($task->task_file) {
                Storage::disk('public')->delete($task->task_file);
            }
            $validated['task_file'] = $file->store('task_consignes', 'public');
        }

        $task->update($validated);

        return $task->load(self::WITH);
    }

    /**
     * @throws AuthorizationException si l'employé n'est pas le destinataire de la tâche
     */
    public function submitReport(User $submitter, Task $task, UploadedFile $file): Task
    {
        $this->assertOwnedByEmployee($submitter, $task);

        if ($task->report_file) {
            Storage::disk('public')->delete($task->report_file);
        }

        $task->update([
            'report_file' => $file->store('task_reports', 'public'),
            'status' => TaskStatus::Completed,
        ]);

        return $task->load(self::WITH);
    }

    /**
     * @throws AuthorizationException
     */
    public function markAsCompleted(User $submitter, Task $task): Task
    {
        $this->assertOwnedByEmployee($submitter, $task);

        if (!$task->report_file) {
            throw new \RuntimeException('Rapport requis');
        }

        $task->update(['status' => TaskStatus::Completed]);

        return $task;
    }

    public function delete(Task $task): void
    {
        if ($task->task_file) {
            Storage::disk('public')->delete($task->task_file);
        }
        if ($task->report_file) {
            Storage::disk('public')->delete($task->report_file);
        }
        $task->delete();
    }

    /**
     * @throws AuthorizationException
     */
    private function assertOwnedByEmployee(User $user, Task $task): void
    {
        if (!$user->employee || (int) $task->employee_id !== (int) $user->employee->id) {
            throw new AuthorizationException('Accès refusé');
        }
    }
}
