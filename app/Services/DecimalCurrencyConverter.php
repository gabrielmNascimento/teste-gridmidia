<?php

namespace App\Services;

use InvalidArgumentException;
use OverflowException;

class DecimalCurrencyConverter
{
    private const RATE_SCALE = 10;

    public function toBrlCents(int $amountCents, string $rate): int
    {
        if ($amountCents < 0
            || ! preg_match('/^(?:0|[1-9]\d{0,9})(?:\.\d{1,10})?$/', $rate)
            || preg_match('/^0(?:\.0{1,10})?$/', $rate)) {
            throw new InvalidArgumentException('Valor ou cotação inválidos para conversão.');
        }

        [$wholeRate, $fractionalRate] = array_pad(explode('.', $rate, 2), 2, '');
        $rateUnits = ltrim($wholeRate.str_pad($fractionalRate, self::RATE_SCALE, '0'), '0');
        $rateUnits = $rateUnits === '' ? '0' : $rateUnits;
        $product = $this->multiplyIntegers((string) $amountCents, $rateUnits);
        $scale = self::RATE_SCALE;

        if (strlen($product) <= $scale) {
            $wholeCents = '0';
            $remainder = str_pad($product, $scale, '0', STR_PAD_LEFT);
        } else {
            $wholeCents = substr($product, 0, -$scale);
            $remainder = substr($product, -$scale);
        }

        if ($remainder[0] >= '5') {
            $wholeCents = $this->addOne($wholeCents);
        }

        $maxCents = (string) PHP_INT_MAX;

        if (strlen($wholeCents) > strlen($maxCents)
            || (strlen($wholeCents) === strlen($maxCents) && strcmp($wholeCents, $maxCents) > 0)) {
            throw new OverflowException('O valor convertido excede o limite suportado em centavos.');
        }

        return (int) $wholeCents;
    }

    private function multiplyIntegers(string $left, string $right): string
    {
        $leftDigits = array_reverse(array_map('intval', str_split($left)));
        $rightDigits = array_reverse(array_map('intval', str_split($right)));
        $result = array_fill(0, count($leftDigits) + count($rightDigits), 0);

        foreach ($leftDigits as $leftIndex => $leftDigit) {
            foreach ($rightDigits as $rightIndex => $rightDigit) {
                $result[$leftIndex + $rightIndex] += $leftDigit * $rightDigit;
            }
        }

        for ($index = 0, $length = count($result) - 1; $index < $length; $index++) {
            $carry = intdiv($result[$index], 10);
            $result[$index] %= 10;
            $result[$index + 1] += $carry;
        }

        return ltrim(implode('', array_reverse($result)), '0') ?: '0';
    }

    private function addOne(string $value): string
    {
        $digits = str_split($value);

        for ($index = count($digits) - 1; $index >= 0; $index--) {
            if ($digits[$index] !== '9') {
                $digits[$index] = (string) ((int) $digits[$index] + 1);

                return implode('', $digits);
            }

            $digits[$index] = '0';
        }

        return '1'.implode('', $digits);
    }
}
