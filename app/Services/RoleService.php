<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Http\Request;

class RoleService
{
    public function paginated(Request $request)
    {
        $search = $request->query('search');

        return Role::query()
            ->when($search, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->withCount('employees')
            ->orderBy('name')
            ->paginate(10);
    }

    public function create(array $validated): Role
    {
        return Role::create($validated);
    }

    public function update(Role $role, array $validated): Role
    {
        $role->update($validated);
        return $role;
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }
}
