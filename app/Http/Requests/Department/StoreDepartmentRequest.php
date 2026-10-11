<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|unique:departments,name',
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:managers,id',
        ];
    }
}
