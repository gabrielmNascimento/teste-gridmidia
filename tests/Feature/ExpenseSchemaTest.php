<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseAllocation;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_allocations_connect_companies_units_and_expenses(): void
    {
        $company = Company::create(['name' => 'Empresa A']);
        $unit = $company->units()->create([
            'name' => 'Unidade A',
            'code' => 'unidade-a',
        ]);
        $expense = Expense::create([
            'description' => 'Licença',
            'supplier' => 'Fornecedor',
            'expense_date' => '2026-09-01',
            'amount_cents' => 10000,
            'currency' => Expense::CURRENCY_BRL,
            'converted_amount_cents' => 10000,
            'conversion_status' => Expense::CONVERSION_NOT_REQUIRED,
        ]);

        $allocation = $expense->allocations()->create([
            'unit_id' => $unit->id,
            'percentage' => '100.0000',
            'amount_cents' => 10000,
        ]);

        $this->assertSame('Empresa A', $unit->company->name);
        $this->assertSame('unidade-a', $unit->code);
        $this->assertSame(10000, $expense->units->first()->pivot->amount_cents);
        $this->assertSame('100.0000', $allocation->percentage);
        $this->assertSame($expense->id, $allocation->expense->id);
        $this->assertSame($unit->id, $allocation->unit->id);
    }

    public function test_pending_usd_expenses_can_be_saved_without_brl_allocations(): void
    {
        $unit = Unit::create([
            'company_id' => Company::create(['name' => 'Empresa B'])->id,
            'name' => 'Unidade B',
            'code' => 'unidade-b',
        ]);
        $expense = Expense::create([
            'description' => 'Serviço em dólar',
            'supplier' => 'Fornecedor',
            'expense_date' => '2026-09-01',
            'amount_cents' => 150000,
            'currency' => Expense::CURRENCY_USD,
            'conversion_status' => Expense::CONVERSION_PENDING,
        ]);
        $allocation = ExpenseAllocation::create([
            'expense_id' => $expense->id,
            'unit_id' => $unit->id,
            'percentage' => '100.0000',
        ]);

        $this->assertNull($expense->converted_amount_cents);
        $this->assertNull($allocation->amount_cents);
        $this->assertSame(Expense::CONVERSION_PENDING, $expense->conversion_status);
    }
}
