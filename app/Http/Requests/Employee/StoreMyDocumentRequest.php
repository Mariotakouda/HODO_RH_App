<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMyDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route self-service : le contrôleur n'agit que sur le dossier de l'utilisateur connecté
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx',
            'type' => ['required', Rule::in(config('hodo.employee_document_types'))],
            'title' => 'nullable|string|max:150',
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'Le fichier ne doit pas dépasser 5 Mo.',
            'file.mimes' => 'Formats acceptés : PDF, JPG, PNG, DOC, DOCX.',
            'type.in' => "Ce type de pièce ne peut être ajouté que par l'administration (ex. contrat).",
        ];
    }
}
