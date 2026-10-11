<?php

namespace App\Http\Requests\Manager;

use Illuminate\Foundation\Http\FormRequest;

class StoreManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id|unique:managers,employee_id',
            'department_id' => 'nullable|exists:departments,id|unique:managers,department_id',
        ];
    }

    public function messages(): array
    {
        return [
            'department_id.unique' => 'Ce département a déjà un manager.',
            'employee_id.unique' => 'Cet employé est déjà manager.',
        ];
    }
}
