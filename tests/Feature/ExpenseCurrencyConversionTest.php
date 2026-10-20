<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Expense;
use App\Models\Unit;
use App\Services\ConvertPendingExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExpenseCurrencyConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_converts_pending_usd_using_the_effective_historical_quote(): void
    {
        Http::fake([
            'api.frankfurter.dev/v1/2026-09-01*' => Http::response([
                'amount' => 1,
                'base' => 'USD',
                'date' => '2026-08-31',
                'rates' => ['BRL' => 5.25],
            ]),
        ]);

        $expense = $this->createPendingUsdExpense();

        $converted = app(ConvertPendingExpense::class)->handle($expense->id);

        $this->assertSame(787500, $converted->converted_amount_cents);
        $this->assertSame(Expense::CONVERSION_CONVERTED, $converted->conversion_status);
        $this->assertSame('5.2500000000', $converted->exchange_rate);
        $this->assertSame('2026-08-31', $converted->exchange_rate_date->format('Y-m-d'));
        $this->assertSame('Frankfurter', $converted->exchange_rate_provider);
        $this->assertSame([393750, 393750], $converted->allocations->pluck('amount_cents')->all());
        $this->assertSame(787500, $converted->allocations->sum('amount_cents'));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.frankfurter.dev/v1/2026-09-01?base=USD&symbols=BRL');
    }

    public function test_failed_exchange_rate_request_keeps_expense_pending_and_unallocated(): void
    {
        Http::fake([
            'api.frankfurter.dev/*' => Http::response(['message' => 'Unavailable'], 503),
        ]);

        $expense = $this->createPendingUsdExpense();

        try {
            app(ConvertPendingExpense::class)->handle($expense->id);
            $this->fail('A falha HTTP deveria ser propagada para permitir nova tentativa da fila.');
        } catch (RequestException) {
            $this->assertDatabaseHas('expenses', [
                'id' => $expense->id,
                'conversion_status' => Expense::CONVERSION_PENDING,
                'converted_amount_cents' => null,
                'exchange_rate' => null,
            ]);
            $this->assertDatabaseMissing('expense_allocations', [
                'expense_id' => $expense->id,
                'amount_cents' => 393750,
            ]);
        }
    }

    public function test_reprocessing_an_already_converted_expense_does_not_fetch_a_new_quote(): void
    {
        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                'base' => 'USD',
                'date' => '2026-09-01',
                'rates' => ['BRL' => 5.25],
            ]),
        ]);
        $expense = $this->createPendingUsdExpense();
        $converted = app(ConvertPendingExpense::class)->handle($expense->id);
        Http::fake();

        $again = app(ConvertPendingExpense::class)->handle($converted->id);

        $this->assertSame(Expense::CONVERSION_CONVERTED, $again->conversion_status);
        Http::assertNothingSent();
    }

    private function createPendingUsdExpense(): Expense
    {
        $company = Company::create(['name' => 'Empresa de teste']);
        $units = [
            Unit::create(['company_id' => $company->id, 'name' => 'Unidade 1', 'code' => 'unidade-1']),
            Unit::create(['company_id' => $company->id, 'name' => 'Unidade 2', 'code' => 'unidade-2']),
        ];

        $expense = Expense::create([
            'description' => 'Despesa em dólar',
            'supplier' => 'Fornecedor',
            'expense_date' => '2026-09-01',
            'amount_cents' => 150000,
            'currency' => Expense::CURRENCY_USD,
            'converted_amount_cents' => null,
            'conversion_status' => Expense::CONVERSION_PENDING,
        ]);

        $expense->allocations()->createMany([
            ['unit_id' => $units[0]->id, 'percentage' => '50.0000', 'amount_cents' => null],
            ['unit_id' => $units[1]->id, 'percentage' => '50.0000', 'amount_cents' => null],
        ]);

        return $expense;
    }
}
