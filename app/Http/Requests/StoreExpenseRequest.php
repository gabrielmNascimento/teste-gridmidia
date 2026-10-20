<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'supplier' => ['required', 'string', 'max:255'],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'string', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,2})?$/'],
            'currency' => ['required', 'in:BRL,USD'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*' => ['required', 'array:unit_id,percentage'],
            'allocations.*.unit_id' => ['required', 'integer', 'distinct:strict', 'exists:units,id'],
            'allocations.*.percentage' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'Informe a descrição da despesa.',
            'supplier.required' => 'Informe o fornecedor.',
            'expense_date.required' => 'Informe a data da despesa.',
            'expense_date.date_format' => 'A data deve estar no formato AAAA-MM-DD.',
            'amount.required' => 'Informe o valor da despesa.',
            'amount.regex' => 'Informe um valor positivo com até duas casas decimais, usando ponto como separador.',
            'currency.required' => 'Selecione a moeda da despesa.',
            'currency.in' => 'A moeda deve ser BRL ou USD.',
            'allocations.required' => 'Informe o rateio da despesa.',
            'allocations.min' => 'Informe ao menos uma unidade no rateio.',
            'allocations.*.unit_id.required' => 'Selecione uma unidade para cada parcela do rateio.',
            'allocations.*.unit_id.exists' => 'Uma das unidades selecionadas não existe.',
            'allocations.*.unit_id.distinct' => 'Cada unidade pode aparecer apenas uma vez no rateio.',
            'allocations.*.percentage.required' => 'Informe o percentual de cada unidade.',
        ];
    }
}
