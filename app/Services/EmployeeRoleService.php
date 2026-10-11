<?php

namespace App\Services;

use App\Models\EmployeeRole;

class EmployeeRoleService
{
    public function all()
    {
        return EmployeeRole::with([
            'employee:id,first_name,last_name',
            'role:id,name',
        ])->get();
    }

    /**
     * @throws \RuntimeException si l'attribution existe déjà
     */
    public function create(array $validated): EmployeeRole
    {
        $exists = EmployeeRole::where('employee_id', $validated['employee_id'])
            ->where('role_id', $validated['role_id'])
            ->exists();

        if ($exists) {
            throw new \RuntimeException('Cette attribution de rôle existe déjà.');
        }

        $employeeRole = EmployeeRole::create($validated);

        return $employeeRole->load(['employee:id,first_name,last_name', 'role:id,name']);
    }

    public function delete(int $employeeId, int $roleId): bool
    {
        return (bool) EmployeeRole::where('employee_id', $employeeId)
            ->where('role_id', $roleId)
            ->delete();
    }
}
