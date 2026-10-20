<?php

namespace Tests\Feature;

use App\Jobs\ConvertPendingExpenseJob;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExpenseRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_api_registers_company_and_unit(): void
    {
        $companyResponse = $this->postJson('/api/v1/companies', [
            'name' => 'Grupo Exemplo',
        ]);

        $companyResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'Grupo Exemplo');

        $unitResponse = $this->postJson('/api/v1/units', [
            'company_id' => $companyResponse->json('data.id'),
            'name' => 'Unidade Centro',
            'code' => 'unidade-centro',
        ]);

        $unitResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'Unidade Centro')
            ->assertJsonPath('data.company.name', 'Grupo Exemplo');
    }

    public function test_api_registers_brl_expense_using_exact_cent_allocations(): void
    {
        $units = $this->createUnits(3);

        $response = $this->postJson('/api/v1/expenses', [
            'description' => 'Licença CRM',
            'supplier' => 'Fornecedor X',
            'expense_date' => '2026-09-01',
            'amount' => '100.00',
            'currency' => 'BRL',
            'allocations' => [
                ['unit_id' => $units[0]->id, 'percentage' => '33.3333'],
                ['unit_id' => $units[1]->id, 'percentage' => '33.3334'],
                ['unit_id' => $units[2]->id, 'percentage' => '33.3333'],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.currency', 'BRL')
            ->assertJsonPath('data.amount_cents', 10000)
            ->assertJsonPath('data.converted_amount_cents', 10000)
            ->assertJsonPath('data.conversion_status', Expense::CONVERSION_NOT_REQUIRED)
            ->assertJsonCount(3, 'data.allocations');

        $this->assertSame(
            [3333, 3334, 3333],
            array_column($response->json('data.allocations'), 'amount_cents'),
        );
        $this->assertSame(10000, array_sum(array_column($response->json('data.allocations'), 'amount_cents')));
    }

    public function test_api_rejects_incomplete_percentages_without_saving_an_expense(): void
    {
        $units = $this->createUnits(2);

        $this->postJson('/api/v1/expenses', [
            'description' => 'Serviço',
            'supplier' => 'Fornecedor',
            'expense_date' => '2026-09-01',
            'amount' => '15.00',
            'currency' => 'BRL',
            'allocations' => [
                ['unit_id' => $units[0]->id, 'percentage' => '50'],
                ['unit_id' => $units[1]->id, 'percentage' => '49.9999'],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('allocations');

        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('expense_allocations', 0);
    }

    public function test_api_keeps_usd_expense_and_allocations_pending_conversion(): void
    {
        Queue::fake();
        $units = $this->createUnits(2);

        $response = $this->postJson('/api/v1/expenses', [
            'description' => 'Serviço em dólar',
            'supplier' => 'Fornecedor',
            'expense_date' => '2026-09-01',
            'amount' => '1500.00',
            'currency' => 'USD',
            'allocations' => [
                ['unit_id' => $units[0]->id, 'percentage' => '50'],
                ['unit_id' => $units[1]->id, 'percentage' => '50'],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.amount_cents', 150000)
            ->assertJsonPath('data.converted_amount_cents', null)
            ->assertJsonPath('data.conversion_status', Expense::CONVERSION_PENDING)
            ->assertJsonPath('data.allocations.0.amount_cents', null)
            ->assertJsonPath('data.allocations.1.amount_cents', null);

        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseCount('expense_allocations', 2);
        Queue::assertPushed(ConvertPendingExpenseJob::class, fn (ConvertPendingExpenseJob $job): bool => $job->expenseId === $response->json('data.id'));
    }

    public function test_web_form_uses_the_same_expense_registration_service(): void
    {
        $unit = $this->createUnits(1)[0];

        $this->get('/')
            ->assertOk()
            ->assertSee('Cadastre empresas, unidades e despesas compartilhadas.');

        $this->post('/expenses', [
            'description' => 'Internet',
            'supplier' => 'Fornecedor local',
            'expense_date' => '2026-09-02',
            'amount' => '120.50',
            'currency' => 'BRL',
            'allocations' => [
                ['unit_id' => (string) $unit->id, 'percentage' => '100'],
            ],
        ])
            ->assertRedirect('/')
            ->assertSessionHas('status', 'Despesa cadastrada com sucesso.');

        $this->assertDatabaseHas('expenses', [
            'description' => 'Internet',
            'amount_cents' => 12050,
            'converted_amount_cents' => 12050,
        ]);
        $this->assertDatabaseHas('expense_allocations', [
            'unit_id' => $unit->id,
            'percentage' => '100.0000',
            'amount_cents' => 12050,
        ]);
    }

    /**
     * @return list<Unit>
     */
    private function createUnits(int $count): array
    {
        $company = Company::create(['name' => 'Empresa de teste']);
        $units = [];

        for ($index = 1; $index <= $count; $index++) {
            $units[] = Unit::create([
                'company_id' => $company->id,
                'name' => "Unidade {$index}",
                'code' => "unidade-{$index}",
            ]);
        }

        return $units;
    }
}
