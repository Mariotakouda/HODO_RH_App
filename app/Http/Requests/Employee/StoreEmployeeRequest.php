<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // déjà filtré par le middleware 'admin' sur la route
    }

    public function rules(): array
    {
        return [
            'first_name'    => 'required|string|max:255',
            'last_name'     => 'required|string|max:255',
            'email'         => 'required|email|unique:employees,email|unique:users,email',
            'phone'         => 'nullable|string',
            'contract_type' => 'required|string',
            'hire_date'     => 'required|date|date_format:Y-m-d',
            'salary_base'   => 'required|numeric',
            'department_id' => 'nullable|exists:departments,id',
            'role_ids'      => 'nullable|array',
            'role_ids.*'    => 'exists:roles,id',
        ];
    }
}
