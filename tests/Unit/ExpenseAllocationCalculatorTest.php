<?php

namespace Tests\Unit;

use App\Services\ExpenseAllocationCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExpenseAllocationCalculatorTest extends TestCase
{
    public function test_it_distributes_equal_shares_and_closes_all_cents(): void
    {
        $allocations = (new ExpenseAllocationCalculator)->calculate(10000, [
            ['unit_id' => 3, 'percentage' => '33.3333'],
            ['unit_id' => 1, 'percentage' => '33.3333'],
            ['unit_id' => 2, 'percentage' => '33.3334'],
        ]);

        $this->assertSame([
            ['unit_id' => 1, 'percentage' => '33.3333', 'amount_cents' => 3333],
            ['unit_id' => 2, 'percentage' => '33.3334', 'amount_cents' => 3334],
            ['unit_id' => 3, 'percentage' => '33.3333', 'amount_cents' => 3333],
        ], $allocations);
        $this->assertSame(10000, array_sum(array_column($allocations, 'amount_cents')));
    }

    public function test_it_assigns_equal_remainders_to_the_lowest_unit_id(): void
    {
        $allocations = (new ExpenseAllocationCalculator)->calculate(10000, [
            ['unit_id' => 20, 'percentage' => '33.3333'],
            ['unit_id' => 10, 'percentage' => '33.3333'],
            ['unit_id' => 30, 'percentage' => '33.3334'],
        ]);

        $this->assertSame([
            ['unit_id' => 10, 'percentage' => '33.3333', 'amount_cents' => 3333],
            ['unit_id' => 20, 'percentage' => '33.3333', 'amount_cents' => 3333],
            ['unit_id' => 30, 'percentage' => '33.3334', 'amount_cents' => 3334],
        ], $allocations);
    }

    public function test_it_supports_decimal_percentages_and_one_cent_total(): void
    {
        $allocations = (new ExpenseAllocationCalculator)->calculate(1, [
            ['unit_id' => 2, 'percentage' => '50'],
            ['unit_id' => 1, 'percentage' => '50.0000'],
        ]);

        $this->assertSame([
            ['unit_id' => 1, 'percentage' => '50.0000', 'amount_cents' => 1],
            ['unit_id' => 2, 'percentage' => '50.0000', 'amount_cents' => 0],
        ], $allocations);
    }

    public function test_it_distributes_cent_remainders_by_largest_fraction_first(): void
    {
        $allocations = (new ExpenseAllocationCalculator)->calculate(150001, [
            ['unit_id' => 1, 'percentage' => '50'],
            ['unit_id' => 2, 'percentage' => '30'],
            ['unit_id' => 3, 'percentage' => '20'],
        ]);

        $this->assertSame([
            ['unit_id' => 1, 'percentage' => '50.0000', 'amount_cents' => 75001],
            ['unit_id' => 2, 'percentage' => '30.0000', 'amount_cents' => 45000],
            ['unit_id' => 3, 'percentage' => '20.0000', 'amount_cents' => 30000],
        ], $allocations);
    }

    #[DataProvider('invalidAllocations')]
    public function test_it_rejects_invalid_rates_and_amounts(int $totalAmountCents, array $allocations): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ExpenseAllocationCalculator)->calculate($totalAmountCents, $allocations);
    }

    public static function invalidAllocations(): iterable
    {
        yield 'percentages do not sum to 100' => [
            100,
            [
                ['unit_id' => 1, 'percentage' => '50'],
                ['unit_id' => 2, 'percentage' => '49.9999'],
            ],
        ];
        yield 'more than four decimal places' => [
            100,
            [
                ['unit_id' => 1, 'percentage' => '50.00001'],
                ['unit_id' => 2, 'percentage' => '49.99999'],
            ],
        ];
        yield 'float percentage' => [
            100,
            [
                ['unit_id' => 1, 'percentage' => 50.0],
                ['unit_id' => 2, 'percentage' => '50'],
            ],
        ];
        yield 'duplicate unit' => [
            100,
            [
                ['unit_id' => 1, 'percentage' => '50'],
                ['unit_id' => 1, 'percentage' => '50'],
            ],
        ];
        yield 'zero percentage' => [
            100,
            [
                ['unit_id' => 1, 'percentage' => '0'],
                ['unit_id' => 2, 'percentage' => '100'],
            ],
        ];
        yield 'negative total' => [
            -1,
            [['unit_id' => 1, 'percentage' => '100']],
        ];
        yield 'empty allocations' => [100, []];
    }
}
