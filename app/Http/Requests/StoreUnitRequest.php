<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'alpha_dash:ascii', 'max:255', Rule::unique('units', 'code')],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'Selecione a empresa da unidade.',
            'company_id.exists' => 'A empresa selecionada não existe.',
            'name.required' => 'Informe o nome da unidade.',
            'code.required' => 'Informe o código da unidade.',
            'code.alpha_dash' => 'O código deve conter apenas letras, números, hífens e sublinhados.',
            'code.unique' => 'Esse código de unidade já está em uso.',
        ];
    }
}
