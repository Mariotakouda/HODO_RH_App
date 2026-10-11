<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Manager;
use Illuminate\Http\Request;

class DepartmentService
{
    public function simpleList()
    {
        return Department::select('id', 'name')->orderBy('name')->get();
    }

    public function paginated(Request $request)
    {
        $query = Department::query();

        if ($search = $request->query('search')) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $query->with('manager');

        return $query->orderBy('name')->paginate(10);
    }

    public function create(array $validated): Department
    {
        $department = Department::create($validated);

        // Garder managers.department_id synchronisé avec departments.manager_id
        if (!empty($validated['manager_id'])) {
            // Un manager ne dirige qu'un seul département
            Department::where('manager_id', $validated['manager_id'])->where('id', '!=', $department->id)->update(['manager_id' => null]);
            Manager::where('id', $validated['manager_id'])->update(['department_id' => $department->id]);
        }

        return $department->load('manager');
    }

    public function update(Department $department, array $validated): Department
    {
        $oldManagerId = $department->manager_id;

        $department->update($validated);

        if (array_key_exists('manager_id', $validated)) {
            $newManagerId = $validated['manager_id'];

            if ($oldManagerId && $oldManagerId != $newManagerId) {
                Manager::where('id', $oldManagerId)->update(['department_id' => null]);
            }
            if (!empty($newManagerId)) {
                Department::where('manager_id', $newManagerId)->where('id', '!=', $department->id)->update(['manager_id' => null]);
                Manager::where('id', $newManagerId)->update(['department_id' => $department->id]);
            }
        }

        return $department->load('manager');
    }

    public function delete(Department $department): void
    {
        $department->delete();
    }
}
