<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Manager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManagerService
{
    public function paginated(Request $request)
    {
        $query = Manager::query()->with(['employee', 'department']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate(10);
    }

    public function create(array $validated): Manager
    {
        return DB::transaction(function () use ($validated) {
            $employee = Employee::findOrFail($validated['employee_id']);

            $validated['full_name'] = $employee->first_name . ' ' . $employee->last_name;
            $validated['email'] = $employee->email;

            $manager = Manager::create($validated);

            // Garder departments.manager_id synchronisé avec managers.department_id
            if (!empty($validated['department_id'])) {
                Department::where('id', $validated['department_id'])->update(['manager_id' => $manager->id]);
            }

            // Le manager doit appartenir au département qu'il dirige : les tâches,
            // présences et employés visibles sont filtrés sur employee.department_id.
            if (!empty($validated['department_id'])) {
                $employee->update(['department_id' => $validated['department_id']]);
            }

            // Synchronisation du rôle utilisateur (crucial pour isManager())
            if ($employee->user) {
                $employee->user->update(['role' => UserRole::Manager->value]);
                Log::info("Rôle utilisateur mis à jour vers 'manager' pour l'ID: " . $employee->user_id);
            }

            return $manager->load(['employee', 'department']);
        });
    }

    public function update(Manager $manager, array $validated): Manager
    {
        $oldDepartmentId = $manager->department_id;

        $manager->update($validated);

        if (array_key_exists('department_id', $validated)) {
            $newDepartmentId = $validated['department_id'];

            if ($oldDepartmentId && $oldDepartmentId != $newDepartmentId) {
                Department::where('id', $oldDepartmentId)
                    ->where('manager_id', $manager->id)
                    ->update(['manager_id' => null]);
            }
            if (!empty($newDepartmentId)) {
                Department::where('id', $newDepartmentId)->update(['manager_id' => $manager->id]);
            }
        }

        if (!empty($validated['department_id']) && $manager->employee) {
            $manager->employee->update(['department_id' => $validated['department_id']]);
        }

        return $manager->load(['employee', 'department']);
    }

    public function delete(Manager $manager): void
    {
        DB::transaction(function () use ($manager) {
            $employee = $manager->employee;

            Department::where('manager_id', $manager->id)->update(['manager_id' => null]);

            // Rétrogradation du rôle : si on supprime le manager, il redevient un simple employé
            if ($employee && $employee->user) {
                $employee->user->update(['role' => UserRole::Employee->value]);
            }

            $manager->delete();
        });
    }
}
