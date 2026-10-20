<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class FrankfurterExchangeRateProvider
{
    /**
     * @return array{rate: string, date: string, provider: string}
     */
    public function quoteFor(DateTimeInterface $date): array
    {
        $requestedDate = $date->format('Y-m-d');
        $baseUrl = rtrim((string) config('services.frankfurter.url'), '/');
        $response = Http::acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->get("{$baseUrl}/{$requestedDate}", [
                'base' => 'USD',
                'symbols' => 'BRL',
            ])
            ->throw();

        try {
            $payload = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('O provedor de câmbio retornou uma resposta JSON inválida.', previous: $exception);
        }

        if (! is_array($payload)
            || ($payload['base'] ?? null) !== 'USD'
            || ! isset($payload['rates'])
            || ! is_array($payload['rates'])
            || ! array_key_exists('BRL', $payload['rates'])
            || ! isset($payload['date'])
            || ! is_string($payload['date'])
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['date'])
            || ! checkdate((int) substr($payload['date'], 5, 2), (int) substr($payload['date'], 8, 2), (int) substr($payload['date'], 0, 4))
            || $payload['date'] > $requestedDate) {
            throw new RuntimeException('O provedor de câmbio retornou uma data efetiva inválida.');
        }

        if (! preg_match('/"rates"\s*:\s*\{[^}]*"BRL"\s*:\s*(\d+(?:\.\d+)?)(?=\s*[,}])/', $response->body(), $matches)) {
            throw new RuntimeException('O provedor de câmbio não retornou uma cotação BRL válida.');
        }

        $rate = $matches[1];

        if (! preg_match('/^(?:0|[1-9]\d{0,9})(?:\.\d{1,10})?$/', $rate)
            || preg_match('/^0(?:\.0{1,10})?$/', $rate)) {
            throw new RuntimeException('A cotação BRL retornada está fora da precisão suportada.');
        }

        return [
            'rate' => $rate,
            'date' => $payload['date'],
            'provider' => 'Frankfurter',
        ];
    }
}
