<?php

namespace Tests\Feature;

use App\Jobs\ConvertPendingExpenseJob;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExpenseImportReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_imports_valid_csv_rows_independently_and_reports_row_errors(): void
    {
        Queue::fake();
        $units = $this->createUnits();
        $file = UploadedFile::fake()->createWithContent('expenses.csv', implode("\n", [
            'description,supplier,expense_date,amount,currency,allocations',
            'Internet,Fornecedor,2026-10-01,100.00,BRL,unidade-1=60;unidade-2=40',
            'Sem unidade,Fornecedor,2026-10-01,50.00,BRL,unidade-inexistente=100',
            'Percentual inválido,Fornecedor,2026-10-01,50.00,BRL,unidade-1=60;unidade-2=30',
            'Hospedagem,Fornecedor,2026-10-02,10.00,USD,unidade-1=100',
        ]));

        $response = $this->post('/api/v1/expenses/import', ['file' => $file]);

        $response
            ->assertOk()
            ->assertJsonPath('data.summary.total_rows', 4)
            ->assertJsonPath('data.summary.imported', 2)
            ->assertJsonPath('data.summary.failed', 2)
            ->assertJsonPath('data.results.0.status', 'imported')
            ->assertJsonPath('data.results.1.line', 3)
            ->assertJsonPath('data.results.1.status', 'failed')
            ->assertJsonPath('data.results.2.status', 'failed')
            ->assertJsonPath('data.results.3.status', 'imported');

        $this->assertDatabaseCount('expenses', 2);
        $this->assertDatabaseHas('expense_allocations', [
            'unit_id' => $units[0]->id,
            'percentage' => '60.0000',
            'amount_cents' => 6000,
        ]);
        $this->assertDatabaseHas('expense_allocations', [
            'unit_id' => $units[1]->id,
            'percentage' => '40.0000',
            'amount_cents' => 4000,
        ]);
        Queue::assertPushed(ConvertPendingExpenseJob::class, 1);
    }

    public function test_web_csv_import_shows_the_per_line_result(): void
    {
        $this->createUnits();
        $file = UploadedFile::fake()->createWithContent('expenses.csv', implode("\n", [
            'description,supplier,expense_date,amount,currency,allocations',
            'Despesa,Fornecedor,2026-10-01,25.00,BRL,unidade-1=100',
            'Inválida,Fornecedor,2026-10-01,25.00,BRL,codigo-ausente=100',
        ]));

        $this->post('/expenses/import', ['file' => $file])
            ->assertRedirect('/')
            ->assertSessionHas('status', 'Importação concluída: 1 linha(s) importada(s), 1 com erro.')
            ->assertSessionHas('import_results.1.status', 'failed');

        $this->get('/')
            ->assertOk()
            ->assertSee('Resultado por linha')
            ->assertSee('O código de unidade');

        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_csv_import_rejects_a_missing_or_invalid_header(): void
    {
        $file = UploadedFile::fake()->createWithContent('expenses.csv', "description,supplier\nDespesa,Fornecedor");

        $this->postJson('/api/v1/expenses/import', ['file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_unit_report_sums_only_allocated_brl_values_within_the_requested_period(): void
    {
        [$unitOne, $unitTwo] = $this->createUnits();
        $company = $unitOne->company;
        Unit::create(['company_id' => $company->id, 'name' => 'Unidade sem despesas', 'code' => 'sem-despesas']);
        $this->createExpense('Despesa BRL', '2026-10-05', Expense::CURRENCY_BRL, Expense::CONVERSION_NOT_REQUIRED, 10000, [
            [$unitOne, 7500],
            [$unitTwo, 2500],
        ]);
        $this->createExpense('Despesa USD convertida', '2026-10-10', Expense::CURRENCY_USD, Expense::CONVERSION_CONVERTED, 20000, [
            [$unitOne, 20000],
        ]);
        $this->createExpense('Despesa USD pendente', '2026-10-12', Expense::CURRENCY_USD, Expense::CONVERSION_PENDING, null, [
            [$unitOne, null],
        ]);
        $this->createExpense('Fora do período', '2026-11-01', Expense::CURRENCY_BRL, Expense::CONVERSION_NOT_REQUIRED, 50000, [
            [$unitOne, 50000],
        ]);

        $this->getJson('/api/v1/reports/units?start_date=2026-10-01&end_date=2026-10-31')
            ->assertOk()
            ->assertJsonPath('data.start_date', '2026-10-01')
            ->assertJsonPath('data.end_date', '2026-10-31')
            ->assertJsonPath('data.total_brl_cents', 30000)
            ->assertJsonPath('data.units.0.total_brl_cents', 27500)
            ->assertJsonPath('data.units.1.total_brl_cents', 2500)
            ->assertJsonPath('data.units.2.total_brl_cents', 0);
    }

    public function test_unit_report_rejects_an_end_date_before_the_start_date(): void
    {
        $this->getJson('/api/v1/reports/units?start_date=2026-10-31&end_date=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');
    }

    public function test_dashboard_displays_the_report_for_its_selected_period(): void
    {
        [$unit] = $this->createUnits();
        $this->createExpense('Despesa', '2026-10-05', Expense::CURRENCY_BRL, Expense::CONVERSION_NOT_REQUIRED, 1250, [
            [$unit, 1250],
        ]);

        $this->get('/?start_date=2026-10-01&end_date=2026-10-31')
            ->assertOk()
            ->assertSee('Relatório por unidade')
            ->assertSee('R$ 12,50')
            ->assertSee('2026-10-01');
    }

    /**
     * @return array{Unit, Unit}
     */
    private function createUnits(): array
    {
        $company = Company::create(['name' => 'Empresa de teste']);

        return [
            Unit::create(['company_id' => $company->id, 'name' => 'Unidade 1', 'code' => 'unidade-1']),
            Unit::create(['company_id' => $company->id, 'name' => 'Unidade 2', 'code' => 'unidade-2']),
        ];
    }

    /**
     * @param  list<array{Unit, ?int}>  $allocations
     */
    private function createExpense(
        string $description,
        string $date,
        string $currency,
        string $status,
        ?int $convertedAmountCents,
        array $allocations,
    ): Expense {
        $expense = Expense::create([
            'description' => $description,
            'supplier' => 'Fornecedor',
            'expense_date' => $date,
            'amount_cents' => 10000,
            'currency' => $currency,
            'converted_amount_cents' => $convertedAmountCents,
            'conversion_status' => $status,
        ]);

        foreach ($allocations as [$unit, $amountCents]) {
            $expense->allocations()->create([
                'unit_id' => $unit->id,
                'percentage' => '100',
                'amount_cents' => $amountCents,
            ]);
        }

        return $expense;
    }
}
