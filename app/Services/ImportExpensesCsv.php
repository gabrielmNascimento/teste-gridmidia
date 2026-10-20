<?php

namespace App\Services;

use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ImportExpensesCsv
{
    private const HEADERS = [
        'description',
        'supplier',
        'expense_date',
        'amount',
        'currency',
        'allocations',
    ];

    public function __construct(
        private readonly RegisterExpense $registerExpense,
    ) {}

    /**
     * @return array{
     *     summary: array{total_rows: int, imported: int, failed: int},
     *     results: list<array{line: int, status: string, expense_id?: int, errors?: list<string>}>
     * }
     */
    public function handle(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if (! is_string($path)) {
            throw new RuntimeException('Não foi possível acessar o arquivo CSV enviado.');
        }

        $stream = fopen($path, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('Não foi possível ler o arquivo CSV enviado.');
        }

        try {
            return $this->importStream($stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     * @return array{
     *     summary: array{total_rows: int, imported: int, failed: int},
     *     results: list<array{line: int, status: string, expense_id?: int, errors?: list<string>}>
     * }
     */
    private function importStream($stream): array
    {
        $headers = fgetcsv($stream, escape: '');

        if ($headers === false) {
            throw ValidationException::withMessages(['file' => 'O arquivo CSV está vazio.']);
        }

        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $headers = array_map(static fn ($header): string => strtolower(trim((string) $header)), $headers);

        if (count($headers) !== count(array_unique($headers))
            || array_diff(self::HEADERS, $headers) !== []
            || array_diff($headers, self::HEADERS) !== []) {
            throw ValidationException::withMessages([
                'file' => 'O cabeçalho deve conter description,supplier,expense_date,amount,currency,allocations.',
            ]);
        }

        $unitIdsByCode = Unit::query()->pluck('id', 'code')->all();
        $results = [];
        $line = 1;

        while (($values = fgetcsv($stream, escape: '')) !== false) {
            $line++;

            if ($values === [null] || count(array_filter($values, static fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }

            if (count($values) !== count($headers)) {
                $results[] = [
                    'line' => $line,
                    'status' => 'failed',
                    'errors' => ['A linha possui quantidade de colunas diferente do cabeçalho.'],
                ];

                continue;
            }

            $row = array_combine($headers, array_map(static fn ($value): string => trim((string) $value), $values));
            $errors = $this->validateRow($row);
            $allocations = $this->parseAllocations($row['allocations'], $unitIdsByCode, $errors);

            if ($errors !== []) {
                $results[] = ['line' => $line, 'status' => 'failed', 'errors' => array_values(array_unique($errors))];

                continue;
            }

            try {
                $expense = $this->registerExpense->handle([
                    'description' => $row['description'],
                    'supplier' => $row['supplier'],
                    'expense_date' => $row['expense_date'],
                    'amount' => $row['amount'],
                    'currency' => $row['currency'],
                    'allocations' => $allocations,
                ]);
            } catch (ValidationException $exception) {
                $results[] = [
                    'line' => $line,
                    'status' => 'failed',
                    'errors' => array_merge(...array_values($exception->errors())),
                ];

                continue;
            }

            $results[] = [
                'line' => $line,
                'status' => 'imported',
                'expense_id' => $expense->id,
            ];
        }

        $imported = count(array_filter($results, static fn (array $result): bool => $result['status'] === 'imported'));

        return [
            'summary' => [
                'total_rows' => count($results),
                'imported' => $imported,
                'failed' => count($results) - $imported,
            ],
            'results' => $results,
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @return list<string>
     */
    private function validateRow(array $row): array
    {
        $validator = Validator::make($row, [
            'description' => ['required', 'string', 'max:255'],
            'supplier' => ['required', 'string', 'max:255'],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'string', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,2})?$/'],
            'currency' => ['required', 'in:BRL,USD'],
            'allocations' => ['required', 'string'],
        ]);

        $errors = array_merge(...array_values($validator->errors()->toArray()));

        foreach ($row as $value) {
            if (preg_match('//u', $value) !== 1) {
                $errors[] = 'A linha contém texto que não está codificado em UTF-8.';

                break;
            }
        }

        return $errors;
    }

    /**
     * @param  array<int|string, int|string>  $unitIdsByCode
     * @param  list<string>  $errors
     * @return list<array{unit_id: int, percentage: string}>
     */
    private function parseAllocations(string $value, array $unitIdsByCode, array &$errors): array
    {
        if ($value === '') {
            $errors[] = 'Informe ao menos um rateio no formato codigo=percentual.';

            return [];
        }

        $allocations = [];

        foreach (explode(';', $value) as $entry) {
            $parts = explode('=', trim($entry));

            if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                $errors[] = 'Cada rateio deve seguir o formato codigo=percentual, separado por ponto e vírgula.';

                continue;
            }

            [$code, $percentage] = array_map('trim', $parts);

            if (! array_key_exists($code, $unitIdsByCode)) {
                $errors[] = "O código de unidade '{$code}' não existe.";

                continue;
            }

            $allocations[] = [
                'unit_id' => (int) $unitIdsByCode[$code],
                'percentage' => $percentage,
            ];
        }

        return $allocations;
    }
}
