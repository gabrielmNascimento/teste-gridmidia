<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use OverflowException;

class UnitExpenseReport
{
    /**
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     total_brl_cents: int,
     *     units: list<array{id: int, name: string, code: string, company: string, total_brl_cents: int}>
     * }
     */
    public function forPeriod(string $startDate, string $endDate): array
    {
        $units = Unit::query()
            ->with('company')
            ->withSum([
                'allocations as total_brl_cents' => function (Builder $query) use ($startDate, $endDate): void {
                    $query->whereNotNull('amount_cents')
                        ->whereHas('expense', function (Builder $query) use ($startDate, $endDate): void {
                            $query->whereBetween('expense_date', [$startDate, $endDate])
                                ->whereIn('conversion_status', [
                                    Expense::CONVERSION_NOT_REQUIRED,
                                    Expense::CONVERSION_CONVERTED,
                                ]);
                        });
                },
            ], 'amount_cents')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'name' => $unit->name,
                'code' => $unit->code,
                'company' => $unit->company->name,
                'total_brl_cents' => $this->toIntegerCents($unit->total_brl_cents ?? 0),
            ])
            ->all();
        $totalBrlCents = 0;

        foreach ($units as $unit) {
            if ($unit['total_brl_cents'] > PHP_INT_MAX - $totalBrlCents) {
                throw new OverflowException('O total do relatório excede o limite suportado em centavos.');
            }

            $totalBrlCents += $unit['total_brl_cents'];
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_brl_cents' => $totalBrlCents,
            'units' => $units,
        ];
    }

    private function toIntegerCents(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $cents = (string) $value;
        $maximum = (string) PHP_INT_MAX;

        if (! ctype_digit($cents)
            || strlen($cents) > strlen($maximum)
            || (strlen($cents) === strlen($maximum) && strcmp($cents, $maximum) > 0)) {
            throw new OverflowException('O total de uma unidade excede o limite suportado em centavos.');
        }

        return (int) $cents;
    }
}
