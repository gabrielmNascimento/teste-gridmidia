<?php

namespace App\Services;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConvertPendingExpense
{
    public function __construct(
        private readonly FrankfurterExchangeRateProvider $exchangeRateProvider,
        private readonly DecimalCurrencyConverter $currencyConverter,
        private readonly ExpenseAllocationCalculator $allocationCalculator,
    ) {}

    public function handle(int $expenseId): Expense
    {
        $expense = Expense::query()->findOrFail($expenseId);

        if ($expense->currency !== Expense::CURRENCY_USD
            || $expense->conversion_status !== Expense::CONVERSION_PENDING) {
            return $expense;
        }

        $quote = $this->exchangeRateProvider->quoteFor($expense->expense_date);
        $convertedAmountCents = $this->currencyConverter->toBrlCents($expense->amount_cents, $quote['rate']);

        $calculatedAllocations = $this->allocationCalculator->calculate(
            $convertedAmountCents,
            $expense->allocations()
                ->get(['unit_id', 'percentage'])
                ->map(static fn ($allocation): array => [
                    'unit_id' => $allocation->unit_id,
                    'percentage' => $allocation->percentage,
                ])
                ->all(),
        );

        return DB::transaction(function () use ($expenseId, $quote, $convertedAmountCents, $calculatedAllocations): Expense {
            $lockedExpense = Expense::query()->lockForUpdate()->findOrFail($expenseId);

            if ($lockedExpense->currency !== Expense::CURRENCY_USD
                || $lockedExpense->conversion_status !== Expense::CONVERSION_PENDING) {
                return $lockedExpense;
            }

            if (array_sum(array_column($calculatedAllocations, 'amount_cents')) !== $convertedAmountCents) {
                throw new RuntimeException('A soma dos rateios não corresponde ao valor convertido.');
            }

            $lockedExpense->update([
                'converted_amount_cents' => $convertedAmountCents,
                'exchange_rate' => $quote['rate'],
                'exchange_rate_date' => $quote['date'],
                'exchange_rate_provider' => $quote['provider'],
                'conversion_status' => Expense::CONVERSION_CONVERTED,
            ]);

            foreach ($calculatedAllocations as $allocation) {
                $lockedExpense->allocations()
                    ->where('unit_id', $allocation['unit_id'])
                    ->update(['amount_cents' => $allocation['amount_cents']]);
            }

            return $lockedExpense->load('allocations.unit.company');
        });
    }
}
