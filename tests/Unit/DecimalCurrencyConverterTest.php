<?php

namespace Tests\Unit;

use App\Services\DecimalCurrencyConverter;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;

class DecimalCurrencyConverterTest extends TestCase
{
    public function test_it_converts_cents_with_decimal_arithmetic_and_rounds_half_up(): void
    {
        $converter = new DecimalCurrencyConverter;

        $this->assertSame(787500, $converter->toBrlCents(150000, '5.25'));
        $this->assertSame(1, $converter->toBrlCents(1, '0.5'));
        $this->assertSame(2, $converter->toBrlCents(1, '1.5'));
    }

    public function test_it_rejects_invalid_rates_and_amounts(): void
    {
        $converter = new DecimalCurrencyConverter;

        foreach ([[100, '0'], [-1, '5.25'], [100, '5.12345678901'], [100, '1e3']] as [$amount, $rate]) {
            try {
                $converter->toBrlCents($amount, $rate);
                $this->fail('Valores inválidos deveriam ser rejeitados.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_it_rejects_converted_values_that_overflow_integer_cents(): void
    {
        $this->expectException(OverflowException::class);

        (new DecimalCurrencyConverter)->toBrlCents(PHP_INT_MAX, '2');
    }
}
