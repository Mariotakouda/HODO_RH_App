<?php

namespace App\Http\Requests\EmployeeRole;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'role_id' => 'required|exists:roles,id',
        ];
    }
}
