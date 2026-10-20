<?php

namespace App\Services;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RegisterExpense
{
    public function __construct(
        private readonly ExpenseAllocationCalculator $calculator,
    ) {}

    /**
     * @param  array{
     *     description: string,
     *     supplier: string,
     *     expense_date: string,
     *     amount: string,
     *     currency: string,
     *     allocations: list<array{unit_id: int|string, percentage: mixed}>
     * }  $data
     */
    public function handle(array $data): Expense
    {
        try {
            $amountCents = $this->parseAmountCents($data['amount']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'amount' => [$exception->getMessage()],
            ]);
        }

        try {
            $allocations = array_map(
                static fn (array $allocation): array => [
                    'unit_id' => (int) $allocation['unit_id'],
                    'percentage' => $allocation['percentage'],
                ],
                $data['allocations'],
            );
            $calculatedAllocations = $this->calculator->calculate(
                $data['currency'] === Expense::CURRENCY_BRL ? $amountCents : 0,
                $allocations,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'allocations' => [$exception->getMessage()],
            ]);
        }

        $isBrl = $data['currency'] === Expense::CURRENCY_BRL;

        return DB::transaction(function () use ($data, $amountCents, $calculatedAllocations, $isBrl): Expense {
            $expense = Expense::create([
                'description' => $data['description'],
                'supplier' => $data['supplier'],
                'expense_date' => $data['expense_date'],
                'amount_cents' => $amountCents,
                'currency' => $data['currency'],
                'converted_amount_cents' => $isBrl ? $amountCents : null,
                'conversion_status' => $isBrl
                    ? Expense::CONVERSION_NOT_REQUIRED
                    : Expense::CONVERSION_PENDING,
            ]);

            $expense->allocations()->createMany(array_map(
                static fn (array $allocation): array => [
                    'unit_id' => $allocation['unit_id'],
                    'percentage' => $allocation['percentage'],
                    'amount_cents' => $isBrl ? $allocation['amount_cents'] : null,
                ],
                $calculatedAllocations,
            ));

            return $expense->load('allocations.unit.company');
        });
    }

    private function parseAmountCents(string $amount): int
    {
        if (! preg_match('/^(?:0|[1-9]\d{0,14})(?:\.(\d{1,2}))?$/', $amount)
            || preg_match('/^0(?:\.0{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('O valor da despesa deve ser positivo e ter no máximo duas casas decimais.');
        }

        $parts = explode('.', $amount, 2);
        $whole = $parts[0];
        $fraction = $parts[1] ?? '';

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
