<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArchiveEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // routes protégées par le middleware admin
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['demission', 'renvoyer', 'retraite'])],
            'departure_date' => 'required|date',
            'departure_reason' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Le motif de départ doit être : démission, renvoi ou retraite.',
            'departure_date.required' => 'Indiquez la date de départ.',
        ];
    }
}
