<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class TransferEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // routes protégées par le middleware admin
    }

    public function rules(): array
    {
        return [
            'department_id' => 'required|integer|exists:departments,id',
            'reason' => 'nullable|string|max:500',
            'effective_date' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'department_id.required' => 'Choisissez le département de destination.',
            'department_id.exists' => 'Ce département est introuvable.',
        ];
    }
}
