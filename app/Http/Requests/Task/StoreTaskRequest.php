<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:pending,in_progress,completed,cancelled',
            'employee_id' => ['required', \Illuminate\Validation\Rule::exists('employees', 'id')->whereNull('archived_at')],
            'due_date' => 'nullable|date',
            'task_file' => 'nullable|file|mimes:pdf|max:10240',
        ];
    }
}
