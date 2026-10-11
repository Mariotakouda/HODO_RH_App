<?php

namespace App\Http\Requests\Manager;

use Illuminate\Foundation\Http\FormRequest;

class UpdateManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $manager = $this->route('manager');
        $managerId = is_object($manager) ? $manager->id : $manager;

        return [
            'department_id' => [
                'nullable',
                'exists:departments,id',
                \Illuminate\Validation\Rule::unique('managers', 'department_id')->ignore($managerId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'department_id.unique' => 'Ce département a déjà un manager.',
        ];
    }
}
