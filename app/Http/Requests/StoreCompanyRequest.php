<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('companies', 'name')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da empresa.',
            'name.max' => 'O nome da empresa deve ter no máximo 255 caracteres.',
            'name.unique' => 'Já existe uma empresa com esse nome.',
        ];
    }
}
