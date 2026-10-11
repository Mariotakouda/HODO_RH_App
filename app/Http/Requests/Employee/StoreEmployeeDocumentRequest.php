<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // routes protégées par le middleware admin
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx',
            'type' => ['required', Rule::in(config('hodo.document_types'))],
            'title' => 'nullable|string|max:150',
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'Le fichier ne doit pas dépasser 5 Mo.',
            'file.mimes' => 'Formats acceptés : PDF, JPG, PNG, DOC, DOCX.',
        ];
    }
}
