<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportExpensesCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Selecione um arquivo CSV para importar.',
            'file.mimes' => 'O arquivo deve estar no formato CSV.',
            'file.max' => 'O arquivo CSV não pode exceder 10 MB.',
        ];
    }
}
