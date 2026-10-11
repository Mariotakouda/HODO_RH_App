<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var \App\Models\Employee $employee */
        $employee = $this->route('employee');

        return [
            'first_name'    => 'sometimes|required|string|max:255',
            'last_name'     => 'sometimes|required|string|max:255',
            'email'         => [
                'sometimes',
                'required',
                'email',
                'unique:employees,email,' . $employee->id,
                'unique:users,email,' . ($employee->user_id ?? 'NULL'),
            ],
            'phone'         => 'nullable|string',
            'contract_type' => 'sometimes|required|string',
            'hire_date'     => 'sometimes|required|date|date_format:Y-m-d',
            'salary_base'   => 'sometimes|required|numeric',
            'department_id' => 'nullable|exists:departments,id',
            'role_ids'      => 'nullable|array',
            'role_ids.*'    => 'exists:roles,id',
            'status'        => 'sometimes|required|string|in:actif,demission,renvoyer,retraite',
        ];
    }
}
