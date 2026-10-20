<?php

namespace App\Services;

use InvalidArgumentException;

class ExpenseAllocationCalculator
{
    private const PERCENTAGE_SCALE = 10_000;

    private const FULL_PERCENTAGE = 100 * self::PERCENTAGE_SCALE;

    /**
     * @param  list<array{unit_id: int, percentage: string|int}>  $allocations
     * @return list<array{unit_id: int, percentage: string, amount_cents: int}>
     */
    public function calculate(int $totalAmountCents, array $allocations): array
    {
        if ($totalAmountCents < 0) {
            throw new InvalidArgumentException('O valor total em centavos não pode ser negativo.');
        }

        if ($allocations === [] || ! array_is_list($allocations)) {
            throw new InvalidArgumentException('Informe ao menos um rateio em formato de lista.');
        }

        $entries = [];
        $seenUnitIds = [];
        $percentageTotal = 0;

        foreach ($allocations as $allocation) {
            if (! is_array($allocation)
                || ! isset($allocation['unit_id'], $allocation['percentage'])
                || ! is_int($allocation['unit_id'])
                || $allocation['unit_id'] < 1) {
                throw new InvalidArgumentException('Cada rateio precisa de um identificador de unidade válido.');
            }

            $unitId = $allocation['unit_id'];

            if (isset($seenUnitIds[$unitId])) {
                throw new InvalidArgumentException('Uma unidade não pode aparecer mais de uma vez no rateio.');
            }

            $seenUnitIds[$unitId] = true;
            $percentage = $this->parsePercentage($allocation['percentage']);
            $percentageTotal += $percentage['units'];
            $entries[] = [
                'unit_id' => $unitId,
                'percentage' => $percentage['formatted'],
                'percentage_units' => $percentage['units'],
            ];
        }

        if ($percentageTotal !== self::FULL_PERCENTAGE) {
            throw new InvalidArgumentException('A soma dos percentuais do rateio deve ser exatamente 100%.');
        }

        $wholeUnits = intdiv($totalAmountCents, self::FULL_PERCENTAGE);
        $remainingUnits = $totalAmountCents % self::FULL_PERCENTAGE;
        $allocatedCents = 0;

        foreach ($entries as &$entry) {
            $weightedRemainder = $remainingUnits * $entry['percentage_units'];
            $entry['amount_cents'] = $wholeUnits * $entry['percentage_units']
                + intdiv($weightedRemainder, self::FULL_PERCENTAGE);
            $entry['fractional_remainder'] = $weightedRemainder % self::FULL_PERCENTAGE;
            $allocatedCents += $entry['amount_cents'];
        }
        unset($entry);

        $leftoverCents = $totalAmountCents - $allocatedCents;
        $remainderOrder = $entries;

        usort($remainderOrder, static function (array $left, array $right): int {
            $remainderComparison = $right['fractional_remainder'] <=> $left['fractional_remainder'];

            return $remainderComparison !== 0
                ? $remainderComparison
                : $left['unit_id'] <=> $right['unit_id'];
        });

        for ($index = 0; $index < $leftoverCents; $index++) {
            $unitId = $remainderOrder[$index]['unit_id'];

            foreach ($entries as &$entry) {
                if ($entry['unit_id'] === $unitId) {
                    $entry['amount_cents']++;
                    break;
                }
            }
            unset($entry);
        }

        usort($entries, static fn (array $left, array $right): int => $left['unit_id'] <=> $right['unit_id']);

        return array_map(
            static fn (array $entry): array => [
                'unit_id' => $entry['unit_id'],
                'percentage' => $entry['percentage'],
                'amount_cents' => $entry['amount_cents'],
            ],
            $entries,
        );
    }

    /**
     * @return array{units: int, formatted: string}
     */
    private function parsePercentage(mixed $percentage): array
    {
        if (! is_string($percentage) && ! is_int($percentage)) {
            throw new InvalidArgumentException('O percentual deve ser informado como inteiro ou decimal textual.');
        }

        $value = (string) $percentage;

        if (! preg_match('/^(\d+)(?:\.(\d{1,4}))?$/', $value, $matches)) {
            throw new InvalidArgumentException('O percentual deve ter no máximo quatro casas decimais.');
        }

        $wholePart = ltrim($matches[1], '0');
        $wholePart = $wholePart === '' ? '0' : $wholePart;

        if (strlen($wholePart) > 3 || (int) $wholePart > 100) {
            throw new InvalidArgumentException('O percentual deve estar entre 0% e 100%.');
        }

        $fractionalPart = str_pad($matches[2] ?? '', 4, '0');
        $percentageUnits = ((int) $wholePart * self::PERCENTAGE_SCALE) + (int) $fractionalPart;

        if ($percentageUnits < 1 || $percentageUnits > self::FULL_PERCENTAGE) {
            throw new InvalidArgumentException('O percentual deve ser maior que 0% e no máximo 100%.');
        }

        return [
            'units' => $percentageUnits,
            'formatted' => sprintf('%d.%04d', (int) $wholePart, (int) $fractionalPart),
        ];
    }
}
